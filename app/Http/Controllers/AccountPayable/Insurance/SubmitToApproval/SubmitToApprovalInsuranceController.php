<?php
namespace App\Http\Controllers\AccountPayable\Insurance\SubmitToApproval;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use App\Library\{Html, Helper, V, HelperMysql, Form, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
use App\Http\Models\AccountPayableModel AS M; // Include the models class

class SubmitToApprovalInsuranceController extends Controller{
  private $_viewPath  = 'app/AccountPayable/Insurance/approval/';
  private $_viewTable = '';
  
  public function __construct(){
    $this->_viewTable  = T::$vendorInsuranceView;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $paymentOption = [0=>'Single Payment', 1=>'Monthly Payment'];
    $fields = [
      'isMonthlyPayment'=>['id'=>'isMonthlyPayment','label'=>'Payment Type','type'=>'option', 'option'=>$paymentOption, 'req'=>1],
      'invoice_date'    =>['id'=>'invoice_date','label'=>'Invoice Date','type'=>'text', 'class'=>'date', 'value'=>Helper::usDate()],
    ];
    $title = Html::div(Html::h3('', ['class'=>'box-title']), ['class'=>'box-header with-border']);
    $body = Html::div(Html::tag('form', implode('',Form::generateField($fields)), ['id'=>'approvalForm', 'class'=>'form-horizontal']), ['class'=>'box-body']);
    $boxProfile = Html::div($title . $body, ['class'=>'box-body box-profile']); 
    $form = Html::div($boxProfile, ['class'=>'box box-primary']);
    return ['html'=>$form];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){      
    $valid = V::startValidate([
      'rawReq'            => $req->all(),
      'setting'           => $this->_getSetting(__FUNCTION__),
      'tablez'            => $this->_getTable(__FUNCTION__),
      'includeUsid'       => 1,
      'validateDatabase'  => [
        'mustExist' => [
          T::$vendorInsurance . '|vendor_insurance_id',
        ]
      ]
    ]);
    $vData    = $valid['dataNonArr'];
    $vDataArr = $valid['dataArr'];
    $usid     = $valid['data']['usid'];
    $ids      = array_column($vDataArr,'vendor_insurance_id');
    $r        = Helper::keyFieldNameElastic(M::getInsuranceElastic(['vendor_insurance_id'=>$ids], ['vendor_insurance_id','prop','bank','amount','policy_num','monthly_payment','gl_acct','remark','vendor_id','vendid', T::$fileUpload]),'vendor_insurance_id');
    $insertData = $updateData = $fileUploads = $fileGroup = [];
    foreach($vDataArr as $i => $v){
      $payment  = [];
      $row      = Helper::getValue($v['vendor_insurance_id'],$r,[]);
      $fileR    = Helper::getValue(T::$fileUpload,$row,[]);
      $prop     = Helper::getValue('prop',$row);
      $rService = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$prop])),'service');
      $rGlChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$prop])),'gl_acct');
      $payment += Helper::selectData(['prop', 'bank', 'amount','gl_acct','remark','vendor_id','vendid'],$row);
      $payment += ['active'=>1,'foreign_id'=>$v['vendor_insurance_id'],'type'=>'insurance'];

      $payment['unit']   = Helper::getValue('unit',$payment);
      $payment['tenant'] = Helper::getValue('tenant',$payment);
      $payment['vendid'] = Helper::getValue('vendid',$payment);
      $payment['invoice']= $row['policy_num'];
      $payment['invoice_date'] = $vData['invoice_date'];
      $payment['amount'] = $vData['isMonthlyPayment'] == 0 ? $payment['amount'] : Helper::getValue('monthly_payment', $row);
      $insertRow         = HelperMysql::getDataset([T::$vendorPayment=>$payment],$usid,$rService,$rGlChart);
//      foreach($fileR as $j => $val){
//        $val['type']  = 'approval';
//        $val['cdate'] = $valid['data']['cdate'];
//        unset($val['fileUpload_id']);
//        $fileGroup[] = $val;
//      }
      $fileUploads[] = $fileGroup;  
      $insertData[T::$vendorPayment][] = $insertRow[T::$vendorPayment];  
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try {
      $success    += Model::insert($insertData);
      $fileUploads = P::generateCopyOfFiles([
        'generatedIds'   => $success['insert:'.T::$vendorPayment],
        'oldType'        => 'insurance',
      ]);
      //$fileUploads = $this->_getForeignIds($fileUploads, $ids);
      $success    += !empty($fileUploads) ? Model::insert([T::$fileUpload=>$fileUploads])  : [];
      $commit['elastic']['insert'] = [T::$vendorPaymentView => ['vp.vendor_payment_id'=>$success['insert:'.T::$vendorPayment]]];
      $commit['success'] = $success;
      Model::commit($commit);
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
    } catch(\Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn){
    $setting = [
      'store' => [
        'rule'=>[
          'isMonthlyPayment'    => 'nullable|string|between:0,1',
          'vendor_insurance_id' => 'required'
        ]
      ]
    ];
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $tablez = [
      'store'  => [T::$vendorInsurance],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' => Html::sucMsg('Insurance(s) Successfully Submitted for Approval'),
    ];
    return $data[$name];
  }
################################################################################
############################ HELPER FUNCTIONS ##################################
################################################################################
//------------------------------------------------------------------------------ 
  private function _getForeignIds($data,$foreignData){
    $newData = [];
    foreach($data as $i => $v){
      foreach($v as $j => $val){
        $val['foreign_id'] = $foreignData[$i];
        $newData[]         = $val;
      }
    }
    return $newData;   
  }
}

