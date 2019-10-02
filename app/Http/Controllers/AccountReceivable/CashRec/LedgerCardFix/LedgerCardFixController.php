<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\LedgerCardFix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountReceivable\CashRec\CashRecController AS P;
use App\Library\{V, Form, Elastic, Html, GridData, Account, Format, TableName AS T, Helper, HelperMysql, TenantTrans};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CashRecModel AS M; // Include the models class
/**
 * ALL THE FIX TRANSACTIONS DO NOT ADD TO cleared_check
 * 
 * Note: 
 * FIX = REMOVE
 */
class LedgerCardFixController extends Controller{
  private $_glChart;
  private $_service;
  private $_usr;
  private $_tab;
  private $_mapping;
  private $_isSameTrust;
  private $_transferData;
  private $_adjustGl = '512';
  private $_today;
//------------------------------------------------------------------------------
  public function __construct(){
    $this->_today   = date('Y-m-d');
    $this->_tab     = P::getInstance()->tab;
    $this->_mapping = Helper::getMapping(['tableName'=>T::$tntTrans]);
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['oldProp', 'prop'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'isExistIfError' =>0,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$prop . '|prop',
        ]
      ]
    ]);
    $vData = $valid['data'];
    if(!isset($valid['error'])){
      $r = Elastic::searchQuery([
        'index'=>T::$bankView, 
        '_source'=>['includes'=>['trust', 'prop']],
        'query'=>['must'=>['prop.keyword'=>[$vData['oldProp'], $vData['prop']]]]
      ]);
      $r = Helper::keyFieldNameElastic($r, 'trust', 'trust');
      return ['isSameTrust'=>(count($r) == 1) ? 1 : 0];
    } else{
      return ['error'=>$valid['error']];
    }
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $tableData = $data = [];
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => isset($req['inAmount']) ? ['inAmount', 'cntl_no', 'oldAmount'] : ['pAmount', 'cntl_no', 'oldAmount'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'isExistIfError' =>0,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tntTrans . '|cntl_no',
        ]
      ]
    ]);
    if(isset($valid['error']['cntl_no'])){
      return ['html'=>$this->_getErrorMsg('showCntlNo')];
//      Helper::echoJsonError($this->_getErrorMsg('showCntlNo'), 'popupMsg');
    } else if(isset($valid['error'])){ // Take care of the error
      return ['html'=> isset($valid['error']['inAmount']) ? $this->_getErrorMsg('showGreaterZero') : $this->_getErrorMsg('showLessZero'), 'isError'=>true];
    }
    
    $vData   = $valid['data'];
    $this->_setPropertyValue($vData);
   
    $fixData = $this->_getFixTransaction($vData, 'show');
    $data    = $fixData['show'];
    $info    = $fixData['info'];
    $button = Html::div(Html::input('', ['id'=>'confirm', 'class'=>'btn btn-info btn-sm col-sm-8 col-sm-offset-2 margin-bottom', 'value'=>'Confirm', 'type'=>'submit']), ['class'=>'row']);
    ########################### FORMAT DATA ###########################
    $header = [
      'col1'=>['val' => 'Type','param'=>['class'=>'info']],
      'col2'=>['val' => 'Service','param'=>['class'=>'info']],
      'col3'=>['val' => 'Desc','param'=> ['class'=>'info']],
      'col4'=>['val' => 'Apply To','param'=> ['class'=>'info']],
      'col5'=>['val' => 'Invoice','param'=> ['class'=>'info']],
      'col6'=>['val' => 'Remark','param'=> ['class'=>'info']],
      'col7'=>['val' => 'Date','param'=> ['class'=>'info']],
      'col8'=>['val' => 'Orignal Amount','param'=> ['class'=>'info']],
    ];
    foreach($data as $appyto=>$val){
      $hiddenField = implode('', Form::generateField(['appyto'=>['id'=>'appyto','label'=>'Post Date','type'=>'hidden','class'=>'','value'=>$appyto]]));
      ##### ORIGINAL DATA TRANSACTION #####
      $originalData = $val['original'];
      if(!empty($originalData)){
        foreach($originalData as $originalVal){
          $icon = $originalVal['tx_code'] == 'P' ? 'fa fa-fw fa-dollar': 'fa fa-fw fa-file-text-o';
          $color = $originalVal['tx_code'] == 'P' ? 'text-green' : 'text-danger';
          $tableData[] = [
            'col1'=>['val'=>'Orignal Trans.', 'header'=>$header['col1']],   
            'col2'=>['val'=>$originalVal['service_code'], 'header'=>$header['col2']],   
            'col3'=>['val'=>Html::i('', ['class'=>$icon]) . ' ' . $this->_mapping['tx_code'][$originalVal['tx_code']],'param'=>['class'=>$color], 'header'=>$header['col3']],
            'col4'=>['val'=>$originalVal['appyto'], 'header'=>$header['col4']],    
            'col5'=>['val'=>$originalVal['invoice'], 'header'=>$header['col5']],
            'col6'=>['val'=>$originalVal['remark'], 'header'=>$header['col6']],
            'val7'=>['val'=>Format::usDate($originalVal['date1']), 'header'=>$header['col7']],
            'col8'=>['val'=>Format::usMoneyMinus($originalVal['amount']), 'param'=>['class'=>$color], 'header'=>$header['col8']],
          ];
        }
        $tableData[] = [
          'col1'=>['val'=>Html::repeatChar('-', 100), 'param'=>['colspan'=>8, 'class'=>'text-center text-info'], 'header'=>$header['col1']],   
        ];
      }
      
      ##### FIX DATA TRANSACTION #####
      $fixData = $val['fix'];
      $icon = $fixData['tx_code'] == 'P' ? 'fa fa-fw fa-dollar': 'fa fa-fw fa-file-text-o';
      $color = $fixData['tx_code'] == 'P' ? 'text-green' : 'text-danger';
      $amoutType  = isset($vData['pAmount']) ? Html::span(Html::i('', ['class'=>'fa fa-fw fa-dollar']) . 'Payment',['class'=>'text-green']) 
                                             : Html::span(Html::i('', ['class'=>'fa fa-fw fa-file-text-o']) . 'Invoice', ['class'=>'text-danger']);
      $desc = 'Change ' . $amoutType . ' From ' . Html::span(Format::usMoneyMinus($fixData['originalAmount']), ['class'=>'text-danger']); 
      $desc .= ' To ' . Html::span(Format::usMoneyMinus($fixData['fixAmount']), ['class'=>'text-green']);


      ##### CHECK THE ERROR FIRST BEFORE MOVING FORWARD #####
      ##### GET THE ERROR FOR WAY TO GREATER AND LESS THAN ORIGINAL AMOUNT #####
      if(isset($vData['pAmount']) && $fixData['fixAmount'] <= $fixData['originalAmount']){
        return ['html'=>$this->_getErrorMsg('showPayment', $fixData), 'isError'=>true];
      } else if(isset($vData['inAmount']) && $fixData['fixAmount'] >= $fixData['originalAmount']){
        return ['html'=>$this->_getErrorMsg('showInvoice', $fixData), 'isError'=>true];
      }
      
      $tableData[] = [
        'col1'=>['val'=>'Fixed Trans.', 'param'=>[], 'header'=>$header['col1']],   
        'col2'=>['val'=>$fixData['service_code'], 'param'=>[], 'header'=>$header['col2']],   
        'col3'=>['val'=>Html::i('', ['class'=>$icon]) . ' ' . $this->_mapping['tx_code'][$fixData['tx_code']], 'param'=>['class'=>$color], 'header'=>$header['col3']],
        'col4'=>['val'=>$appyto, 'header'=>$header['col4']],    
        'col5'=>['val'=>0, 'header'=>$header['col5']],
        'col6'=>['val'=>Html::input($fixData['remark'], ['id'=>'remark', 'name'=>'remark']) . $hiddenField, 'header'=>$header['col6']],
        'val7'=>['val'=>Format::usDate($fixData['date1']), 'header'=>$header['col7']],
        'col8'=>['val'=>$desc, 'header'=>$header['col8'], 'param'=>[] ],
      ];
            
      $tableData[] = [
        'col2'=>['val'=>'Ending Balance: ' . Html::span(Format::usMoneyMinus($fixData['balance']), ['id'=>'endingBalance']), 'param'=>['colspan'=>8, 'class'=>'text-center'], 'header'=>$header['col1']],   
      ];
      $tableData[] = [
        'col1'=>['val'=>Html::repeatChar('-', 100), 'param'=>['colspan'=>8, 'class'=>'text-center text-info'], 'header'=>$header['col1']],   
      ];
      
      $tableData[] = [
        'col1'=>['val'=>'', 'param'=>['class'=>'text-left'], 'header'=>$header['col1']],   
        'col2'=>['val'=>$info['text'], 'param'=>['colspan'=>7, 'class'=>'text-left'], 'header'=>$header['col1']],   
      ];
    }
    
    $html  = Html::div(Html::tag('h4', 'You are changing the amount for Prop: '.$info['prop'].', Unit: '.$info['unit'].', Tenant: '.$info['tenant']),['class'=>'text-center']);
    $html .= Html::buildTable(['data'=>$tableData, 'isOrderList'=>0, 'tableParam'=>['class'=>'table table-bordered table-hover', ]]);
    $html .= $button;
    return [
      'html'=>Html::tag('form', $html, ['id'=>'showConfirmForm', 'class'=>'form-horizontal']), 
      'removePaymentField'=>$info['removePaymentField'], 
      'isFixInvoiceWithPayment'=>$info['isFixInvoiceWithPayment'], 
      'info'=>$info
    ];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $data = [];//    $paymentMove  = ['removeInvoice','removePayment','notRemoveInvoice'];
    $deleteVendorPaymentId = '';
    $this->_isSameTrust  = isset($req['isSameTrust']) ? $req['isSameTrust'] : 0;
    $orderField = isset($req['inAmount']) ? ['inAmount','remark', 'cntl_no', 'appyto'] : ['pAmount','remark', 'cntl_no', 'appyto'];
    
    ##### MAKE SURE USER SELECT FROM AUTOCOMPLETE WHEN THEY FIX TRANSACTION#####
    if(is_null($this->_isSameTrust) && isset($req['pAmount'])){
      Helper::echoJsonError($this->_getErrorMsg('storeNotSelectAutocomplete'), 'popupMsg');
    }
    
    if(isset($req['pAmount'])){
      $orderField = array_merge($orderField, ['prop', 'unit', 'tenant']);
      $orderField = !$this->_isSameTrust ? array_merge($orderField, ['vendid', 'gl_acct']) : $orderField;
    }
    
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => $orderField, 
      'setting'         => $this->_getSetting('show', $req), 
      'formId'          => 'showConfirmForm',
      'includeUsid'     => 1,
      'validateDatabase'=> [
        'mustExist'=>[
          T::$tntTrans . '|cntl_no',
        ]
      ]
    ]);
    $vData      = $valid['data'];
    $this->_usr = $vData['usid'];
    $this->_setPropertyValue($vData);
    $this->_transferData = Helper::selectData(['prop', 'unit', 'tenant', 'vendid', 'gl_acct'], $vData);
    $this->_transferData['bank'] = isset($vData['prop']) ? Helper::getElasticResult(HelperMysql::getProp($vData['prop']), 1)['_source']['ar_bank'] : '';
    $this->_adjustGl     = (isset($this->_transferData['gl_acct']) && in_array('gl_acct', $orderField)) ? $this->_transferData['gl_acct'] : '';
    ##### START TO VALIDATE THE TENANT 2 DATA #####
    if(isset($this->_transferData['prop']) && isset($this->_transferData['gl_acct']) && in_array('gl_acct', $orderField)){
      V::validateionDatabase(['mustExist'=>[T::$glChart . '|prop,gl_acct']], ['data'=>$this->_transferData]);
    } else if(isset($this->_transferData['prop']) && isset($this->_transferData['unit']) && isset($this->_transferData['tenant']) && in_array('gl_acct', $orderField)){
      V::validateionDatabase(['mustExist'=>[T::$glChart . '|prop,gl_acct']], ['data'=>$this->_transferData]);
    }
    
    ##### GET FIX TRANSACTION FROM getFixTransaction #####
    $fixData = $this->_getFixTransaction($vData, 'store');
    if(isset($vData['prop']) && $this->_transferData['prop'] == $fixData['info']['prop'] && $this->_transferData['unit'] == $fixData['info']['unit'] && $this->_transferData['tenant'] == $fixData['info']['tenant']){
      Helper::echoJsonError($this->_getErrorMsg('store'), 'popupMsg');
    }
    $dataset = $fixData['store'];
    $info    = $fixData['info'];
    if(!empty($dataset[T::$glTrans])){
      $dataset[T::$batchRaw] = $dataset[T::$glTrans];
      
      if(empty($fixData['isIncludeSummary'])){
      } else{
        $dataset['summaryAcctReceivable'] = [];
      }
    }
    ##### ALWAYS CHECK THE SUM SYS TRANS IS ZERO. IF NOT, THE FUNCTION WILL EXIT AND ISSUE ERROR #####
    Helper::isSysTransBalZero($dataset);
    ##### BUILD THE DATASET FOR INSERT INTO DATABASE #####
    $dataset = TenantTrans::getTntSurityDeposit($dataset);
    
    ##### DEAL WITH THE TNT SECURITY DEPOSIT REFUND #####
    $deleteVendorPaymentData = TenantTrans::deleteVendorPaymentIdDeposit($dataset);
    if(!empty($deleteVendorPaymentData['vendorPaymentId'])){
      $deleteVendorPaymentId = $deleteVendorPaymentData['vendorPaymentId'];
      $dataset[T::$tntSecurityDeposit][] = $deleteVendorPaymentData[T::$tntSecurityDeposit];
    } else if(!empty($deleteVendorPaymentData[T::$tntTrans])){
      $dataset[T::$tntTrans][] = $deleteVendorPaymentData[T::$tntTrans];
    }
    $insertData = HelperMysql::getDataSet($dataset, $this->_usr, $this->_glChart, $this->_service, !empty($fixData['splitCashAcct']));
    unset($insertData['cleared_check']); // FIX TRANSACTION NEVER TOUCH CASH ACCT/BANK RAC
