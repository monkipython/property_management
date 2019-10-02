<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\MassiveBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, Helper,HelperMysql, TableName AS T, Format, TenantTrans};
use \App\Http\Controllers\AccountReceivable\CashRec\PostInvoice\PostInvoiceController;
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class

class MassiveBillingController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Tenant/massivebilling/';
  private $_viewTable  = '';
  private $_batch = '';
  private static $_instance;
  public function __construct(){
    $this->_viewTable = T::$tntTransView;
  }
/**
 * @desc this getInstance is important because to activate __contract we need to call getInstance() first
 */
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
    $billForm = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__)
    ]);
    return view($page, [
      'data'=>[
        'massiveForm'=>$billForm
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    set_time_limit(600);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable('create'),
      'setting'     => $this->_getSetting('create'),
      'includeCdate'=> 0
    ]);
    $vData = $valid['dataNonArr'];
    return $this->storeData($vData, $req);
    
//    $postDate         = strtotime($vData['date1']);
//    $postDateFirstDay = strtotime(date('m/1/Y', $postDate));
//    $explodedProp     = Helper::explodeProp($vData['prop']);
//    $result           = M::getMassiveBilling($explodedProp['prop']);
//    $service          = Helper::keyFieldName(HelperMysql::getService(['prop'=>'Z64']), 'service');
//    $glChart          = Helper::keyFieldName(HelperMysql::getGlChat(['g.prop'=>'Z64']), 'gl_acct');
//    $rProp            = Helper::keyFieldName(M::getProp(), 'prop');
//    $usr              = 'SYS';
//    $this->_batch = HelperMysql::getBatchNumber();
//    $dataset = $updateBillingIds = $elasticInsertTenantIds = $billingInsertData = $billingPostDateIds = $billingChecker = [];
//    ##### IMPORTAIN NOTE #####
//    /**
//     * 1. Bill all the tenants and invoice tenant
//     * 2. if it missing the billing and not manager, it will add the new billing with base rent and invoice tenant
//     * 3. if it missing the billing and it is manager, don't do anything
//     * 4. if it is not missing the billing, but there is no current post_date or greater post date than the user input post date in billing, 
//     * it will add the new billing with base rent and invoice tenant
//     */
//    foreach($result as $val){
//      $tenant = $val['_source'];
//      $id = $tenant['prop'] . $tenant['unit'] . $tenant['tenant'];
//      if(!empty($tenant['billing'])) {
//        foreach($tenant['billing'] as $billingVal) {
//          $strStartDate = strtotime($billingVal['start_date']);
//          $strStopDate  = strtotime($billingVal['stop_date']);
//          $valPostDateFirstDay = strtotime(date("m/1/Y", strtotime($billingVal['post_date'])));
////          dd($billing['post_date'],$vData['date1'], $postDate, $postDateFirstDay, $valPostDateFirstDay);
//          if($strStartDate <= $postDate && $postDate < $strStopDate && $postDateFirstDay >= $valPostDateFirstDay && $billingVal['service_code'] != '607') {
//            $dataset[T::$tntTrans][] =  [
//              'prop'      => $tenant['prop'],
//              'unit'      => $tenant['unit'],
//              'tenant'    => $tenant['tenant'],
//              'bank'      => $rProp[$tenant['prop']]['ar_bank'],
//              'gl_contra' => '999',
//              'tx_code'   => 'IN',
//              'batch'     => $this->_batch,
//              'date1'     => $vData['date1'],
//              'tnt_name'  => $tenant['tnt_name'],
//            ] + $billingVal;
//            
//            $billingPostDateIds[]    = $billingVal['billing_id'];
//            if($billingVal['schedule'] == 'S') {
//              $updateBillingIds[] = $billingVal['billing_id'];
//            }
//            $elasticInsertTenantIds[$tenant['tenant_id']] = $tenant['tenant_id'];
//            $billingChecker[$id] = 0;
//          }
//        }
//      }else{ // DEAL WITH MISSING BILLING
//        if($tenant['manager'] == 'No'){
//          $missingBillingData = $this->_getMissingBillingData($tenant, $rProp, $vData, $postDate);
//          $dataset[T::$tntTrans][] = $missingBillingData[T::$tntTrans];
//          $dataset[T::$billing][] = $missingBillingData[T::$billing];
//          $elasticInsertTenantIds[$tenant['tenant_id']] = $tenant['tenant_id'];
//        }
//        $billingChecker[$id] = 0;
//      }
//    }
//    ##### CHECK THE BILLING THAT HAVEN'T BILL YET #####
//    foreach($result as $val) {
//      $tenant = $val['_source'];
//      $id = $tenant['prop'] . $tenant['unit'] . $tenant['tenant'];
//      if(!isset($billingChecker[$id])) { // We know that this has billing, but not sure if they are correct set the post date or not
//        $isInsertNewBilling = 1;
//        foreach($tenant['billing'] as $v){
//          // CHECK THE ONE THAT M ONLY
//          // Check is there any post_date greater than the input post_date
//          // If it does have it, don't do anything
//          if($v['schedule'] == 'M' && $v['service_code'] != '607' && $postDate < strtotime($v['stop_date']) && $postDate < strtotime($v['post_date'])){
//            $isInsertNewBilling = 0;
//            break;
//          }
//        }
//        
//        if($isInsertNewBilling){
//          $missingBillingData = $this->_getMissingBillingData($tenant, $rProp, $vData, $postDate);
//          $dataset[T::$tntTrans][] = $missingBillingData[T::$tntTrans];
//          $dataset[T::$billing][] = $missingBillingData[T::$billing];
//          $elasticInsertTenantIds[$tenant['tenant_id']] = $tenant['tenant_id'];
//        }
//      }
//    }
//    
//    $insertData = HelperMysql::getDataSet($dataset,$usr, $glChart, $service);
//    if(!empty($insertData[T::$billing])) {
//      $insertData[T::$billing] = array_values($insertData[T::$billing]);
//    }
//    if(!empty($insertData[T::$tntTrans])) {
//      $insertData[T::$tntTrans] = array_values($insertData[T::$tntTrans]);
//    }
//    $elasticInsertTenantIds = array_values($elasticInsertTenantIds);
////    dd($insertData,$billingPostDateIds,$updateBillingIds);
//    if(empty($insertData) && empty($billingPostDateIds) && empty($updateBillingIds)) {
//      Helper::echoJsonError($this->_getErrorMsg(__FUNCTION__));
//    }
//    
//    if(!empty($billingPostDateIds)){
//      $updateData = [
//        T::$billing => [[
//          'whereInData'=>['field'=>'billing_id', 'data'=>$billingPostDateIds],
//          'updateData' =>['post_date'=>date('Y-m-d', strtotime('first day of next month', $postDate))]
//      ]]];
//    }
//    if(!empty($updateBillingIds)){
//      $updateData[T::$billing][] = [
//        'whereInData'=>['field'=>'billing_id', 'data'=>$updateBillingIds],
//        'updateData' =>['stop_date'=>$vData['date1']]
//      ];
//    }
//    ############### DATABASE SECTION ######################
//    DB::beginTransaction();
//    $success = $response = $elastic = [];
//    try{
//      $success += Model::insert($insertData);
//      $insertIds = $success['insert:'.T::$tntTrans];
//      $updateData[T::$tntTrans] = [
//          'whereInData'=>['field'=>'cntl_no', 'data'=>$insertIds],
//          'updateData' =>['appyto'=>DB::raw('cntl_no'), 'invoice'=>DB::raw('cntl_no')]
//      ];
//      if(!empty($updateData)){
//        $success += Model::update($updateData);
//      }
//      ## Commented out until tnt_trans and ledger card is completed
//    /*$elastic = [
//        'insert'=>[
//          $this->_viewTable=>['tt.cntl_no'=>$insertIds]
//        ]
//      ];*/
//      $elastic['insert'][T::$tenantView] = [
//        't.tenant_id'=>$elasticInsertTenantIds
//      ];
//            
//      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__, count($elasticInsertTenantIds));
//      Model::commit([
//        'success' =>$success,
//        'elastic' =>$elastic,
//      ]);
//    } catch(Exception $e){
//      $response['error']['mainMsg'] = Model::rollback($e);
//    }
//    return $response;
  }  

