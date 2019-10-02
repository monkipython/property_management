<?php
namespace App\Console\PropertyManagement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Library\{Helper, TableName AS T, HelperMysql, Mail};
use App\Http\Models\{Model, TenantModel AS M}; // Include the models class

class TenantUpdateStatus extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'tenant:updateStatus';
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Update Tenant Status';
  public function handle(){
    $currentTenantId = $futureTenantId = $currentUnitId = $futureUnitId = $success = $updateData = $rTntCntlNo = [];
    $today = Helper::date();
    
    ## Update current Tenant
    $rCurrentTenant = HelperMysql::getTenant(['status.keyword'=>'C', 'move_out_date'=>$today], ['tenant_id', 'prop', 'unit', 'tenant'], [], 0);
    if(!empty($rCurrentTenant)) {
      foreach($rCurrentTenant as $i => $v) {
        $source = $v['_source'];
        $rUnit = HelperMysql::getUnit(['prop.prop.keyword'=>$source['prop'], 'unit.keyword'=>$source['unit']], ['unit_id']);
        $currentUnitId[]  = $rUnit['unit_id'];
        $currentTenantId[] = $source['tenant_id'];
        $updateData[T::$unit][] = [
          'whereData' => ['unit_id'=>$rUnit['unit_id']],
          'updateData'=> ['past_tenant'=>$source['tenant']],
        ];
        ## Get cntl_no of the tnt_trans
        $rTntCntlNo = array_merge($rTntCntlNo, M::getCntlNo($source));
      }
      $updateData[T::$tenant][] = ['whereInData'=>['field'=>'tenant_id','data'=>$currentTenantId], 'updateData'=>['status'=>'P']];
    }
    
    ## Update future Tenant
    $rFutureTenant = HelperMysql::getTenant(['status.keyword'=>'F', 'move_in_date'=>$today], ['tenant_id', 'prop', 'unit', 'tenant'], [], 0);
    if(!empty($rFutureTenant)) {
      foreach($rFutureTenant as $i => $v) {
        $source = $v['_source'];
        $rUnit = HelperMysql::getUnit(['prop.prop.keyword'=>$source['prop'], 'unit.keyword'=>$source['unit']], ['unit_id']);
        $futureUnitId[]  = $rUnit['unit_id'];
        $futureTenantId[] = $source['tenant_id'];
        $updateData[T::$unit][] = [
          'whereData' => ['unit_id'=>$rUnit['unit_id']],
          'updateData'=> ['status'=>'C', 'curr_tenant'=>$source['tenant'],'move_out_date'=>'9999-12-31']
        ];
        ## Get cntl_no of the tnt_trans
        $rTntCntlNo = array_merge($rTntCntlNo, M::getCntlNo($source));
      }
      $updateData[T::$tenant][] = ['whereInData'=>['field'=>'tenant_id','data'=>$futureTenantId], 'updateData'=>['status'=>'C']];
    }
    
    $tenantId = array_merge($currentTenantId,$futureTenantId);
    $unitId   = array_merge($currentUnitId,$futureUnitId);
    if(empty($tenantId) && empty($unitId)) {
      $this->_sendEmail('No tenant to update.');
      return;
    }
    DB::beginTransaction();
    try {
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[
          T::$tenantView =>['tenant_id'=>$tenantId],
          T::$unitView   =>['unit_id'  =>$unitId] 
        ],
         
      ];
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
      ## Reindex tnt_trans
      if(!empty($rTntCntlNo)) {
        $elastic2 = [
          'insert'=>[
            T::$tntTransView => ['cntl_no' => $rTntCntlNo]
          ] 
        ];
        Model::commit([
          'success' =>$success,
          'elastic' =>$elastic2 
        ]);
      }
    } catch (\Exception $e) {
      $this->_sendEmail(Model::rollback($e));
    }
  }
  private function _sendEmail($msg) {
    Mail::send([
      'to'=>'sean@pamamgt.com',
      'from'=>'admin@pamamgt.com',
      'subject' =>'Tenant Update Status :: Run on ' . date("F j, Y, g:i a"),
      'msg'=>$msg
    ]);
  }
}