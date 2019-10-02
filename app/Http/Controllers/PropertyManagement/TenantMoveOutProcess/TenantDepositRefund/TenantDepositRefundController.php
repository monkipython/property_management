<?php
namespace App\Http\Controllers\PropertyManagement\TenantMoveOutProcess\TenantDepositRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{Format, V, Form, Html, Helper, HelperMysql, Elastic, File, TenantTrans, TableName AS T, GlName AS G};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class
use PDF;
use Storage;
use Illuminate\Support\Str;
use \App\Http\Controllers\AccountReceivable\CashRec\LedgerCard\LedgerCardController;
use \App\Http\Controllers\AccountReceivable\CashRec\PostInvoice\PostInvoiceController;

class TenantDepositRefundController extends Controller{
  private $_viewPath = 'app/PropertyManagement/TenantMoveOutProcess/tenantDepositRefund/';
  
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $valid = V::startValidate([
      'rawReq'          => ['tnt_move_out_process_id'=>$id],
      'tablez'          => [T::$tntMoveOutProcess],
      'orderField'      => ['tnt_move_out_process_id'],
      'validateDatabase'=> [
        'mustExist'=>[
          T::$tntMoveOutProcess . '|tnt_move_out_process_id' 
        ]
      ]
    ]);
    $r = Helper::getElasticResult(Elastic::searchQuery([
      'index'   =>T::$tntMoveOutProcessView,
      '_source' =>['prop', 'unit', 'tenant', 'status'],
      'query'   =>['must'=>['tnt_move_out_process_id'=>$id]]
    ]), 1);
    $rTntMoveOutProcess = !empty($r) ? $r['_source'] : [];
    $propUnitTenantMustQuery = Helper::getPropUnitTenantMustQuery($rTntMoveOutProcess, [], 0);
    $rTenant = HelperMysql::getTenant($propUnitTenantMustQuery);
    TenantTrans::cleanTransaction($rTenant, '', 1);
    $rTenant['remaining_balance'] = TenantTrans::getApplyToSumResult(['prop'=>$rTenant['prop'], 'unit'=>$rTenant['unit'], 'tenant'=>$rTenant['tenant']])['balance'];
    $rTenantDeposit = M::getTenantDeposit(Model::buildWhere(['prop'=>$rTenant['prop'], 'unit'=>$rTenant['unit'], 'tenant'=>$rTenant['tenant']]))['deposit_credit'];
    $rTenant['deposit_credit'] = $rTenantDeposit ? Format::floatNumber($rTenantDeposit) : 0;
    $rTenant['tnt_name'] = title_case($rTenant['tnt_name']);
    $includeField = ['tnt_name', 'prop', 'unit', 'tenant', 'move_in_date', 'move_out_date', 'base_rent', 'remaining_balance', 'deposit_credit'];
    $tableData = [];
    ## Create Tenant Information table
    foreach($rTenant as $fl=>$val){
      if(in_array($fl, $includeField)){
        if($fl == 'tnt_name') {
          $fl = 'Tenant Name';
        }
        $label = title_case(str_replace('_', ' ', $fl));
        if($fl == 'base_rent' || $fl == 'remaining_balance' || $fl == 'deposit_credit'){
          $val = Format::usMoneyMinus($val); 
        }
        if($fl == 'prop') {
          $val = Html::span($val, ['id'=>'prop']);
        }
        $tableData[] = ['desc'=>['val'=>$label],$fl=>['val'=>$val]];
      }
    }
    $tntIdInput = Html::input($id, ['type'=>'hidden', 'name'=>'tnt_move_out_process_id']);

