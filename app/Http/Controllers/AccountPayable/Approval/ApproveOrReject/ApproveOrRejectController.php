<?php
namespace App\Http\Controllers\AccountPayable\Approval\ApproveOrReject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, Helper, Mail, TableName AS T};
use App\Http\Models\{Model, ApprovalModel AS M};
use \App\Http\Controllers\AccountPayable\Approval\ApprovalController AS P;

class ApproveOrRejectController extends Controller {
  public function create(Request $req){
    $num = !empty($req['num']) ? $req['num'] : 0;
    $approvalOrReject = !empty($req['approvalOrReject']) ? title_case(preg_replace('/ed$/', '', $req['approvalOrReject'])) : 'Reject';
    
    $label = ($num) ? $num : 'all';
    $option = [0=>$approvalOrReject . ' All Checks'];
    $option = $option + (!empty($num) ? [$num=>$approvalOrReject . ' ' . $label .' Check(s).'] : []);
    $text = Html::h4('Are you sure you want to approve these checks?', ['class'=>'text-center']) . Html::br();
    $fields = [
      'numTransaction' => ['id'=>'numTransaction','label'=>'How Many Transactions?','type'=>'option', 'option'=>$option, 'req'=>1],
      'noLimit'        => ['id'=>'noLimit','label'=>'Do you want to approve the amount over '. Html::bu('$5,000', ['class'=>'text-danger']) .' with signature','type'=>'option', 'option'=>['no'=>'No', 'yes'=>'Yes']],
    ];
    return ['html'=>$text . Html::tag('form', implode('',Form::generateField($fields)), ['id'=>'showConfirmForm', 'class'=>'form-horizontal'])];
  }
//------------------------------------------------/.,;',;-----------------------------
  public function store(Request $req){
    $id = 'vendor_payment_id';
    $vendorPaymentId = [];
    $approvalStatus  = P::getInstance()->approvalStatus;
    $req->merge([$id=>$req['id']]);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$vendorPayment],
      'orderField'      => [$id,'batch_group', 'noLimit'],
      'includeCdate'    => 0, 
      'setting'         => $this->_getSetting('store', $req), 
      'isExistIfError'  => 0,
    ]);
    
    if(isset($valid['error']['batch_group'])){
      Helper::echoJsonError($this->_getErrorMsg('storeNoBatchGroup'), 'popupMsg');
    }
    
    $vData = $valid['data'];
    $approvedOrRejected = ($vData['approvedOrRejected'] == $approvalStatus['approved']) ? $approvalStatus['approved'] : $approvalStatus['rejected'];
    $batchGroup         = $vData['batch_group'];
        
    if(!empty($valid['dataArr'])){
      $vendorPaymentId = array_column($valid['dataArr'], $id );
      $r = M::getVendorApprovalElastic([
        'batch_group'=>$batchGroup, 
        $id=>$vendorPaymentId, 
        'approve.keyword'=>$approvalStatus['waiting']
      ], [$id, 'usid']);
    } else {
      $r = M::getVendorApprovalElastic([
        'batch_group'=>$batchGroup, 
        'approve.keyword'=>$approvalStatus['waiting']
      ], [$id, 'usid']);
    }
    $vendorPaymentId = array_column(array_column(Helper::getElasticResult($r), '_source'), $id);
    
    if(empty($vendorPaymentId)){ 
      Helper::echoJsonError($this->_getErrorMsg('storeNoVendorPaymentId'), 'popupMsg');
    }
    
    ##### THIS IS THE REQUESTER EMAIL NOT MIKE EMAIL #####
    $vData['usid'] = Helper::getElasticResult($r)[0]['_source']['usid'];
    $updateData = [
      T::$vendorPayment=>[
        'whereInData' => ['field'=>$id, 'data'=>$vendorPaymentId],
        'updateData'  => ['approve'=>$approvedOrRejected, 'batch_group'=>$batchGroup, 'approve_date'=>($vData['approvedOrRejected'] == $approvalStatus['approved']) ? Helper::date() : ''],
      ]
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $cntlNoData = [];
    try{
      $success += Model::update($updateData);
      $elastic[T::$vendorPaymentView] = ['vp.' . $id=>$vendorPaymentId];
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
    $usid = $vData['usid'];
    $msg  = 'Hi' . Html::br();
    $msg  .= 'Mike just '. $vData['approvedOrRejected'] .' the checks.' . Html::br();
    $msg  .= Html::a('Please click here to see the checks', ['href'=>P::getInstance()->getBatchGroupLink($batchGroup)]);
    
    if(!Mail::send([
      'to'      =>'mike@pamamgt.com',
      'cc'      =>$usid,
      'bcc'     =>'sean@pamamgt.com',
      'from'    =>'admin@pamamgt.com',
      'subject' =>'Check Approval Status',
      'msg'     =>$msg
    ])){
      Helper::echoJsonError($this->_getErrorMsg('email'), 'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeNoBatchGroup' =>Html::errMsg('You cannot approve or reject the whole checks at once. Please click on "Approval Request" button to select which check you want to approve or reject.'),
      'storeNoVendorPaymentId' => Html::errMsg('Please select either send all transcations for approval or select checks that have ' . P::getInstance()->approvalStatus['pending'] . '.'),
      'email' =>Html::errMsg('There some issues with requesting for approval. Please report this to sean.hayes@dataworker.com.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' =>Html::sucMsg('Successfully Approve The Check(s).'),
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
          'batch_group'=>'required|integer',
          'approvedOrRejected'=>'required|string|between:8,8',
        ]
      ],
    ];
    return $data[$fn];
  }  
}