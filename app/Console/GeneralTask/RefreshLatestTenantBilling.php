<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, Elastic, TableName AS T,ServiceName AS S,GlName AS G};

class RefreshLatestTenantBilling extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:refreshLatestTenantBilling';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Update Tenant 9999 Billing start date if it is older than the tenants lease start date';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_defaultUsid = 'SYS';
  public function __construct(){
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $tenantIds = $updateData = [];
    $billingUpdated = 0;
    $rTenant      = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'size'     => 500000,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'  => ['tenant_id','prop','unit','tenant','base_rent','lease_start_date',T::$billing],
      'query'    => [
        'raw'    => [
          'must' => [
            [
              'term'  => ['status.keyword'=>'C'],
            ],
            [
              'term'  => [T::$billing . '.schedule.keyword' => 'M'],
            ],
            [
              'terms' => [T::$billing . '.service_code.keyword'=>[S::$rent,S::$hud,G::$resort]],
            ],
            [
              'term'  => [T::$billing . '.stop_date' => '9999-12-31']
            ],
            [
              'range' => [
                T::$billing . '.start_date'  => ['lte'=>Helper::date()]
              ]
            ],
            [
              'range' => [
                'lease_start_date'  => ['lte'=>Helper::date()]
              ]
            ]
          ]
        ],
      ]
    ]));
  
    //$csvRows           = [implode(',',['Prop','Unit','Tenant','Lease Start Date'])];
    foreach($rTenant as $i => $v){
      //$tenantIds       = $updateData = [];
      $billing         = Helper::getValue(T::$billing,$v,[]);
      
      $updateBillings  = $this->_findBillingToUpdate($billing, $v);
      if(!empty($updateBillings)){
        $updateData[T::$billing][]   = [
          'whereInData'   => ['field'=>'billing_id','data'=>$updateBillings],
          'updateData'    => ['start_date'  => $v['lease_start_date']],
        ];
        
        $updateData[T::$rentRaise][] = [
          'whereInData'    => ['field'=>'billing_id','data'=>$updateBillings],
          'updateData'     => ['last_raise_date'=>$v['lease_start_date'],'effective_date'=>$v['lease_start_date'],'usid'=>$this->_defaultUsid],
        ];
        
        $tenantIds[]   = $v['tenant_id'];
        //$csvRows[]     = implode(',',[$v['prop'],$v['unit'],$v['tenant'],$v['lease_start_date']]);
        ++$billingUpdated;
        ############### DATABASE SECTION ######################
        $success = $elastic = $response = [];
        DB::beginTransaction();
        try {
          $success += Model::update($updateData);
          $elastic  = ['insert'=>[T::$tenantView=>['t.tenant_id'=>$tenantIds]]];
          $response = Model::commit([
            'success' => $success,
            'elastic' => $elastic,
          ]);
          
          $response = Model::commit([
            'success'  => $success,
            'elastic'  => ['insert'=>[T::$rentRaiseView=>['t.tenant_id'=>$tenantIds]]],
          ]);
        } catch(\Exception $e) {
          Model::rollback($e);
        }
      }
    }
    
    ############### DATABASE SECTION ######################
//    if(!empty($tenantIds)){
//      $success = $elastic = $response = [];
//      DB::beginTransaction();
//      echo 'start';
//      try {
//        $success += Model::update($updateData);
//        $elastic  = ['insert'=>[T::$tenantView => ['t.tenant_id'=>$tenantIds]]];
//        
//        $response = Model::commit([
//          'success' => $success,
////          'elastic' => $elastic,
//        ]);
          
//        $response = Model::commit([
//          'success'  => $success,
//          'elastic'  => ['insert'=>[T::$rentRaiseView=>['t.tenant_id'=>$tenantIds]]],
//        ]);
//      } catch(\Exception $e) {
//        Model::rollback($e);
//      }
//    }
    //echo implode("\n",$csvRows);
    $msg  = 'Update Complete, ' . $billingUpdated . ' tenant billing(s) were updated';
    dd($msg);
  }
//------------------------------------------------------------------------------
  private function _findBillingToUpdate($billing,$source){
    $leaseStart = strtotime($source['lease_start_date']);
    $ids        = [];
    
    foreach($billing as $i => $v){
      if($v['service_code'] != G::$depositI && $v['stop_date'] == '9999-12-31' && $v['schedule'] == 'M'){
        if(($v['service_code'] == S::$hud) || ( ($v['gl_acct'] == G::$rent && !preg_match('/MJC[1-9]+/',$source['prop'])) || ($v['service_code'] == G::$resort && preg_match('/MJC[1-9]+/',$source['prop'])) )){
          if(strtotime($v['start_date']) < $leaseStart){
            $ids[]  = $v['billing_id'];
          }
        }
      }   
    }
    
    return $ids;
  }
}
