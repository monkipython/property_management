<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\PostInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountReceivable\CashRec\CashRecController AS P;
use App\Library\{V, Html, TableName AS T, Helper, HelperMysql, TenantTrans};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CashRecModel AS M; // Include the models class

class PostInvoiceController extends Controller{
  private static $_instance;
  private $_tab;
//------------------------------------------------------------------------------
  public function __construct(){
    $this->_tab = P::getInstance()->tab;
  }
//------------------------------------------------------------------------------
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }  
//------------------------------------------------------------------------------
  public function create(Request $req){
    $default= P::getDefaulValue($req);
    $fields = [
      'dateRange'=>['id'=>'dateRange','label'=>'View LC Fr/To','type'=>'text','class'=>'daterange','value'=>Helper::getValue('dateRange', $default['value']), 'req'=>1],
      'date1'   => ['id'=>'date1','label'=>'Post Date','type'=>'text','class'=>'date','req'=>1, 'value'=>date('m/d/Y')],
      'amount'  => ['id'=>'amount','label'=>'Amount','class'=>'decimal', 'type'=>'text','req'=>1],
      'prop'    => ['id'=>'prop','label'=>'Prop','class'=>'autocomplete', 'type'=>'text','value'=>Helper::getValue('prop', $default['value']), 'req'=>1],
      'unit'    => ['id'=>'unit','label'=>'Unit','class'=>'autocomplete', 'type'=>'text','value'=>Helper::getValue('unit', $default['value']), 'req'=>1],
      'tenant'  => ['id'=>'tenant','label'=>'Tenant','type'=>'option', 'option'=>Helper::getValue('tenant', $default['option']), 'value'=>Helper::getValue('tenant', $default['value']), 'req'=>1],
      'service' => ['id'=>'service','label'=>'Service Code','class'=>'autocomplete', 'type'=>'text','req'=>1],
      'remark'  => ['id'=>'remark','label'=>'Remark', 'type'=>'text','req'=>1],
    ];
    return P::getCreateContent($fields, $this->_tab);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $updateData = $insertData = $table = [];
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['date1','amount','remark', 'service', 'prop', 'unit', 'tenant'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeCdate'    => 0, 
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tenant . '|prop,unit,tenant',
          T::$service . '|service'
        ]
      ]
    ]);
    $vData = $valid['data'];
//    if($vData['service'] == '755'){
//      return ['error'=>['popupMsg'=>$this->_getErrorMsg('store')]];
//    }
    $vData['appyto'] = 0;
    $vData['batch']    = HelperMysql::getBatchNumber();
    $vData['tnt_name'] = M::getTableData(T::$tenant,Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $vData)), 'tnt_name', 1)['tnt_name'];
    $vData['_glChart'] = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$vData['prop']])), 'gl_acct');
    $vData['_service'] = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$vData['prop']])), 'service');
    $vData['_rProp']   = M::getTableData(T::$prop,Model::buildWhere(['prop'=>$vData['prop']]), ['ar_bank','group1'], 1);
    $vData['_rBank']   = Helper::keyFieldName(M::getTableData(T::$propBank,Model::buildWhere(['prop'=>$vData['prop']])), 'bank');
    
    $dataset = $this->getStoreData($vData);
    
