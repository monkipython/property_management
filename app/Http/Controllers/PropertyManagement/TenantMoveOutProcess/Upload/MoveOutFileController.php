<?php
namespace App\Http\Controllers\PropertyManagement\TenantMoveOutProcess\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Elastic, Mail, Html, Helper, Upload, V, File, TableName AS T};
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;

class MoveOutFileController extends Controller{

  public function index(Request $req){
    $perm = Helper::getPermission($req);
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
    
    //Get move out File
    $r = Elastic::searchMatch(T::$tntMoveOutProcessView,['match'=>['tnt_move_out_process_id'=>$tntMoveOutProcessId]]);
    $r = !empty($r['hits']['hits']) ? $r['hits']['hits'][0]['_source'] : [];
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    $moveOutFile = [];
    
    foreach($fileUpload as $v){
      if($v['type'] == 'tenantMoveOutFile') {
        $moveOutFile[] = $v;
      }
    }
    
    $reject = Html::button('Reject This File', [
      'class'=>'btn btn-danger btn-sm rejectMoveOutFile col-md-12', 
      'data-type'=>'tenantMoveOutFile',
    ]);
    $reasonField = Html::input('', ['id'=>'reason', 'name'=>'reason', 'placeholder'=>'Reason to Reject This File.', 'class'=>'col-md-12']);
    $form = Html::tag(
      'form', 
      Html::div($reject, ['class'=>'col-md-3']) . Html::div($reasonField, ['class'=>'col-md-9', 'id'=>'reason']),
      ['id'=>'rejectForm']
    );
    
    $rejectContainer = $r['status'] == '0' && isset($perm['uploadMoveOutFiledestroy']) ? Html::div($form ,['class'=>'row']) : '';

    $fileUploadList = Upload::getViewlistFile($moveOutFile,'/uploadMoveOutFile');
    return $rejectContainer . $fileUploadList;

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
      $valid['data']['path'] = File::getLocation('TenantMoveOut')['showUpload'] . implode('/', ['tenantMoveOutFile', $r['file']]);
    } else{
      $valid['data']['path'] = File::getLocation('TenantMoveOut')['showUpload'] . implode('/', ['tenantMoveOutFile', $r['uuid'], $r['file']]);
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
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = [];
    try{
      $success += Model::insert($insertData);
      $commit['success'] = $success;
      if(!empty($vData['data']['foreign_id'])){
        $commit['elastic'] = ['insert'=>[
          T::$tntMoveOutProcessView=>['tnt_move_out_process_id'=>[$vData['data']['foreign_id']]]
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
      'rawReq'          => ['tnt_move_out_process_id'=>$id] + $req->all(),
      'rule'            => ['reason'=>'required|string|between:5,255', 'tnt_move_out_process_id'=>'required'],
      'orderField'      => ['reason', 'tnt_move_out_process_id'],
      'validateDatabase'=> [
        'mustExist'=>[
          T::$tntMoveOutProcess . '|tnt_move_out_process_id', 
        ]
      ]
    ]);
    $vData = $v['data'];
    $where = Model::buildWhere(['foreign_id'=>$vData['tnt_move_out_process_id'], 'type'=>'tenantMoveOutFile']);
    $r = DB::table(T::$fileUpload)->where($where)->first();
    $r['reason'] = $vData['reason'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $commit = [];
    if(!empty($r)){
      try{
        $success[T::$fileUpload] =  DB::table(T::$fileUpload)->where($where)->delete();
        $elastic['insert'] = [T::$tntMoveOutProcessView=>['tnt_move_out_process_id'=>[$vData['tnt_move_out_process_id']]]];
    
        Model::commit([
          'success' =>$success,
          'elastic' =>$elastic,
        ]);
        $this->_sendEmail(__FUNCTION__, $req->all(), $r);
        $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      } catch(\Exception $e){
        $response['error']['mainMsg'] = Model::rollback($e);
      }
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################

  private function _getSuccessMsg($fn){
    $data = [
      'destroy'  =>Html::sucMsg('Successfully Rejected the File(s).'),
    ];
    return $data[$fn];
  }
//------------------------------------------------------------------------------
  private function _sendEmail($fn, $req = [], $r = []){
    $update =  function($req, $r){
      $rejectingUsr = $req['ACCOUNT'];
      $rTnt = Helper::getElasticResult(Elastic::searchMatch(T::$tntMoveOutProcessView,['match' =>['tnt_move_out_process_id' => $r['foreign_id']]]));
      $rTnt = $rTnt[0]['_source'];
      $name = $rejectingUsr['firstname'] . ' ' . $rejectingUsr['lastname'];
      $toName = strstr($r['usid'], '@', true);
      $toName = title_case(str_replace('.', ' ', $toName));
      
      $msg = 'Dear ' . $toName . ':' .Html::br(2);
      $msg .= 'The files you uploaded was rejected.' . Html::br() ;
      $msg .= 'Person who rejected the file: ' . $name . Html::br();
      $msg .= $name . '\'s email: ' . $rejectingUsr['email']. Html::br();
      $msg .= 'Rejected Date: ' . Helper::usDateTime(). Html::br(2);
      
      $msg .= 'Move Out Process Information:'. Html::br();
      $msg .= 'Property #: ' . $rTnt['prop']. Html::br();
      $msg .= 'Unit #: ' . $rTnt['unit']. Html::br();
      $msg .= 'Adresss: ' .  $rTnt['street']. Html::br();
      $msg .= 'City: ' .  $rTnt['city']. Html::br();
      $msg .= 'State:  ' . $rTnt['state']. Html::br();
      $msg .= 'Zipcode: ' . $rTnt['zip']. Html::br();
      $msg .= 'Move Out date: ' . $rTnt['move_out_date']. Html::br();
      $msg .= 'Reason: ' . $r['reason']. Html::br();
      
      Mail::send([
        'to'=>$r['usid'] . ',ryan@pamamgt.com,sherri@pamamgt.com,lizeth@pamamgt.com,' . $rejectingUsr['email'],
        'from'=>'admin@pamamgt.com',
        'subject' =>'Tenant Move Out Process File Rejected',
        'msg'=>$msg
      ]);
    };
    
    $data = [
      'destroy'=>$update($req, $r)
    ];
    return $data[$fn];
  }
}