<?php
namespace App\Http\Controllers\AccountPayable\Mortgage\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Html, Helper, Upload, V, File, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
use App\Http\Models\AccountPayableModel AS M; // Include the models class

class UploadMortgageController extends Controller{
  //------------------------------------------------------------------------------
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['vendor_mortgage_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'isAjax'=>$req->ajax(),
      'tablez'=>[T::$vendorMortgage],
      'orderField'=>$this->_getOrderField(__FUNCTION__),
      'validateDatabase'=>[
        'mustExist'=>[
          T::$vendorMortgage . '|vendor_mortgage_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    //$r = M::getFileUpload(['vendor_mortgage_id'=>$vData['vendor_mortgage_id'], 'f.type'=>'mortgage']);
    $r     = M::getFileUploadJoin(Model::buildWhere(['v.vendor_mortgage_id'=>$vData['vendor_mortgage_id'],'f.type'=>'mortgage']),T::$vendorMortgage,'vendor_mortgage_id');
    return Upload::getViewlistFile($r, '/uploadMortgage','upload',['includeDeleteIcon'=>1]);
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
    $r = M::getFileUploadJoin(Model::buildWhere(['f.uuid'=>$id]),T::$vendorMortgage,'vendor_mortgage_id',['f.type','f.file','f.uuid','f.ext']);
    //$r = M::getFileUpload(Model::buildWhere(['uuid'=>$id]), 1,['f.type','f.file','f.uuid','f.ext']);
    if(preg_match('/^old_/', $id)){
      $v['data']['path'] = File::getLocation('Mortgage')['showUpload'] . implode('/', [$r['type'], $r['file']]);
    } else{
      $v['data']['path'] = File::getLocation('Mortgage')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
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
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $html = Upload::getHtml();
    $ul = Html::ul('', ['class'=>'nav nav-pills nav-stacked', 'id'=>'uploadList']);
    return Html::div(
      Html::div($html['container'], ['class'=>'col-md-12']), 
      ['class'=>'row']
    ) .
    Html::div(
      Html::div($ul, ['class'=>'col-md-3']) . Html::div('', ['class'=>'col-md-9', 'id'=>'uploadView']), 
      ['class'=>'row']
    ) . 
    $html['hiddenForm'];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $extensionAllow = ['pdf', 'png', 'jpg', 'gif'];
    $valid = V::startValidate([
      'rawReq'    => $req->all(),
      'rule'      => Upload::getRule(),
      'orderField'=> Upload::getOrderField()
    ]);
    $uploadData = Upload::startUpload($valid, $extensionAllow);
    $location   = $uploadData['location'];
    $vData      = $uploadData['data'];
    $response   = $uploadData['uploadData'];
    
    $insertData = [T::$fileUpload=>[
      'name'  =>$vData['data']['qqfilename'], 
      'file'  =>$vData['data']['file'], 
      'uuid'  =>$vData['data']['qquuid'], 
      'ext'   =>$vData['data']['ext'], 
      'path'  =>$location,
      'type'  =>$vData['data']['type'], 
      'foreign_id' => !empty($vData['data']['foreign_id']) ? $vData['data']['foreign_id'] : 0, 
      'cdate' =>$vData['data']['cdate'], 
      'usid'  =>'',  
    ]];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = [];
    try{
      $success += Model::insert($insertData);
      $commit['success'] = $success;
      if(!empty($insertData['foreign_id'])){
        $elastic = ['insert'=>[
          T::$vendorMortgageView=>['vm.vendor_mortgage_id'=>[$vData['data']['foreign_id']]]
        ]];
        $commit['elastic'] = $elastic;
      }
      Model::commit($commit);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req){
    $v = V::startValidate([
      'rawReq'=>['uuid'=>$id] + $req->all(),
      'tablez'=>[T::$fileUpload],
      'orderField'=>['uuid', 'type'],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$fileUpload . '|uuid', 
        ]
      ]
    ]);
    $vData = $v['data'];
 
    $where = Model::buildWhere(['uuid'=>$vData['uuid'], 'type'=>$vData['type']]);
    $r = DB::table(T::$fileUpload)->where($where)->first();

    $otherWhere  = Model::buildWhere(['uuid'=>$vData['uuid'],'type'=>'approval']);
    $approvalFile= DB::table(T::$fileUpload)->where(array_merge($otherWhere,Model::buildWhere(['foreign_id'=>$r['foreign_id']],'<>')))->first();
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $commit = [];
    try{
      $success[T::$fileUpload][] =  DB::table(T::$fileUpload)->where($where)->delete();
      
      if(!empty($approvalFile)){
        $success[T::$fileUpload][] = DB::table(T::$fileUpload)->where($otherWhere)->delete();
      }
      
      $elastic            =  ['insert'=>[T::$vendorMortgageView=>['vm.vendor_mortgage_id'=>[$r['foreign_id']]]]];
      $elastic['insert'] += !empty($approvalFile) ? [T::$vendorPaymentView=>['vp.vendor_payment_id'=>[$approvalFile['foreign_id']]]] : [];
      $commit = [
        'success'=>$success, 
        'elastic'=>$elastic,
      ];
      $response = Model::commit($commit);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['vendor_mortgage_id'],
    ];
    return $orderField[$fn];
  }
}