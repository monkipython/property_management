<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, HelperMysql, Elastic, Format, TableName AS T};
class MigrateTenantBaseRentCsv extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:migrateTenantBaseRentCsv';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Insert correct original base rents and last raise dates in rent raise table from input *.csv file';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_defaultUsid   = 'SYS';
  private $_allowUpdate   = 1;
  private $_displayTenant = 1;
  private $_csvColumns    = [];
  private $_filePath      = 'app/private/tmp/new_tenant_base_rent.csv';
  public function __construct(){
    $this->_csvColumns  = ['prop','unit','tenant','old_rent','new_rent'];
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $numRecords = 0;
    $updateData = $tenantIds = [];
    $fullPath   = storage_path($this->_filePath);
    if(!file_exists(storage_path($this->_filePath))){
      dd('File Path: ' . $this->_filePath . ' does not exist, please try again');
    }
      
    $fileHandle    = fopen(storage_path($this->_filePath),'r');
    $fileContents  = fread($fileHandle,filesize($fullPath));
    fclose($fileHandle);
      
    $textList      = explode("\n",$fileContents);
    unset($textList[0]);
      
    $rows          = $this->_parseCsvBody($textList);
//      $props         = array_column($rows,'prop');
    
    $props         = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      '_source'  => ['prop'],
      'size'     => 50000,
      'query'    => [
        'must'   => [
          'prop.keyword'  => array_column($rows,'prop'),
        ]
      ]
    ]),'prop');
      
    $rTenant       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['tenant_id','prop','unit','tenant','base_rent',T::$billing],
      'size'     => 120000,
      'query'    => [
        'must'   => [
          'prop.keyword'      => array_values($props),
        ]
      ]
    ]),['prop','unit','tenant']);
   
    foreach($rows as $i => $v){
      echo $v['prop'] . '-' . $v['unit'] . '-' . $v['tenant'] . "\n";
      $tenant = Helper::getValue($v['prop'] . $v['unit'] . $v['tenant'],$rTenant,[]);
      $updateData = $tenantIds = [];
      if(!empty($tenant) && ($tenant['base_rent'] != $v['new_rent'])){
        $updateData[T::$tenant][] = [
          'whereData'  => ['tenant_id'=>$tenant['tenant_id']],
          'updateData' => ['base_rent'=>$v['new_rent'],'usid'=>$this->_defaultUsid],
        ];
        $tenantIds[]   = $tenant['tenant_id'];
      }
      
      ############### DATABASE SECTION ######################
      $success = $elastic = $response = [];
      try {
        if($this->_allowUpdate && !empty($tenantIds)){
          DB::beginTransaction();
          $success += Model::update($updateData);
          $elastic  = ['insert' => [T::$tenantView => ['t.tenant_id' => $tenantIds]]];
          $response = Model::commit([
            'success' => $success,
            'elastic' => $elastic,
          ]);
          ++$numRecords;
        }
        
      } catch (\Exception $e) {
        $response = Model::rollback($e);
      }
    }

    ############### DATABASE SECTION ######################
//    DB::beginTransaction();
//    $success = $elastic = [];
//    try {
//      if($this->_allowUpdate && !empty($tenantIds)){
//        $success  += Model::update($updateData);
//        $elastic   = ['insert' => [T::$tenantView => ['t.tenant_id'=>$tenantIds]]];
//        $response  = Model::commit([
//          'success' => $success,
//          'elastic' => $elastic,
//        ]);
//        $numRecords = count($tenantIds);
//      }
//    } catch(\Exception $e) {
//      $response = Model::rollback($e);
//    }
    $msg  = 'Tenant Base Rent Update Complete: ' . $numRecords . ' were updated';
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