<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\DepositCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper, HelperMysql, Format};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use App\Http\Models\Model; // Include the models class
use SimpleCsv;
use PDF;

class DepositCheckController extends Controller{
  private static $_instance;
  private $_tab;
  private $_batchOption = [''=>'New Batch#'];
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
    $text = 'To Enter a Cash Receipt, Please Fill out the Information on the Left and Click Submit.' . Html::br(2); 
    $text .= 'Deposit Check/Cash Rec function allows users to enter money collected and record transactions both negative and positive.';
    
    $default= P::getDefaulValue($req);
    $fields = [
      'date1'   => ['id'=>'date1','label'=>'Post Date','type'=>'text','class'=>'date','req'=>1, 'value'=>date('m/d/Y')],
      'amount'  => ['id'=>'amount','label'=>'Amount','class'=>'decimal', 'type'=>'text','req'=>1],
//      'prop'    => ['id'=>'prop','label'=>'Prop','class'=>'autocomplete', 'type'=>'text','value'=>Helper::getValue('prop', $default['value']), 'req'=>1],
//      'unit'    => ['id'=>'unit','label'=>'Unit','class'=>'autocomplete', 'type'=>'text','value'=>Helper::getValue('unit', $default['value'])],
//      'ar_bank' => ['id'=>'ar_bank','label'=>'Bank','type'=>'option', 'option'=>Helper::getValue('bank', $default['option']),'req'=>1, 'value'=>Helper::getValue('defaultBank', $default)],
      'prop'    => ['id'=>'prop','label'=>'Prop','class'=>'autocomplete', 'type'=>'text','value'=>'', 'req'=>1],
      'unit'    => ['id'=>'unit','label'=>'Unit','class'=>'autocomplete', 'type'=>'text','value'=>''],
      'gl_acct' => ['id'=>'gl_acct','label'=>'GL','class'=>'autocomplete', 'type'=>'text','req'=>1],
      'remark'  => ['id'=>'remark','label'=>'Remark', 'type'=>'text','req'=>1],
      'check_no'=> ['id'=>'check_no','label'=>'Check No.', 'type'=>'text','value'=>'000000','req'=>1],
      'ar_bank' => ['id'=>'ar_bank','label'=>'Bank','type'=>'option', 'option'=>[],'req'=>1, 'value'=>Helper::getValue('defaultBank', $default)],
//      'batch'   => ['id'=>'batch','label'=>'Use Batch','type'=>'option', 'option'=>$this->_batchOption],
    ];
    return P::getCreateContent($fields, $this->_tab) + ['text'=>Html::h2(Html::br() . $text, ['class'=>'text-center text-muted'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['date1','amount','remark', 'gl_acct', 'prop', 'unit', 'ar_bank', 'check_no'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$prop . '|prop',
//          T::$prop . '|prop,ar_bank', 
          T::$glChart . '|prop,gl_acct'
        ]
      ]
    ]);
    $vData = $valid['data'];
    
    $maxDateAllowToPost = strtotime('-3 month');
    if($maxDateAllowToPost > strtotime($vData['date1'])){
      Helper::echoJsonError($this->_getErrorMsg('storePostedDateTooOld'), 'popupMsg');
    }
    $vData['_batch']   = HelperMysql::getBatchNumber();
    $vData['_glChart'] = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$vData['prop']])), 'gl_acct');
    $vData['_service'] = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$vData['prop']])), 'service');
    
    $dataset = $this->getStoreData($vData);
    ##### BUILD THE DATASET FOR INSERT INTO DATABASE #####
    $insertData = HelperMysql::getDataSet($dataset, $vData['usid'], $vData['_glChart'], $vData['_service']);
    $vData['batch'] = $vData['_batch'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      # IT IS ALWAYS ONE TRANSACTION ONLY
      $success += Model::insert($insertData);
      $elastic = [
        T::$glTransView=>['seq'=>$success['insert:'.T::$glTrans]],
      ];
      
      $response = [
        'success'   => 1, 
        'sideMsg'   =>$this->_getSuccessMsg('store', $vData),
        'batchHtml' => Html::buildOption($this->_batchOption, ''), 
        'date1'     =>Format::usDate($vData['date1']),
      ];
      
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
    $dataset = [T::$glTrans=>[], T::$batchRaw=>[], 'summaryAcctReceivable'=>[]];
    ##### CHECK TO MAKE SURE THE DATE IS THE SAME OTHER IT WILL NOT ALL USER TO POST THE PAYMENT #####
//    if(!empty($vData['batch'])){
//      if(!empty(V::validateionDatabase(['mustNotExist'=>[T::$clearedCheck . '|bank,orgprop,batch,ref1']], ['data'=>['bank'=>$vData['ar_bank'], 'orgprop'=>$vData['prop'], 'batch'=>$vData['batch'], 'ref1'=>$vData['check_no']], 'isExitIfError'=>0]))){
//        Helper::echoJsonError($this->_getErrorMsg('clearedCheck', $vData), 'popupMsg');
//      } else if(V::validateionDatabase(['mustExist'=>[T::$glTrans . '|date1,batch']], ['data'=>$vData, 'isExitIfError'=>0])){
//        Helper::echoJsonError($this->_getErrorMsg('diffData1', $vData), 'popupMsg');
//      }
//    }else {
//      $vData['batch'] = $vData['_batch'];
//    }
    
    $vData['batch'] = $vData['_batch'];
    $this->_batchOption[$vData['batch']] = 'Previous Batch#: ' . $vData['batch'];
    $rPropBank = Helper::keyFieldNameElastic(HelperMysql::getPropBank(['prop.keyword'=>[$vData['prop']]]), ['prop', 'bank']);
    $vData['tx_code'] = 'P';
    $vData['bank']    = $vData['ar_bank'];
    $vData['tenant']  = 255;
    $vData['amount']  = $vData['amount'] * -1;
    $vData['gl_contra']    = $rPropBank[$vData['prop'] . $vData['ar_bank']]['gl_acct'];
    $vData['service_code'] = $vData['_glChart'][$vData['gl_acct']]['service'];
    $vData['inv_remark']   = $vData['remarks'] = $vData['remark'];
    $dataset[T::$glTrans]  = [$vData];
    $dataset[T::$batchRaw] = $dataset[T::$glTrans];
    return $dataset;
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'clearedCheck' =>Html::errMsg('You recorded the payment for this prop, unit, tenant, and bank with this batch# '. Helper::getValue('batch', $vData) .' once already. You cannot record it twice with this batch. Please use different batch or prop,tenant,and unit.'),
      'diffData1'    =>Html::errMsg('You cannot change the post date if you use the batch# '. Helper::getValue('batch', $vData) .'. Please change the post date to the previous one.'),
      'storePostedDateTooOld'    =>Html::errMsg('You cannot post the transaction that is older than 3 months.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $vData){
    $h4 = Html::h4(Html::icon('fa fa-fw fa-check') . ' Successfully Deposit the Check.');
    $p = Html::p('Batch# '. Helper::getValue('batch', $vData) . ' with the amount of ' . Format::usMoneyMinus(Helper::getValue('amount', $vData) * -1));
    $data = [
      'store'=>Html::div($h4 . $p, ['class'=>'callout callout-info']),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------  
  private function _getTable($fn){
    return [T::$glTrans, T::$prop];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    return [
      'field'=>[
      ],
      'rule'=>[
        'check_no' =>'required|string|between:6,6',
        'unit'     =>'nullable|string|between:4,4',
//        'batch'    =>'nullable|integer',
      ]
    ];
  }
}
