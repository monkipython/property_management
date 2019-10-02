<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, HelperMysql, Elastic, Format, TableName AS T};
class MigrateLastRaiseDate extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:migrateLastRaiseDate';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Insert correct original base rents and last raise dates in rent raise table from input *.csv file';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_defaultUsid  = 'SYS';
  private $_allowUpdate  = 0;
  private $_displayTenant= 1;
  private $_defaultService= '602';
  private $_defaultGlAcct = '602';
  private $_csvColumns   = [];
  private $_filePath     = 'app/private/tmp/new_raise_date.csv';
  public function __construct(){
    $this->_csvColumns  = ['prop','unit','tenant','old_rent','new_rent','diff_pct','diff_dol','last_raise_date'];
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $insertData = $tenantIds = [];
    $numRecords = 0;
    $fullPath   = storage_path($this->_filePath);
    if(!file_exists(storage_path($this->_filePath))){
      dd('File Path: ' . $this->_filePath . ' does not exist, please try again');
    }
      
    $fileHandle    = fopen(storage_path($this->_filePath),'r');
    $fileContents  = fread($fileHandle,filesize($fullPath));
    fclose($fileHandle);
      
    $textList      = explode("\r\n",$fileContents);
    unset($textList[0]);
      
    $rows          = $this->_parseCsvBody($textList);
    
    $props         = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      '_source'  => ['prop'],
      'size'     => 50000,
      'query'    => [
        'must'   => [
          'prop.keyword'  => array_column($rows,'prop'),
        ],
        'must_not'  => [
          'prop_class.keyword'  => 'X',
        ]
      ]
    ]),'prop');
      
    $rUnit         = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$unitView,
      '_source'   => ['prop.prop','unit'],
      'size'      => 50000,
      'query'     => [
        'must'    => [
          'prop.prop.keyword'  => array_values($props),
        ],
        'must_not'=> [
          'prop.prop_class.keyword' => 'X',
        ]
      ]
    ]),['prop.prop','unit']);
    $rTenant       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['tenant_id','prop','unit','tenant','base_rent',T::$billing],
      'size'     => 50000,
      'query'    => [
        'must'   => [
          'status.keyword'    => 'C',
            'prop.keyword'      => array_values($props),
          ]
        ]
    ]),['prop','unit','tenant']);
      
    $rService      = Helper::keyFieldName(HelperMysql::getService(['prop'=>'Z64']),'service');
    $defaultRemark = !empty($rService[$this->_defaultService]['remark'])  ? $rService[$this->_defaultService]['remark'] : ''; 
   
    foreach($rows as $i => $v){
      $tenant  = Helper::getValue($v['prop'] . $v['unit'] . $v['tenant'],$rTenant,[]);
      $unit    = Helper::getValue($v['prop'] . $v['unit'],$rUnit,[]);
      $propRow = Helper::getValue($v['prop'],$props,[]);
      $insertData = $tenantIds = $success = $elastic = $response = [];
      if(!empty($unit) && !empty($propRow) &&  !empty($tenant)){
        $insertData[T::$rentRaise][] = Helper::selectData(['prop','unit','tenant'],$v) + [
          'foreign_id'        => $tenant['tenant_id'],
          'billing_id'        => 0,
          'rent'              => $tenant['base_rent'],
          'raise'             => $tenant['base_rent'],
          'raise_pct'         => 0,
          'notice'            => 30,
          'service_code'      => $this->_defaultService,
          'gl_acct'           => $this->_defaultGlAcct,
          'remark'            => $defaultRemark,
          'isCheckboxChecked' => 0,
          'effective_date'    => '1000-01-01',
          'file'              => '',
          'last_raise_date'   => Format::mysqlDate($v['last_raise_date']),
          'submitted_date'    => Format::mysqlDate($v['last_raise_date']),
          'cdate'             => Helper::mysqlDate(),
          'usid'              => $this->_defaultUsid,
          'active'            => 1,
        ];
        $tenantIds[] = $tenant['tenant_id'];
      } 
      
      try {
        if(!empty($tenantIds)){
          $success += Model::insert($insertData);
          $elastic  = ['insert' => [T::$rentRaiseView => ['t.tenant_id'=>$tenantIds]]];
          $response = Model::commit([
            'success' => $success,
            'elastic' => $elastic,
          ]);
          ++$numRecords;
          echo  implode('-', Helper::selectData(['prop','unit','tenant'],$v)) . ': ' . $numRecords . "\n";
        }
      } catch(\Exception $e) {
        Model::rollback($e);
      }
    }
      
    ############### DATABASE SECTION ######################
//    DB::beginTransaction();
//    $success = $elastic = [];
//    try {
//      if(!empty($tenantIds)){
//        $success  += Model::insert($insertData);
//        $elastic   = ['insert' => [T::$rentRaiseView => ['t.tenant_id'=>$tenantIds]]];
//        $response  = Model::commit([
//          'success' => $success,
//          'elastic' => $elastic,
//        ]);
//        $numRecords = count($success['insert:'.T::$rentRaise]);
//      }
//    } catch(\Exception $e) {
//      $response = Model::rollback($e);
//    }
    $msg  = 'Last Raise Date Migration Complete: ' . $numRecords . ' were updated';
    dd($msg);
  }
//------------------------------------------------------------------------------
  private function _parseCsvBody($csvArr){
    $data = [];
    foreach($csvArr as $i => $v){
      if(!empty($v)){
        $items        = explode(',',$v);
        $row          = array_combine($this->_csvColumns,$items);
        $row['prop']  = is_numeric($row['prop']) ? str_pad($row['prop'],4,'0',STR_PAD_LEFT) : $row['prop'];
        $row['unit']  = is_numeric($row['unit']) ? str_pad($row['unit'],4,'0',STR_PAD_LEFT) : $row['unit'];
        $data[]       = $row;
      }
    }
    return $data;
  }
}