    $rTntTrans = HelperMysql::getTntTrans($propUnitTenantMustQuery, [], ['sort'=>['date1'=>'asc', 'tx_code.keyword'=>'asc','cntl_no'=>'asc']], 0, 0);
    $openItem  = LedgerCardController::getInstance()->_getOpenItem($rTntTrans);
    array_pop($openItem);
    $openItem  = TenantTrans::reorderPayment($openItem);
    if($rTenant['deposit_credit'] > 0) {
      $depositAmount = [
        'service_code' => '607',
        'remark'       => 'Security Deposit',
        'amount'       => $rTenant['deposit_credit'] * -1
      ];
      array_unshift($openItem, $depositAmount);
    }
    $formSetting = [
      'phone'  => $rTenant['phone1'],
      'e_mail' => $rTenant['e_mail'],
      'line2'  => $rTenant['line2'],
      'street' => $rTenant['street'],
      'city'   => $rTenant['city'],
      'state'  => $rTenant['state'],
      'zip'    => $rTenant['zip'],
    ];
    $tenantForm = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__),
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $formSetting)
    ]);
    //  $billingCount = count($openItem) + 2;
    return [
      'html'=>view($page, [ 'data'=>[
        'tenantInfo'      => Html::createTable($tableData, ['class'=>'table table-bordered'], 0, 0),
        'tenantForm'      => $tenantForm,
        'fullBillingForm' => $this->_getFullBillingField(1, $openItem) . $this->_getTotalAmountRow(),
        'tenantName'      => $rTenant['tnt_name'],
        'submitBtn'       => $this->_getButton(__FUNCTION__, $req),
        'tntIdInput'      => $tntIdInput,
      ]])->render(), 
    //  'fullBillingEmpty' => $this->_getFullBillingField($billingCount),
    //  'billingCount'     => $billingCount
    ];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $validation[] = T::$tntMoveOutProcess . '|tnt_move_out_process_id';
    if(isset($req['service_code'])) {
      $req['service'] = $req['service_code'];
      $validation[] = T::$service.'|service,prop:Z64';
    }
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => $this->_getOrderField(__FUNCTION__, $req),
      'setting'         => $this->_getSetting(__FUNCTION__),
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'   => $validation
      ]
    ]);
    $today   = Helper::date();
    $vData   = $valid['dataNonArr'];
    $vData['usid'] = $valid['data']['usid'];
    $vDataArr= $valid['dataArr'];
    $dataset = $response = $insertData = $openItem = [];
    $result = $openItemData = $advancedRentData = $balanceDiffData = [T::$glTrans=>[], T::$tntTrans=>[]];
    $openItemSum = 0;
    $batch   = HelperMysql::getBatchNumber();
    $rTntMoveOutProcess = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'   =>T::$tntMoveOutProcessView,
      '_source' =>['prop', 'unit', 'tenant', 'status'],
      'query'   =>['must'=>['tnt_move_out_process_id'=>$vData['tnt_move_out_process_id']]]
    ]), 1);
    $vData['vendid'] = $rTntMoveOutProcess['prop'] . '-' . $rTntMoveOutProcess['unit'] . '-' . $rTntMoveOutProcess['tenant'];
    $rVendor = HelperMysql::getVendor(['must'=> ['vendid.keyword'=>$vData['vendid']]], ['vendor_id']);
    $rTenant = HelperMysql::getTenant(Helper::getPropUnitTenantMustQuery($rTntMoveOutProcess, [], 0));
    $rUnit   = HelperMysql::getUnit(['prop.prop.keyword'=>$rTenant['prop'], 'unit.keyword'=>$rTenant['unit']], ['unit_id', 'street', 'prop']);
    $rProp   = HelperMysql::getProp([$rTenant['prop']],[],1,1);
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(['g.prop'=>$rTenant['prop']]), 'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(['prop'=>$rTenant['prop']]), 'service');
    $rCompany= M::getTableData(T::$company, Model::buildWhere(['company_code'=>$rProp['mangtgroup']]), '*', 1);
    $rTenant['diffAmount'] = M::getTenantDepositBalance(Model::buildWhere(['prop'=>$rTenant['prop'], 'unit'=>$rTenant['unit'], 'tenant'=>$rTenant['tenant']]))['deposit_balance'];
    $rTenantDeposit = M::getTenantDeposit(Model::buildWhere(['prop'=>$rTenant['prop'], 'unit'=>$rTenant['unit'], 'tenant'=>$rTenant['tenant']]))['deposit_credit'];

    ## Insert tnt_security_deposit 
    if(count($vDataArr) > 0) {
      # Remove 607 from the array
      if($vDataArr[0]['service_code'] == '607') {
        unset($vDataArr[0]);
        $vDataArr = array_values($vDataArr);
      }
      foreach($vDataArr as $i=>$val){
        if($val['service_code'] == '755') {
          Helper::echoJsonError($this->_getErrorMsg('refundDeposit'));
        }
        if(isset($val['sign'])) {
          $val['amount'] = $val['sign'] == '-' ? $val['sign'] . $val['amount'] : $val['amount'];
          ## Sum up open item amount except for 375
          if($val['service_code'] != '375') {
            $openItemSum += $val['amount'];
            $openItem[] = $val;
          }
        }
        ## If tenant has advance rent
        if($val['service_code'] == '375') {
          $sData = [];
          $sData['prop']     = $rTenant['prop'];
          $sData['unit']     = $rTenant['unit'];
          $sData['tenant']   = $rTenant['tenant'];
          $sData['service']  = '605';
          $sData['amount']   = $val['amount'];
          $sData['usid']     = $vData['usid'];
          $sData['remark']   = $service['605']['remark'];  
          $sData['appyto']   = 0;
          $sData['batch']    = $batch;
          $sData['_glChart'] = $glChart;
          $sData['_service'] = $service;
          $sData['_rProp']   = Helper::selectData(['ar_bank','group1'], $rProp);
          $sData['_rBank']   = Helper::keyFieldName($rProp['bank'], 'bank');
          $sData['tnt_name'] = $rTenant['tnt_name'];
          $advancedRentData  = PostInvoiceController::getInstance()->getStoreData($sData);
        }
        $amount = Format::floatNumber($val['amount']) * -1;
        ## sum up the amounts to get the balance difference
        $rTenant['diffAmount'] += $amount;
        $dataset[T::$tntSecurityDeposit][$i]['prop']         = $rTenant['prop'];
        $dataset[T::$tntSecurityDeposit][$i]['unit']         = $rTenant['unit'];
        $dataset[T::$tntSecurityDeposit][$i]['tenant']       = $rTenant['tenant'];
        $dataset[T::$tntSecurityDeposit][$i]['service_code'] = $val['service_code'];
        $dataset[T::$tntSecurityDeposit][$i]['remark']       = $val['remark'];
        $dataset[T::$tntSecurityDeposit][$i]['amount']       = $amount;
        $dataset[T::$tntSecurityDeposit][$i]['tx_code']      = 'P';
        $dataset[T::$tntSecurityDeposit][$i]['batch']        = $batch;
        $dataset[T::$tntSecurityDeposit][$i]['date1']        = isset($val['date1']) ? $val['date1'] : $today;
        $dataset[T::$tntSecurityDeposit][$i]['gl_acct']      = !empty($service[$val['service_code']]) ? $service[$val['service_code']]['gl_acct'] : ''; 
        $dataset[T::$tntSecurityDeposit][$i]['is_move_out_process_trans'] = 1;
      }
      ## Insert open items to offset the tnt_trans and gl_trans
      if($rTenantDeposit > 0 && $openItemSum > 0) {
        $outstandingInvoice = abs($openItemSum);
        $paymentAmount = $rTenantDeposit > $outstandingInvoice ? $outstandingInvoice * -1 : $rTenantDeposit * -1;
        foreach($openItem as $i => $val) {
          $itemAmount    = Format::floatNumber($val['amount']);
          $paymentAmount = $paymentAmount + $itemAmount;
          $sData = [];         
          $sData['prop']     = $rTenant['prop'];
          $sData['unit']     = $rTenant['unit'];
          $sData['tenant']   = $rTenant['tenant'];
//          $sData['service']  = '640';
          $sData['service']  = $val['service'];
          
          $sData['amount']   = $paymentAmount <= 0 ? $itemAmount : ($paymentAmount - $itemAmount);
          $sData['usid']     = $vData['usid'];
//          $sData['remark']   = $service['640']['remark'];  
          $sData['remark']   = 'Payment From Deposit';  
          $sData['appyto']   = $val['appyto'];
          $sData['batch']    = $batch;
          $sData['_glChart'] = $glChart;
          $sData['_service'] = $service;
          $sData['_rProp']   = Helper::selectData(['ar_bank','group1'], $rProp);
          $sData['_rBank']   = Helper::keyFieldName($rProp['bank'], 'bank');
          $sData['tnt_name'] = $rTenant['tnt_name'];
          $balanceDiffData   = PostInvoiceController::getInstance()->getStoreData($sData);
          $balanceDiffData[T::$tntTrans][0]['amount'] = $balanceDiffData[T::$tntTrans][0]['amount'] * -1;
          $balanceDiffData[T::$tntTrans][0]['appyto'] = $val['appyto'];
          $balanceDiffData[T::$tntTrans][0]['invoice'] = $val['appyto'];
          $balanceDiffData[T::$tntTrans][0]['tx_code'] = 'P';
          
          $secondTntTrans = $thirdTntTrans = $balanceDiffData[T::$tntTrans][0];
          $secondTntTrans['tx_code'] = $thirdTntTrans['tx_code'] = 'S';
          $secondTntTrans['journal'] = $thirdTntTrans['journal'] = 'JE';
//          $thirdTntTrans['gl_acct']  = $thirdTntTrans['service_code'] = G::$rentCollection;
          $secondTntTrans['gl_acct'] = $secondTntTrans['service_code'] = G::$rentCollection;
//          $secondTntTrans['remark']  = $secondTntTrans['inv_remark'] = $service[G::$securityDeposit]['remark'];
          $secondTntTrans['amount']  = $secondTntTrans['amount'] * -1;
//          $balanceDiffData[T::$tntTrans][] = $secondTntTrans;
//          $balanceDiffData[T::$tntTrans][] = $thirdTntTrans;
          $balanceDiffData[T::$glTrans] = [$secondTntTrans, $thirdTntTrans];
    
          $openItemData[T::$tntTrans] = array_merge($openItemData[T::$tntTrans], $balanceDiffData[T::$tntTrans]);
          $openItemData[T::$glTrans]  = array_merge($openItemData[T::$glTrans], $balanceDiffData[T::$glTrans]);
          
          // Stop, when the remaining payment is Zero 
          if($paymentAmount >= 0){  break; }
        }
      }
      /*
      ### If difference amount + sum of open item minus 375 is less than 0, call getStoreData 
      ## Calculate the total balance of tenenant security deposit + open item sum to get the total amount of added charges
      $openBalanceDiff = $rTenant['diffAmount'] + $openItemSum;
      dd($rTenant['diffAmount'], $openItemSum);
      if($openBalanceDiff < 0) {
        $sData = [];
        $sData['prop']     = $rTenant['prop'];
        $sData['unit']     = $rTenant['unit'];
        $sData['tenant']   = $rTenant['tenant'];
        $sData['service']  = '631';
        $sData['amount']   = abs($openBalanceDiff);
        $sData['usid']     = $vData['usid'];
        $sData['remark']   = $service['631']['remark'];  
        $sData['appyto']   = 0;
        $sData['batch']    = $batch;
        $sData['_glChart'] = $glChart;
        $sData['_service'] = $service;
        $sData['_rProp']   = Helper::selectData(['ar_bank','group1'], $rProp);
        $sData['_rBank']   = Helper::keyFieldName($rProp['bank'], 'bank');
        $sData['tnt_name'] = $rTenant['tnt_name'];
        $tmp               = PostInvoiceController::getInstance()->getStoreData($sData);
        $tmp               = Helper::keyFieldName($tmp[T::$tntTrans], 'tx_code');
        $differenceData[T::$tntTrans] = [$tmp['IN']];
      }
       * 
       */
      $result[T::$tntTrans] = array_merge($result[T::$tntTrans], $advancedRentData[T::$tntTrans], $openItemData[T::$tntTrans]);
      $result[T::$glTrans]  = array_merge($result[T::$glTrans], $advancedRentData[T::$glTrans], $openItemData[T::$glTrans]);
      $dataset    = array_merge($dataset, $result);
      if(empty($dataset[T::$glTrans])) {
        unset($dataset[T::$glTrans]);
      }
      if(empty($dataset[T::$tntTrans])) {
        unset($dataset[T::$tntTrans]);
      }
      $insertData = HelperMysql::getDataSet($dataset, $vData['usid'], $glChart, $service);
    }
    $updateData[T::$tntMoveOutProcess] = ['whereData'=>['tnt_move_out_process_id'=>$vData['tnt_move_out_process_id']],'updateData'=>['status'=>1]];
    if(empty($rVendor)) {
      $vendorOp = 'insert:';
      $insertData[T::$vendor] = $this->_getVendor($rTenant, $vData);
    }else {
      $vendorOp = 'update:';
      $updateData[T::$vendor] = ['whereData'=>['vendor_id'=>$rVendor['vendor_id']], 'updateData'=>$this->_getForwardData($vData)]; 
    }
    ## Save move out report in the DB
    $ext      = 'pdf';
    $uuid     = Str::uuid()->toString();
    $location = File::getLocation('TenantMoveOut');
    $location = !empty($location['tenantMoveOutReport']) ? $location['tenantMoveOutReport'] : '';
    $fileInfo = self::_getFileAndHref($ext, 'move_out_' . $vData['vendid'], $uuid, 'tenantMoveOutReport');
    if(!empty($location) && !file_exists($location . '/' . $uuid)){
      mkdir($location . $uuid, '0755', true);
    }
    $insertData[T::$fileUpload][] = [
      'name'       => $fileInfo['file'], 
      'file'       => $fileInfo['file'], 
      'uuid'       => $uuid, 
      'ext'        => $ext, 
      'path'       => str_replace($uuid . '/' . $fileInfo['file'], '', $fileInfo['filePath']),
      'type'       => 'tenantMoveOutReport', 
      'foreign_id' => $vData['tnt_move_out_process_id'], 
      'cdate'      => Helper::mysqlDate(),
      'usid'       => $vData['usid'],  
    ];
      
    ## If the balance is negative, then tenant owes us money so print out the acounting statement
    if($rTenant['diffAmount'] < 0) {
      $moveOutContent = $this->_getAccountingStatement($rTenant, $rProp, $rUnit, $rCompany, $vData);
      ## Save accounting statement in the DB
      $accUuid        = Str::uuid()->toString();
      $accFileInfo    = self::_getFileAndHref($ext, 'accounting_statement_' . $vData['vendid'], $accUuid, 'tenantMoveOutReport');
      if(!empty($location) && !file_exists($location . '/' . $accUuid)){
        mkdir($location . $accUuid, '0755', true);
      }
      $insertData[T::$fileUpload][] = [
        'name'       => $accFileInfo['file'], 
        'file'       => $accFileInfo['file'], 
        'uuid'       => $accUuid, 
        'ext'        => $ext, 
        'path'       => str_replace($accUuid . '/' . $accFileInfo['file'], '', $accFileInfo['filePath']),
        'type'       => 'tenantMoveOutReport', 
        'foreign_id' => $vData['tnt_move_out_process_id'], 
        'cdate'      => Helper::mysqlDate(),
        'usid'       => $vData['usid'],  
      ];
      $params         = ['title'=>'Accounting Statement', 'popupMsg' => 'Accounting Statement is ready. Please click the link here to download it.'];
      $moveOutPdf     = self::_getPdf([$moveOutContent], $params, $accFileInfo);
      $thirdMsg       = $moveOutPdf['popupMsg'];
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $insertSecond = $insertThird = $datasetSecond = [];
    try{
      $success += Model::insert($insertData);
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[
          T::$tntMoveOutProcessView => ['tnt_move_out_process_id' => $success['update:'.T::$tntMoveOutProcess]],
          T::$vendorView            => ['vendor_id' => $success[$vendorOp . T::$vendor]]
        ]
      ];
      
      if($rTenant['diffAmount'] > 0) {
        $rTenant['vendor_id'] = empty($rVendor) ? $success['insert:'.T::$vendor][0] : $rVendor['vendor_id'];
        $datasetSecond[T::$vendorPayment] = $this->_getVendorPayment($rTenant, $vData, $glChart);
        ## Insert security deposit refunds
        $datasetSecond[T::$tntSecurityDeposit][] = [
          'prop'                      => $rTenant['prop'],
          'unit'                      => $rTenant['unit'],
          'tenant'                    => $rTenant['tenant'],
          'service_code'              => '755',
          'remark'                    => $service['755']['remark'],
          'amount'                    => $rTenant['diffAmount'] * -1,
          'tx_code'                   => 'P',
          'batch'                     => $batch,
          'date1'                     => $today,
          'gl_acct'                   => !empty($service['755']) ? $service['755']['gl_acct'] : '', 
          'is_move_out_process_trans' => 1
        ];
        $insertSecond = HelperMysql::getDataSet($datasetSecond, $vData['usid'], $glChart, $service);
        $success += Model::insert($insertSecond);
        $elastic['insert'][T::$vendorPaymentView] = ['vendor_payment_id' => $success['insert:'.T::$vendorPayment]];
        $thirdMsg = 'Check is ready in the Approval.';
        ## file for vendor payment approval
        $insertThird[T::$fileUpload][] = [
          'name'       => $fileInfo['file'], 
          'file'       => $fileInfo['file'], 
          'uuid'       => $uuid, 
          'ext'        => $ext, 
          'path'       => str_replace($uuid . '/' . $fileInfo['file'], '', $fileInfo['filePath']),
          'type'       => 'approval', 
          'foreign_id' => $success['insert:'.T::$vendorPayment][0], 
          'cdate'      => Helper::mysqlDate(),
          'usid'       => $vData['usid'],  
        ];
      }
      $success += Model::insert($insertThird);
      if(!empty($success['insert:' . T::$tntTrans])) {
        $elastic['insert'][T::$tntTransView] = ['tt.cntl_no'=>$success['insert:' . T::$tntTrans]];
      }
      if(!empty($success['insert:' . T::$glTrans])) {
        $elastic['insert'][T::$glTransView]  = ['gl.seq'=>$success['insert:' . T::$glTrans]];
      }

      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
      ## Create a Move Out report
      $tableTenantMoveOutInfo = $this->_tenantMoveOutInfo($rTenant, $rUnit, $rCompany, $vData);
      $tableTenantBalanceInfo = $this->_tenantBalanceInfo($rTenant);
      $tableContent           = $tableTenantMoveOutInfo . $tableTenantBalanceInfo;
      $pdfMoveOut      = self::_getPdf([$tableContent], ['title'=>'Move Out Itemize Detail Report'], $fileInfo);
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__) . Html::warnMsg($pdfMoveOut['popupMsg']);
      $response['msg'] .= isset($thirdMsg) ? Html::warnMsg($thirdMsg) : '';
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }  

