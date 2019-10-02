<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\PostPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, TableName AS T, Helper, HelperMysql, TenantTrans, Format};
use App\Http\Models\Model; // Include the models class
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use App\Http\Models\CashRecModel AS M; // Include the models class

class PostPaymentController extends Controller{
  public  $reportList = [
    'edit'=>'View/Fix Ledger Card',
    'create'=>'Post Payment/Invoice',
  ];
  private $_batchOption = [''=>'New Batch#'];
  private $_glChart;
  private $_service;
  private $_tab;
  private $_mapping;
//------------------------------------------------------------------------------  
  public function __construct(){
    $this->_mapping = Helper::getMapping(['tableName'=>T::$tntTrans]);
    $this->_tab = P::getInstance()->tab;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $perm = Helper::getPermission($req);
    $default= P::getDefaulValue($req);
    $arBankReadonly = isset($perm['changeDefaultBankOption']) ? 0 : 1;
    $payOrderReadonly = isset($perm['changePaymentOrder']) ? 0 : 1;
    $fields = [
      'dateRange'=>['id'=>'dateRange','label'=>'View LC Fr/To','type'=>'text','class'=>'daterange','value'=>Helper::getValue('dateRange', $default['value']), 'req'=>1],
      'date1'   => ['id'=>'date1','label'=>'Post Date','type'=>'text','class'=>'date','req'=>1, 'value'=>date('m/d/Y')],
      'amount'  => ['id'=>'amount','label'=>'Amount','class'=>'decimal', 'type'=>'text','req'=>1],
      'prop'    => ['id'=>'prop','label'=>'Prop','class'=>'autocomplete', 'type'=>'text','value'=>Helper::getValue('prop', $default['value']), 'req'=>1],
      'unit'    => ['id'=>'unit','label'=>'Unit','class'=>'autocomplete', 'type'=>'text','value'=>Helper::getValue('unit', $default['value']), 'req'=>1],
      'tenant'  => ['id'=>'tenant','label'=>'Tenant','type'=>'option', 'option'=>Helper::getValue('tenant', $default['option']), 'value'=>Helper::getValue('tenant', $default['value']), 'req'=>1],
      'ar_bank' => ['id'=>'ar_bank','label'=>'Bank','type'=>'option', 'disabled'=>$arBankReadonly,  'option'=>Helper::getValue('bank', $default['option']),'req'=>1, 'value'=>Helper::getValue('defaultBank', $default)],
//      'batch'   => ['id'=>'batch','label'=>'Use Batch','type'=>'option', 'option'=>$this->_batchOption],
      'payOrder'=> ['id'=>'payOrder','label'=>'Pay Order Of','type'=>'option', 'disabled'=>$payOrderReadonly, 'option'=>['rentLast'=>'Rent (602 & Hub) Last', 'rentFirst'=>'Rent (602 & Hub) First']],
    ];
    return P::getCreateContent($fields, $this->_tab);
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $tableData = $data = [];
    $perm = Helper::getPermission($req);
    $orderField = $this->_getBankPayOrderField($perm);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => array_merge(['date1','amount','prop', 'unit', 'tenant'], $orderField), 
      'setting'         => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          'tenant|prop,unit,tenant',
        ]
      ]
    ]);
    $vData = $valid['data'];
    
    ##### CHECK THE DEFAULT BANK WITH PERMISSION #####
    $vData['ar_bank']  = isset($perm['changeDefaultBankOption']) ? $vData['ar_bank'] : HelperMysql::getDefaultBank($vData['prop']);
    ##### CHECK PAY ORDER WITH PERMISSION #####
    $vData['payOrder'] = (isset($vData['payOrder']) && $vData['payOrder'] == 'rentFirst') ? ['602'=>0, 'other'=>1] : ['602'=>1, 'other'=>0];
    
    
    ##### CHECK TO MAKE SURE THE DATE IS THE SAME OTHER IT WILL NOT ALL USER TO POST THE PAYMENT #####
    if(!empty($vData['batch'])){
      if(!empty(V::validateionDatabase(['mustNotExist'=>[T::$clearedCheck . '|bank,orgprop,batch']], ['data'=>['bank'=>$vData['ar_bank'], 'orgprop'=>$vData['prop'], 'batch'=>$vData['batch']], 'isExitIfError'=>0]))){
        Helper::echoJsonError($this->_getErrorMsg('clearedCheck', $vData), 'popupMsg');
      } else if(V::validateionDatabase(['mustExist'=>[T::$glTrans . '|date1,batch']], ['data'=>$vData, 'isExitIfError'=>0])){
        Helper::echoJsonError($this->_getErrorMsg('diffData1', $vData), 'popupMsg');
      }
    }
    
    TenantTrans::cleanTransaction($vData);
    $postPaymentData = TenantTrans::getPostPayment($vData, ['isNewBatch'=>0]);
    $endBalance = $postPaymentData['endBalance'];
    $data   = $postPaymentData['show'];
    $rData  = $postPaymentData['rData'];
    $cashGl = $rData['rBank'][$vData['ar_bank']]['gl_acct'];
    $line   = 'border-bottom: 1px solid #ccc;';
    $num    = 0;
    
    ########################### FORMAT DATA ###########################
    $header = [
      'col1'=>['val' => 'Service','param'=>['class'=>'info']],
      'col2'=>['val' => 'Desc','param'=> ['class'=>'info']],
      'col3'=>['val' => 'Apply To','param'=> ['class'=>'info']],
      'col4'=>['val' => 'Invoice','param'=> ['class'=>'info']],
      'col5'=>['val' => 'Remark','param'=> ['class'=>'info']],
      'col6'=>['val' => 'Date','param'=> ['class'=>'info']],
      'col7'=>['val' => 'Charge','param'=> ['class'=>'info']],
      'col8'=>['val' => 'Payment','param'=> ['class'=>'info']],
    ];
    foreach($data as $appyto=>$val){
      $inData = isset($val['IN']) ? $val['IN'] : [];
      $index = '[' . $num . ']';
      $hiddenField = implode('', Form::generateField(['appyto'=>['id'=>'appyto'.$index,'label'=>'Post Date','type'=>'hidden','class'=>'','value'=>$appyto]]));
      if(!empty($inData)){
        $tableData[] = [
          'col1'=>['val'=>$inData['service_code'], 'header'=>$header['col1']],   
          'col2'=>['val'=>Html::i('', ['class'=>'fa fa-fw fa-file-text-o']) . ' ' . $this->_mapping['tx_code'][$inData['tx_code']],'param'=>['class'=>'text-danger'], 'header'=>$header['col2']],
          'col3'=>['val'=>$inData['appyto'], 'header'=>$header['col3']],    
          'col4'=>['val'=>$inData['invoice'], 'header'=>$header['col4']],
          'col5'=>['val'=>$inData['remark'], 'header'=>$header['col5']],
          'val6'=>['val'=>Format::usDate($inData['date1']), 'header'=>$header['col6']],
          'col7'=>['val'=>Format::usMoneyMinus($inData['amount']), 'param'=>['class'=>'text-danger'], 'header'=>$header['col7']],
          'val8'=>['val'=>'', 'header'=>$header['col8'] ],
        ];
      }
      
      $pData = $val['P'];
      $tableData[] = [
        'col1'=>['val'=>$pData['service_code'], 'param'=>[], 'header'=>$header['col1']],   
        'col2'=>['val'=>Html::i('', ['class'=>'fa fa-fw fa-dollar']) . ' ' . $this->_mapping['tx_code'][$pData['tx_code']], 'param'=>['class'=>'text-green'], 'header'=>$header['col2']],
        'col3'=>['val'=>$appyto, 'header'=>$header['col3']],    
        'col4'=>['val'=>0, 'header'=>$header['col4']],
        'col5'=>['val'=>Html::input($pData['remark'], ['id'=>'remark'.$index, 'name'=>'remark'.$index]) . $hiddenField, 'header'=>$header['col5']],
        'val6'=>['val'=>Format::usDate($pData['date1']), 'header'=>$header['col6']],
        'col7'=>['val'=>'', 'header'=>$header['col7']],
        'val8'=>['val'=>Format::usMoneyMinus($pData['amount']), 'param'=>['class'=>'text-green'], 'header'=>$header['col8']],
      ];
      ++$num;
    }
    
    $html  = Html::div(Html::tag('h4', 'You are posting the payment to Prop: '.$vData['prop'].', Unit: '.$vData['unit'].', Tenant: '.$vData['tenant'].', Bank: ' . $vData['ar_bank']),['class'=>'text-center']);
    $html .= Html::buildTable(['data'=>$tableData, 'isOrderList'=>0, 'tableParam'=>['class'=>'table table-bordered table-hover', ]]);
    $html .= Html::div('Ending Balance: ' . Format::usMoneyMinus($endBalance), ['class'=>($endBalance >= 0 ) ? 'text-danger text-right' : 'text-green text-right']);
    $html .= Html::div('Total Paid Amount (Go to  GL '.$cashGl.') : ' . Format::usMoney($vData['amount']), ['class'=>'text-right']);
    return ['html'=>Html::tag('form', $html, ['id'=>'showConfirmForm'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    /** ##### POST PAYMENT #####
     * 1. Pay rent first
     * 2. Pay rent last
     */
    $perm = Helper::getPermission($req);
    $insertData = $table = [];
    $dataset = [T::$tntTrans=>[], T::$glTrans=>[], T::$batchRaw=>[], 'summaryAcctReceivable'=>[]];
    $orderField = $this->_getBankOrderField($perm);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => array_merge(['date1','amount','prop', 'unit', 'tenant', 'appyto', 'remark'], $orderField), 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeCdate'    => 0,
      'includeUsid'     => 1, 
      'validateDatabase'=> [
        'mustExist' => [
          T::$tenant . '|prop,unit,tenant',
//          T::$prop . '|prop,ar_bank'
        ]
      ]
    ]);
    
    $vData    = $valid['data'];
    $this->_glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$vData['prop']])), 'gl_acct');
    $this->_service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$vData['prop']])), 'service');
    
    ##### CHECK THE DEFAULT BANK WITH PERMISSION #####
    $vData['ar_bank']  = isset($perm['changeDefaultBankOption']) ? $vData['ar_bank'] : HelperMysql::getDefaultBank($vData['prop']);

    ##### CHECK TO MAKE SURE THE DATE IS THE SAME OTHER IT WILL NOT ALL USER TO POST THE PAYMENT #####
    if(!empty($vData['batch'])){
      // THIS IS USED TO STOP ONLY THE ERROR IS IN THE 'SHOW' ROUTE
      V::validateionDatabase(['mustNotExist'=>[T::$clearedCheck . '|bank,orgprop,batch']], ['data'=>['bank'=>$vData['ar_bank'], 'orgprop'=>$vData['prop'], 'batch'=>$vData['batch']]]);
      V::validateionDatabase(['mustExist'=>[T::$glTrans . '|date1,batch']], ['data'=>$vData]);
    }
    
    unset($vData['remark'], $vData['appyto']);
    $vDataArr = Helper::keyFieldName($valid['dataArr'], 'appyto');
    $postPaymentData = TenantTrans::getPostPayment($vData, ['vDataArr'=>$vDataArr]);
    $endBalance = $postPaymentData['endBalance'];
    $rData  = $postPaymentData['rData'];
    $cashGl = $rData['rBank'][$vData['ar_bank']]['gl_acct'];
    $line   = 'border-bottom: 1px solid #ccc;';
    
    $dataset = $postPaymentData['store'];
    $dataset['summaryAcctReceivable'] = [];
    $dataset[T::$batchRaw] = $dataset[T::$glTrans];
    
    if(!empty($rData['batch'])){
      $this->_batchOption[$rData['batch']] = 'Previous Batch#: ' . $rData['batch'];
    }
    ##### ALWAYS CHECK THE SUM SYS TRANS IS ZERO. IF NOT, THE FUNCTION EXIT AND ISSUE ERROR #####
    Helper::isSysTransBalZero($dataset);
    ##### BUILD THE DATASET FOR INSERT INTO DATABASE #####
    unset($dataset['paymentAmount']);// NOT NEEDED
    
    $insertData = HelperMysql::getDataSet($dataset, $vData['usid'], $this->_glChart, $this->_service);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      # IT IS ALWAYS ONE TRANSACTION ONLY
      $success += Model::insert($insertData);
      $cntlNo   = end($success['insert:'.T::$tntTrans]);
      
      if(!empty($insertData[T::$tntSecurityDeposit])){
        $tenantData = TenantTrans::getUpdateTenantDepositData($insertData[T::$tntSecurityDeposit]);
        $success   += Model::update($tenantData['updateData']);
        $elastic[T::$tenantView] = $tenantData['elastic'][T::$tenantView];
      }
      
      $success += Model::update([
        T::$glTrans=>[
          'whereInData'=>[['field'=>'seq', 'data'=>$success['insert:'.T::$glTrans]],['field'=>'appyto', 'data'=>[0]]], 
          'updateData'=>['appyto'=>$cntlNo, 'invoice'=>$cntlNo],
        ]
      ]);
      
      ##### UPDATE APPYTO WHEN IT'S ZERO #####
      $updateDataZeroAppyto = P::getZeroAppytoUpdateData($vData,  $success['insert:'.T::$tntTrans]);
      if(!empty($updateDataZeroAppyto)){
         $success += Model::update($updateDataZeroAppyto);
      }
      
      $response = [
        'mainMsg'   =>$this->_getSuccessMsg('store'),
        'batchHtml' => Html::buildOption($this->_batchOption, ''), 
        'date1'=>Format::usDate($vData['date1']),
      ];
      
      $elastic[T::$tntTransView] = ['tt.cntl_no'=>$success['insert:' . T::$tntTrans]];
      $elastic[T::$glTransView]  = ['gl.seq'=>$success['insert:' . T::$glTrans]];
      
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic],
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getTable($fn){
    return [T::$tntTrans, T::$service, T::$prop];
  }
