<?php
namespace App\Http\Controllers\AccountPayable\DebitExpenseTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, Account, TableName AS T, Helper, HelperMysql, Format};
use App\Http\Models\Model; // Include the models class

class DebitExpenseTransactionController extends Controller{
  private $_viewPath  = 'app/AccountPayable/DebitExpenseTransaction/debitExpenseTransaction/';
  private static $_instance;
  private $_batchOption = [''=>'New Batch#'];
  public  $type = [
    'debitExpenseTransaction'       =>'Debit / Expense Booking',
    'debitExpenseTransactionUpload' =>'Debit / Expense Booking Upload',
  ];

  //------------------------------------------------------------------------------
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  } 
  //------------------------------------------------------------------------------
  public function index(Request $req){
    $page = $this->_viewPath . 'index';
    
    return view($page, ['data'=>[
      'nav'     => $req['NAV'],
      'account' => Account::getHtmlInfo($req['ACCOUNT']), 
      'debitExpenseTransactionForm' => Form::getField(['id'=>'type','label'=>'Type','type'=>'option','option'=>$this->type, 'req'=>1]),
    ]]);  
  } 
//------------------------------------------------------------------------------
  public function create(Request $req){
    $text  = 'To Enter a Debit / Expense Booking, Please Fill out the Information on the Left and Click Submit.' . Html::br(2); 
    $text .= 'Debit / Expense Booking function allows users to enter money collected and record transactions both negative and positive.';
    $title = Html::h2(Html::br() . $text, ['class'=>'text-center text-muted']);
    
    return ['html'=>$this->_getDebitExpenseField(), 'text'=>Html::h2(Html::br() . $title, ['class'=>'text-center text-muted'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$glTrans, T::$prop, T::$vendor], 
      'orderField'      => ['date1','amount','remark', 'gl_acct', 'prop', 'unit', 'ar_bank', 'check_no', 'batch', 'invoice', 'vendid'], 
      'setting'         => $this->_getSetting(__FUNCTION__), 
      'includeUsid'     => 1,
      'isPopupMsgError' => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$prop . '|prop',
          T::$glChart . '|prop,gl_acct'
        ]
      ]
    ]);
    $vData = $valid['data'];
    $maxDateAllowToPost = strtotime('-3 month');
    if($maxDateAllowToPost > strtotime($vData['date1'])){
      Helper::echoJsonError($this->_getErrorMsg('storePostedDateTooOld'), 'popupMsg');
    }
    ##### CHECK TO MAKE SURE THE DATE IS THE SAME OTHER IT WILL NOT ALL USER TO POST THE PAYMENT #####
    if(!empty($vData['batch'])){
      if(V::validateionDatabase(['mustExist'=>[T::$glTrans . '|date1,batch']], ['data'=>$vData, 'isExitIfError'=>0])){
        Helper::echoJsonError($this->_getErrorMsg('diffData1', $vData), 'popupMsg');
      }
    }else{
      $vData['batch'] = HelperMysql::getBatchNumber();
    }
    $this->_batchOption[$vData['batch']] = 'Previous Batch#: ' . $vData['batch'];
    $dataset  = $this->getStoreData($vData);
    
    ##### BUILD THE DATASET FOR INSERT INTO DATABASE #####
    $insertData = HelperMysql::getDataSet($dataset, $vData['usid']);
    if(empty($insertData)) {
      Helper::echoJsonError($this->_getErrorMsg('storeEmpty'));
    };
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
        'sideMsg'   => $this->_getSuccessMsg('store', $vData),
        'batchHtml' => Html::buildOption($this->_batchOption, ''), 
        'date1'     => Format::usDate($vData['date1']),
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
    $dataset = [T::$glTrans=>[], T::$batchRaw=>[],'summaryAcctReceivable'=>[]];
    $rPropBank = Helper::keyFieldNameElastic(HelperMysql::getPropBank(['prop.keyword'=>[$vData['prop']]]), ['prop', 'bank']);

    $vData['tx_code']      = 'P';
    $vData['bank']         = $vData['ar_bank'];
    $vData['tenant']       = 255;
    $vData['vendor']       = $vData['vendid'];
    $vData['gl_contra']    = $rPropBank[$vData['prop'] . $vData['ar_bank']]['gl_acct'];
    $vData['service_code'] = '';
    $vData['inv_remark']   = $vData['remarks'] = $vData['remark'];
    $dataset[T::$glTrans]  = [$vData];
    $dataset[T::$batchRaw] = $dataset[T::$glTrans];
    
    return $dataset;
  }
//------------------------------------------------------------------------------
   private function _getErrorMsg($name, $vData = []){
    $data = [
      'diffData1'    => Html::errMsg('You cannot change the post date if you use the batch# '. Helper::getValue('batch', $vData) .'. Please change the post date to the previous one.'),
      'storePostedDateTooOld' => Html::errMsg('You cannot post the transaction that is older than 3 months.'),
      'storeEmpty'   => Html::errMsg('Please make sure the amount is greater or less than $0.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $vData){
    $h4 = Html::h4(Html::icon('fa fa-fw fa-check') . ' Successfully Debit / Expense Booking.');
    $p = Html::p('Batch# '. Helper::getValue('batch', $vData) . ' with the amount of ' . Format::usMoneyMinus(Helper::getValue('amount', $vData)));
    $data = [
      'store'=>Html::div($h4 . $p, ['class'=>'callout callout-info']),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn){
    $setting = [
      'store' => [
        'rule'=>[
          'check_no' =>'required|string|between:6,6',
          'unit'     =>'nullable|string|between:4,4',
          'batch'    =>'nullable|integer',
        ]
      ]
    ];
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getDebitExpenseField() {
    $fields = [
      'date1'   => ['id'=>'date1','label'=>'Post Date','type'=>'text','class'=>'date','req'=>1, 'value'=>date('m/d/Y')],
      'amount'  => ['id'=>'amount','label'=>'Amount','class'=>'decimal', 'type'=>'text','req'=>1],
      'prop'    => ['id'=>'prop','label'=>'Prop','class'=>'autocomplete', 'type'=>'text', 'req'=>1, 'hint'=>'You can type property address or number or trust for autocomplete'],
      'unit'    => ['id'=>'unit','label'=>'Unit','class'=>'autocomplete', 'type'=>'text', 'hint'=>'You can type Unit number or Prop address for autocomplete'],
      'gl_acct' => ['id'=>'gl_acct','label'=>'GL Account','class'=>'autocomplete', 'type'=>'text','req'=>1, 'hint'=>'You can type GL account number or title for autocomplete'],
      'remark'  => ['id'=>'remark','label'=>'Remark', 'type'=>'text','req'=>1],
      'vendid'  => ['id'=>'vendid','label'=>'Vendor', 'type'=>'text', 'class'=>'autocomplete','req'=>1, 'autocomplete'=>'false', 'hint'=>'You can type vendor name or number for autocomplete'],
      'check_no'=> ['id'=>'check_no','label'=>'Check No.', 'type'=>'text','value'=>'000000','req'=>1],
      'ar_bank' => ['id'=>'ar_bank','label'=>'Bank','type'=>'option', 'option'=>[],'req'=>1],
      'batch'   => ['id'=>'batch','label'=>'Use Batch','type'=>'option', 'option'=>$this->_batchOption],
      'invoice' => ['id'=>'invoice','label'=>'Invoice', 'type'=>'text','req'=>1],
    ];
    return  implode('',Form::generateField($fields));
  }
}
