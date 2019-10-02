<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{RuleField,Form, Elastic, Mail, Html, Helper, Auth, GridData, Upload, V, File, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\{CreditCheckModel,TenantModel AS M}; // Include the models class
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller{
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['application_id'=>$req['id']]);
    unset($req['id']);
    //Validate request
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'tablez'=>[T::$application],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$application . '|application_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $appId = $vData['application_id'];
    
    //Get credit check application
//    $r     = Elastic::searchMatch(T::$creditCheckView,['match'=>['application_id'=>$appId]]);
    $r     = Elastic::searchQuery([
      'index'   => T::$creditCheckView,
      'query'   => [
        'must'  => [
          'application_id'          => $appId,
          T::$fileUpload . '.type'  => 'application',
        ]
      ]
    ]);
    $r     = !empty($r['hits']['hits']) ? $r['hits']['hits'][0]['_source'] : [];
    
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    $applicationFile = [];
    
    //Gather application file names
    foreach($fileUpload as $v){
      if($v['type'] === 'application'){
        $applicationFile[] = $v;
      }
    }
    
    $fileUploadList = Upload::getViewlistFile($applicationFile,'/tenantUploadApplication');
    return $fileUploadList;
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
   
    $r = CreditCheckModel::getFileUpload(Model::buildWhere(['uuid'=>$id]), 1);
    if(preg_match('/^old_/', $id)){
      $v['data']['path'] = File::getLocation('CreditCheckUpload')['showUpload'] . implode('/', [$r['type'], $r['file']]);
    } else{
      $v['data']['path'] = File::getLocation('CreditCheckUpload')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
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

  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $extensionAllow = ['pdf', 'png', 'jpg', 'gif'];
    $v = V::startValidate([
      'rawReq'    => ['qqfilename'=>Upload::getName()] + $req->all(),
      'rule'      => Upload::getRule(),
      'orderField'=> Upload::getOrderField()
    ]);
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
      'usid'  =>'',  
    ]];
    $tenant = CreditCheckModel::getTenantElastic(['application_id'=>$vData['data']['foreign_id']],['tenant_id']);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = [];
    try{
      $success += Model::insert($insertData);
      $commit['success'] = $success;
      $commit['elastic'] = ['insert'=>[
        T::$creditCheckView=>['a.application_id'=>[$vData['data']['foreign_id']]],
        T::$tenantView => ['t.tenant_id'=>[$tenant['tenant_id']]]
      ]];
      Model::commit($commit);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req){

  }
}