//    dd('insert', $insertData);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $cntlNoData = [];
    try{
      $trackLedgerCardFixCntlNo = array_column($insertData[T::$trackLedgerCardFix], 'cntl_no'); 
      unset($insertData[T::$trackLedgerCardFix]);

      # IT IS ALWAYS ONE TRANSACTION ONLY
      $success += Model::insert($insertData);
      $success['insert:'.T::$tntTrans] = array_merge($success['insert:'.T::$tntTrans], $trackLedgerCardFixCntlNo);
      
      ##### TAKE CARE OF THE track_ledger0card_fix DATABASE #####
      foreach($success['insert:'.T::$tntTrans] as $cntlNoVal){
        $cntlNoData[T::$trackLedgerCardFix][] = [
          'cntl_no'=>$cntlNoVal,
          'batch_group'=>end($success['insert:'.T::$tntTrans]),
          'cdate'=>Helper::mysqlDate(),
          'usid'=>$this->_usr
        ];
      }
      $success += Model::insert($cntlNoData);
      ##### DEALING WITH ELASTIC INSERT DATA #####
      $elastic = ['insert'=>[
        T::$tntTransView =>['tt.cntl_no'=>$success['insert:' . T::$tntTrans]],
        T::$trackLedgerCardFixView=>['t.track_ledgercard_fix_id'=>$success['insert:' . T::$trackLedgerCardFix]],
      ]];
      if(isset($success['insert:' . T::$glTrans])){
        $elastic['insert'][T::$glTransView] = ['gl.seq'=>$success['insert:' . T::$glTrans]];
      }
      if(isset($success['insert:' . T::$vendorPayment])){
        $elastic['insert'][T::$vendorPaymentView] = ['vp.vendor_payment_id'=>$success['insert:' . T::$vendorPayment]];
      }
      if(!empty($deleteVendorPaymentId)){
        $success[T::$vendorPayment][] = M::deleteTableData(T::$vendorPayment, Model::buildWhere(['type'=>'deposit_refund', 'vendor_payment_id'=>$deleteVendorPaymentId]));
        $elastic['delete'][T::$vendorPaymentView] = ['vendor_payment_id'=>$deleteVendorPaymentId];
      }
      
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
      $response['html'] = $this->_getSuccessMsg('store');
      TenantTrans::cleanTransaction($info, $info['batch']);
//      TenantTrans::invoiceTenantSecurityDeposit($info);
//      if(!empty($invoiceBatch)){
//        TenantTrans::cleanTransaction($info, $invoiceBatch); // NEED TO CLEAN UP AGAIN AFTER INVOICE IT
//      }
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
########################## FIX TRA NACTION FUNCTION SECTION #####################  
################################################################################  
  private function _getFixTransaction($vData, $route){
    $data = [];
    $text = '';
    $rTntTrans     = $this->_getFixTransData($vData);
    $rFixTransData = $rTntTrans;
    $isFixInvoiceWithPayment = 0;
    
    $removePaymentField = Html::div(Html::i('', ['class'=>'fa fa-fw fa-exclamation-circle']) . Html::b('Where should the payment belong to? (Ex. Payment should go to Prop:0001, Unit:0001, Tenant:1)'), ['class'=>'text-center text-danger']);
    $removePaymentField .= implode('', Form::generateField([
      'prop'        => ['id'=>'prop','label'=>'Prop','class'=>'autocomplete', 'type'=>'text', 'req'=>1],
      'unit'        => ['id'=>'unit','label'=>'Unit','class'=>'autocomplete', 'type'=>'text','req'=>1],
      'tenant'      => ['id'=>'tenant','label'=>'Tenant','type'=>'option', 'option'=>[], 'req'=>1],
      'isSameTrust' => ['id'=>'isSameTrust','label'=>'isSameTrust','type'=>'hidden'],
    ]));

    $label = Html::div(Html::i('', ['class'=>'fa fa-fw fa-exclamation-circle']) . Html::b('You removed payment that does not belongs to the same entity. Please provide the following information cut the check.'), ['class'=>'text-center text-danger']);
    $removePaymentField .= Html::div($label . implode('', Form::generateField([
      'vendid'  => ['id'=>'vendid','label'=>'Vendor','class'=>'autocomplete', 'type'=>'text', 'req'=>1],
      'gl_acct' => ['id'=>'gl_acct','label'=>'Payable GL', 'type'=>'text', 'req'=>1],
    ])), ['id'=>'additionalForm', 'style'=>'display:none;']);
        
    ##### LIST SELECTION OF WHAT TO DO WITH PAYMENT #####
    if(isset($vData['pAmount'])){ // We know they fixing the payment amount
      $data = $this->_fixPayment($vData, $rFixTransData, $route);
    } else { // We know they fixing the invoice amount
      if($this->_isHaveInvoicePayment($rFixTransData['associateTrans'], $rTntTrans['fixInfo'])){
        $isFixInvoiceWithPayment = 1;
        $vData['payOrder'] = [$rFixTransData['fixInfo']['gl_acct']=>0, '602'=>1, 'other'=>3];  
        $text = Html::h3(Html::icon('fa fa-fw fa-exclamation-triangle') . ' The remaining payment will apply to to different invoice OR advanace rent.', ['class'=>'text-center text-yellow']); 
        $data = $this->_fixInvoiceWithPayment($vData, $rFixTransData, $route);
      } else{
        $removePaymentField = '';
        $data = $this->_fixInvoiceNoPayment($vData, $rFixTransData, $route);
      }
    }
    
    if($route == 'store'){
      ##### UPDATE BATCH, USID, ACCT_TYPE, FOR ALL TRANSACTION #####
      $batch = HelperMysql::getBatchNumber();
      foreach($data['store'] as $dbTable=>$val){
        foreach($val as $i=>$v){
          $data['store'][$dbTable][$i]['batch'] = $batch;
          $data['store'][$dbTable][$i]['usid'] = $vData['usid'];
          
          if($dbTable == T::$tntTrans || $dbTable == T::$glTrans){
            $data['store'][$dbTable][$i]['acct_type'] =   $this->_glChart[$v['gl_acct']]['acct_type'];
          }
        }
      }
//      dd($data);
      ##### MERGE ALL THE ADJUST GL ACCT TRANSACTION eg. 505 #####
      if(!empty($data['store'][T::$glTrans])){
        $adjustTrans = [];
        $adjustTransamount = 0;
        foreach($data['store'][T::$glTrans] as $i=>$v){
          if($v['gl_acct'] == $this->_adjustGl){
            $adjustTransamount += $v['amount'];
            $adjustTrans = $v;
            unset($data['store'][T::$glTrans][$i]);
          }
        }
        if(!empty($adjustTrans)){
          $adjustTrans['amount'] = $adjustTransamount;
          $data['store'][T::$glTrans][] = $adjustTrans;
        }
      }
      
      ##### MERGE ALL THE S TRANSACTION IF THEY HAS THE SAME GL_ACCT ##### 
      foreach($data['store'] as $dbTable=>$val){
        if($dbTable == T::$tntTrans || $dbTable == T::$glTrans){
          $sTrans = [];
          $sTransAmount = [];
          foreach($val as $i=>$v){
            if($v['tx_code'] == 'S') {
              $id =  $v['gl_acct'] . '-'. $v['appyto'];
              $sTransAmount[$id] = isset($sTransAmount[$id]) ? $sTransAmount[$id] + $v['amount'] : $v['amount'];
              $sTrans[$id] = $v;
              unset($data['store'][$dbTable][$i]);
            }
          }
          
          foreach($sTransAmount as $glAcct=>$amount){
            if($amount != 0){
              $sTrans[$glAcct]['amount'] = $amount;
              $data['store'][$dbTable][] = $sTrans[$glAcct];
            }
          }
        }
        
        if(empty($data['store'][$dbTable])){
          unset($data['store'][$dbTable]);
        } else{
          $data['store'][$dbTable] = array_values($data['store'][$dbTable]);
        }
      }
    }
    
    ##### OUTPUT THE DATA #####
    $data['info']['text'] = $text . Html::div('', ['id'=>'removePaymentContainer']);
    $data['info']['removePaymentField'] = $removePaymentField;
    $data['info']['prop']   = $rTntTrans['fixInfo']['prop'];
    $data['info']['unit']   = $rTntTrans['fixInfo']['unit'];
    $data['info']['tenant'] = $rTntTrans['fixInfo']['tenant'];
    $data['info']['batch']  = !empty($batch) ? $batch : '';
    $data['info']['isFixInvoiceWithPayment'] = $isFixInvoiceWithPayment;
    return $data;
  }
//------------------------------------------------------------------------------
  private function _fixPayment($vData, $rFixTrans, $route){
    $data  = [];
    $totalPaymentAmount = $rFixTrans['fixTotalAmount']; // TOTAL AMOUNT THAT HAS IN THE PAYMENT TRANSACTION
    $originalFixAmount  = ($vData['pAmount'] - $totalPaymentAmount);
    $fixAmount          = $originalFixAmount;
    $endingBalance      = $rFixTrans['openItemData']['balance'] + $fixAmount;
    
    ##### SHOW SECTION #####
    $data['show'][0]['original'] = [];  
    $data['show'][0]['fix'] =  $rFixTrans['fixInfo'];
    $data['show'][0]['fix']['remark']        = 'Remove Payment';
    $data['show'][0]['fix']['originalAmount']= $totalPaymentAmount;
    $data['show'][0]['fix']['fixAmount']     = $vData['pAmount'];
    $data['show'][0]['fix']['balance']       = $endingBalance;
    $data['show'][0]['fix']['tx_code']       = 'P';
    $data['show'][0]['fix']['service_code']  = '0';
    
    ##### STORE SECTION #####
    if($route == 'store'){
      $resolvePaymentTrans      = [];
      $allPaymentTrans          = $rFixTrans['fixPaymentData'];
      $data['isIncludeSummary'] = 1;
      $data['splitCashAcct']    = 1;
      ##### PREPARE THE DATA AND CHANGE ALL THE AMOUNT #####
 
      foreach($allPaymentTrans as $i=>$paymentTrans){
        ##### THE fixAmount WILL NEVER GREATER THAN ALL TRRANSACTION #####
        $fixAmount = $fixAmount + $paymentTrans['amount']; 
        $amount    = ($fixAmount >= 0) ? $paymentTrans['amount'] * -1 : ($paymentTrans['amount'] - $fixAmount) * -1;
        $paymentTrans['amount'] = $amount;
        $paymentTrans['tx_code'] = 'P';
        $paymentTrans['journal'] = 'CR';
        $paymentTrans['remark']  = $paymentTrans['remarks'] = $paymentTrans['inv_remark'] = $vData['remark'];
        $paymentTrans['date1']   = $paymentTrans['date2']   = $this->_today;
        $resolvePaymentTrans[$i] = $paymentTrans;

        // Break out the loop
        if($fixAmount <= 0){ break; }
      }
//      ##### GET ALL OLD cnlt_no SO THAT WE CAN DISABLE THE FIX
//      ##### THIS HAS TO BE SEPERATE LOOP BECAUSE THE TOP ONE WILL STOP #####
//      foreach($allPaymentTrans as $i=>$paymentTrans){
//        $data['store'][T::$trackLedgerCardFix][] = [
//          'cntl_no'=>$paymentTrans['cntl_no']
//        ];
//      }
      $data['store'][T::$trackLedgerCardFix][] = [
        'cntl_no'=>$rFixTrans['fixInfo']['cntl_no']
      ];
      
      if($this->_isSameTrust){// IT'S SAME TRUST SO NOT CUTTING CHECK 
        // REMOVE PAYMENT AND DO NOT REMOVE INVOICE WITH THE SAME TRUST/ENTITY
        /* BEGINNING BALANCE NEGATIVE (ONLY 1 APPLYTO)
         * -----------------TENANT 1 -------------------------
         * today is 2019-01-20
         * Type       TX_CODE date       batch   GL    APPYTO    BALANCE
         * Original   IN      2019-01-01   1    602   13937496    600
         * Original   P       2019-01-02   2    602   13937496   -600
         * -----------------tnt_trans -------------------------
         * Change to  P       2019-01-20   3    602   13937496    600
         * -----------------gl_trans---------------------------
         * Change to  P       2019-01-20   3    602   13937496    600      
         * Change to  P       2019-01-20   3    102   13937496   -600    
         * -----------------TENANT 2----------------------
         * USE TenantTrans::getPostPayment to get all the trans
         */
        ##### TENANT 1 #####
        foreach($resolvePaymentTrans as $paymentTrans){
          $data['store'][T::$tntTrans][] = $paymentTrans;
          $data['store'][T::$glTrans][]  = $paymentTrans;
        }
        ##### TENANT 2 #####
        $tenant2Trans = $this->_getPostPayment($originalFixAmount, [], ['isRunCleanup'=>0]);
        // UPDATE REMARK
        foreach($tenant2Trans as $table=>$val){
          if(is_array($val)){
            foreach($val as $i=>$v){
              $tenant2Trans[$table][$i]['remark'] = 'Payment Fr '.implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $v));
            }
          }
        }
//        $tenant2Trans[T::$tntTrans]['remark'] = $tenant2Trans[T::$glTrans]['remark'] = 
//        'Payment Fr '.implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $tenant2Trans[T::$tntTrans][0]));
        
        $data['store'][T::$tntTrans] = array_merge($data['store'][T::$tntTrans], $tenant2Trans[T::$tntTrans]);
        $data['store'][T::$glTrans]  = array_merge($data['store'][T::$glTrans], $tenant2Trans[T::$glTrans]);
      } else{ // CUT CHECK BECAUSE IT'S DIFFERENT TRUST
        // REMOVE PAYMENT BUT DO NOT REMOVE INVOICE WITH DIFFERENT TRUST/ENTITY
        /* BEGINNING BALANCE NEGATIVE (ONLY 1 APPLYTO)
         * today is 2019-01-20
         * Type       TX_CODE date       batch   GL    APPYTO    BALANCE
         * Original   IN      2019-01-01   1    602   13937496    600
         * Original   P       2019-01-02   2    602   13937496   -600
         * -----------------tnt_trans---------------------------
         * Change to  P       2019-01-20   3    602   13937496    600
         * -----------------gl_trans---------------------------
         * Change to  P       2019-01-20   3    602   13937496    600      
         * Change to  P       2019-01-20   3    102   13937496   -600    
         * Change to  P       2019-01-20   3    512   13937496   -600 // Default Remark "Payment belongs to prop-unit-tenant"      
         * Change to  P       2019-01-20   3    102   13937496    600 // Default Remark "Payment belongs to prop-unit-tenant"    
         * -----------------pending_Check----------------------
         * issue one pending check  
         */
        ##### THE REASON LOOP 2 TIME IS BECAUSE WE GET THE TRANSACTION IN ORDER #####
        foreach($resolvePaymentTrans as $paymentTrans){
          $data['store'][T::$tntTrans][] = $paymentTrans;
          $data['store'][T::$glTrans][]  = $paymentTrans;
        }
        ##### THE REASON LOOP 2 TIME IS BECAUSE WE GET THE TRANSACTION IN ORDER #####
        foreach($resolvePaymentTrans as $paymentTrans){
          $paymentTrans['amount'] = $paymentTrans['amount'] * -1;
          $data['store'][T::$glTrans][]   = $this->_getAdjustGlTrans($paymentTrans);
        }
        $data['store'][T::$vendorPayment][] = $this->_getVendorPayment($rFixTrans['fixInfo'], abs($originalFixAmount)); 
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _fixInvoiceWithPayment($vData,$rFixTrans, $route){ 
    $data  = [];
    $totalPaymentAmount = $rFixTrans['fixTotalAmount']; // TOTAL AMOUNT THAT HAS IN THE PAYMENT TRANSACTION
    $originalFixAmount  = ($vData['inAmount'] - $totalPaymentAmount);
    $fixAmount          = $originalFixAmount;
    $endingBalance      = $rFixTrans['openItemData']['balance'] + $fixAmount;
    $originalFixInvoice = $rFixTrans['fixInfo'];
    $fixApplyTo         = $originalFixInvoice['appyto'];
    
    $splitTrans           = TenantTrans::splitTxcode($rFixTrans['associateTrans']);
    $groupTrans           = self::_groupTransAmount($splitTrans, $originalFixInvoice['appyto']);
    $originalPaymentTrans = $groupTrans['sum']['P'];
    
    ##### SHOW SECTION #####
    $data['show'][$fixApplyTo]['original'] = [];
    $data['show'][$fixApplyTo]['fix'] =  $rFixTrans['fixInfo'];
    $data['show'][$fixApplyTo]['fix']['remark']        = 'Remove ' . $originalFixInvoice['remark'];
    $data['show'][$fixApplyTo]['fix']['originalAmount']= $totalPaymentAmount;
    $data['show'][$fixApplyTo]['fix']['fixAmount']     = $vData['inAmount'];
    $data['show'][$fixApplyTo]['fix']['balance']       = $endingBalance;

//    dd($rFixTrans['openItemData']['balance'], $endingBalance, $fixAmount, $rFixTrans);
    ##### STORE SECTION #####
    if($route == 'store'){
      $fixInvoiceTrans = $rFixTrans['fixInvoiceData'];
      $fixInvoiceTrans['amount'] = $fixAmount;
//      $rFixTrans['fixInfo']['amount'] = $vData['oldAmount'] - $vData['inAmount'];
      $leftOverPayment = ($this->_getLeftoverPayment($rFixTrans['associateTrans'], $rFixTrans['fixInfo']));
      $tntInvoiceTrans = $fixInvoiceTrans;
      $tntInvoiceTrans['remark']  = $tntInvoiceTrans['remarks'] = $tntInvoiceTrans['inv_remark'] = $vData['remark'];
      $tntInvoiceTrans['date1']   = $this->_today;
      $data['store'][T::$tntTrans][] = $tntInvoiceTrans;
      
      ##### GET ALL OLD cnlt_no SO THAT WE CAN DISABLE THE FIX
      ##### THIS HAS TO BE SEPERATE LOOP BECAUSE THE TOP ONE WILL STOP #####
      $data['store'][T::$trackLedgerCardFix][] = ['cntl_no'=>$rFixTrans['fixInfo']['cntl_no']];
      // REMOVE INVOICE BUT APPLY PAYMENT INVOICE
      /* BEGINNING BALANCE NEGATIVE (ONLY 1 APPLYTO)
       * INVOICE OPEN ITEM IS LESS THAN THE NEGATIVE NUMBER
       * today is 2019-01-20
       * Type       TX_CODE date       batch   GL    APPYTO    BALANCE
       * Original   IN      2019-01-01   1    614   13937000    600 (NEED TO BE REMOVE)
       * Original   IN      2019-01-01   1    602   13937025    500
       * -----------------tnt_trans---------------------------
       * Change to  IN      2019-01-20   3    614   13937000   -600
       * Change to  S       2019-01-20   3    614   13937000    600  
       * Change to  S       2019-01-20   3    602   13937025   -500
       * Change to  S       2019-01-20   3    375   13937001   -100
       * -----------------gl_trans---------------------------
       * Change to  S       2019-01-20   3    614   13937000    600
       * Change to  S       2019-01-20   3    602   13937025   -500
       * Change to  S       2019-01-20   3    375   13937001   -100
       *----------------------------XXX---------------------------------------
       *----------------------------XXX---------------------------------------
       *----------------------------XXX---------------------------------------
       * INVOICE OPEN ITEM IS GREATER THAN THE NEGATIVE NUMBER
       * today is 2019-01-20
       * Type       TX_CODE date       batch   GL    APPYTO    BALANCE
       * Original   IN      2019-01-01   1    614   13937000    600 (NEED TO BE REMOVE)
       * Original   IN      2019-01-01   1    602   13937025    700
       * -----------------tnt_trans---------------------------
       * Change to  IN      2019-01-20   3    614   13937000   -600
       * Change to  S       2019-01-20   3    614   13937000    600  
       * Change to  S       2019-01-20   3    602   13937025   -600
       * -----------------gl_trans---------------------------
       * Change to  S       2019-01-20   3    614   13937000    600
       * Change to  S       2019-01-20   3    602   13937025   -600
       */

      //****** THIS SHOULD BE THE SAME AS CLEAN TRANSACTION ******//
      // _getPostPayment ONLY NEED POSITIVE PAYMENT ONLY 
      
      if(!empty($leftOverPayment['P'])){
        $vData['glNeedDelete'] = $rFixTrans['fixInfo']['gl_acct'];
        
        ##### THIS IS IMPORTANT. WE TAKE OUT MONEY THE AMOUNT USER WANT TO TAKE OUT. NOT ALL THE PAYMENT ####
        $vData['totalLeftOverPayment'] = $leftOverPayment['P'];
        $totalInvoiceAmountRemoved  = $vData['oldAmount'] - $vData['inAmount'];
        $leftOverPayment['P'] = ($leftOverPayment['P'] + $totalInvoiceAmountRemoved) >= 0 ? $leftOverPayment['P'] : $totalInvoiceAmountRemoved * -1;
        
        $trans = $this->_getPostPayment(abs($leftOverPayment['P']), Helper::selectData(['prop', 'unit', 'tenant', 'bank'], $fixInvoiceTrans), $vData);
        $storeData[T::$tntTrans] = $trans[T::$tntTrans];
        $storeData[T::$glTrans] = $trans[T::$glTrans];
//        $trans = $this->_getPostPayment(abs($fixAmount), Helper::selectData(['prop', 'unit', 'tenant', 'bank'], $allInvoiceTrans), $vData);
//        $storeData[T::$tntTrans] = $trans[T::$tntTrans];
//        $storeData[T::$glTrans] = $trans[T::$glTrans];
      
        // OFFSET PAYMENT
//        $fixInvoiceTrans['amount']  = abs($leftOverPayment['P']);
        $fixInvoiceTrans['amount']  = abs($trans['paymentAmount']);
        $fixInvoiceTrans['date1']   = $fixInvoiceTrans['inv_date'] = $this->_today;
        $storeData[T::$tntTrans][]  = $fixInvoiceTrans;
        $storeData[T::$glTrans][]   = $fixInvoiceTrans;
        
        foreach($storeData as $dbTable=>$val){
          foreach($val as $i=>$v){
            $storeData[$dbTable][$i]['tx_code'] = 'S';
            $storeData[$dbTable][$i]['journal'] = 'JE';
          }
        }
        $data['store'][T::$tntTrans] = array_merge($data['store'][T::$tntTrans], $storeData[T::$tntTrans]);
        $data['store'][T::$glTrans] = $storeData[T::$glTrans];
        // OFFSET INVOICE 
        $data['isIncludeSummary'] = 0;
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _fixInvoiceNoPayment($vData, $rFixTrans, $route){
    $data  = [];
    $totalPaymentAmount = $rFixTrans['fixTotalAmount']; // TOTAL AMOUNT THAT HAS IN THE PAYMENT TRANSACTION
    $originalFixAmount  = ($vData['inAmount'] - $totalPaymentAmount);
    $fixAmount          = $originalFixAmount;
    $endingBalance      = $rFixTrans['openItemData']['balance'] + $fixAmount;
    $originalFixInvoice = $rFixTrans['fixInfo'];
    $fixApplyTo         = $originalFixInvoice['appyto'];
        
    ##### SHOW SECTION #####
    $data['show'][$fixApplyTo]['original'] = [];  
    $data['show'][$fixApplyTo]['fix'] =  $rFixTrans['fixInfo'];
    $data['show'][$fixApplyTo]['fix']['remark']        = 'Remove ' . $originalFixInvoice['remark'];
    $data['show'][$fixApplyTo]['fix']['originalAmount']= $totalPaymentAmount;
    $data['show'][$fixApplyTo]['fix']['fixAmount']     = $vData['inAmount'];
    $data['show'][$fixApplyTo]['fix']['balance']       = $endingBalance;
    
    ##### STORE SECTION #####
    if($route == 'store'){
      $fixInvoiceTrans = $rFixTrans['fixInvoiceData'];
      ##### GET ALL OLD cnlt_no SO THAT WE CAN DISABLE THE FIX
      ##### THIS HAS TO BE SEPERATE LOOP BECAUSE THE TOP ONE WILL STOP #####
      $data['store'][T::$trackLedgerCardFix][] = ['cntl_no'=>$fixInvoiceTrans['cntl_no']];
      
      $fixInvoiceTrans['amount']  = $fixAmount;
      $fixInvoiceTrans['remark']  = $fixInvoiceTrans['remarks'] = $fixInvoiceTrans['inv_remark'] = $vData['remark'];
      $fixInvoiceTrans['date1']   = $this->_today;
      $data['store'][T::$tntTrans][] = $fixInvoiceTrans;
    }
    return $data;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------  
  private function _getTable($fn){
    return [T::$tntTrans, T::$vendor];
  }
//------------------------------------------------------------------------------
  private function _getSetting($name, $req){
    $data = [
      'show'=>[
        'field'=>[],
        'rule'=>[
          'inAmount'=>'nullable|numeric|between:0.00,10000000.00',
          'pAmount' =>'nullable|numeric|between:-10000000.00,0.00',
          'oldAmount' =>'nullable|numeric',
          'cntl_no'   =>'required|integer',
        ]
      ],
      'index'=>[
        'rule'=>[
          'prop'    =>'required|string|between:4,9',
          'oldProp' =>'required|string|between:4,9',
        ]
      ]
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' =>Html::sucMsg('Successfully Fixed.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'store' =>Html::errMsg('You cannot transfer payment the same Property, Unit and Tenant.'),
      'storeNotSelectAutocomplete' =>Html::errMsg('Please make sure you select from autocomplete.'),
      'fixPaymentNoInvoice' =>Html::errMsg('There is not no invoice to apply this payment to. Please check your transaction again.'),
      'showGreaterZero' =>Html::errMsg('The charge amount must be greater than 0 (Zero)'),
      'showLessZero'    =>Html::errMsg('The payment amount must be less than 0 (Zero)'),
      'showCntlNo'    =>Html::errMsg('There is an issue with data. Please Contact sean.hayes@dataworkers.com for assistant.'),
    ];
    if(isset($vData['fixAmount'])){
      $data['showInvoice'] = Html::errMsg('The fixed amount "'.Format::usMoneyMinus($vData['fixAmount']).'" cannot be greater than or equal original amount "' . Format::usMoneyMinus($vData['originalAmount']) . '"');
      $data['showPayment'] = Html::errMsg('The fixed amount "'.Format::usMoneyMinus($vData['fixAmount']).'" cannot be less than or equal original amount "' . Format::usMoneyMinus($vData['originalAmount']) . '"');
    }
    return $data[$name];
  }  
//------------------------------------------------------------------------------
  private function _isHaveInvoicePayment($r, $fixInfo){
    $check = $this->_getLeftoverPayment($r, $fixInfo);
    return isset($check['P']) && isset($check['IN']) ? 1 : 0;
  }
//------------------------------------------------------------------------------
  private function _groupTransAmount($trans, $oldCntlNo){
    $data = ['IN'=>[], 'P'=>[], 'sum'=>['IN'=>[], 'P'=>[]], 'sltBatch'=>''];
    $check = [];
    foreach($trans as $txCode=>$val){
      $amount = 0;
      $tran   = [];
      foreach($val as $v){
        // CONTROL NUMBER
//        $data['track_ledgercard_fix'][] = $v['cntl_no'];
        
        // SUM DATA
        $tran = $v;
        $amount += $v['amount'];
        
        $id = $txCode . $v['batch'];
        $check[$id] = isset($check[$id]) ? $check[$id] + $v['amount'] : $v['amount'];
        $v['amount'] = $check[$id];
        $data[$txCode][$v['batch']] = $v;
        
        // GET THE BATCH BELONG TO select cntl_no
        if($oldCntlNo == $v['cntl_no']){
          $data['sltBatch'] = $v['batch'];
        }
      }
      //SUM DATA
      $tran['amount'] = $amount;
      $data['sum'][$txCode] = $tran;
    }
    return $data;
  } 
//------------------------------------------------------------------------------
  private function _getVendorPayment($vData, $amount){
    $rVendor = HelperMysql::getVendor(['vendid.keyword'=>$this->_transferData['vendid']]);
    if(empty($rVendor)){
      Helper::echoJsonError(Html::errMsg($this->_transferData['vendid'] . ' does not exist.'), 'popupMsg');
    }
    $id = implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $this->_transferData));
    return [
      'prop'         =>$vData['prop'], // Check is from those old prop, not the transfer prop
      'unit'         =>$vData['unit'], 
      'tenant'       =>$vData['tenant'],
      'vendid'       =>$this->_transferData['vendid'],
      'gl_acct'      =>$this->_transferData['gl_acct'],
      'amount'       =>abs($amount),
      'vendor_id'    =>$rVendor['vendor_id'],
      'type'         =>'issue_check',// Will change this to pending_check after release Account Payable
      'invoice_date' =>$this->_today,
      'remark'       =>'Payment Belong To '.$id,
      'foreign_id'   =>0,
      'bank'         =>HelperMysql::getDefaultBank($vData['prop'], 'ap_bank'), 
      'invoice'      =>$id, 
    ];
  }
//------------------------------------------------------------------------------
  private function _getPostPayment($amount, $trans = [], $param = []){
    $trans = !empty($trans) ? $trans : $this->_transferData;
    $isRunCleanup = Helper::getValue('isRunCleanup', $param, 0);
    $postPaymentParam = isset($param['glNeedDelete']) ? ['glNeedDelete'=>$param['glNeedDelete']] : [];
    $fixInvoiceData = [];
    if(isset($param['totalLeftOverPayment'])){
      $postPaymentParam['totalLeftOverPayment'] = $param['totalLeftOverPayment'];
      $fixInvoiceData['appyto']  = Helper::getValue('appyto', $param);
      $fixInvoiceData['oldAmount'] = Helper::getValue('oldAmount', $param);
      $fixInvoiceData['inAmount']  = $param['inAmount'];
      $fixInvoiceData['openItemAmount'] = $param['oldAmount'] - $param['inAmount'];
    }
    
    if($isRunCleanup){
      TenantTrans::cleanTransaction($this->_transferData);
    }
    $payOrder = !empty($param['payOrder']) ? $param['payOrder'] : ['602'=>0, 'other'=>1];
    $data = TenantTrans::getPostPayment($fixInvoiceData + [
      'date1'   => $this->_today,
      'amount'  => abs($amount),
      'prop'    => $trans['prop'],
      'unit'    => $trans['unit'],
      'tenant'  => $trans['tenant'],
      'ar_bank' => $trans['bank'],
      'batch'   => '',
      'payOrder'=> $payOrder,
    ], $postPaymentParam);
    return $data['store'];
  }
//------------------------------------------------------------------------------
  private function _getAdjustGlTrans($trans){
    $trans['remark']  = 'Payment Belong To '.implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $this->_transferData));
    $trans['gl_acct'] = $this->_adjustGl;
    $trans['date1']   = $trans['date2'] = $trans['inv_date'] = $this->_today;
    return $trans;
  }
//------------------------------------------------------------------------------
  private function _setPropertyValue($vData){
    $prop = !empty($vData['prop']) ? $vData['prop'] : 'Z64';
    $this->_glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$prop])), 'gl_acct');
    $this->_service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$prop])), 'service');
  }