//------------------------------------------------------------------------------
   private function _getErrorMsg($name, $vData = []){
    $data = [
      'clearedCheck' =>Html::errMsg('You recorded the payment for this prop, unit, tenant, and bank with this batch# '. Helper::getValue('batch', $vData) .' once already. You cannot record it twice with this batch. Please use different batch or prop,tenant,and unit.'),
      'diffData1'    =>Html::errMsg('You cannot change the post date if you use the batch# '. Helper::getValue('batch', $vData) .'. Please change the post date to the previous one.'),
    ];
    return $data[$name];
  }  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($fn){
    $data = [
      'store'  =>Html::sucMsg('Successfully Record the Payment.'),
    ];
    return $data[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    return [
      'field'=>[
      ],
      'rule'=>[
        'dateRange'=>'required|date_format:m/d/Y',
//        'batch'    =>'nullable|integer',
        'bank'     =>'nullable|integer',
        'amount'   =>'required|numeric|between:0.01,100000.00', 
        'payOrder' =>'nullable|string|between:8,9'
      ]
    ];
  }
//------------------------------------------------------------------------------
  private function _getBankPayOrderField($perm){
    $data = [];
    if(isset($perm['changePaymentOrder'])){
      $data[] = 'payOrder';
    } 
    return array_merge($data, $this->_getBankOrderField($perm));
  }
//------------------------------------------------------------------------------
  private function _getBankOrderField($perm){
    $data = [];
    if(isset($perm['changeDefaultBankOption'])){
      $data[] = 'ar_bank';
    }
    return $data;
  }
}

