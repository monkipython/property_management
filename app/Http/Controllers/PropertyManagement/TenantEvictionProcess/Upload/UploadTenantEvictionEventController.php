<?php
namespace App\Http\Controllers\PropertyManagement\TenantEvictionProcess\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Elastic, Html, Helper, Upload, V, File, TableName AS T};
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Models\TenantModel; // Include the models class
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;

class UploadTenantEvictionEventController extends Controller{

  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['tnt_eviction_process_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'tablez'=>[T::$tntEvictionProcess],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tntEvictionProcess . '|tnt_eviction_process_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $tntEvictionProcessId = $vData['tnt_eviction_process_id'];
    
    //Get move out File
    $r = Elastic::searchMatch(T::$tntEvictionProcessView,['match'=>['tnt_eviction_process_id'=>$tntEvictionProcessId]]);
    $r = !empty($r['hits']['hits']) ? $r['hits']['hits'][0]['_source'] : [];
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    $moveOutFile = [];
    
    foreach($fileUpload as $v){
      if($v['type'] == 'tenantEvictionFile') {
        $moveOutFile[] = $v;
      }
    }

    return Upload::getViewlistFile($moveOutFile,'/uploadTenantEvictionEvent');
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
      $valid['data']['path'] = File::getLocation('TenantEviction')['showUpload'] . implode('/', ['tenantEvictionEvent', $r['file']]);
    } else{
      $valid['data']['path'] = File::getLocation('TenantEviction')['showUpload'] . implode('/', ['tenantEvictionEvent', $r['uuid'], $r['file']]);
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
    $v = V::startValidate([
      'rawReq'      => ['qqfilename'=>Upload::getName()] + $req->all(),
      'rule'        => Upload::getRule(),
      'orderField'  => Upload::getOrderField(),
      'includeUsid' => 1
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
      'usid'  =>$v['data']['usid'],  
    ]];
    if(!empty($vData['data']['foreign_id'])){
      $r = TenantModel::getTntEvictionProcess(Model::buildWhere(['tnt_eviction_event_id'=>$vData['data']['foreign_id']]), 'tnt_eviction_process_id');
      $tntEvictionProcessId = $r['tnt_eviction_process_id'];
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = [];
    try{
      $success += Model::insert($insertData);
      $commit['success'] = $success;
      if(!empty($vData['data']['foreign_id'])){
        $commit['elastic'] = ['insert'=>[
          T::$tntEvictionProcessView=>['ep.tnt_eviction_process_id'=>[$tntEvictionProcessId]]
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
    
    $rEvictionProcess = TenantModel::getTntEvictionProcess(Model::buildWhere(['tnt_eviction_event_id'=>$r['foreign_id']]), 'tnt_eviction_process_id');
    $tntEvictionProcessId = $rEvictionProcess['tnt_eviction_process_id'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $commit = [];
    try{
      $success[T::$fileUpload] = DB::table(T::$fileUpload)->where($where)->delete();
      $commit = [
        'success'=>$success, 
        'elastic'=>['insert'=>[T::$tntEvictionProcessView=>['ep.tnt_eviction_process_id'=>[$tntEvictionProcessId]]]]
      ];
      $response = Model::commit($commit);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }

}