################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'edit'  => [T::$vendor, T::$tenant],
      'store' => [T::$tenant, T::$tntSecurityDeposit, T::$tntMoveOutProcess, T::$vendor,T::$service]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $orderField = [
      'edit'  => [
        'phone', 'e_mail', 'line2', 'street', 'city', 'state', 'zip'
      ],
      'store' => [
        'tnt_move_out_process_id', 'phone', 'e_mail', 'line2', 'street', 'city', 'state', 'zip'
      ]
    ];
    if(isset($req['service_code']) || isset($req['remark']) || isset($req['amount']) || isset($req['date1'])) {
      $orderField['store'] = array_merge($orderField['store'], ['service_code', 'service', 'remark', 'amount', 'date1', 'appyto']);
    }
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $setting = [
      'edit'  => [
        'field' => [
          'e_mail'  => ['label'=>'Email', 'req'=>0],
          'line2'   => ['label'=>'Forward Line2', 'req'=>0],
          'street'  => ['label'=>'Forward Street'],
          'city'    => ['label'=>'Forward City'],
          'state'   => ['label'=>'Forward State'],
          'zip'     => ['label'=>'Forward Zip'],
        ]
      ],
      'store' => [
        'rule'=>[
          'amount' => 'required|numeric',
          'line2'  => 'nullable',
          'e_mail' => 'nullable',
          'sign'   => 'nullable|string|between:0,1'
         ]
      ]
    ];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
    }
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit'  =>  isset($perm['tenantDepositRefundstore']) ? Html::input('Issue Refund', ['class' => 'btn btn-info pull-right btn-sm col-sm-12','type'  => 'submit']) : []
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getErrorMsg($name){
    $data = [
      'service_code' => Html::errMsg('Please fill out the Service #'),
      'remark'       => Html::errMsg('Please fill out the Service Description.'),
      'refundDeposit'=> Html::errMsg('Please remove the 755 Security Deposit Refunds, system will automatically add it for you.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' => Html::sucMsg('Refund Deposit was Successful.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getFullBillingField($count, $tntTrans = []){
    $_getField  = function ($i, $source = []){
      $form = '';
      $default  = ['req'=>1, 'includeLabel'=>0, 'readonly'=>1];
     // $disabled = !empty($source) ? $source['service_code'] == '607' ? ['disabled'=>1] : ['readonly'=>1] : [];
     // $default += $disabled;
      $amount   = isset($source['amount']) ? Format::floatNumber($source['amount']) : 0;
      $classSign = $amount > 0 || empty($source) ? ' negative' : ' positive';

      $fields = [
        'service_code' => $default + [
          'id'    => 'service_code[' . $i . ']', 
          'type'  => 'text',
          'class' => 'autocomplete',
          'value' => Helper::getValue('service_code', $source)],
        'remark' => $default + [
          'id'    => 'remark[' . $i . ']', 
          'type'  => 'text',
          'value' => Helper::getValue('remark', $source)],
        'amount' => $default + [
          'id'    => 'amount[' . $i . ']', 
          'type'  => 'text',
          'class' => 'decimal',
          'value' => isset($source['amount']) ? abs($amount) : 0],
        'date1'  => $default + [
          'id'    => 'date1[' . $i . ']', 
          'type'  => 'hidden',
          'value' => isset($source['date1']) ? $source['date1'] : Helper::date()],
        'appyto'  => $default + [
          'id'    => 'appyto[' . $i . ']', 
          'type'  => 'hidden',
          'value' => Helper::getValue('appyto', $source, 0)]
      ];
      if(!empty($source)) {
        $fields['sign'] = $default + [
          'id'    => 'sign[' . $i . ']',
          'type'  => 'hidden',
          'value' => $amount > 0 ? '+' : '-'
        ];
      }
      foreach($fields as $k=>$v){
        $col = $k == 'service_code' || $k == 'amount' ? 3 : 6;
        $colClass = 'col-md-' . $col;
        $colClass .= $k == 'amount' ? $classSign : '';
   //     $removeMark = empty($source) ?  '<a class="billingRemove" data-key="'.$i.'" title="Full Billing Remove"><i class="fa fa-trash-o text-red pointer tip" title="Full Billing Remove"></i></a>' : '';

      /*  if($k == 'amount'){
          $removeDiv = Html::div(Form::getField($v) . $removeMark, ['class'=>'trashIcon']);
          $form .= Html::div($removeDiv ,['class'=>$colClass]);
        }else*/ 
        if($k == 'sign' || $k == 'date1' || $k == 'appyto') {
          $form .= Form::getField($v);
        }else{
          $form .= Html::div(Form::getField($v),['class'=>$colClass]);
        }
      }
      $containerAttr = empty($source) ? ['class'=>'row emptyField' , 'data-key'=>$i] : ['class'=>'row', 'data-key'=>$i];
      return Html::div($form, $containerAttr);
    };
    $data = '';
    if(!empty($tntTrans)) {
      foreach($tntTrans as $i => $value) {
        $data .= $_getField($i, $value);
      }
    }/*else {
      $data .= $_getField($count, $tntTrans);
    }*/
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getTotalAmountRow() {
    $_getField  = function (){
      $div = '';
      $fields = [
        'emptyDivFirst' => [
          'class' => 'emptyDiv'],
        'emptyDivSecond' => [
          'class' => 'emptyDiv'],
        'amount' => [
          'id'           => 'totalAmount', 
          'type'         => 'text',
          'class'        => 'decimal',
          'readonly'     => 1,
          'includeLabel' => 0,
          'value'        => 0]
      ];

      foreach($fields as $k=>$v){
        $col = $k == 'emptyDivFirst' || $k == 'amount' ? 3 : 6;
        $colClass = 'col-md-' . $col;

        if($k == 'amount'){
          $div .= Html::div(Form::getField($v),['class'=>$colClass]);
        }else{
          $text = $k == 'emptyDivSecond' ? 'Total Balance:' : '';
          $div .= Html::div($text,['class'=>$colClass]);
        }
      }
      return Html::div($div, ['class'=>'row totalAmount']);
    };
    return $_getField();
  }
//------------------------------------------------------------------------------
  private static function _getPdf($contentData, $param, $fileInfo){
    $file        = Helper::getValue('filePath', $fileInfo);
    $href        = Helper::getValue('href', $fileInfo);
    $title       = Helper::getValue('title', $param);
    $headerMargin= $title == 'Accounting Statement' ? 68 : 60;
    $orientation = 'P';

    $font = Helper::getValue('font', $param, 'times');
    $size = Helper::getValue('size', $param, '9');
    $msg  = Helper::getValue('popupMsg', $param, 'Your Move Out file is ready. Please click the link here to download it.');
    try{
      PDF::reset();
      PDF::SetTitle($title);
      PDF::setPageOrientation($orientation);

      # HEADER SETTING
      PDF::SetHeaderData('', '0', Html::repeatChar(' ', $headerMargin) . $title);
      PDF::setHeaderFont([$font, '', ($size + 3)]);
      PDF::SetHeaderMargin(3);

      # FOOTER SETTING
      PDF::SetFont($font, '', $size);
      PDF::setFooterFont([$font, '', $size]);
      PDF::SetFooterMargin(5);

      PDF::SetMargins(5, 13, 5);
      PDF::SetAutoPageBreak(TRUE, 10);

      $contentData = isset($contentData[0]) ? $contentData : [$contentData];
      
      foreach($contentData as $content){
        PDF::AddPage();
        PDF::writeHTML($content,true,false,true,false,$orientation);
      }
     
      PDF::Output($file, 'F');
      return [
        'file'=>(php_sapi_name() == "cli") ? $fileInfo['filePath'] : '',
        'popupMsg'=>Html::a($msg, [
          'href'=>$href, 
          'target'=>'_blank',
          'class'=>'downloadLink'
        ])
      ];
    } catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
//------------------------------------------------------------------------------
  private static function _getFileAndHref($extension, $fileName, $uuid, $folderName){
    $file     = $fileName . '.' . $extension;
    $folder   = $folderName .'/' . $uuid . '/';
    $filePath = storage_path('app/public/'. $folder . $file);
    $href     = Storage::disk('public')->url($folder . $file);
    return ['filePath'=>$filePath, 'href'=>$href, 'file'=>$file];
  }
//------------------------------------------------------------------------------
  private static function _tenantBalanceInfo($tenant){
    $rTntSecurityDeposit = M::getTntSecurityDeposits(Model::buildWhere(['prop'=>$tenant['prop'], 'unit'=>$tenant['unit'], 'tenant'=>$tenant['tenant']]));
    $balanceData = [ 
      [
        'row'=>['val'=>Html::br(1)]  
      ],
      [
        'row'=>['val'=>Html::b('Tenant Move Out Charges and Balances'), 'param'=>['align'=>'center', 'style'=>'font-size:12px;']]    
      ],
      [
        'row'=>['val'=>Html::br(1)]  
      ],
      [
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>Html::b('Date'), 'param'=>['width'=>'21%', 'style'=>'border-bottom: 1px solid black']],
        'desc3'=>['val'=>Html::b('Description'), 'param'=>['width'=>'46%', 'style'=>'border-bottom: 1px solid black']],
        'desc4'=>['val'=>Html::b('Amount'), 'param'=>['align'=>'right', 'width'=>'15%', 'style'=>'border-bottom: 1px solid black']],
        'desc5'=>['val'=>Html::b('Balance'), 'param'=>['align'=>'right', 'width'=>'10%', 'style'=>'border-bottom: 1px solid black']],
        'desc6'=>['val'=>'', 'param'=>['width'=>'4%']],
      ]
    ];
    $totalAmount = 0;
    foreach($rTntSecurityDeposit as $i => $v) {
      $totalAmount += Format::floatNumber($v['amount']);
      $amount = strripos($v['amount'], '$') !== 0 ? Format::usMoneyMinus($v['amount']) : $v['amount'];
      $balanceData[] = [
        'desc1'=>['val'=>''],
        'desc2'=>['val'=>Format::usDate($v['date1'])],
        'desc3'=>['val'=>$v['remark']],
        'desc4'=>['val'=>$amount, 'param'=>['align'=>'right']],
        'desc5'=>['val'=>Format::usMoneyMinus($totalAmount), 'param'=>['align'=>'right']],
        'desc6'=>['val'=>''],
      ];
      $totalAmount = Format::floatNumber($totalAmount);
    }
    $balanceData[] = [ 
      'desc1'=>['val'=>''],
      'desc4'=>['val'=>Html::b($totalAmount > 0 ? 'Security Deposit Held as of ' . date('m/d/Y')  : 'Amount Due By Tenant:'), 'param'=>['align'=>'right', 'colspan'=>3, 'style'=>'border-top: 1px solid black']],
      'desc5'=>['val'=>Html::b(Format::usMoneyMinus($totalAmount)), 'param'=>['align'=>'right', 'style'=>'border-top: 1px solid black']],
      'desc6'=>['val'=>''],
    ];

    return Html::buildTable([
      'data'        => $balanceData, 
      'isHeader'    => 0, 
      'isOrderList' => 0
    ]);
  }
//------------------------------------------------------------------------------
  private function _tenantMoveOutInfo($tenant, $unit, $company, $vData) {
    $tenantAcct  = Html::b($tenant['prop'].'-'.$tenant['unit'].'-'.$tenant['tenant']);
    $tntName     = Html::b(title_case($tenant['tnt_name']));
    $today       = Html::b(Format::usDate(date('m/d/Y')));
    $moveInDate  = Html::b(Format::usDate($tenant['move_in_date']));
    $moveOutDate = Html::b(Format::usDate($tenant['move_out_date']));
    $moveOutData = [
      [
        'row' => ['val'=>Html::br(2), 'param'=>['colspan'=>4]]  
      ],
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>Html::bu('Property Information'), 'param'=>['width'=>'37%']],
        'desc3'=>['val'=>Html::bu('Tenant Information'), 'param'=>['width'=>'33%']],
        'desc4'=>['val'=>Html::bu('Forward Information'), 'param'=>['width'=>'22%']],  
        'desc5'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>Html::b($unit['street']), 'param'=>['width'=>'37%']],
        'desc4'=>['val'=>$tntName, 'param'=>['width'=>'33%']],
        'desc5'=>['val'=>$tntName, 'param'=>['width'=>'26%']],
      ],
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>title_case($company['company_name'].' <br>'.$company['street'].' <br>'.$company['city']) . ', ' . $company['state'] . ' ' . $company['zip'].' <br>'.$company['phone'], 'param'=>['width'=>'37%']],
        'desc3'=>['val'=>'Vendor Code:<br>Today Date:<br>Move In Date:<br>Move Out Date:<br>', 'param'=>['width'=>'11%']],
        'desc4'=>['val'=>Html::b($vData['vendid']) .' <br>' . $today . ' <br>' . $moveInDate . ' <br>' . $moveOutDate, 'param'=>['width'=>'22%']],
        'desc5'=>['val'=>title_case($vData['street'] . '<br>' . $vData['city']) . ', ' . $vData['state'] . ' ' . $vData['zip'] . '<br>' . $vData['phone'] . '<br>' . $vData['e_mail'], 'param'=>['width'=>'26%']],
      ]
    ];

    return Html::buildTable([
      'data'        => $moveOutData, 
      'isHeader'    => 0, 
      'isOrderList' => 0
    ]);
  }