//    $rTntTrans      = TenantTrans::getApplyToSumResult($vData);
//    // CLEAN TRANSACTION NEED TO BE HERE REGARDLESS
//    TenantTrans::cleanTransaction($vData);
//    $invoiceAmount = $vData['amount'];
//    if($rTntTrans['balance'] < 0){
//      $appyto = array_keys($rTntTrans['data']);
//      $r = TenantTrans::searchTntTrans([
//        'query'=>[
//          'must'=>['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant'], 'appyto'=>$appyto],
//        ]
//      ]);
//      
//      ##### THIS WILL USE THE LAST TRANSACTION IF THEY HAVE MULTIPLE TRANS
////      $oldTrans = Helper::keyFieldNameElastic($r, 'appyto');
//      $openItem = Helper::keyFieldName(TenantTrans::getOpenItem($r)['data'], 'appyto');
//      // START TO BUILD S TRANSACTION 
//      /* BEGINNING BALANCE NEGATIVE (ONLY 1 APPLYTO)
//       * TX_CODE    GL    APPYTO    BALANCE
//       * Old transaction
//       * P          375   13937496  -25.00
//       * -----------------CASE 1---------------------------
//       * Incoming Transaction
//       * IN         602   1234      100
//       * 
//       * Moving Transaction
//       * S          375   13937496  25.00
//       * S          602   1234      -25.00
//       * -----------------@CASE 1: END BAL with IN-602-1234:  75----------
//       * -----------------CASE 2-----------------------
//       * Incoming Transaction
//       * P          375   1111      -200
//       * -----------------@CASE 2: END BAL: -225 with GL 375 and appyto 1111 
//       */
//      foreach($rTntTrans['data'] as $appyto=>$negAmount){
//        $advRentAppyto = $appyto;
//        $invoiceAmount = $invoiceAmount + $negAmount;
//        ##### IT DOES NOT MATTER POSITVE OR NEGATIVE. THE FUNCTION WILL DO THE WORK FOR US ####
//        $sysAmount = ($invoiceAmount >= 0) ? $negAmount : ($invoiceAmount - $negAmount);
//        $vData['appyto'] = $openItem[$appyto]['appyto'];
//        
//        $sysTntTrans = $this->_getSysTntTrans($sysAmount, $openItem[$appyto], $vData);
//        $sysGlTrans  = $this->_getSysGlTrans($sysTntTrans, $vData);
//        
//        $dataset[T::$tntTrans] = Helper::pushArray($dataset[T::$tntTrans], $sysTntTrans);
//        $dataset[T::$glTrans]  = Helper::pushArray($dataset[T::$glTrans], $sysGlTrans);
//
//        if($invoiceAmount <= 0){
//          break; // Done here
//        }
//      }
//    }
//    
//    
//    ##### MAKE SURE ALWAYS IN THE LAST TRANSACTION #####
//    $dataset[T::$tntTrans][] = $this->_getInvoiceTrans($vData['amount'], $advRentAppyto, $vData);
//
//    ##### ALWAYS CHECK THE SUM SYS TRANS IS ZERO. IF NOT, THE FUNCTION EXIT AND ISSUE ERROR #####
//    Helper::isSysTransBalZero($dataset);
//    
//    ##### $dataset[T::$glTrans] NEED TO BE INITIALIZE FIRST OTHER INVOICE WITH NEGATIVE NUMBER WILL BREAK #####
//    if(empty($dataset[T::$glTrans])){
//      unset($dataset[T::$glTrans]);
//    }
//    
    ##### BUILD THE DATASET FOR INSERT INTO DATABASE #####
    $dataset = TenantTrans::getTntSurityDeposit($dataset); ##### STORE THE DATA TO THE tnt_security_deposit #####   
    $insertData = HelperMysql::getDataSet($dataset, $vData['usid'], $vData['_glChart'], $vData['_service']);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      # IT IS ALWAYS ONE TRANSACTION ONLY
      $success += Model::insert($insertData);
      $cntlNo   = end($success['insert:'.T::$tntTrans]);
      
      $success += Model::update([
        T::$tntTrans=>[ 
          'whereInData'=>[['field'=>'cntl_no', 'data'=>$success['insert:'.T::$tntTrans]], ['field'=>'appyto', 'data'=>[0]]], 
          'updateData'=>['appyto'=>$cntlNo, 'invoice'=>$cntlNo],
        ]
      ]);
      
      $success += Model::update([
        T::$tntTrans=>[ 
          'whereInData'=>['field'=>'cntl_no', 'data'=>$success['insert:'.T::$tntTrans]], 
          'updateData'=>['invoice'=>DB::raw('appyto')],
        ]
      ]);
      
      $elastic = [
        T::$tntTransView =>['tt.cntl_no'=>$success['insert:' . T::$tntTrans]],
      ];

      if(!empty($success['insert:'.T::$glTrans])){
        $success += Model::update([
          T::$glTrans=>[
            'whereInData'=>[['field'=>'seq', 'data'=>$success['insert:'.T::$glTrans]],['field'=>'appyto', 'data'=>[0]]], 
            'updateData'=>['appyto'=>$cntlNo, 'invoice'=>$cntlNo],
          ]
        ]);
        $elastic[T::$glTransView] = ['gl.seq'=>$success['insert:' . T::$glTrans]];
      }

      $response['mainMsg'] = $this->_getSuccessMsg('store');
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
  public function getStoreData($vData){
    $advRentAppyto = 0;
    $dataset = [T::$tntTrans=>[], T::$glTrans=>[]];
    if($vData['service'] == '755'){
      Helper::echoJsonError($this->_getErrorMsg('store'), 'popupMsg');
    }
    
    $rTntTrans = TenantTrans::getApplyToSumResult($vData);
    // CLEAN TRANSACTION NEED TO BE HERE REGARDLESS
    TenantTrans::cleanTransaction($vData);
    $invoiceAmount = $vData['amount'];
    if($rTntTrans['balance'] < 0){
      $appyto = array_keys($rTntTrans['data']);
      $r = TenantTrans::searchTntTrans([
        'query'=>Helper::getPropUnitTenantMustQuery($vData, ['appyto'=>$appyto])
      ]);
      ##### THIS WILL USE THE LAST TRANSACTION IF THEY HAVE MULTIPLE TRANS
      $openItem = Helper::keyFieldName(TenantTrans::getOpenItem($r)['data'], 'appyto');
      // START TO BUILD S TRANSACTION 
      /* BEGINNING BALANCE NEGATIVE (ONLY 1 APPLYTO)
       * TX_CODE    GL    APPYTO    BALANCE
       * Old transaction
       * P          375   13937496  -25.00
       * -----------------CASE 1---------------------------
       * Incoming Transaction
       * IN         602   1234      100
       * 
       * Moving Transaction
       * S          375   13937496  25.00
       * S          602   1234      -25.00
       * -----------------@CASE 1: END BAL with IN-602-1234:  75----------
       * -----------------CASE 2-----------------------
       * Incoming Transaction
       * P          375   1111      -200
       * -----------------@CASE 2: END BAL: -225 with GL 375 and appyto 1111 
       */
      foreach($rTntTrans['data'] as $appyto=>$negAmount){
        $advRentAppyto = $appyto;
        $invoiceAmount = $invoiceAmount + $negAmount;
        ##### IT DOES NOT MATTER POSITVE OR NEGATIVE. THE FUNCTION WILL DO THE WORK FOR US ####
        $sysAmount = ($invoiceAmount >= 0) ? $negAmount : ($invoiceAmount - $negAmount);
        $vData['appyto'] = $openItem[$appyto]['appyto'];
        
        $sysTntTrans = $this->_getSysTntTrans($sysAmount, $openItem[$appyto], $vData);
        $sysGlTrans  = $this->_getSysGlTrans($sysTntTrans, $vData);
        
        $dataset[T::$tntTrans] = Helper::pushArray($dataset[T::$tntTrans], $sysTntTrans);
        $dataset[T::$glTrans]  = Helper::pushArray($dataset[T::$glTrans], $sysGlTrans);

        if($invoiceAmount <= 0){
          break; // Done here
        }
      }
    }
    
    ##### MAKE SURE ALWAYS IN THE LAST TRANSACTION #####
    $dataset[T::$tntTrans][] = $this->_getInvoiceTrans($vData['amount'], $advRentAppyto, $vData);

    ##### ALWAYS CHECK THE SUM SYS TRANS IS ZERO. IF NOT, THE FUNCTION EXIT AND ISSUE ERROR #####
    Helper::isSysTransBalZero($dataset);
    
    ##### $dataset[T::$glTrans] NEED TO BE INITIALIZE FIRST OTHER INVOICE WITH NEGATIVE NUMBER WILL BREAK #####
    if(empty($dataset[T::$glTrans])){
      unset($dataset[T::$glTrans]);
    }
    
    return $dataset;
  }
  
