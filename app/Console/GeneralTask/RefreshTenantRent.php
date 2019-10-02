<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, Elastic, TableName AS T,ServiceName AS S,GlName AS G};

class RefreshTenantRent extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:refreshTenantRent';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Update Tenant and Unit Rent if recent 602 billing cycle is due to start';
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
    $unitIds      = $tenantIds = $updateData = [];
    $rentsUpdated = 0;
    $rTenant      = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'size'     => 120000,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'  => ['tenant_id','prop','unit','tenant','base_rent',T::$billing],
      'query'    => [
        'must'   => [
          'status.keyword'                      => 'C',
          T::$billing . '.schedule.keyword'     => 'M',
          T::$billing . '.service_code.keyword' => [S::$rent,S::$hud,G::$resort],
          T::$billing . '.stop_date'            => '9999-12-31',
          'range' => [
            T::$billing . '.start_date'         => [
              'lte'   => Helper::date(),
            ]
          ]
        ]
      ]
    ]));
    $props     = array_column($rTenant,'prop');
    $rUnit     = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$unitView,
      'size'     => 100000,
      '_source'  => ['prop.prop','unit','unit_id'],
      'query'    => [
        'must'   => [
          'prop.prop.keyword' => $props,
        ]
      ]
    ]),['prop.prop','unit'],'unit_id');
    
    foreach($rTenant as $i => $v){
      $tenantIds = $unitIds = $updateData = [];
      $unitId   = Helper::getValue($v['prop'] . $v['unit'],$rUnit,0);
      
      $billing       = Helper::getValue(T::$billing,$v,[]);
      $latestBilling = $this->_fetchLatestBillingAmount($billing,$v);
      if(!empty($latestBilling) && $v['base_rent'] != $latestBilling ){
        $updateData[T::$tenant][] = [
          'whereData'  => ['tenant_id' => $v['tenant_id']],
          'updateData' => ['base_rent' => $latestBilling],
        ];
          
        if(!empty($unitId) && empty($unitIds[$unitId])){
          $updateData[T::$unit][]     = [
            'whereData'  => ['unit_id'=>$unitId],
            'updateData' => ['rent_rate'=>$latestBilling],
          ];
            
          $unitIds[$unitId] = $unitId;
        }
        $tenantIds[]   = $v['tenant_id'];
        ############### DATABASE SECTION ######################
        $success = $elastic = $response = [];
        try {
          if(!empty($updateData)){
            $success            += Model::update($updateData);
            $elastic             = ['insert' => [T::$tenantView => ['t.tenant_id'=>$tenantIds]]];
            $elastic['insert']  += !empty($unitIds) ? [T::$unitView => ['u.unit_id'=>array_values($unitIds)]] : [];
            $response            = Model::commit([
              'success'   => $success,
              'elastic'   => $elastic,
            ]);
            ++$rentsUpdated;
          }
          
        } catch(\Exception $e) {
          $response = Model::rollback($e);
        }        
      }
    }
    
    $msg  = 'Update Complete, ' . $rentsUpdated . ' rent(s) were updated';
    dd($msg);
  }
//------------------------------------------------------------------------------
  private function _fetchLatestBillingAmount($billing,$source){
    $today   = strtotime(Helper::date());
    $amount  = 0;
    $has9999 = $this->_hasCurrentEndBilling($billing,$source);
    if($has9999 == 'Current 9999'){
      foreach($billing as $v){
        if($v['service_code'] != G::$depositI && strtotime($v['start_date']) <= $today && $v['stop_date'] == '9999-12-31' && $v['schedule'] == 'M'){
          if($v['service_code'] == S::$hud){
            $amount  += $v['amount'];  
          } else if( ($v['gl_acct'] == G::$rent && !preg_match('/MJC[1-9]+/',$source['prop'])) || ($v['service_code'] == G::$resort && preg_match('/MJC[1-9]+/',$source['prop'])) ){
            $amount  += $v['amount'];
          }
        }
      }
    } else if($has9999 == 'Future 9999'){
      $data = $this->_removeFutureBilling($billing,$source);
      foreach($data as $service => $val){
        $recentStart  = strtotime(last($val)['start_date']);
        foreach($val as $i => $v){
          if(strtotime($v['start_date']) == $recentStart){
            $amount += $v['amount'];    
          }
        }
      } 
    }
    return $amount;
  }
//------------------------------------------------------------------------------
  private function _hasCurrentEndBilling($billing,$source){
    $returnCode = 'No 9999';
    $today      = strtotime(Helper::date());
    foreach($billing as $v){
      //Adjust return code if there is a monthly billing item that is not 607 that is HUD or 602
      if($v['service_code'] != G::$depositI && $v['stop_date'] == '9999-12-31' && $v['schedule'] == 'M'){
        if( ($v['service_code'] == S::$hud) || ( ($v['gl_acct'] == G::$rent && !preg_match('/MJC[1-9]+/',$source['prop'])) ||  ($v['service_code'] == G::$resort && preg_match('/MJC[1-9]+/',$source['prop'])) )){
          //Immediately indicate there is 9999 stop date billing item that is before today to be used for the billing
          if(strtotime($v['start_date']) <= $today){
            return 'Current 9999';
          } else {
            //Indicate that the only 9999 billing items are in the future
            $returnCode = 'Future 9999';
          }
        }
      }
    }
    return $returnCode;
  }
//------------------------------------------------------------------------------
  private function _removeFutureBilling($billing,$source){
    $data = [];
    $today= strtotime(Helper::date());
    foreach($billing as $v){
      if($v['service_code'] != G::$depositI && strtotime($v['start_date']) <= $today && $v['schedule'] == 'M'){
        $data[] = $v;
      }
    }
    
    $_sortByStartDate  = function($a,$b){
      $aTime       = strtotime(Helper::getValue('start_date',$a,0));
      $bTime       = strtotime(Helper::getValue('start_date',$b,0));
      $aStopTime   = strtotime(Helper::getValue('stop_date',$a,0));
      $bStopTime   = strtotime(Helper::getValue('stop_date',$b,0));
      return $aTime == $bTime ? ($aStopTime == $bStopTime ? 0 : ($aStopTime < $bStopTime ? -1 : 1)) : ($aTime < $bTime ? -1 : 1);
    };
    
    usort($data,$_sortByStartDate);
    $groupedData = [];
    foreach($data as $i => $v){
      if($v['service_code'] == S::$hud){
        $groupedData[S::$hud][]  = $v;
      } else if( ($v['gl_acct'] == G::$rent && !preg_match('/MJC[1-9]+/',$source['prop'])) || ($v['service_code'] == G::$resort && preg_match('/MJC[1-9]+/',$source['prop'])) ){
        $groupedData[G::$rent][] = $v;
      }
    }
    return $groupedData;
  }
  
}
