<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\MoveOutUndo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Html, Helper, HelperMysql, Elastic, TableName AS T};
use App\Http\Models\{Model, TenantModel AS M}; // Include the models class

class MoveOutUndoController extends Controller{
  
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist' =>[
          T::$tenant . '|tenant_id'
        ]
      ]
    ]);
    $maxDate = '9999-12-31';
    $vData   = $valid['dataNonArr'];
    $usr     = $vData['usid'];
    $rTenant       = HelperMysql::getTenant(['tenant_id'=>$vData['tenant_id']]);
    $currentTenant = HelperMysql::getTenant(['prop.keyword'=>$rTenant['prop'], 'unit.keyword'=>$rTenant['unit'], 'status.keyword'=>'C']);
    if(!empty($currentTenant) && $rTenant['tenant_id'] != $currentTenant['tenant_id']) {
      Helper::echoJsonError($this->_getErrorMsg('currentTenant'), 'mainMsg');
    }
    $rUnit = HelperMysql::getUnit(['prop.prop.keyword'=>$rTenant['prop'], 'unit.keyword'=>$rTenant['unit']], ['unit_id']);
    ## Get past tenant to update the past_tenant column in the unit table
    $rPastTenant = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'   =>T::$tenantView,
      '_source' =>['tenant'],
      'sort'    =>['move_out_date'=>'desc'],
      'query'   =>['must'=>Helper::getPropUnitMustQuery($rTenant, [], 0), 'must_not'=>['tenant_id'=>$vData['tenant_id']]]
    ]), 1);
    $pastTenant = !empty($rPastTenant) ? $rPastTenant['tenant'] : 255;
    ## Get tenantMoveOutProcess ID to delete it
    $rTntMoveOutProcess = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'   =>T::$tntMoveOutProcessView,
      '_source' =>['tnt_move_out_process_id', 'fileUpload', 'status'],
      'query'   =>['must'=>Helper::getPropUnitTenantMustQuery($rTenant, [], 0)]
    ]), 1);
    if(!empty($rTntMoveOutProcess)) {
      $tntMoveOutProcessId = $rTntMoveOutProcess['tnt_move_out_process_id'];
      ## If tenant was issued a deposit refund, don't allow undo move out
      if($rTntMoveOutProcess['status'] == 'Yes') {
        Helper::echoJsonError($this->_getErrorMsg('depositRefund'), 'mainMsg');
      }
    }
    $updateData = [
      T::$tenant=>[
        'whereData' =>['tenant_id'=>$vData['tenant_id']], 
        'updateData'=>['status'=>'C', 'move_out_date'=>$maxDate, 'usid'=>$usr]
      ],
      T::$unit=>[ 
        'whereData' =>['unit_id'=>$rUnit['unit_id']], 
        'updateData'=>['status'=>'C', 'move_out_date'=>$maxDate, 'curr_tenant'=>$rTenant['tenant'], 'past_tenant'=>$pastTenant, 'usid'=>$usr],
      ]
    ];
    ## Get cntl_no of the tnt_trans
    $rTntCntlNo = M::getCntlNo($rTenant);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[
          T::$tenantView => ['tenant_id' => $success['update:'.T::$tenant]],
          T::$unitView   => ['unit_id'   => $success['update:'.T::$unit]]
        ]
      ];
      if(!empty($tntMoveOutProcessId)) {
        $success[T::$tntMoveOutProcess] = DB::table(T::$tntMoveOutProcess)->where(Model::buildWhere(['tnt_move_out_process_id'=>$tntMoveOutProcessId]))->delete();
        $elastic['delete'][T::$tntMoveOutProcessView] = ['tnt_move_out_process_id' => $tntMoveOutProcessId];
      }
      if(!empty($rTntMoveOutProcess['fileUpload'])) {
        $success[T::$fileUpload] =  DB::table(T::$fileUpload)->where(['foreign_id'=>$tntMoveOutProcessId])->delete();
      }
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
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
      'store' => [T::$tenant]
    ];
    return $tablez[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'store' => Html::sucMsg('Move Out was Reverted Successfully.')
    ];
    return $data[$name];
  }
  private function _getErrorMsg($name) {
    $data = [
      'currentTenant' => Html::errMsg('Current tenant already exists for this unit.'),
      'depositRefund' => Html::errMsg('Cannot undo move out after issuing a deposit refund.')
    ];
    return $data[$name];
  }
}