//------------------------------------------------------------------------------  
  private function _getSysTntTrans($amount, $oldTrans, $vData){
    $amount = abs($amount);
    $oldTrans['amount']   = $amount;
    $oldTrans['tx_code']  = 'S';
    $oldTrans['gl_acct']  = '375';
    $oldTrans['date1']    = $oldTrans['inv_date'] = Helper::getValue('date1', $vData, date('Y-m-d'));
    $oldTrans['journal']  = 'JE';
    $oldTrans['batch']    = $vData['batch'];
    $oldTrans['tnt_name'] = $vData['tnt_name'];
    $oldTrans['name_key'] = $vData['tnt_name'];
    unset($oldTrans['cntl_no']);
    $copyData = $oldTrans;
    
    $copyData['amount']       = $amount * -1;
    $copyData['service_code'] = $vData['service'];
    $copyData['remark']       = $vData['_service'][$vData['service']]['remark'];
    $copyData['remarks']      = $copyData['remark'];
    $copyData['inv_remark']   = $copyData['remark'];
    $copyData['gl_acct']      = $vData['_service'][$vData['service']]['gl_acct'];
    return [$oldTrans, $copyData];
  }
//------------------------------------------------------------------------------
  private function _getSysGlTrans($sysTntTrans, $vData){
    $bank = isset($vData['_rBank'][$sysTntTrans[0]['bank']]) ? $sysTntTrans[0]['bank'] : HelperMysql::getDefaultBank($sysTntTrans[0]['prop']);

    $sysTntTrans[0]['journal'] = $sysTntTrans[1]['journal'] = 'JE';
    $sysTntTrans[0]['group1']  = $sysTntTrans[1]['group1'] = $vData['_rProp']['group1'];
    
    $sysTntTrans[0]['gl_contra'] = $vData['_rBank'][$bank]['gl_acct'];
    $sysTntTrans[1]['gl_contra'] = $vData['_rBank'][$bank]['gl_acct'];
    return $sysTntTrans;
  }
