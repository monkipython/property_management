<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, Elastic, TableName AS T,ServiceName AS S,GlName AS G};

class RefreshPendingLastRentRaise extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:refreshPendingLastRentRaise';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Update the last raise of the pending rent raise to ';
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
    $tenantIds = $updateData = [];
    $raiseUpdated = 0;
    $rRentRaise   = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'    => T::$rentRaiseView,
      'size'     => 500000,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'  => ['tenant_id','prop','unit','tenant','last_raise_date',T::$rentRaise],
      'query'    => [
        'must'   => [
          'range' => ['last_raise_date' => ['lte'=>Helper::date()]],
        ]
      ]
    ]));
  
    foreach($rRentRaise as $i => $v){
      //$tenantIds = $updateData = [];
      $rentRaiseData       = Helper::getValue(T::$rentRaise,$v,[]);
     
      $pendingRentRaise    = $this->_getPendingRentRaise($rentRaiseData);
      $lastSubmittedRaise  = $this->_findLatestStartDate($rentRaiseData);

      if(!empty($pendingRentRaise['last_raise_date']) && !empty($lastSubmittedRaise) && strtotime($pendingRentRaise['last_raise_date']) < strtotime($lastSubmittedRaise)){
        $updateData[T::$rentRaise][]  = [
          'whereData'   => ['rent_raise_id'=>$pendingRentRaise['rent_raise_id']],
          'updateData'  => ['last_raise_date' => $lastSubmittedRaise],
        ];
        
        $tenantIds[]   = $v['tenant_id'];
        ++$raiseUpdated;
        ############### DATABASE SECTION ######################
//        $success = $elastic = $response = [];
//        DB::beginTransaction();
//        try {
//          $success += Model::update($updateData);
//          $elastic  = ['insert'=>[T::$rentRaiseView=>['t.tenant_id'=>$tenantIds]]];
//          $response = Model::commit([
//            'success' => $success,
//            'elastic' => $elastic,
//          ]);
//
//        } catch(\Exception $e) {
//          Model::rollback($e);
//        }
      }
    }
    
    //$csvRows           = [implode(',',['Prop','Unit','Tenant','Lease Start Date'])];
    if(!empty($tenantIds)){
      ############### DATABASE SECTION ######################
      $success = $elastic = $response = [];
      DB::beginTransaction();
      try {
        $success += Model::update($updateData);
        $elastic  = ['insert'=>[T::$rentRaiseView=>['t.tenant_id'=>$tenantIds]]];
        $response = Model::commit([
          'success' => $success,
          'elastic' => $elastic,
        ]);
      } catch(\Exception $e) {
        Model::rollback($e);
      }    
    }
    //echo implode("\n",$csvRows);
    $msg  = 'Update Complete, ' . $raiseUpdated . ' pending rent raise(s) were updated';
    dd($msg);
  }
//------------------------------------------------------------------------------
  private function _findLatestStartDate($rentRaise){
    $lastRaise = '';
    foreach($rentRaise as $i => $v){
      if(!empty($v['billing_id'])){
        $lastRaise = $v['last_raise_date'];
      }
    }
    return $lastRaise;
  }
//------------------------------------------------------------------------------
  private function _getPendingRentRaise($rentRaise){
    $lastRentRaise = !empty($rentRaise) ? last($rentRaise) : [];
    $lastRentRaise = isset($lastRentRaise['billing_id']) && $lastRentRaise['billing_id'] == 0 ? $lastRentRaise : [];
    return $lastRentRaise;
  }
}
