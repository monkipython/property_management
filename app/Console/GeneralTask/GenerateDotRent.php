<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Elastic, Helper, HelperMysql, TableName AS T, V};
class GenerateDotRent extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:generateDotRent';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Generate General Ledger Transactions for Sum of Property Rent(s)';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_defaultUsid = 'SYS';
  public function __construct(){
    parent::__construct();
  }
  public function handle(){
    $batch  = HelperMysql::getBatchNumber();
    $rUnit  = Helper::getElasticAggResult(Elastic::searchQuery([
      'index'    => T::$unitView,
      'size'     => 0,
      'query'    => [
        'must_not' => [
          'prop.prop_class.keyword'=>'X',
        ]
      ],
      'aggs'     => [
        'by_prop' => [
          'terms' => [
            'field' => 'prop.prop.keyword',
            'size'  => 50000,
          ],
          'aggs'  => [
            'prop_sort' => [
              'bucket_sort' => [
                'sort'      => ['_key'],
              ]
            ],
            'rent_sum'      => [
              'sum'         => [
                'field'     => 'rent_rate',
              ]
            ]
          ]
        ]
      ]
    ]),'by_prop');
    
    $insertData  = [];
    foreach($rUnit as $i => $bucket){
      $prop        = $bucket['key'];
      $rent        = $bucket['rent_sum']['value'];
      
      if($rent > 0){
        $rService    = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$prop])),'service');
        $rGlChart    = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$prop])),'gl_acct');
        $glTransData = [
          'prop'         => $prop,
          'unit'         => '',
          'tenant'       => 255,
          'date1'        => date('Y-m-01'),
          'inv_date'     => date('Y-m-01'),
          'date2'        => date('Y-m-01'),
          'amount'       => (-1.0 * $rent),
          'remark'       => 'Budget',
          'gl_acct'      => '.RENT',
          'batch'        => $batch,
          'tx_code'      => 'BUD',
          'journal'      => 'BUD',
          'jnl_class'    => 'B',
          'service_code' => '',
          'bank'         => '',
          'check_no'     => '',
          'code1099'     => '',
          'invoice'      => '',
          'cons_prop'    => '',
          'net_jnl'      => '',
          'group1'       => '',
          'rock'         => '@@@',
        ];
      
        $insertRow   = HelperMysql::getDataSet([T::$glTrans=>$glTransData],$this->_defaultUsid,$rGlChart,$rService);
        $insertData[T::$glTrans][] = $insertRow[T::$glTrans];  
      }
    }
    
    if(!empty($insertData[T::$glTrans])){
      foreach($insertData[T::$glTrans] as $i => $v){
        $insertData[T::$glTrans][$i]['other_amt']   = $insertData[T::$glTrans][$i]['amount'];
        $insertData[T::$glTrans][$i]['amount']      = 0;
        $insertData[T::$glTrans][$i]['gl_acct_org'] = '';
        $insertData[T::$glTrans][$i]['title']       = 'BASE RENT FROM RENT ROLL';
        $insertData[T::$glTrans][$i]['check_no']    = '';
        $insertData[T::$glTrans][$i]['doc_no']      = '';
      }
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    try {
      $success += Model::insert($insertData);
      $elastic  = ['insert' => [T::$glTransView => ['gl.seq' => $success['insert:' . T::$glTrans]]]];
      
      $response = Model::commit([
        'success' => $success,
        'elastic' => $elastic,
      ]);
    } catch(\Exception $e) {
      $response = Model::rollback($e);
    }
    
    dd('Successfully inserted ' . count($success['insert:'.T::$glTrans]) . ' .RENT records into the General Ledger');
  }
}
