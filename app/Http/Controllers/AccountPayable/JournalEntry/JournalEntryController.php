<?php
namespace App\Http\Controllers\AccountPayable\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Html, TableName AS T, Helper, HelperMysql,Account, Form};
use App\Http\Models\Model; // Include the models class

class JournalEntryController extends Controller{
  private $_viewPath  = 'app/AccountPayable/JournalEntry/journalEntry/';
  private static $_instance;

//------------------------------------------------------------------------------
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }  
  //------------------------------------------------------------------------------
  public function index(Request $req){
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . 'index';
    $iconData = '<i class="fa fa-fw fa-plus-square text-aqua tip tooltipstered pointer" title="Add More Full Billing Field" id="moreBilling"></i>';
    return view($page, ['data'=>[
        'nav'=>$req['NAV'],
        'account'=>Account::getHtmlInfo($req['ACCOUNT']), 
        'journalEntryForm' => $this->_getJournalEntryField(),
        'addMoreIcon'      => $iconData
      ]]);  
  } 
//------------------------------------------------------------------------------
  public function store(Request $req){
    $insertData = [];
    $req->merge(['bank'=>$req['ar_bank']]);
    unset($req['ar_bank']);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$glTrans], 
      'orderField'      => ['prop','bank','gl_acct','date1','amount','remark', 'check_no'], 
      'includeCdate'    => 0,
      'isPopupMsgError' => 1,
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$propBank . '|prop,bank',
        ]
      ]
    ]);
    $vData    = $valid['data'];
    $vDataArr = $valid['dataArr'];
 
    $vData['batch'] = HelperMysql::getBatchNumber();
    
    $dataset = $this->getStoreData($vData, $vDataArr);
    
    ##### BUILD THE DATASET FOR INSERT INTO DATABASE #####
    $insertData = HelperMysql::getDataSet($dataset, $vData['usid']);

    if(empty($insertData)) {
      Helper::echoJsonError($this->_getErrorMsg('storeEmpty'));
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      # IT IS ALWAYS ONE TRANSACTION ONLY
      $success += Model::insert($insertData);
      
      $elastic = [
        'insert'=>[
          T::$glTransView  => ['gl.seq'=>$success['insert:' . T::$glTrans]],
        ]
      ];
      $response['sideMsg'] = $this->_getSuccessMsg('store', $vData);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  public function getStoreData($vData, $vDataArr){
    $dataset = [];
    $groupedData = Helper::groupBy($vDataArr, 'prop');
    foreach($groupedData as $prop => $val) {
      $sumArr = array_column($val, 'amount');
      $sumAmount = array_sum($sumArr);
      if(count($val) < 2) {
        Helper::echoJsonError($this->_getErrorMsg('storeMinTwoEntries', $prop));
      }else if($sumAmount != 0) {
        Helper::echoJsonError($this->_getErrorMsg('storeZeroTotalAmount', $prop));
      }
    }
    foreach($vDataArr as $i => $v) {
      $vDataArr[$i]['tenant']       = '';
      $vDataArr[$i]['unit']         = '';
      $vDataArr[$i]['service_code'] = '';
      $vDataArr[$i]['journal']      = 'JE';
      $vDataArr[$i]['batch']        = $vData['batch'];
      $vDataArr[$i]['post_mo']      = 0;
      $vDataArr[$i]['tx_code']      = 'JE';
      $vDataArr[$i]['inv_date']     = $v['date1'];
      $vDataArr[$i]['date2']        = $v['date1'];
    }
    $dataset[T::$glTrans] = $vDataArr;
    return $dataset;
  }


//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $vData){
    $h4 = Html::h4(Html::icon('fa fa-fw fa-check') . ' Journal Entry Successful.');
    $p = Html::p('Batch# '. Helper::getValue('batch', $vData) . ' with the Total Amount of 0');
    $data = [
      'store' =>Html::div($h4 . $p, ['class'=>'callout callout-info']),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $prop = ''){
    $data = [
      'storeMinTwoEntries'   =>Html::errMsg('Minimum of two entries required for Prop ' . $prop . '.'),
      'storeZeroTotalAmount' =>Html::errMsg('Total Amount for Prop '. $prop .' needs to be zero.'),
      'storeEmpty'           =>Html::errMsg('Please make sure the amount per row is greater or less than $0.')
    ];
    return $data[$name];
  }  
//------------------------------------------------------------------------------
   private function _getJournalEntryField(){
     $i = 0;
    $form = '';
    $default = ['req'=>1, 'includeLabel'=>0];

    $fields = [
      'prop' => $default + [
        'id'    =>'prop[' . $i . ']', 
        'type'  =>'text',
        'class' =>'autocomplete prop',
      ],
      'ar_bank' => $default + [
        'id'     =>'ar_bank[' . $i . ']', 
        'type'   =>'option',
        'option' =>[], 
        'value'  =>'',
      ],
      'gl_acct'=> $default + [
        'id'     =>'gl_acct[' . $i . ']', 
        'type'   =>'text',
        'class' =>'autocomplete',
      ],
      'date1' => $default + [
        'id'    => 'date1[' . $i . ']',
        'type'  => 'text',
        'class' => 'date',
        'value' => Helper::usDate(),
      ],
      'amount' =>$default + [
        'id'    => 'amount[' . $i . ']', 
        'type'  => 'text',
        'class' => 'decimal amount',
        'value' => 0
      ],
      'remark' => $default + [
        'id'    => 'remark[' . $i . ']',
        'type'  => 'text',
      ],
      'check_no'=>$default+ [
        'id'    => 'check_no[' . $i . ']', 
        'type'  => 'text',
        'value' => '000000',
      ]
    ];
    foreach($fields as $k=>$v){
      $col = 2;
      if($k == 'prop' || $k =='amount') { $col = 1; };
      $colClass = 'col-md-' . $col;
      if($k == 'check_no'){
        $removeDiv = Html::div(Form::getField($v) . '<a class="journalEntryRemove" data-key="'.$i.'" title="Journal Entry Remove"><i class="fa fa-trash-o text-red pointer tip" title="Full Billing Remove"></i></a>', ['class'=>'trashIcon']);
        $form .= Html::div($removeDiv ,['class'=>$colClass]);
      }else{
        $form .= Html::div(Form::getField($v),['class'=>$colClass]);
      }
    }
    return Html::div($form, ['class'=>'row journalEntryRow', 'data-key'=>$i]);
  }
}