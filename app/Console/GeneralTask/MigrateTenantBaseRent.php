<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, HelperMysql, Elastic, TableName AS T};
class MigrateTenantBaseRent extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:migrateTenantBaseRent';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Set all base rents of current tenants to their most recent non-HUD billing';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_defaultUsid  = 'SYS';
  private $_allowUpdate  = 1;
  private $_displayTenant= 1;
  public function __construct(){
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $numRecords    = 0;
    $rTenant       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['tenant_id','prop','unit','tenant',T::$billing,'base_rent'],
      'size'     => 50000,
      'query'    => [
        'must'   => [
          'status.keyword' => 'C',
        ]
      ]
    ]),'tenant_id');
    
    $tenantIds = $updateData = $tenantInfo = [];
    
    foreach($rTenant as $k => $v){
      $billing        = Helper::getValue(T::$billing,$v,[]);
      $nonHudBilling  = $this->_getLatestNonHudBilling($billing);
      $newRent        = !empty($nonHudBilling) ? last($nonHudBilling)['amount'] : $v['base_rent'];
      if(!empty($nonHudBilling) && $newRent != $v['base_rent']){
        $tenantIds[]  = $k;
        $tenantInfo[] = implode(',',[$v['prop'],$v['unit'],Helper::getValue('tenant',$v,0),Helper::getValue('base_rent',$v,0),$newRent]);
        $updateData[T::$tenant][] = [
          'whereData'   => ['tenant_id' => $k],
          'updateData'  => ['base_rent' => $newRent,'usid'=>$this->_defaultUsid],
        ];
        ++$numRecords;
      }
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    try {
        if($this->_displayTenant){
          echo implode(PHP_EOL,$tenantInfo) . PHP_EOL;
          echo implode(',', $tenantInfo) . "\n";
        }
         
//      if(!empty($tenantIds) && $this->_allowUpdate){
//        $success += Model::update($updateData);
//        $elastic  = ['insert' => [T::$tenantView => ['t.tenant_id' => $tenantIds]]];
//        
//        $response = Model::commit([
//          'success'  => $success,
//          'elastic'  => $elastic,
//        ]);
//      }  
    } catch(\Exception $e) {
      $response = Model::rollback($e);
    }
    $msg = 'Tenant Base Rent Migration Complete: ' . $numRecords . ' were updated';
    dd($msg);
  }
//------------------------------------------------------------------------------
  private function _getLatestNonHudBilling($billing){
    $lastBillings = [];
    foreach($billing as $i => $v){
      if(!empty($v['service_code']) && $v['service_code'] != 'HUD' && $v['gl_acct'] == '602' && $v['schedule'] == 'M' && Helper::getValue('stop_date',$v,'1000-01-01') == '9999-12-31'){
        $lastBillings[] = $v;    
      } else if(!empty($v['service_code']) && $v['service_code'] == 'HUD' && $v['gl_acct'] == '602' && $v['schedule'] == 'M' && Helper::getValue('stop_date',$v,'1000-01-01') == '9999-12-31'){
        return [];
      }
    }
    return $lastBillings;
  }
}