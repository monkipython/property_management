<?php
namespace App\Http\Controllers\CreditCheck\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Library\{RuleField};
use App\Library\{RuleField,Form, Elastic, Mail, Html, Helper, Auth, GridData, Upload, V, File, TableName AS T};
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;

class AgreementController extends Controller{
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['application_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'isAjax'=>$req->ajax(),
      'tablez'=>[T::$application],
      'orderField'=>$this->_getOrderField(__FUNCTION__),
      'validateDatabase'=>[
        'mustExist'=>[
          T::$application . '|application_id', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $rApp = M::getApplication(Model::buildWhere(['a.application_id'=>$vData['application_id']]), 1);
    $r = M::getFileUpload(['application_id'=>$vData['application_id'], 'type'=>'agreement']);
    $reject = ($rApp['moved_in_status']) ? '' : Html::button('Reject This Application', [
      'class'=>'btn btn-danger btn-sm rejectApplication col-md-12', 
      'data-type'=>'application',
    ]);
    $reasonField = ($rApp['moved_in_status']) ? '' : Html::input('', ['id'=>'reason', 'name'=>'reason', 'placeholder'=>'Reason to reject this application.', 'class'=>'col-md-12']);
    $form = isset($perm['uploadAgreementupdate']) ? Html::tag(
      'form', 
      Html::div($reject, ['class'=>'col-md-3']) . Html::div($reasonField, ['class'=>'col-md-9', 'id'=>'reason']),
      ['id'=>'rejectForm']
    ) : '';
    
//    $rejectContainer = ($r[0]['moved_in_status']) ? '' : Html::div($form ,['class'=>'row']);
    $rejectContainer = Html::div($form ,['class'=>'row']);
    return $rejectContainer . Upload::getViewlistFile($r, '/uploadAgreement');
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
      $valid['data']['path'] = File::getLocation('CreditCheckUpload')['showUpload'] . implode('/', [$r['type'], $r['file']]);
    } else{
      $valid['data']['path'] = File::getLocation('CreditCheckUpload')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
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
  public function update($id, Request $req){
    $v = V::startValidate([
      'rawReq'=>$req->all(),
      'rule'=>['reason'=>'required|string|between:10,255'],
      'orderField'=>['reason'],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$application . '|application_id:' . $id, 
        ]
      ]
    ]);
    $vData = $v['data'];
    $vData['id'] = $id;
    $updateData = [
      T::$application=>[
        'whereData'=>['application_id'=>$id], 
        'updateData'=>['moved_in_status'=>0,'raw_agreement'=>'','status'=>'Rejected','is_upload_agreement'=>0]
      ], 
      T::$fileUpload=>[
        'whereData'=>['foreign_id'=>$id, 'type'=>'agreement'],
        'updateData'=>['foreign_id'=>0],
      ]
    ];
    $tenant  = M::getTenantElastic(['application_id'=>$id],['tenant_id']);
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      
      $elastic = [
        'insert'=>[
          T::$creditCheckView=>['a.application_id'=>[$id]],
          
        ]
      ];
      
      $elastic['insert'] += !empty($tenant['tenant_id']) ? [T::$tenantView     =>['t.tenant_id'     =>[$tenant['tenant_id']]]] : [];
      $response['msg'] = $this->_getSuccessMsg('update');
      $this->_sendEmail(__FUNCTION__, $req->all(), $vData);
      
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
    
  }
//------------------------------------------------------------------------------
  public function create(){
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
    
    $updateData = $vData['data']['ext'] === 'pdf' ? [
        T::$application => [
            'whereData'  => ['application_id'=>$vData['data']['foreign_id']],
            'updateData' => ['is_upload_agreement'=>1,'status'=>'Approved'],
        ]
    ] : [];
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

    $tenant      = M::getTenantElastic(['application_id'=>$vData['data']['foreign_id']],['tenant_id']);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = [];
    try{
      $success += Model::insert($insertData);
      $success += !empty($updateData) ? Model::update($updateData) : [];
      $commit['success'] = $success;
      if(!empty($vData['data']['foreign_id'])){
        $commit['elastic'] = ['insert'=>[
          T::$creditCheckView=>['a.application_id'=>[$vData['data']['foreign_id']]],
        ]];
        
        $commit['elastic']['insert'] += !empty($tenant['tenant_id']) ? [T::$tenantView => ['t.tenant_id'=>[$tenant['tenant_id']]]] : [];
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
      'orderField'=>['uuid', 'foreign_id', 'type'],
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
        $response = Model::commit(['success' => $success]);
        if(!empty($r['foreign_id'])){
          $response['elastic'] = ['insert'=>[T::$creditCheckView=>['a.application_id'=>[$r['foreign_id']]]]];
        }
      } catch(\Exception $e){
        $response['error']['mainMsg'] = Model::rollback($e);
      }
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['application_id'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($fn){
    $data = [
      'update'  =>Html::sucMsg('Successfully Reject This Application.'),
    ];
    return $data[$fn];
  }
//------------------------------------------------------------------------------
  private function _sendEmail($fn, $req = [], $vData = []){
    $update =  function($req, $vData){
      $rejectingUsr = $req['ACCOUNT'];
      $r = M::getRoleEmail(Model::buildWhere(['a.application_id'=>$vData['id']]));
      
      $name = $rejectingUsr['firstname'] . ' ' . $rejectingUsr['lastname'];
      $msg = 'Dear ' . $r['firstname'] . ' ' . $r['lastname'] . ':' .Html::br(2);
      $msg .= 'The agreement you uploaded got rejected.' . Html::br() ;
      $msg .= 'Person who rejected application: ' . $name . Html::br();
      $msg .= $name . '\'s email: ' . $rejectingUsr['email']. Html::br();
      $msg .= 'Rejected Date: ' . Helper::usDateTime(). Html::br(2);
      
      $msg .= 'Property Information:'. Html::br();
      $msg .= 'Property #: ' . $r['prop']. Html::br();
      $msg .= 'Unit #: ' . $r['unit']. Html::br();
      $msg .= 'Adresss: ' .  $r['street']. Html::br();
      $msg .= 'City: ' .  $r['city']. Html::br();
      $msg .= 'State:  ' . $r['state']. Html::br();
      $msg .= 'Zipcode: ' . $r['zip']. Html::br();
      $msg .= 'Application scanned date: ' . $r['cdate']. Html::br();
      $msg .= 'Reason: ' . $vData['reason']. Html::br();
      
      Mail::send([
        'to'=>$r['email'] . ',ryan@pamamgt.com,sherri@pamamgt.com,lizeth@pamamgt.com,' . $rejectingUsr['email'],
        'from'=>'admin@pamamgt.com',
        'subject' =>'Application Agreement Rejected',
        'msg'=>$msg
      ]);
    };
    
    $data = [
      'update'=>$update($req, $vData)
    ];
    return $data[$fn];
  }
}