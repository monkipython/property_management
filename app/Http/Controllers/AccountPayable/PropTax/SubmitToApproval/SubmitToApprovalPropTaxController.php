<?php
namespace App\Http\Controllers\AccountPayable\PropTax\SubmitToApproval;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use App\Library\{Html, Helper, V, HelperMysql, Form, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
use App\Http\Models\AccountPayableModel AS M; // Include the models class

class SubmitToApprovalPropTaxController extends Controller{
  private $_viewPath  = 'app/AccountPayable/PropTax/approval/';
  private $_viewTable = '';
  
  public function __construct(){
    $this->_viewTable  = T::$vendorPropTaxView;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
    $approvalForm = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__)
    ]);
    return view($page, [
      'data'=>[
        'approvalForm'=>$approvalForm
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){      
    $valid = V::startValidate([
      'rawReq'            => $req->all(),
      'orderField'        => $this->_getOrderField('create'),
      'setting'           => $this->_getSetting('create'),
      'tablez'            => $this->_getTable(__FUNCTION__),
      'includeUsid'       => 1,
      'validateDatabase'  => [
        'mustExist' => [
          T::$vendorPropTax . '|vendor_prop_tax_id',
        ]
      ]
    ]);
    $vData    = $valid['dataNonArr'];
    $vDataArr = $valid['dataArr'];
    $usid     = $valid['data']['usid'];
    $ids      = array_column($vDataArr,'vendor_prop_tax_id');
    $r        = Helper::keyFieldNameElastic(M::getPropTaxElastic(['vendor_prop_tax_id'=>$ids], ['vendor_prop_tax_id','prop','apn','amount1','amount2','amount3','gl_acct','remark1','vendor_id','vendid', T::$fileUpload]),'vendor_prop_tax_id');
    $insertData = $updateData = $fileUploads = $fileGroup = [];
    foreach($vDataArr as $i => $v){
      $payment  = [];
      $row      = Helper::getValue($v['vendor_prop_tax_id'],$r,[]);
      $fileR    = Helper::getValue(T::$fileUpload,$row,[]);
      $prop     = Helper::getValue('prop',$row);
      $rService = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$prop])),'service');
      $rGlChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$prop])),'gl_acct');
      $payment += Helper::selectData(['prop', 'gl_acct','vendor_id','vendid'],$row);
      $payment += ['active'=>1,'foreign_id'=>$v['vendor_prop_tax_id'],'type'=>'prop_tax'];

      $payment['unit']   = Helper::getValue('unit',$payment);
      $payment['tenant'] = Helper::getValue('tenant',$payment);
      $payment['vendid'] = Helper::getValue('vendid',$payment);
      $payment['invoice']= Helper::getValue('apn', $row);
      $payment['remark'] = Helper::getValue('remark1', $row);
      $payment['invoice_date'] = $vData['invoice_date'];
      $payment['amount'] = ($vData['approvalType'] == 'firstInstallment') ? Helper::getValue('amount1', $row) : (($vData['approvalType'] == 'secondInstallment') ? Helper::getValue('amount2', $row) : Helper::getValue('amount3', $row));
      $insertRow         = HelperMysql::getDataset([T::$vendorPayment=>$payment],$usid,$rService,$rGlChart);
      foreach($fileR as $j => $val){
        $val['type']  = 'approval';
        $val['cdate'] = $valid['data']['cdate'];
        unset($val['fileUpload_id']);
        $fileGroup[] = $val;
      }
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
        'oldType'        => 'prop_tax',
        
      ]);

      //$fileUploads = $this->_getForeignIds($fileUploads, $ids);
      $success    += !empty($fileUploads) ? Model::insert([T::$fileUpload=>$fileUploads])  : [];
      $commit['elastic']['insert'] = [T::$vendorPaymentView => ['vp.vendor_payment_id'=>$success['insert:'.T::$vendorPayment]]];
      $commit['success'] = $success;
      Model::commit($commit);
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
    } catch(\Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField  = [
      'create' => ['invoice_date'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn){
    $setting = [
      'create' => [
        'field' => [
          'invoice_date' => ['label'=>'Invoice Date', 'value'=>Helper::usDate()],  
        ],
        'rule'=>[
          'vendor_prop_tax_id' => 'required',
          'approvalType'       => 'nullable|string'
        ]
      ]
    ];
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn){
    $buttton = [
      'create'=>['submit'=>['id'=>'submit', 'value'=>'Submit Approval', 'class'=>'col-sm-12']],
    ];
    return $buttton[$fn];
  }
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$vendorPropTax, T::$vendorPayment],
      'store'  => [T::$vendorPropTax, T::$vendorPayment],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' => Html::sucMsg('Prop Tax Successfully Submitted for Approval'),
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