//------------------------------------------------------------------------------
  private function _getFixTransData($vData){
//    dd($vData);
    $applyTo = $openItemTrans = [];
    $balance = $vData['oldAmount']; 
    $singleTrans = Helper::getElasticResult(Elastic::searchQuery([
      'index'   =>T::$tntTransView,
      'query'   =>['must'=>['cntl_no'=>$vData['cntl_no']]]
    ]), 1)['_source'];
    ##### GET ALL THE OPEN ITEM AND BALANCE #####
    $r = TenantTrans::searchTntTrans([
      'query'=>['must' =>['prop.keyword'=>$singleTrans['prop'], 'unit.keyword'=>$singleTrans['unit'], 'tenant'=>$singleTrans['tenant']]]
    ]);
    
    $openItem = TenantTrans::getOpenItem($r);
    if($singleTrans['tx_code'] == 'P'){
      $data = $orderData = $tmpPayment = [];
      ##### BY DEFAULT, WE TAKE OUT THE MONEY FROM 375 FIRST AND THEN 602 AND THEN THE REST #####
      $order = ['375'=>0, '602'=>1];
      
      ##########################################################################
      # MAIN TRANSACTION SECTION WHICH THE PRIMARY TRANSACITON TAHT WANT TO FIX#
      ##########################################################################
      // GET ALL THE APPYTO THAT INVOLVES WITH THIS TRANSACTION
      $rPaymentTrans = Helper::getElasticResult(Elastic::searchQuery([
        'index'   =>T::$tntTransView,
        'query'   =>['must'=>[
          'prop.keyword'=>$singleTrans['prop'],
          'unit.keyword'=>$singleTrans['unit'],
          'tenant'=>$singleTrans['tenant'],
          'date1'=>$singleTrans['date1'],
          'tx_code.keyword'=>$singleTrans['tx_code'],
          'batch'=>$singleTrans['batch'],
          'check_no.keyword'=>$singleTrans['check_no']
        ]]
      ]));
      $mainAppyto = array_column(array_column($rPaymentTrans, '_source'), 'appyto'); 
      $rMainPaymentAndSTrans = TenantTrans::getApplyToSumResult($singleTrans, ['must'=>['appyto'=>$mainAppyto, 'tx_code.keyword'=>['P', 'S']]]);
      $tmpFixAmount = $balance;
      if($rMainPaymentAndSTrans['balance'] <= 0){
        ##### USE THIS ONE AS THE MAIN DATA #####
        $tmpPayment = $this->_getAmountForEachGl($singleTrans, array_column(array_column($rPaymentTrans, '_source'), 'appyto'));
        $tmpFixAmount -= $rMainPaymentAndSTrans['balance'];
      }
//      dd($tmpFixAmount, $balance, $rMainPaymentAndSTrans['balance']);
      
      ##########################################################################
      ################ GET ALL THE PAYMENT AND S DATA FROM THIS ################
      ################   TENANT AND RESERVER TO BE USED LATER   ################
      ##########################################################################
      if(empty($tmpPayment)){
//      if($tmpFixAmount < 0){
        $rPaymentAndSTrans = TenantTrans::getApplyToSumResult($singleTrans, ['must'=>['tx_code.keyword'=>['P', 'S']]])['data'];
        krsort($rPaymentAndSTrans); // NEED TO REVERSE SO THAT WE CAN GET THE EARLIEST ONE
        ##### NEED TO DELETE THE 375 APPYTO TRANSACTION #####
        foreach($openItem['data']  as $v){
          if($v['gl_acct'] == '375' && isset($rPaymentAndSTrans[$v['appyto']])){
            unset($rPaymentAndSTrans[$v['appyto']]);
          }
        }
        
        ##### START TO RESERVE THE TRANSCATION TO BE USED IF THE AMOUNT IS NOT ENOUGH ######
//        $tmpFixAmount = $balance;
        foreach($rPaymentAndSTrans as $appyto=>$amount){
          if($tmpFixAmount < 0){
            $tmpPayment += $this->_getAmountForEachGl($singleTrans, $appyto);
          }
          $tmpFixAmount -= $amount;
        }
        
        ##### IN THIS CASE WE IGNORE THE 602, WE WANT TO TAKE CARE OF THE 375 FIRST AND THEN THE EARLIEST TRANSACTION #####
        $order = ['375'=>0];
      }
      ##### GET ALL THE OPEN ITEM OF 375 #####
      foreach($openItem['data'] as $v){
        if($v['gl_acct'] == '375'){
          $tmpPayment[$v['gl_acct']][] = $v;
        }
      }
      
      ##########################################################################
      ##################### REORDER TRANSACTION SECTION ########################
      ##########################################################################
      foreach($tmpPayment as $glAppyto=>$val){
        $p = explode('-', $glAppyto);
        $gl = $p[0];
        $index = isset($order[$gl]) ? $order[$gl] : 2;
        foreach($val as $v){
          $orderData[$index][] = $v;
        } 
      }
      ksort($orderData);
      // SIMPLIFY TRNSACTION TO 2 ARRAYS
      foreach($orderData as $v){
        $data = array_merge($data, $v);
      }
      return [
        'fixData'       =>$data, 
        'fixPaymentData'=>$data, 
        'fixAppyto'     =>$applyTo, 
        'fixInfo'       =>$singleTrans, 
        'fixTotalAmount'=>$balance, 
        'openItemData'  =>$openItem, 
//        'associateTrans'=>Helper::getElasticResult(TenantTrans::searchTntTrans(['query'=>['must'=>['appyto'=>array_keys($applyTo)]]]))
      ];
    }
    return [
      'fixData'       =>[$singleTrans], 
      'fixInvoiceData'=>$singleTrans, 
      'fixAppyto'     =>[$singleTrans['appyto']=>$singleTrans['amount']], 
      'fixInfo'       =>$singleTrans, 
      'fixTotalAmount'=>$singleTrans['amount'], 
      'openItemData'  =>$openItem, 
      'associateTrans'=>Helper::getElasticResult(TenantTrans::searchTntTrans(['query'=>['must'=>['appyto'=>$singleTrans['appyto']]]]))
    ];
  }
