<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\TenantStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper, HelperMysql, TenantTrans, TenantStatement};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CashRecModel AS M; // Include the models class
use SimpleCsv;
use PDF;

class TenantStatementController extends Controller{
  private $_viewPath  = 'app/Report/report/';
  public  $reportList = [
    'edit'=>'View/Fix Ledger Card',
    'create'=>'Post Payment/Invoice',
  ];
  private $_batchOption = [''=>'New Batch#'];
  private $_glChart;
  private $_service;
  private $_rProp;
  private $_rBank;
  private $_rTenant;
  private $_batch;
  private $_usr;
  private $_propBank;
  
//------------------------------------------------------------------------------
  public function index(Request $req){
    $dateRange = Helper::getRangeDate($req->all());
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['prop', 'unit', 'tenant', 'dateRange'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          'tenant|prop,unit,tenant'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $tenantId = array_values(Helper::keyFieldNameElastic(M::getTenantId($vData), 'tenant_id', 'tenant_id'));
    TenantStatement::getPdf($tenantId, $dateRange);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $fields = [
      'date1'   => ['id'=>'date1','label'=>'Post Date','type'=>'text','class'=>'date','req'=>1],
      'amount'  => ['id'=>'amount','label'=>'Amount','class'=>'decimal', 'type'=>'text','req'=>1],
      'prop'    => ['id'=>'prop','label'=>'Prop','class'=>'autocomplete', 'type'=>'text','req'=>1],
      'unit'    => ['id'=>'unit','label'=>'Unit','class'=>'autocomplete', 'type'=>'text','req'=>1],
      'tenant'  => ['id'=>'tenant','label'=>'Tenant','type'=>'option', 'option'=>[''=>'Select Tenant'],'req'=>1],
      'ar_bank' => ['id'=>'ar_bank','label'=>'Bank','type'=>'option', 'option'=>[''=>'Select Bank'],'req'=>1],
      'batch'   => ['id'=>'batch','label'=>'Use Batch','type'=>'option', 'option'=>$this->_batchOption],
      'payOrder'=> ['id'=>'payOrder','label'=>'Pay To','type'=>'option', 'option'=>['rentFirst'=>'Rent (602 & Hub) First', 'rentLast'=>'Rent (602 & Hub) Last']],
    ];
    return [
      'html'=>implode('',Form::generateField($fields)), 
    ];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $updateData = $insertData = $table = [];
    $dataset = [T::$tntTrans=>[], T::$glTrans=>[], 'summaryAcctReceivable'=>[]];
    $req['ar_bank'] = 8;
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['date1','amount','prop', 'unit', 'tenant','ar_bank', 'batch'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          'tenant|prop,unit,tenant',
//          'prop|prop,ar_bank'
        ]
      ]
    ]);
    
    $vData   = $valid['data'];
    $this->_usr     = $req['ACCOUNT']['email'];
    $this->_batch   = empty($vData['batch']) ? HelperMysql::getBatchNumber() : $vData['batch'];
    $this->_glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$vData['prop']])), 'gl_acct');
    $this->_service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$vData['prop']])), 'service');
    $this->_rProp   = M::getTableData(T::$prop,Model::buildWhere(['prop'=>$vData['prop']]), ['ar_bank','group1'], 1);
    $this->_rBank   = Helper::keyFieldName(M::getTableData(T::$propBank,Model::buildWhere(['prop'=>$vData['prop']])), 'bank');
    $this->_rTenant = M::getTableData(T::$tenant,Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $vData)), 'tnt_name', 1);
    $this->_rPropBank = Helper::keyFieldNameElastic(HelperMysql::getPropBank(['prop'=>[$vData['prop']]]), ['prop', 'bank']);
    
    $rTntTrans      = TenantTrans::getApplyToSumResult($vData);
    $response['batchHtml'] = Html::buildOption($this->_batchOption + [$this->_batch=>'Previous Batch#: ' . $this->_batch], '');
    
    // START TO BUILD S TRANSACTION 
    /* BEGINNING BALANCE POSITIVE
     * TX_CODE    GL    APPYTO    BALANCE
     * Old Transaction
     * IN         602   1111      100  
     * IN         607   2222      200
     * Incoming Transaction -550
     * P          602   1111      -100
     * P          607   2222      -200
     * P          375   1234      -250
     * -250 with P 375 1234
     */
    $paymentAmount = $vData['amount'] * -1; ##### PAYMENT IS ALWAYS NEGATIVE #####
    if($rTntTrans['balance'] > 0){
      $appyto  = array_values(array_flip($rTntTrans['data']));
      $r = TenantTrans::searchTntTrans([
        'query'=>[
          'must'=>Helper::selectData(['prop', 'tenant', 'unit'], $vData),
          'filter'=>['appyto'=>$appyto]
        ]
      ]);
      ##### THIS WILL USE THE LAST TRANSACTION IF THEY HAVE MULTIPLE TRANS
      $oldTrans = Helper::keyFieldNameElastic($r, 'appyto');
      
      foreach($rTntTrans['data'] as $appyto=>$posAmount){
        $paymentAmount = $paymentAmount + $posAmount; 
        $transAmount   = ($paymentAmount <= 0) ? ($posAmount * -1) : ($paymentAmount - $posAmount);
        
        $dataset[T::$tntTrans][] = $this->_getPaymentTntTrans($transAmount, $oldTrans[$appyto], $vData);
        $dataset[T::$glTrans][]  = $this->_getPaymentGlTrans($transAmount, $oldTrans[$appyto], $vData);
      }
    }      
      
      
    if($rTntTrans['balance'] <= 0 || $paymentAmount < 0){
      $vData['gl_acct'] = '375';
      $vData['remark']  = $this->_glChart[$vData['gl_acct']]['title'];
      $dataset[T::$glTrans][] = $this->_getPaymentGlTrans($paymentAmount, $vData, $vData);
      $dataset[T::$tntTrans][] = $this->_getPaymentTntTrans($paymentAmount, $vData, $vData);
    }
    ##### ALWAYS CHECK THE SUM SYS TRANS IS ZERO. IF NOT, THE FUNCTION EXIT AND ISSUE ERROR #####
    Helper::isSysTransBalZero($dataset);
    ##### BUILD THE DATASET FOR INSERT INTO DATABASE #####
    $insertData = HelperMysql::getDataSet($dataset, $this->_usr, $this->_glChart, $this->_service);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      # IT IS ALWAYS ONE TRANSACTION ONLY
      $success += Model::insert($insertData);
      $cntlNo   = end($success['insert:'.T::$tntTrans]);
      $success += Model::update([
        T::$tntTrans=>[ 
          'whereInData'=>[['field'=>'cntl_no', 'data'=>$success['insert:'.T::$tntTrans]],['field'=>'appyto', 'data'=>[0]]], 
          'updateData'=>['appyto'=>$cntlNo, 'invoice'=>$cntlNo],
        ],
        T::$glTrans=>[
          'whereInData'=>[['field'=>'seq', 'data'=>$success['insert:'.T::$glTrans]],['field'=>'appyto', 'data'=>[0]]], 
          'updateData'=>['appyto'=>$cntlNo],
        ]
      ]);
      