//------------------------------------------------------------------------------
  private function _getInvoiceTrans($amount, $appyto, $vData){
    $rPropBank = Helper::keyFieldNameElastic(HelperMysql::getPropBank(Helper::getPropMustQuery($vData, [], 0)), ['prop', 'bank']);
    return [
      'tx_code'      => 'IN',
      'gl_acct'      => $vData['_service'][$vData['service']]['gl_acct'],
      'service_code' => $vData['service'],
      'batch'        => $vData['batch'],
      'bank'         => $vData['_rProp']['ar_bank'],
      'amount'       => abs($amount),
      'gl_contra'    => $rPropBank[$vData['prop'] . $vData['_rProp']['ar_bank']]['gl_acct'],
      'appyto'       => $appyto,
      'inv_remark'   => isset($vData['remark']) ? $vData['remark'] : $vData['_service'][$vData['service']]['remark'],
      'bill_seq'     => '8',
      'tnt_name'     => $vData['tnt_name'],
    ] + $vData; // + $vData must be at the end so that if there is duplication, it won't override it
  }
//------------------------------------------------------------------------------  
  private function _getTable($fn){
    return [T::$tntTrans, T::$service];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' =>Html::sucMsg('Successfully Invoice this tenant.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'store' =>Html::errMsg('You cannot invoice Security Deposit Refunds (755) here. Please Issue it in the Tenant Deposit Detail instead.'),
    ];
    return $data[$name];
  }  
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    return [
      'field'=>[
      ],
      'rule'=>[
        'amount'   =>'required|numeric|between:0.01,100000.00'
      ]
    ];
  }
}