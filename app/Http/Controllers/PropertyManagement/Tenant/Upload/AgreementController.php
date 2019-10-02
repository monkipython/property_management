<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Library\{RuleField};
use App\Library\{RuleField,Form, Elastic, Mail, Html, Helper, Auth, GridData, Upload, V, File, TableName AS T};
use App\Http\Models\{CreditCheckModel,TenantModel AS M}; // Include the models class
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
    
    $rApp = CreditCheckModel::getApplication(Model::buildWhere(['a.application_id'=>$vData['application_id']]), 1);
    $r = CreditCheckModel::getFileUpload(['foreign_id'=>$vData['application_id'],'type'=>'agreement']);
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
    
    $rejectContainer = Html::div($form ,['class'=>'row']);
    return $rejectContainer . Upload::getViewlistFile($r, '/tenantUploadAgreement');
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
    $r = CreditCheckModel::getFileUpload(Model::buildWhere(['uuid'=>$id]), 1);
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
    $perm = Helper::getPermission($req);
    $option = [''=>'Select File'];
    if(isset($perm['tenantUploadAgreementstore'])) {
      $option['agreement'] = 'Agreement';
    }
    if(isset($perm['tenantUploadApplicationstore'])) {
      $option['application'] = 'Application';
    }
    $select = Html::buildOption($option, '',['class'=>'fm form-control form-control uploadSelect']);
    return Html::div(
      Html::div($select, ['class'=>'col-md-6 col-md-offset-3']), 
      ['class'=>'row margin-bottom']
    ) .
    Html::div(
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
      $success += !empty($updateData) ? Model::update($updateData) : [];
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
      'update'  =>Html::sucMsg('Successfully Rejected This Application.'),
    ];
    return $data[$fn];
  }
}
