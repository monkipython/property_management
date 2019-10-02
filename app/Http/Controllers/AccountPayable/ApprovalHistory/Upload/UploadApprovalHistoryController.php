<?php
namespace App\Http\Controllers\AccountPayable\ApprovalHistory\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Html, Helper, Upload, V, File, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
use App\Http\Models\ApprovalModel AS M; // Include the models class

class UploadApprovalHistoryController extends Controller{
//------------------------------------------------------------------------------
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['vendor_payment_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'isAjax'=>$req->ajax(),
      'tablez'=>[T::$vendorPayment],
      'orderField'=>$this->_getOrderField(__FUNCTION__),
      'validateDatabase'=>[
        'mustExist'=>[
          T::$vendorPayment . '|vendor_payment_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $r = M::getFileUpload(['vendor_payment_id'=>$vData['vendor_payment_id'], 'f.type'=>'approval']);

    return Upload::getViewlistFile($r, '/uploadApproval');
  }
//------------------------------------------------------------------------------  
  public function show($id, Request $req){
    $v = V::startValidate([
      'rawReq'=>['uuid'=>$id] + $req->all(),
      'tablez'=>[T::$fileUpload],
      'orderField'=>['uuid'],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$fileUpload . '|uuid', 
        ]
      ]
    ]);
    $r = M::getFileUpload(Model::buildWhere(['uuid'=>$id]), 1,['f.type','f.file','f.uuid','f.ext']);
    if(preg_match('/^old_/', $id)){
      $v['data']['path'] = File::getLocation('ApprovalHistory')['showUpload'] . implode('/', [$r['type'], $r['file']]);
    } else{
      $v['data']['path'] = File::getLocation('ApprovalHistory')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
    }
    $v['data']['ext']  = $r['ext'];
    $vData = $v['data'];
    if($req->ajax()) {
      return Upload::getViewContainerType($vData['path']);
    } else{
      header('Location: ' . $vData['path']);
      exit;
    }
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getOrderField($fn){
    $orderField = [
      'index'   => ['vendor_payment_id'],
    ];
    return $orderField[$fn];
  }
}