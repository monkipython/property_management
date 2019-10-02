<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\DepositRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountReceivable\CashRec\CashRecController AS P;
use App\Library\{V, Form, Elastic, Html, GridData, Account, Format, TableName AS T, Helper, HelperMysql, TenantTrans};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CashRecModel AS M; // Include the models class
use App\Http\Controllers\AccountReceivable\CashRec\Autocomplete\TenantInfoController AS TenantInfo;

use SimpleCsv;
use PDF;
/**
 * ALL THE FIX TRANSACTIONS DO NOT ADD TO cleared_check
 */
class DepositRefundController extends Controller{
  private $_glChart;
  private $_service;
  private $_usr;
  private $_tab;
  private $_mapping;
  private $_isSameTrust;
  private $_isPaymentMove;
  private $_method;
  private $_transferData;
  private $_perm;
//------------------------------------------------------------------------------
  public function create(Request $req){
    $op = !empty($req['op']) ? $req['op'] : '';
    $fields = ['gl_acct' => ['id'=>'gl_acct','label'=>'Deposit Refund Gl','class'=>'autocomplete', 'type'=>'text','req'=>1, 'value'=>'755']];
    $html = Html::div('Are you sure you want to issue total amount of the deposit to the tenant?', ['class'=>'text-center']) . Html::br(2) . implode('',Form::generateField($fields));
    
    if($op == 'reverseIssueDeposit'){
      $html = 'Are you sure you want to Reverse the Deposit Refund?';
    }
    return ['html'=>$html];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $this->_perm = Helper::getPermission($req);
    $this->_usr   = Helper::getUsid($req);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['prop', 'unit', 'tenant', 'gl_acct'], 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tntSecurityDeposit . '|prop,unit,tenant'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $this->_setPropertyValue($vData);
    
    $trans   = $dataset = [];
    $amount  = $depositIssued = $depositPayment =  0;
    $r       = M::getTableData(T::$tntSecurityDeposit, Helper::selectData(['prop', 'unit', 'tenant'], $vData));
    $rTenant = HelperMysql::getTenant(Helper::selectData(['prop', 'unit', 'tenant'], $vData), ['tenant_id']);
    $rVendorPayment = M::getTableData(T::$vendorPayment, ['type'=>'deposit_refund', 'vendid'=>implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $vData))], '*', 1);
    
    if(empty($r)){
      Helper::echoJsonError($this->_getErrorMsg('store'), 'popupMsg');
    } 
