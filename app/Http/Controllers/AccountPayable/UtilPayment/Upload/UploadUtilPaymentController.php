<?php
namespace App\Http\Controllers\AccountPayable\UtilPayment\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Html, Helper, Upload, V, File, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
use App\Http\Models\AccountPayableModel AS M; // Include the models class

class UploadUtilPaymentController extends Controller{
//------------------------------------------------------------------------------
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['vendor_util_payment_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'isAjax'=>$req->ajax(),
      'tablez'=>[T::$vendorUtilPayment],
      'orderField'=>$this->_getOrderField(__FUNCTION__),
      'validateDatabase'=>[
        'mustExist'=>[
          T::$vendorUtilPayment . '|vendor_util_payment_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    //$r     = M::getFileUpload(['vendor_util_payment_id'=>$vData['vendor_util_payment_id'], 'f.type'=>'business_license'],0,['f.foreign_id','f.file','f.name','f.path','f.ext','f.uuid','f.type','f.active']);
    $r     = M::getFileUploadJoin(Model::buildWhere(['v.vendor_util_payment_id'=>$vData['vendor_util_payment_id'],'f.type'=>'util_payment']),T::$vendorUtilPayment,'vendor_util_payment_id',0,['f.foreign_id','f.file','f.name','f.path','f.ext','f.uuid','f.type','f.active']);
    return Upload::getViewlistFile($r, '/uploadUtilPayment','upload',['includeDeleteIcon'=>1]);
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
    //$r = M::getFileUpload(Model::buildWhere(['uuid'=>$id]), 1,['f.type','f.file','f.uuid','f.ext']);
    $r = M::getFileUploadJoin(Model::buildWhere(['f.uuid'=>$id]),T::$vendorUtilPayment,'vendor_util_payment_id',1,['f.type','f.file','f.uuid','f.ext']);
    if(preg_match('/^old_/', $id)){
      $v['data']['path'] = File::getLocation('UtilPayment')['showUpload'] . implode('/', [$r['type'], $r['file']]);
    } else{
      $v['data']['path'] = File::getLocation('UtilPayment')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
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
      'rule'      => Upload::getRule() + ['vendor_payment_id'=>'nullable|integer'],
      'orderField'=> array_merge(Upload::getOrderField(),['vendor_payment_id'])
    ]);
    
    $uploadData = Upload::startUpload($valid, $extensionAllow);
    $location   = $uploadData['location'];
    $vData      = $uploadData['data'];
    $response   = $uploadData['uploadData'];
    $type       = !empty($vData['data']['type']) ? $vData['data']['type'] : 'util_payment';
    
    $insertData = [T::$fileUpload=>[
      [
        'name'  =>$vData['data']['qqfilename'], 
        'file'  =>$vData['data']['file'], 
        'uuid'  =>$vData['data']['qquuid'], 
        'ext'   =>$vData['data']['ext'], 
        'path'  =>$location,
        'type'  =>$type, 
        'foreign_id' => !empty($vData['data']['foreign_id']) ? $vData['data']['foreign_id'] : 0, 
        'cdate' =>$vData['data']['cdate'], 
        'usid'  =>'',  
      ],
      [
        'name'  =>$vData['data']['qqfilename'], 
        'file'  =>$vData['data']['file'], 
        'uuid'  =>$vData['data']['qquuid'], 
        'ext'   =>$vData['data']['ext'], 
        'path'  =>$location,
        'type'  =>'approval', 
        'foreign_id' => !empty($vData['data']['vendor_payment_id']) ? $vData['data']['vendor_payment_id'] : 0, 
        'cdate' =>$vData['data']['cdate'], 
        'usid'  =>'',
      ]
    ]];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = [];
    
    try{
      $success += Model::insert($insertData);
      $elastic  = ['insert'=>[]];
      $commit['success'] = $success;
      if(!empty($insertData[T::$fileUpload][0]['foreign_id'])){
        $elastic['insert'] += [
          T::$vendorUtilPaymentView=>['up.vendor_util_payment_id'=>[$vData['data']['foreign_id']]]
        ];
        $commit['elastic'] = $elastic;
      }
      
      if(!empty($insertData[T::$fileUpload][1]['foreign_id'])){
        $elastic['insert'] += !empty($vData['data']['vendor_payment_id']) ? [T::$vendorPaymentView=>['vp.vendor_payment_id'=>[$vData['data']['vendor_payment_id']]]] : [];
        $commit['elastic']  = $elastic;
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
    $where         = Model::buildWhere(['uuid'=>$vData['uuid'], 'type'=>$vData['type']]);
    $view          = $vData['type'] == 'util_payment' ? T::$vendorUtilPaymentView : T::$vendorPaymentView;
    $idCol         = $vData['type'] == 'util_payment' ? 'up.vendor_util_payment_id' : 'vp.vendor_payment_id';
    $otherType     = $vData['type'] == 'util_payment' ? 'Approval' : 'util_payment';
    $otherWhere    = Model::buildWhere(['uuid'=>$vData['uuid'],'type'=>$otherType]);
    $otherView     = $vData['type'] == 'util_payment' ? T::$vendorPaymentView : T::$vendorUtilPaymentView;
    $otherIdCol    = $vData['type'] == 'util_payment' ? 'vp.vendor_payment_id' : 'up.vendor_util_payment_id';
    $r             = DB::table(T::$fileUpload)->where($where)->first();
    $license       = DB::table(T::$fileUpload)->where($otherWhere)->first();
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $commit = [];
    try{
      $success[T::$fileUpload][] =  DB::table(T::$fileUpload)->where($where)->delete();
      $success[T::$fileUpload][] =  DB::table(T::$fileUpload)->where($otherWhere)->delete();
      $elastic                   =  ['insert'=>[$view=>[$idCol=>[$r['foreign_id']]]]];
      $elastic['insert']        += !empty($license['foreign_id']) ? [$otherView=>[$otherIdCol => [$license['foreign_id']]]] : [];
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
      'index' =>['vendor_util_payment_id'],
    ];
    return $orderField[$fn];
  }
}