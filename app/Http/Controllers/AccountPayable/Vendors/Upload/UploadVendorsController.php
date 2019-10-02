<?php
namespace App\Http\Controllers\AccountPayable\Vendors\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{RuleField,Form, Elastic, Mail, Html, Helper, Auth, GridData, Upload, V, File, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;

class UploadVendorsController extends Controller{

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
    $r = DB::table(T::$fileUpload)->where('uuid', '=', $id)->first();
    $v['data']['path'] = File::getLocation('Vendors')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
    $v['data']['ext']  = $r['ext'];
    $vData = $v['data'];
    
    $larger = Html::a('View Larger', ['class'=>'btn btn-info pull-left btn-sm viewLarger', 'target'=>'_blank', 'href'=>$vData['path']]);
    $delete = Html::span('Delete', [
      'class'=>'btn btn-danger pull-right btn-sm deleteFile', 
      'data-type'=>'vendors',
    ]);
    return ['html'=>$larger . $delete . Upload::getViewContainerType($vData['path'])['html']];
    
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
      'rawReq'    => $req->all(),
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
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = [];
    try{
      $success += Model::insert($insertData);
      $commit['success'] = $success;
      if(!empty($insertData['foreign_id'])){
        $elastic = ['insert'=>[
          T::$vendorView=>['v.vendor_id'=>[$vData['data']['foreign_id']]]
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
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $commit = [];
    try{
      $success[T::$fileUpload] =  DB::table(T::$fileUpload)->where($where)->delete();
      $commit = [
        'success'=>$success, 
        'elastic'=>['insert'=>[T::$vendorView=>['v.vendor_id'=>[$r['foreign_id']]]]]
      ];
      $response = Model::commit($commit);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
}