<?php
namespace App\Http\Controllers\PropertyManagement\Group\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Library\{RuleField};
use App\Library\{Html, Helper, HelperMysql, Upload, V, File, TableName AS T};
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;

class GroupFileController extends Controller{
//------------------------------------------------------------------------------
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['prop_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'tablez'=>[T::$prop],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$prop . '|prop_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $propId = $vData['prop_id'];
    //Get group File
    $r = Helper::getElasticResultSource(HelperMysql::getGroup(['prop_id'=>$propId], ['fileUpload']), 1);
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];

    $delete = isset($perm['uploadGroupFiledestroy']) ? ['includeDeleteIcon'=>1] : [];
  
    return Upload::getViewlistFile($fileUpload,'/uploadGroupFile','upload',$delete);

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
    if(preg_match('/^old_/', $id)){
      $valid['data']['path'] = File::getLocation('Group')['showUpload'] . implode('/', [$r['type'], $r['file']]);
    } else{
      $valid['data']['path'] = File::getLocation('Group')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
    }
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
      Html::div('Recommended Image Size is  1000 by 422 pixels or 1000 by 908 pixels', ['class'=>'text-center', 'style'=>'padding-bottom:15px;']) .
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
    $extensionAllow = ['png', 'jpg', 'jpeg'];
    $v = V::startValidate([
      'rawReq'      => ['qqfilename'=>Upload::getName()] + $req->all(),
      'rule'        => Upload::getRule(),
      'orderField'  => Upload::getOrderField(),
      'includeUsid' => 1
    ]);
    $propId = $v['data']['foreign_id'];
    $r = Helper::getElasticResultSource(HelperMysql::getGroup(['prop_id'=>$propId], ['fileUpload']), 1);
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    ## Allow only 1 file upload per group
    if(!empty($fileUpload)) {
      Helper::echoJsonError($this->_getErrorMsg(__FUNCTION__), 'uploadMsg');
    }
    $uploadData = Upload::startUpload($v, $extensionAllow);
    $location   = $uploadData['location'];
    $vData      = $uploadData['data'];
    $response   = $uploadData['uploadData'];
    $imgSize    = getimagesize($location . $vData['data']['qquuid'] . '/'.$vData['data']['file']);

    ## $imgSize[0] = Width and $imgSize[1] = Height
    if($imgSize[0] > 1400 || $imgSize[0] < 950 || $imgSize[1] > 1000 || $imgSize[1] < 400) {
      Helper::echoJsonError($this->_getErrorMsg('imgSize'), 'uploadMsg');
    }

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
          T::$groupView=>['p.prop_id'=>[$vData['data']['foreign_id']]]
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
        $elastic['insert'] = [T::$groupView=>['p.prop_id'=>[$r['foreign_id']]]];
        
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
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store'   => Html::errMsg('Only one file can be uploaded per Group.'),
      'imgSize' => Html::errMsg('Please set the image width to 950-1400 and height to 400-1000')
    ];
    return $data[$name];
  }
}