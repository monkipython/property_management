<?php
namespace App\Http\Controllers\AccountPayable\Approval\RequestApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Mail, Format, GridData, Upload, Account, TableName AS T};
use App\Http\Models\{Model, ApprovalModel AS M};
use \App\Http\Controllers\AccountPayable\Approval\ApprovalController AS P;

class RequestApprovalController extends Controller {
  public function create(Request $req){
    $num = !empty($req['num']) ? $req['num'] : 0;
    $label = ($num) ? $num : 'all';
    $option = [0=>'Request All Transactions To Be Approved'];
    $option = $option + (!empty($num) ? [$num=>'Request ' . $label .' Trasction(s) to be Approved.'] : []);
    $text = Html::h4('Are you sure you want to request these checks to be approved?', ['class'=>'text-center']) . Html::br();
    $fields = [
      'numTransaction'=>['id'=>'numTransaction','label'=>'How Many Transactions?','type'=>'option', 'option'=>$option, 'req'=>1],
      'remark'=>['id'=>'remark','label'=>'Note For Mike:','type'=>'textarea', 'placeholder'=>'Message or Note to Mike'],
    ];
    return ['html'=>$text . Html::tag('form', implode('',Form::generateField($fields)), ['id'=>'showConfirmForm', 'class'=>'form-horizontal'])];
  }
//-----------------------------------------------------------------------------
  public function store(Request $req){
    $vendorPaymentId = [];
    $approvalStatus  = P::getInstance()->approvalStatus;
    $id = 'vendor_payment_id';
    $req->merge([$id=>$req['id']]);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$vendorPayment],
      'orderField'      => [$id,'remark'],
      'includeCdate'    => 0, 
      'setting'         => $this->_getSetting('store', $req), 
      'isPopupMsgError'  => 1,
      'includeUsid'     => 1, 
    ]);
    $vData = $valid['data'];
    $usid  = $vData['usid'];
    
    $mustQuery = ['usid.keyword'=>$usid, 'approve.keyword'=>[$approvalStatus['pending'], $approvalStatus['rejected']]];
    if(!empty($valid['dataArr'])){
      $vendorPaymentId = array_column($valid['dataArr'],'vendor_payment_id'); 
      $mustQuery['approve.keyword'] = $approvalStatus['pending'];
    } 
    $r = M::getVendorApprovalElastic(['query'=>['must'=>$mustQuery]], ['vendor_payment_id']);
    
    $vendorPaymentId = array_column(array_column(Helper::getElasticResult($r), '_source'), 'vendor_payment_id');
    if(empty($vendorPaymentId)){ 
      Helper::echoJsonError($this->_getErrorMsg('storeNoVendorPaymentId'), 'popupMsg');
    }
    
    $batchGroup = HelperMysql::getBatchGroupNumber();
    $updateData = [
      T::$vendorPayment=>[
        'whereInData' => ['field'=>'vendor_payment_id', 'data'=>$vendorPaymentId],
        'updateData'  => ['approve'=>P::getInstance()->approvalStatus['waiting'], 'batch_group'=>$batchGroup, 'send_approval_date'=>Helper::date()],
      ]
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $cntlNoData = [];
    try{
      $success += Model::update($updateData);
      $elastic[T::$vendorPaymentView] = ['vp.vendor_payment_id'=>$vendorPaymentId];
      
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic],
      ]);
      $this->_sendEmail($req, $vData, $batchGroup);
      $response['html'] = $this->_getSuccessMsg('store');
      
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _sendEmail($req, $vData, $batchGroup){
    $name = Helper::getUsidName($req);
    $usid = Helper::getUsid($req);
    $msg  = 'Hi Mike:' . Html::br();
    $msg  .= '"'.$name.'" has requested to approve the checks.' . Html::br();
    $msg  .= Html::a('Please click here to approve the checks', ['href'=>P::getInstance()->getBatchGroupLink($batchGroup) ]);
    
//    if(!empty($vData['remark'])){
      $msg  .= Html::br(2) . Html::h3($name . '\'s Note:' . Html::br() . (!empty($vData['remark']) ? $vData['remark'] : 'None') , ['style'=>'color:red;']);
//    }
    
    if(!Mail::send([
      'to'      =>'mike@pamamgt.com',
      'cc'      =>$usid,
      'bcc'     =>'sean@pamamgt.com',
      'from'    =>'admin@pamamgt.com',
      'subject' =>'Request For Approval From ' . $name,
      'msg'     =>$msg
    ])){
      Helper::echoJsonError($this->_getErrorMsg('email'), 'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'store' =>Html::errMsg('You cannot transfer payment the same Property, Unit and Tenant.'),
      'storeNoVendorPaymentId' => Html::errMsg('Please select either send all transcations for approval or select checks that have ' . P::getInstance()->approvalStatus['pending'] . '.'),
      'email' =>Html::errMsg('There some issues with requesting for approval. Please report this to sean.hayes@dataworker.com.'),
    ];
    return $data[$name];
  }  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' =>Html::sucMsg('Successfully Send The Request For Approval To Upper Management.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    $data = [
      'store'=>[
        'field'=>[
        ],
        'rule'=>[
          'vendor_payment_id'=>'required|integer',
          'remark'=>'nullable|string|between:0,1000',
        ]
      ],
    ];
    return $data[$fn];
  }  
}