//      $elastic = [
//        'insert'=>[
//          T::$tntTransView =>['t.cntl_no'=>[$cntlNo]],
//        ]
//      ];
      
      $response['msg'] = '';
      Model::commit([
        'success' =>$success,
//        'elastic' =>$elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    dd('sdfjdddk');
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSysTntTrans($amount, $oldTrans, $vData){
    $oldTrans['amount']   = $amount * -1;
    $oldTrans['tx_code']  = 'S';
    $oldTrans['usid']     = 'SYS';
    $oldTrans['batch']    = $this->_batch;
    $oldTrans['tnt_name'] = $this->_rTenant['tnt_name'];
    unset($oldTrans['cntl_no']);
    $copyData = $oldTrans;
    
    $copyData['amount']       = $amount;
    $copyData['service_code'] = $vData['service'];
    $copyData['appyto']       = 0;
    $copyData['gl_acct']      = $this->_service[$vData['service']]['gl_acct'];
    return [$oldTrans, $copyData];
  }
//------------------------------------------------------------------------------
  private function _getSysGlTrans($sysTntTrans){
    $sysTntTrans[0]['journal'] = $sysTntTrans[1]['journal'] = 'JE';
    $sysTntTrans[0]['group1']  = $sysTntTrans[1]['group1'] = $this->_rProp['group1'];
    $sysTntTrans[0]['gl_contra'] = $this->_rBank[$sysTntTrans[0]['bank']]['gl_acct'];
    $sysTntTrans[1]['gl_contra'] = $this->_rBank[$sysTntTrans[1]['bank']]['gl_acct'];
    return $sysTntTrans;
  }  
//------------------------------------------------------------------------------
  private function _getPaymentGlTrans($amount, $oldTrans, $vData){
    $bank = $vData['ar_bank'];
    return [
      'tx_code'      => 'P',
      'service_code' => $this->_service[$oldTrans['gl_acct']]['service'],
      'batch'        => $this->_batch,
      'bank'         => $bank,
      'amount'       => $amount,
      'gl_contra'    => $this->_rPropBank[$vData['prop'] . $bank]['gl_acct'],
      'inv_remark'   => ($oldTrans['gl_acct'] == '375') ? $oldTrans['remark'] : 'Payment ' . $oldTrans['remark'],
      'bill_seq'     => '8',
      'date1'        => $vData['date1'],
    ] + $oldTrans; // + $vData must be at the end so that if there is duplication, it won't override it
  }
//------------------------------------------------------------------------------
  private function _getPaymentTntTrans($amount, $oldTrans,$vData){
    $bank = $vData['ar_bank'];
    return [
      'tx_code'      => 'P',
      'journal'      => 'CR',
      'service_code' => $this->_service[$oldTrans['gl_acct']]['service'],
      'batch'        => $this->_batch,
      'bank'         => $bank,
      'amount'       => $amount,
      'gl_contra'    => $this->_rPropBank[$vData['prop'] . $bank]['gl_acct'],
      'remark'       => ($oldTrans['gl_acct'] == '375') ? $oldTrans['remark'] : 'Payment ' . $oldTrans['remark'],
      'inv_remark'   => $oldTrans['remark'],
      'bill_seq'     => '8',
      'date1'        => $vData['date1'],
      'date2'        => $vData['date1'],
    ] + $oldTrans; // + $vData must be at the end so that if there is duplication, it won't override it
  }
//------------------------------------------------------------------------------
  private function _getTable($fn){
    return [T::$tntTrans, T::$service, T::$prop];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    return [
      'field'=>[
      ],
      'rule'=>[
        'dateRange'=>'required|date_format:m/d/Y',
        'batch'    =>'nullable|integer',
        'bank'     =>'nullable|integer',
        'amount'   =>'required|numeric|between:0.01,100000.00'
      ]
    ];
  }
}