################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create'  =>[T::$billing, T::$tntTrans]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'create'  =>['date1', 'prop']
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $setting = [
      'create'=>[
        'field'=>[
          'date1' => ['label'=>'Post Date', 'class' =>'date', 'value'=>date("m/d/Y", strtotime('first day of next month'))],
          'prop'  => ['label'=>'Property Numbers', 'type'=>'textarea', 'placeholder'=>'e.g: 0001-9999 for all properties', 'value'=>'0001-ZZZZ'],
        ],
        'rule'=>[
          'prop' =>'required|string|between:4,1000',
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
  private function _getButton($fn){
    $buttton = [
      'create'=>['submit'=>['id'=>'submit', 'value'=>'Generate Massive Billing', 'class'=>'col-sm-12']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name, $count){
    $data = [
      'store' =>Html::sucMsg($count . " Tenant(s) were Billed Successfully!"),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store' =>Html::errMsg('No Billable Tenants.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getMissingBillingData($tenant, $rProp, $vData, $postDate){
    $tenant['bank']      = $rProp[$tenant['prop']]['ar_bank'];
    $tenant['tx_code']   = 'IN';
    $tenant['batch']     = $this->_batch;
    $tenant['date1']     = $vData['date1'];
    $tenant['gl_contra'] = '999';
    $tenant['amount']    = $tenant['base_rent'];
    $tenant['gl_acct']   = $tenant['service_code'] = '602';
    $tenant['remark']    = 'Rent';
    $tenant['tnt_name']  = $tenant['tnt_name'];

    $billingData = [];
    $billingData['prop']         = $tenant['prop'];
    $billingData['unit']         = $tenant['unit'];
    $billingData['tenant']       = $tenant['tenant'];
    $billingData['amount']       = $tenant['base_rent'];
    $billingData['remark']       = $tenant['remark'];
    $billingData['schedule']     = 'M';
    $billingData['seq']          = 1;
    $billingData['post_date']    = date('Y-m-d', strtotime('first day of next month', $postDate));
    $billingData['start_date']   = $tenant['move_in_date'];
    $billingData['stop_date']    = '9999-12-31';
    $billingData['service_type'] = 'RNT';
    $billingData['remarks']      = 'Rent';
    $billingData['service_code'] = $billingData['gl_acct'] = $billingData['gl_acct_past'] = $billingData['gl_acct_next'] ='602';
    $billingData['bill_seq']     = $billingData['cam_exp_gl_acct'] = '';
    return [T::$billing=>$billingData, T::$tntTrans=>$tenant];
  }
//------------------------------------------------------------------------------
  public function storeData($vData, $req = []){
    $postDate         = strtotime($vData['date1']);
    $postDateFirstDay = strtotime(date('m/1/Y', $postDate));
    $explodedProp     = Helper::explodeProp($vData['prop']);
    $result           = M::getMassiveBilling($explodedProp['prop']);
    $service          = Helper::keyFieldName(HelperMysql::getService(['prop'=>'Z64']), 'service');
    $glChart          = Helper::keyFieldName(HelperMysql::getGlChat(['g.prop'=>'Z64']), 'gl_acct');
//    $rProp            = Helper::keyFieldName(M::getProp(), 'prop');
    $rProp            = Helper::keyFieldNameElastic(HelperMysql::getProp($explodedProp['prop']), 'prop');
    
    $usr              = Helper::getUsid($req) != '' ? Helper::getUsid($req) :  'SYS';
    $this->_batch     = HelperMysql::getBatchNumber();
    $dataset = $updateBillingIds = $elasticInsertTenantIds = $billingInsertData = $billingPostDateIds = $billingChecker = [];
    ##### IMPORTAIN NOTE #####
    /**
     * 1. Bill all the tenants and invoice tenant
     * 2. if it missing the billing and not manager, it will add the new billing with base rent and invoice tenant
     * 3. if it missing the billing and it is manager, don't do anything
     * 4. if it is not missing the billing, but there is no current post_date or greater post date than the user input post date in billing, 
     * it will add the new billing with base rent and invoice tenant
     */
    foreach($result as $val){
      $tenant = $val['_source'];
      $id = $tenant['tenant_id'];
      if(!empty($tenant['billing'])) {
        foreach($tenant['billing'] as $billingVal) {
          $strStartDate = strtotime($billingVal['start_date']);
          $strStopDate  = strtotime($billingVal['stop_date']);
          $valPostDateFirstDay = strtotime(date("m/1/Y", strtotime($billingVal['post_date'])));
//          dd($billing['post_date'],$vData['date1'], $postDate, $postDateFirstDay, $valPostDateFirstDay);
          if($strStartDate <= $postDate && $postDate < $strStopDate && $postDateFirstDay >= $valPostDateFirstDay && $billingVal['service_code'] != '607') {
            $dataset[T::$tntTrans][] =  [
              'prop'      => $tenant['prop'],
              'unit'      => $tenant['unit'],
              'tenant'    => $tenant['tenant'],
              'tenant_id' => $id,
              'bank'      => $rProp[$tenant['prop']]['ar_bank'],
              'gl_contra' => '999',
              'tx_code'   => 'IN',
              'batch'     => $this->_batch,
              'date1'     => $vData['date1'],
              'tnt_name'  => $tenant['tnt_name'],
            ] + $billingVal;
            
            $billingPostDateIds[]    = $billingVal['billing_id'];
            if($billingVal['schedule'] == 'S') {
              $updateBillingIds[] = $billingVal['billing_id'];
            }
            $elasticInsertTenantIds[$tenant['tenant_id']] = $tenant['tenant_id'];
            
            ##### IGNORE THE SECOND CHECK ONLY ONLY IF IT IS 602######
            // It is possible the monthly has different gl_acct than 602
            if($billingVal['gl_acct'] == 602){
              $billingChecker[$id] = 0;
            }
          }
        }
      }else{ // DEAL WITH MISSING BILLING
        if($tenant['isManager'] == 'No'){
          $missingBillingData = $this->_getMissingBillingData($tenant, $rProp, $vData, $postDate);
          $dataset[T::$tntTrans][] = $missingBillingData[T::$tntTrans];
          $dataset[T::$billing][] = $missingBillingData[T::$billing];
          $elasticInsertTenantIds[$tenant['tenant_id']] = $tenant['tenant_id'];
        }
        $billingChecker[$id] = 0;
      }
    }
    ##### CHECK THE BILLING THAT HAVEN'T BILL YET #####
    foreach($result as $val) {
      $tenant = $val['_source'];
//      $id = $tenant['prop'] . $tenant['unit'] . $tenant['tenant'];
      $id = $tenant['tenant_id'];
      if(!isset($billingChecker[$id])) { // We know that this has billing, but not sure if they are correct set the post date or not
        $isInsertNewBilling = 1;
        foreach($tenant['billing'] as $v){
          // CHECK THE ONE THAT M ONLY
          // Check is there any post_date greater than the input post_date
          // If it does have it, don't do anything
          if($v['schedule'] == 'M' && $v['service_code'] != '607' && $postDate < strtotime($v['stop_date']) && $postDate < strtotime($v['post_date']) && $v['stop_date'] == '9999-12-31'){
            $isInsertNewBilling = 0;
            break;
          }
        }
        
        if($isInsertNewBilling){
          $missingBillingData = $this->_getMissingBillingData($tenant, $rProp, $vData, $postDate);
          $dataset[T::$tntTrans][] = $missingBillingData[T::$tntTrans];
          $dataset[T::$billing][] = $missingBillingData[T::$billing];
          $elasticInsertTenantIds[$tenant['tenant_id']] = $tenant['tenant_id'];
        }
      }
    }
    if(empty($dataset[T::$billing]) && empty($dataset[T::$tntTrans])){
       Helper::echoJsonError($this->_getErrorMsg('store'));
    }
    
    ##### SPECIAL CASE FOR MJC1, MJC2, MJC3 ####
    if(isset($dataset[T::$billing])){
      foreach($dataset[T::$billing] as $i=>$v){
        if(preg_match('/MJC[0-9]+/', $v['prop']) && $v['schedule'] == 'M' && $v['gl_acct'] == '602'){
          unset($dataset[T::$billing][$i]);
        }
      }
    }
    foreach($dataset[T::$tntTrans] as $i=>$v){
      if(preg_match('/MJC[0-1]+/', $v['prop']) && $v['gl_acct'] == '602'){
        unset($dataset[T::$tntTrans][$i]);
      }
    }
    
    ##### START TO INSERT TNT TRANS DATA INTO DATABASE AND ELASTIC #####
    $rTntTrans = [];
    foreach($dataset['tnt_trans'] as $v){
      $rTntTrans[$v['tenant_id']][] = $v;
    }
    unset($dataset['tnt_trans']);
    $insertData = HelperMysql::getDataSet($dataset,$usr, $glChart, $service);    
    $insertData[T::$glTrans] =[];
    if(!empty($rTntTrans)){
      foreach($rTntTrans as $val){
        $val = self::_reorderPayment($val);
        foreach($val as $v){
          if($v['amount'] != 0){
            $v['service']  = $v['service_code'];
            $v['_glChart'] = $glChart;
            $v['_service'] = $service;
            $v['_rProp']   = Helper::selectData(['ar_bank','group1'], $rProp[$v['prop']]);
            $v['_rBank']   = Helper::keyFieldName($rProp[$v['prop']]['bank'], 'bank');

            $insertDataTmp = HelperMysql::getDataSet(PostInvoiceController::getInstance()->getStoreData($v), $usr, $glChart, $service);
            if(isset($insertDataTmp[T::$glTrans])){
              $insertData[T::$glTrans] = array_merge($insertData[T::$glTrans], $insertDataTmp[T::$glTrans]);
            }
            unset($insertDataTmp[T::$glTrans]);

            DB::beginTransaction();
            try{
              $success = Model::insert($insertDataTmp);
              $insertIds = $success['insert:'.T::$tntTrans];
              $success += Model::update([
                T::$tntTrans=>[ 
                  'whereInData'=>[['field'=>'cntl_no', 'data'=>$insertIds], ['field'=>'appyto', 'data'=>[0]]], 
                  'updateData'=>['appyto'=>DB::raw('cntl_no')],
                ]
              ]);
              $success += Model::update([
                T::$tntTrans=>[ 
                  'whereInData'=>['field'=>'cntl_no', 'data'=>$insertIds], 
                  'updateData'=>['invoice'=>DB::raw('appyto')],
                ]
              ]);
              $elastic[T::$tntTransView] = ['tt.cntl_no'=>$insertIds];
              Model::commit([
                'success' =>$success,
                'elastic' =>['insert'=>$elastic]
              ]);
            } catch(\Exception $e){
              $response['error']['mainMsg'] = Model::rollback($e);
            }
          }
        }
      }
    }
    if(empty($insertData[T::$glTrans])){
      unset($insertData[T::$glTrans]);
    }
    
    if(!empty($insertData[T::$billing])) {
      $insertData[T::$billing] = array_values($insertData[T::$billing]);
    }
    if(!empty($insertData[T::$tntTrans])) {
      $insertData[T::$tntTrans] = array_values($insertData[T::$tntTrans]);
    }
    $elasticInsertTenantIds = array_values($elasticInsertTenantIds);
//    dd($insertData,$billingPostDateIds,$updateBillingIds);
    if(empty($insertData) && empty($billingPostDateIds) && empty($updateBillingIds)) {
      Helper::echoJsonError($this->_getErrorMsg('store'));
    }
    
    if(!empty($billingPostDateIds)){
      $updateData = [
        T::$billing => [[
          'whereInData'=>['field'=>'billing_id', 'data'=>$billingPostDateIds],
          'updateData' =>['post_date'=>date('Y-m-d', strtotime('first day of next month', $postDate))]
      ]]];
    }
    if(!empty($updateBillingIds)){
      $updateData[T::$billing][] = [
        'whereInData'=>['field'=>'billing_id', 'data'=>$updateBillingIds],
        'updateData' =>['stop_date'=>$vData['date1']]
      ];
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::insert($insertData);
//      $insertIds = $success['insert:'.T::$tntTrans];
//      if(!empty($insertIds)){
//        $success += Model::update([
//          T::$tntTrans=>[ 
//            'whereInData'=>[['field'=>'cntl_no', 'data'=>$insertIds], ['field'=>'appyto', 'data'=>[0]]], 
//            'updateData'=>['appyto'=>DB::raw('cntl_no')],
//          ]
//        ]);
//        $success += Model::update([
//          T::$tntTrans=>[ 
//            'whereInData'=>['field'=>'cntl_no', 'data'=>$insertIds], 
//            'updateData'=>['invoice'=>DB::raw('appyto')],
//          ]
//        ]);
//        $elastic[T::$tntTransView] = ['tt.cntl_no'=>$insertIds];
//      }
      if(!empty($success['insert:'.T::$glTrans])){
        $elastic[T::$glTransView] = ['gl.seq'=>$success['insert:'.T::$glTrans]];
      }
      if(!empty($updateData)){
        $success += Model::update($updateData);
      }
      
      $elastic[T::$tenantView] = ['t.tenant_id'=>$elasticInsertTenantIds];
      $response['msg'] = $this->_getSuccessMsg('store', count($elasticInsertTenantIds));
      
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic],
      ]);
    } catch(Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private static function _reorderPayment($openItems){
    $tmp = $data = [];
    foreach($openItems as $gl=>$v) {
      $i = ($gl == '602') ? 0 : $gl;
      $tmp[$i] = [$gl=>$v];
    }
    krsort($tmp);
    foreach($tmp as $i=>$val){
      foreach($val as $gl=>$v){
        $data[$gl] = $v;
      }
    }
    return $data;
  }
}