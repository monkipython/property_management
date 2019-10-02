<?php
namespace App\Http\Controllers\PropertyManagement\Violation\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Html, Helper, Upload, V, File, TableName AS T};
use App\Http\Models\{Model, ViolationModel AS VM, CreditCheckModel AS M}; // Include the models class
use Illuminate\Support\Facades\DB;

class ViolationFileController extends Controller{
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['violation_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'tablez'=>[T::$violation],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$violation . '|violation_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $violationId = $vData['violation_id'];
    //Get Violation File
    $r = VM::getViolation(['violation_id'=>$violationId], ['fileUpload']);
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    $delete = isset($perm['uploadViolationdestroy']) ? ['includeDeleteIcon'=>1] : [];
    return Upload::getViewlistFile($fileUpload,'/uploadViolation','upload',$delete);

  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $valid = V::startValidate([
      'rawReq'=>['uuid'=>$id] + $req->all(),
      'tablez'=>[T::$fileUpload],
      'orderField'=>['uuid'],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$fileUpload . '|uuid', 
        ]
      ]
    ]);
    $r = M::getFileUpload(Model::buildWhere(['uuid'=>$id]), 1);
    $valid['data']['path'] = File::getLocation('Violation')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
    $valid['data']['ext']  = $r['ext'];
    $vData = $valid['data'];
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
    $extensionAllow = ['png', 'jpg', 'jpeg', 'pdf'];
    $v = V::startValidate([
      'rawReq'      => ['qqfilename'=>Upload::getName()] + $req->all(),
      'rule'        => Upload::getRule(),
      'orderField'  => Upload::getOrderField(),
      'includeUsid' => 1
    ]);
    $violationId = $v['data']['foreign_id'];
    $r = VM::getViolation(['violation_id'=>$violationId], ['fileUpload']);
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];

    $uploadData = Upload::startUpload($v, $extensionAllow);
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
      'usid'  =>$vData['data']['usid'],  
    ]];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = [];
    try{
      $success += Model::insert($insertData);
      $commit['success'] = $success;
      if(!empty($vData['data']['foreign_id'])){
        $commit['elastic'] = ['insert'=>[
          T::$violationView=>['v.violation_id'=>[$vData['data']['foreign_id']]]
        ]];
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
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $commit = [];
    if(!empty($r)){
      try{
        $success[T::$fileUpload] =  DB::table(T::$fileUpload)->where($where)->delete();
        $elastic['insert'] = [T::$violationView=>['v.violation_id'=>[$r['foreign_id']]]];
        
        Model::commit([
          'success' =>$success,
          'elastic' =>$elastic,
        ]);
        $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      } catch(\Exception $e){
        $response['error']['mainMsg'] = Model::rollback($e);
      }
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getSuccessMsg($fn){
    $data = [
      'destroy'  =>Html::sucMsg('Successfully Deleted this File'),
    ];
    return $data[$fn];
  }
}