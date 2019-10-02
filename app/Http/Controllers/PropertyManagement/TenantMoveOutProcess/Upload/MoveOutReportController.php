<?php
namespace App\Http\Controllers\PropertyManagement\TenantMoveOutProcess\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Elastic, Helper, Upload, V, File, TableName AS T};

class MoveOutReportController extends Controller{
  
  public function index(Request $req){
    $req->merge(['tnt_move_out_process_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'tablez'=>[T::$tntMoveOutProcess],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tntMoveOutProcess . '|tnt_move_out_process_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $tntMoveOutProcessId = $vData['tnt_move_out_process_id'];
    
    //Get move out report
    $r = Elastic::searchMatch(T::$tntMoveOutProcessView,['match'=>['tnt_move_out_process_id'=>$tntMoveOutProcessId]]);
    $r = !empty($r['hits']['hits']) ? $r['hits']['hits'][0]['_source'] : [];
    
    $fileUpload    = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    $moveOutReport = [];
    
    foreach($fileUpload as $v){
      if($v['type'] == 'tenantMoveOutReport') {
        $moveOutReport[] = $v;
      }
    }
    
    $fileUploadList = Upload::getViewlistFile($moveOutReport,'/uploadMoveOutReport');
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
    $r = Helper::getElasticResult(Elastic::searchMatch(T::$tntMoveOutProcessView,['match'=>['fileUpload.uuid'=>$id]]), 1);
    foreach($r['_source']['fileUpload'] as $file) {
      if($file['uuid'] == $id) {
        $selectedFile = $file;
        break;
      }
    }
    $v['data']['path'] = File::getLocation('TenantMoveOut')['showUpload'] . implode('/', ['tenantMoveOutReport', $selectedFile['uuid'], $selectedFile['file']]);
    $v['data']['ext']  = $selectedFile['ext'];
    $vData = $v['data'];
    if($req->ajax()) {
      return Upload::getViewContainerType($vData['path']);
    } else{
      header('Location: ' . $vData['path']);
      exit;
    }
  }
}