//------------------------------------------------------------------------------
  private function _getLeftoverPayment($r, $fixInfo){
    $check = [];
    foreach($r as $v){
      $v = $v['_source'];
      $txCode = $v['tx_code'] == 'S' ? 'P' : $v['tx_code'];
      $check[$v['gl_acct']][$txCode] = isset($check[$v['gl_acct']][$txCode]) ? $check[$v['gl_acct']][$txCode] + $v['amount'] : $v['amount'];
    }
    
    foreach($check as $gl=>$val){
      foreach($val as $txCode=>$amount){
        if($amount == 0){
          unset($check[$gl][$txCode]);
        }
      }
    }
    return $check[$fixInfo['gl_acct']];
  }
//------------------------------------------------------------------------------
  private function _getAmountForEachGl($singleTrans, $appyto){
    $sTransaction = [];
    // GET ALL THE TRANSACTION WITH GIVEN APPYTO
    $rAllTrans    = Helper::getElasticResult(Elastic::searchQuery([
      'index'   =>T::$tntTransView,
      'query'   =>['must'=>[
        'prop.keyword'=>$singleTrans['prop'],
        'unit.keyword'=>$singleTrans['unit'],
        'tenant'=>$singleTrans['tenant'],
        'appyto'=>$appyto
      ]]
    ]));
    
    foreach($rAllTrans as $v){
      $v = $v['_source'];
//          echo $v['tx_code'] . ',' . $v['gl_acct'] . ',' . $v['amount'] . "\n";
      $id = $v['gl_acct'] . '-' . $v['appyto'];
      if($v['tx_code'] == 'S' || $v['tx_code'] == 'P'){
        $v['cntl_no'] = $singleTrans['cntl_no'];
        $v['tx_code'] = $singleTrans['tx_code'];
        $v['journal'] = $singleTrans['journal'];
        if(isset($sTransaction[$id][0]['amount'])){
          $sTransaction[$id][0]['amount'] += $v['amount'];
        } else{
          $sTransaction[$id][0] = $v; 
        }
      }
    }
    foreach($sTransaction as $id=>$v){
      list($gl, $appyto) = explode('-', $id);
      if($v[0]['amount'] == 0 || $gl == '375'){
        unset($sTransaction[$id]);
      }
    }
    return $sTransaction;
  }
}