//    else if(!empty($rVendorPayment) && $rVendorPayment['print']){
//      Helper::echoJsonError($this->_getErrorMsg('storePrinted', $rVendorPayment), 'popupMsg');
//    }
    
    foreach($r as $v){
      $amount += $v['amount'];
      $trans = $v;
      $depositIssued = ($v['tx_code'] == 'D') ? $v['amount'] : 0;
      $depositPayment += ($v['tx_code'] == 'P') ? $v['amount'] : 0;
    }
    
    if($valid['op'] == 'issueDeposit'){
      ##### CHECK IF IT IS ALEADY ISSUED CHECK YET #####
      if(!empty($rVendorPayment) && !$rVendorPayment['print']){
        Helper::echoJsonError($this->_getErrorMsg('storeInApproval'), 'popupMsg');
      }
      $vendorInfo = $this->_getVendorId($vData);
      $trans['vendor_id'] = $vendorInfo['vendor_id'];
      $trans['gl_acct'] = $vData['gl_acct'];
      $trans['remark']    = 'Issue Security Deposit';
      $dataset = $this->_issueDepositData($vData, $rTenant, $trans, $vendorInfo, $amount);
    }else if($valid['op'] == 'reverseIssueDeposit'){
      $trans['remark'] = 'Reverse Security Deposit';
      $dataset = $this->_reverseDopositData($vData, $rTenant, $trans, $depositIssued, $depositPayment);
    }
    
    
    // 1. INSERT DATA TO vendor if not exist
    // 2. INSERT DATA TO vendor_paymeent
    // 3. INSERT DATA TO tnt_security_deposit
    // 4. UPDATE tenant deposit to Zero
    // 5. refreesh elastic for vendor_payment_view
    // 6. refresh elastic for tenant
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $cntlNoData = [];
    try{
      if(!empty($dataset)){
        $success += Model::insert($dataset['insertData']);
        if(!empty($success['insert:'. T::$vendor])){
          $dataset['updateData'][T::$vendorPayment] = [
            'whereData'=>['vendor_payment_id'=>$success['insert:'. T::$vendorPayment][0]],
            'updateData'=>['vendor_id'=>$success['insert:'. T::$vendor][0]],
          ];
          $elastic['insert'][T::$vendorView] = ['v.vendor_id'=>$success['insert:'. T::$vendor]];
        }
        $success += Model::update($dataset['updateData']);
        $elastic['insert'][T::$tenantView] = ['tenant_id'=>Helper::selectData(['tenant_id'], $rTenant)];
        
        if(isset($success['insert:' . T::$vendorPayment])){
          $elastic['insert'][T::$vendorPaymentView] = ['vp.vendor_payment_id'=>$success['insert:' . T::$vendorPayment]];
        }
        
        if($valid['op'] == 'reverseIssueDeposit' && !empty($rVendorPayment['vendor_payment_id'])){    
          $success[T::$vendorPayment][] = M::deleteTableData(T::$vendorPayment, Model::buildWhere(['vendor_payment_id'=>$rVendorPayment['vendor_payment_id']]));
          $elastic['delete'][T::$vendorPaymentView] = ['vendor_payment_id'=>$rVendorPayment['vendor_payment_id']];
        }
        Model::commit([
          'success' =>$success,
          'elastic' =>$elastic,
        ]);
        $response['popupMsg'] = $dataset['msg'];
        $response['tenantDepositDetail'] = $this->_getTenantDepositDetail($vData);
        $response['data'] = $vData;
      }
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _issueDepositData($vData, $rTenant, $trans,$vendorInfo, $amount){
    // 1. INSERT DATA TO vendor_paymeent
    // 2. INSERT DATA TO tnt_security_deposit
    // 3. UPDATE tenant deposit to Zero
    // 4. refreesh elastic for vendor_payment_view
    // 5. refresh elastic for tenant
    if(!empty($vendorInfo['dataset'])){
      $dataset = $vendorInfo['dataset'];
    }
    $dataset[T::$vendorPayment] = $this->_getVendorPaymentData($trans, $amount);
    $dataset[T::$tntSecurityDeposit] = $this->_getTntSecurityDeposit($trans, $amount * -1);
    $updateData = [
      T::$tenant=>[
        'whereData'=>Helper::selectData(['tenant_id'], $rTenant), 
        'updateData'=>['dep_held1'=>0],
      ],
    ];
    return [
      'insertData'=>HelperMysql::getDataSet($dataset, $this->_usr, $this->_glChart, $this->_service), 
      'updateData'=>$updateData,
      'msg'=>$this->_getSuccessMsg('storeIssueDeposit'),
    ];
  }
//------------------------------------------------------------------------------
  private function _reverseDopositData($vData, $rTenant, $trans, $depositIssued, $depositPayment){
    $dataset = [
      T::$tntSecurityDeposit => $this->_getTntSecurityDeposit($trans, $depositIssued * -1)
    ];
    $updateData = [
      T::$tenant=>[ 
        'whereData'=>Helper::selectData(['tenant_id'], $rTenant), 
        'updateData'=>['dep_held1'=>$depositPayment],
      ],
    ];
    return [
      'insertData'=>HelperMysql::getDataSet($dataset, $this->_usr, $this->_glChart, $this->_service), 
      'updateData'=>$updateData, 
      'msg'=>$this->_getSuccessMsg('storeReverseDeposit'), 
    ];
  }
//------------------------------------------------------------------------------
  public function _getVendorId($vData){ // MOVE OUT PROCESS MIGHT USE IT
    $data   = ['dataset'=>[], 'vendor_id'=>0];
    $vendid = $this->_getVendid($vData);
    $r = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'=>T::$vendorView,
      '_source'=>['vendor_id'],
      'query'=>['must'=>['vendid.keyword'=>$vendid]]
    ]), 'vendor_id');
    $rTenant = HelperMysql::getTenant(['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant']]);
    if(empty($r)){
      $data['dataset'][T::$vendor] = [
        'vendid'     =>$vendid, 
        'name'       =>$rTenant['tnt_name'],
        'line2'      =>$rTenant['line2'],
        'street'     =>$rTenant['street'], 
        'city'       =>$rTenant['city'], 
        'state'      =>$rTenant['state'], 
        'zip'        =>$rTenant['zip'], 
        'phone'      =>'', 
        'gl_acct'    =>$vData['gl_acct'], 
        'name_key'   =>$rTenant['tnt_name'], 
        'flg_1099'   =>'N', 
        'vendor_type'=>'T', 
        'remarks'    =>$rTenant['tnt_name'], 
      ];
    } else{
      $data['vendor_id'] = reset($r)['vendor_id'];
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $tablez = [
      'store' =>[T::$tntSecurityDeposit, T::$tntTrans]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $rVendorPayment = []){
    $data = [
      'store' =>Html::errMsg('You cannot make the deposit refund for this tenant.'),
      'storePrinted' =>Html::errMsg('This tenant is already issued the check ' . Helper::getValue('check_no', $rVendorPayment) . ' for deposit refund once. You cannot make deposit refund for this tenant anymore.'),
      'storeInApproval' =>Html::errMsg('A deposit refund check is already issued for this tenant. It is in the Approval. Please double check it in the Approval.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $approvalLink = Html::a('click the link here', ['href'=>'http://192.168.1.248/pub/acct_payable/approval', 'target'=>'_blank']);
    $data = [
      'storeIssueDeposit' =>Html::sucMsg('Refund the deposit for this tenant is successfully completed. Please '.$approvalLink.' to access approval.'),
      'storeReverseDeposit' =>Html::sucMsg('Reverse deposit refund for this tenant is successfully completed. Please do not forget to void the check.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getVendorPaymentData($trans, $amount){
    $trans['amount']    = $amount;
    $trans['vendor_id'] = $trans['vendor_id'];
    $trans['type']      = 'deposit_refund';// Will change this to pending_check after release Account Payable
    $trans['invoice_date'] = Helper::mysqlDate();
    $trans['vendid'] = $trans['invoice'] = $this->_getVendid($trans);
    $trans['invoice'] = 'Deposit';
    return $trans;
  }
//------------------------------------------------------------------------------
  private function _getVendid($vData){
    return implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $vData));
  }
//------------------------------------------------------------------------------
  private function _getTntSecurityDeposit($trans, $amount){
    unset($trans['tnt_security_deposit_id']);
    $trans['amount']   = $amount;
    $trans['tx_code']  = 'D';
    $trans['batch']    = 0;
    $trans['date1']    = Helper::mysqlDate();
    return $trans;
  }
//------------------------------------------------------------------------------
  private  function _setPropertyValue($vData){
    $this->_glChart   = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$vData['prop']])), 'gl_acct');
    $this->_service   = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$vData['prop']])), 'service');
  }
//------------------------------------------------------------------------------
  private function _getTenantDepositDetail($vData){
    $r = M::getTableData(T::$tntSecurityDeposit, Model::buildWhere(['prop'=>$vData['prop'], 'unit'=>$vData['unit'], 'tenant'=>$vData['tenant']]));
    return TenantInfo::getInstance()->getTenantDepositDetail($vData, $r, $this->_perm, 0);
  }
}