//------------------------------------------------------------------------------
  private function _getVendorPayment($data, $vData, $glChart){
    // 'type'   => 'pending_check' -  When Account Payable is finished
    return [
      'prop'   => $data['prop'],
      'unit'   => $data['unit'], 
      'tenant' => $data['tenant'],
      'vendor_id'=>$data['vendor_id'],
      'type'   => 'deposit_refund', 
      'vendid' => $vData['vendid'],
      'gl_acct'=> '755',
      'invoice_date'=> Helper::date(),
      'amount' => $data['diffAmount'],
      'remark' => $glChart['755']['serviceRemark'],
      'bank'   => '0', 
      'invoice'=> 'Deposit'
    ];
  }
//------------------------------------------------------------------------------
  private function _getVendor($rTenant, $vData) {
    return [
      'vendid'      => $vData['vendid'],
      'name'        => $rTenant['tnt_name'],
      'vendor_type' => 'T',
      'flg_1099'    => 'N',
      'line2'       => $vData['line2'],
      'street'      => $vData['street'],
      'city'        => $vData['city'],
      'state'       => $vData['state'],
      'zip'         => $vData['zip'],
      'name_key'    => $rTenant['tnt_name'],
      'phone'       => $vData['phone'],
      'fax'         => $rTenant['fax'],
      'e_mail'      => $vData['e_mail'],
      'web'         => $rTenant['web'],
      'usid'        => $vData['usid']
    ];
  }
