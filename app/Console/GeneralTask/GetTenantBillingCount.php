<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use App\Library\{Helper, Elastic, TableName AS T};
use Illuminate\Support\Facades\DB;

class GetTenantBillingCount extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:getTenantBillingCount';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Get the total number of billing items for each tenant in Elasticsearch';
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
    $csvRows      = [implode(',',['Prop','Unit','Tenant','Number of Billings Elastic','Number of Billing DB'])];
    $rBillingDb   = Helper::keyFieldName($this->_getTenantBillingDb(),['prop','unit','tenant']);
    $rTenant      = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'size'     => 500000,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'  => ['tenant_id','prop','unit','tenant',T::$billing],
    ]));
  
    foreach($rTenant as $i => $v){
      $billing    = Helper::getValue(T::$billing,$v,[]);
      $dbBilling  = Helper::getValue($v['prop'] . $v['unit'] . $v['tenant'],$rBillingDb,[]);
      $numBilling = count($billing);
      if(isset($dbBilling['billing_count']) && $numBilling != $dbBilling['billing_count']){
        $csvRows[]  = implode(',',[$v['prop'],$v['unit'],$v['tenant'],$numBilling,$dbBilling['billing_count']]);
      }
    }
    
    $csvStr       = implode("\n",$csvRows);
    echo $csvStr;
  }
//------------------------------------------------------------------------------
  private function _getTenantBillingDb($firstRowOnly=0){
    $select = DB::raw('prop,unit,tenant,COUNT(*) AS billing_count');
    $r      = DB::table(T::$billing)->select($select)->groupBy(['prop','unit','tenant']);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
}
