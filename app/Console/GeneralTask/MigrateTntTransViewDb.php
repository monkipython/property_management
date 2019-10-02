<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, Elastic, TableName AS T,ServiceName AS S,GlName AS G};

class MigrateTntTransViewDb extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:migrateTntTransViewDb';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Upsert all elastic search documents from tenant trans view into the database';
  /**
   * Create a new command instance.
   * @return void
   */
  public function __construct(){
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $sourceFields = ['prop','unit','tenant','date1','cntl_no','journal','tx_code','jnl_class','building','per_code','sales_off','sales_agent','amount','dep_amt','appyto','remark','remarks','bk_transit','bk_acct','name_key','gl_acct','gl_contra','gl_acct_org','check_no','service_code','usid','sys_date','batch','job','doc_no','bank','inv_date','invoice','inv_remark','bill_seq','net_jnl','date2'];
    $cntlNo  = [];
    $csvRows = [];
    $rowsUpserted = 0;
    $rTntTrans   = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'    => T::$tntTransView,
      'size'     => 500000,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      'query'    => ['must'=>['prop.keyword'=>'0499', 'unit.keyword'=>'0002', 'tenant'=>7]],
      '_source'  => $sourceFields,
    ]));
    $csvRows[]    = implode(',',['Prop','Unit','Tenant','cntl_no']);
    foreach($rTntTrans as $i => $v){
      $rowData           = Helper::selectData($sourceFields,$v);
      $upsertResponse    = $this->_upsertRecord(T::$tntTrans,['cntl_no'=>$v['cntl_no']],$rowData);
      
      if($upsertResponse){
        $csvRows = implode(',',[$v['prop'],$v['unit'],$v['tenant'],$v['cntl_no']]);
        ++$rowsUpserted;
      }
    }

    $msg  = 'Update Complete, ' . $rowsUpserted . ' tenant transaction(s) updated/inserted';
    dd($msg);
  }
//------------------------------------------------------------------------------
  private function _upsertRecord($table,$where,$updateData){
    return DB::table($table)->updateOrInsert($where,$updateData);
  }
}