//------------------------------------------------------------------------------
  private function _getForwardData($vData) {
    return [
      'line2'  => $vData['line2'],
      'street' => $vData['street'],
      'city'   => $vData['city'],
      'state'  => $vData['state'],
      'zip'    => $vData['zip'],
      'phone'  => $vData['phone'],
      'e_mail' => $vData['e_mail']
    ];
  }
//------------------------------------------------------------------------------
  private function _getAccountingStatement($tenant, $prop, $unit, $company, $vData){
    $companyAddress   = title_case($company['street'] . ', ' . $company['city']) . ', ' . $company['state'] . ' ' . $company['zip'];
    $companyName      = title_case($company['company_name']);
    $tntName          = Html::b(title_case($tenant['tnt_name']));
    $fullAddress      = Html::b(title_case($unit['street'].', '.$prop['city']).', '.$prop['state'].' '.$prop['zip']);
    $tenantAcct       = $tenant['prop'].'-'.$tenant['unit'].'-'.$tenant['tenant'];
    $remainingBalance = Format::usMoney($tenant['diffAmount'] * -1);
    $today            = Html::b(Format::usDate(date('m/d/Y')));
    $tableData = [
      [
        'row'=>['val'=>Html::br()]
      ], 
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc3'=>['val'=>Html::bu('Forward Information'), 'param'=>['width'=>'65%']],  
        'desc2'=>['val'=>Html::bu('Tenant Information'), 'param'=>['width'=>'37%']],
        'desc4'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc3'=>['val'=>$tntName, 'param'=>['width'=>'65%']],
        'desc2'=>['val'=>$tntName, 'param'=>['width'=>'37%']],
        'desc4'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>title_case($vData['street'] . '<br>' . $vData['city']) . ' ' . $vData['state'] . ' ' . $vData['zip'] . '<br>' . $vData['phone'] . '<br>' . $vData['e_mail'], 'param'=>['width'=>'65%']],
        'desc3'=>['val'=>'Vendor Code:<br>Today Date:<br>Move In Date:<br>Move Out Date:<br>', 'param'=>['width'=>'11%']],
        'desc4'=>['val'=>Html::b($vData['vendid']) .' <br>' . $today . ' <br>' . Html::b(Format::usDate($tenant['move_in_date'])) . ' <br>' . Html::b(Format::usDate($tenant['move_out_date'])), 'param'=>['width'=>'16%']],
        'desc5'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      ['row'=>['val'=>Html::br(2)]],     
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>'Premises: ', 'param'=>['width'=>'10%']],
        'desc3'=>['val'=>$fullAddress, 'param'=>['width'=>'86%']]
      ],
      ['row'=>['val'=>Html::br(2)]],     
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'14%']],
        'desc2'=>['val'=>'Per your rental agreement, you have agreed to reimburse us ' . $remainingBalance . ' for costs incured to make certain repairs to your unit.', 'param'=>['width'=>'72%']],
        'desc3'=>['val'=>'', 'param'=>['width'=>'14%']],
      ],
      ['row'=>['val'=>Html::br(2)]], 
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'14%']],
        'desc2'=>['val'=>'Please make your cashier\'s check or money order in the amount of ' . $remainingBalance . ' payable to '. $companyName .' Payable upon received.', 'param'=>['width'=>'72%']],
        'desc3'=>['val'=>'', 'param'=>['width'=>'14%']],
      ],
      ['row'=>['val'=>Html::br(2)]],   
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>'"AS REQUIRED BY LAW, YOU ARE HEREBY NOTIFIED THAT A NEGATIVE CREDIT', 'param'=>['width'=>'92%', 'align'=>'center']],
        'desc3'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>'REPORT REFLECTION ON YOUR CREDIT RECORD MAY BE SUBMITTED TO A CREDIT', 'param'=>['width'=>'92%', 'align'=>'center']],
        'desc3'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>'REPORTING AGENCY IF YOU FAIL TO FULFILL THE TERMS OF YOUR CREDIT OBLIGATIONS"', 'param'=>['width'=>'92%', 'align'=>'center']],
        'desc3'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      ['row'=>['val'=>Html::br(2)]],  
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>Html::u('Civil Code Section 1785.26 (c)(2)'), 'param'=>['width'=>'92%', 'align'=>'center']],
        'desc3'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      ['row'=>['val'=>Html::br(2)]],  
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>$companyName, 'param'=>['width'=>'92%', 'align'=>'center']],
        'desc3'=>['val'=>'', 'param'=>['width'=>'4%']],
      ],
      [ 
        'desc1'=>['val'=>'', 'param'=>['width'=>'4%']],
        'desc2'=>['val'=>$companyAddress, 'param'=>['width'=>'92%', 'align'=>'center']],
        'desc3'=>['val'=>'', 'param'=>['width'=>'4%']],
      ]
    ];
    return Html::buildTable([
      'data'       => $tableData,
      'isHeader'   => 0,
      'isOrderList'=> 0
    ]);
  }

}
