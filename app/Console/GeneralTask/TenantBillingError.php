<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, HelperMysql, Elastic, TableName AS T};
class TenantBillingError extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:tenantBillingError';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Collect all current tenants where the base ';
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
    $rTenant       = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['tenant_id','prop','unit','tenant',T::$billing,'base_rent','rent_rate','status'],
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      'size'     => 120000,
      'query'    => [
        'must'   => [
          'status.keyword' => 'C',
        ]
      ]
    ]));
    
    $tenantIds = $updateData = $tenantInfo = [];
    
    $csvRows   = [];
    $csvRows[] = implode(',',['prop','unit','tenant','base_rent','tnt_rent','HUD']);
    foreach($rTenant as $i => $v){
      $billing      = Helper::getValue(T::$billing,$v,[]);
      $billingRent  = $this->_getBillingRents($billing,$v);
      $tntRent      = $billingRent['tnt_rent'];
      $hudRent      = $billingRent['HUD'];
      
      $tntRent      = ($tntRent == 0 && $hudRent == 0) ? $v['rent_rate'] : $tntRent;
      
      $baseRent     = Helper::getValue('base_rent',$v,0);
      
      $difference   = $baseRent - ($tntRent + $hudRent);
      if($difference != 0){
        $row  = [
          'prop'       => $v['prop'],
          'unit'       => $v['unit'],
          'tenant'     => $v['tenant'],
          'base_rent'  => $baseRent,
          'tnt_rent'   => $tntRent,
          'HUD'        => $hudRent,
        ];
        $csvRows[]     = implode(',',$row);
      }
    }
    echo implode("\n",$csvRows);
  }
//------------------------------------------------------------------------------
  private function _getBillingRents($billing,$tenant){
    $tntRent  = $hudRent = 0;
    
    foreach($billing as $i => $v){
      if($v['stop_date'] == '9999-12-31' && $v['schedule'] == 'M'){
        if($v['service_code'] == 'HUD'){
          $hudRent = $v['amount'];
        } else if( ($v['service_code'] == '602' && !preg_match('/MJC[1-9]+/', $tenant['prop'])) || ($v['service_code'] == '633' && preg_match('/MJC[1-9]+/', $tenant['prop'])) ){
          $tntRent += $v['amount'];
        }
      }
    }
    
    return ['tnt_rent'=>$tntRent,'HUD'=>$hudRent];
  }
}