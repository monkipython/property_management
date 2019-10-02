<?php
namespace App\Http\Controllers\PropertyManagement\TenantMoveOutProcess\TenantDepositRefundUndo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Html, Helper, HelperMysql, Elastic, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class

class TenantDepositRefundUndoController extends Controller{
  
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['tnt_move_out_process_id'],
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist' =>[
          T::$tntMoveOutProcess . '|tnt_move_out_process_id'
        ]
      ]
    ]);
    $dataset = [];
    $vData   = $valid['dataNonArr'];
    $usr     = $vData['usid'];
    $batch   = HelperMysql::getBatchNumber();
    $today   = Helper::date();
    $rTntMoveOutProcess = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'   => T::$tntMoveOutProcessView,
      '_source' => ['prop', 'unit', 'tenant', 'status'],
      'query'   => ['must'=>['tnt_move_out_process_id'=>$vData['tnt_move_out_process_id']]]
    ]), 1);
    $service             = Helper::keyFieldName(HelperMysql::getService(['prop'=>$rTntMoveOutProcess['prop']]), 'service');
    $glChart             = Helper::keyFieldName(HelperMysql::getGlChat(['g.prop'=>$rTntMoveOutProcess['prop']]), 'gl_acct');
    $rTntSecurityDeposit = M::getTableData(T::$tntSecurityDeposit, Model::buildWhere(['prop'=>$rTntMoveOutProcess['prop'], 'unit'=>$rTntMoveOutProcess['unit'], 'tenant'=>$rTntMoveOutProcess['tenant'], 'is_move_out_process_trans'=>1]));
    $rVendorPayment      = M::getTableData(T::$vendorPayment, Model::buildWhere(['prop'=>$rTntMoveOutProcess['prop'], 'unit'=>$rTntMoveOutProcess['unit'], 'tenant'=>$rTntMoveOutProcess['tenant'],'type'=>'deposit_refund']), '*', 1);
    $oldBatchNumber      = '';
    $rTntTrans = $rGlTrans = [];
    ## Get the tntSecurityDeposit where is_move_out_process_trans = 1 and multiply the amount by -1 and insert to DB to offset the amounts
    foreach($rTntSecurityDeposit as $i=>$val){
      $oldBatchNumber = $val['batch'];
      $dataset[T::$tntSecurityDeposit][$i]['prop']         = $val['prop'];
      $dataset[T::$tntSecurityDeposit][$i]['unit']         = $val['unit'];
      $dataset[T::$tntSecurityDeposit][$i]['tenant']       = $val['tenant'];
      $dataset[T::$tntSecurityDeposit][$i]['service_code'] = $val['service_code'];
      $dataset[T::$tntSecurityDeposit][$i]['remark']       = $val['remark'];
      $dataset[T::$tntSecurityDeposit][$i]['amount']       = $val['amount'] * -1;
      $dataset[T::$tntSecurityDeposit][$i]['tx_code']      = 'P';
      $dataset[T::$tntSecurityDeposit][$i]['batch']        = $batch;
      $dataset[T::$tntSecurityDeposit][$i]['date1']        = $today;
      $dataset[T::$tntSecurityDeposit][$i]['gl_acct']      = !empty($service[$val['service_code']]) ? $service[$val['service_code']]['gl_acct'] : '';
      $dataset[T::$tntSecurityDeposit][$i]['is_move_out_process_trans'] = 1;
    }
    if(!empty($oldBatchNumber)) {
      $rTntTrans = HelperMysql::getTntTrans(['batch'=>$oldBatchNumber], [], [], 0);
      $rGlTrans = HelperMysql::getGlTrans(['batch'=>$oldBatchNumber, 'tx_code.keyword'=>'S'], [], [], 0);
    }
    foreach($rTntTrans as $i => $v) {
      $source = $v['_source'];
      $source['amount'] = $source['amount'] * -1;
      $source['usid']   = $usr;
      $source['batch']  = $batch;
      $source['date1']  = $source['date2'] = $source['sys_date'] = $source['inv_date'] = $today;
      unset($source['id'], $source['cntl_no']);
      $dataset[T::$tntTrans][$i] = $source;
    }
 
    foreach($rGlTrans as $i => $v) {
      $source = $v['_source'];
      $source['amount'] = $source['amount'] * -1;
      $source['usid']   = $usr;
      $source['batch']  = $batch;
      $source['date1']  = $source['date2'] = $source['sys_date'] = $source['inv_date'] = $today;
      unset($source['id'], $source['seq']);
      $dataset[T::$glTrans][$i] = $source;
    }
    $insertData = HelperMysql::getDataSet($dataset, $usr, $glChart, $service);
    
    $updateData = [
      T::$tntMoveOutProcess=>[
        'whereData' =>['tnt_move_out_process_id'=>$vData['tnt_move_out_process_id']], 
        'updateData'=>['status'=>0, 'usid'=>$usr]
      ]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    try{
      $success += Model::insert($insertData);
      $success += Model::update($updateData);
      if(!empty($rVendorPayment)) {
        $success[] = DB::table(T::$vendorPayment)->where(Model::buildWhere(['vendor_payment_id'=>$rVendorPayment['vendor_payment_id']]))->delete();
        $success[] = DB::table(T::$fileUpload)->whereIn('foreign_id', [$rVendorPayment['vendor_payment_id'], $vData['tnt_move_out_process_id']])->delete();
        $elastic['delete'][T::$vendorPaymentView] = ['vendor_payment_id'=>$rVendorPayment['vendor_payment_id']];
      }
      $elastic['insert'][T::$tntMoveOutProcessView] = ['tnt_move_out_process_id' => $success['update:'.T::$tntMoveOutProcess]];
      if(!empty($success['insert:' . T::$tntTrans])) {
        $elastic['insert'][T::$tntTransView] = ['tt.cntl_no'=>$success['insert:' . T::$tntTrans]];
      }
      if(!empty($success['insert:' . T::$glTrans])) {
        $elastic['insert'][T::$glTransView]  = ['gl.seq'=>$success['insert:' . T::$glTrans]];
      }
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }  

################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'store' => [T::$tntMoveOutProcess]
    ];
    return $tablez[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'store' => Html::sucMsg('Deposit Refund was Reverted Successfully.')
    ];
    return $data[$name];
  }
}