<?php
namespace App\Http\Controllers\AccountReceivable\CashRec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Upload, Account, TableName AS T, Helper, TenantTrans,Format};
use App\Http\Models\Model; // Include the models class
use \App\Http\Models\CashRecModel AS M; // Include the models class
use SimpleCsv;
use PDF;
use Storage;

class CashRecController extends Controller{
  private static $_instance;
  private $_viewPath  = 'app/AccountReceivable/CashRec/';
  private $_mapping   = [];
  public  $_viewTable = '';
  public  $_indexMain = '';
  public  $type = [
    'ledgerCard'=>'View/Fix Ledger Card',
    'postPayment'=>'Record Payment',
    'postInvoice'=>'Invoice Tenant',
    'paymentUpload'=>'Record Payment Upload',
    'invoiceUpload'=>'Invoice Tenant Upload',
    'rpsCheckOnly'=>'RPS Upload Check Only',
    'rpsCreditCheck'=>'RPS Upload Credit Check',
    'rpsTenantStatement'=>'RPS Upload Tenant Statement',
    'depositCheck'=>'Deposit Check/Cash Rec',
    'depositUpload'=>'Deposit Check/Cash Upload',
  ];
  public $tab = [
    'ledgerCard' => 'Ledger Card',
    'ledgerCardDetail' => 'Ledger Card Detail',
    'openItem'   => 'List Open Items',
    'detail'     => 'Transaction Detail',
  ];
//------------------------------------------------------------------------------
  public function __construct(){
    $this->_mapping = Helper::getMapping(['tableName'=>T::$tntTrans]);
    $this->_viewTable = T::$tntTransView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
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
    $perm = Helper::getPermission($req);
    $tab  = $this->tab;
//  <span class="btn btn-primary" id="pastMonth"></span>
//	<span class="btn btn-info" id="currentMonth"></span>
//  <span class="btn btn-danger" id="nextMonth"></span>
//  <span id="notPaidTenant"></span>
//    $test = Html::span(Html::span('safjh', ['class'=>'callout callout-info']), ['class'=>'box-body']);
//    $deposit = Html::span('', []);
    $toolbar = Html::div('', ['id'=>'toolbar']);
    $initText = Html::h2(Html::br(1). 'To View Ledger Card, Please Fill out the Information on the Left and Submit. '.Html::br(2).'The Ledger Card is where the history of charges, credits, payments with the balance for each tenant is recorded. This place is where user can view ledger card, post payment, bill invoice, and fix incorrect payment or invoice.' . Html::br(3), ['id'=>'initText' ,'class'=>'text-center text-muted']);
    $tab = Html::buildTab([
        camel_case($tab['ledgerCard'])       => Html::div($toolbar . $initText,['id'=>'bar'.camel_case($tab['ledgerCard'])]).Html::table('',['id'=> camel_case($tab['ledgerCard'])]),
        camel_case($tab['ledgerCardDetail']) => Html::div('',['id'=>'bar'.camel_case($tab['ledgerCardDetail'])]).Html::table('',['id'=> camel_case($tab['ledgerCardDetail'])]),
        camel_case($tab['detail'])           => Html::div('',['id'=>'bar'.camel_case($tab['detail'])]).Html::table('',['id'=> camel_case($tab['detail'])]),
        camel_case($tab['openItem'])         => Html::div('',['id'=>'bar'.camel_case($tab['openItem'])]).Html::table('',['id'=> camel_case($tab['openItem'])]),
      ], ['tabClass'=>'']
    );
    
    foreach($this->type as $k=>$v){
      if(!isset($perm[$k])){
        unset($this->type[$k]);
      }
    }
    
    return view($page, ['data'=>[
      'reportHeader'=> strtoupper(reset($this->type)), // Get the first element 
      'reportForm'  =>Form::getField(['id'=>'type','label'=>'Type','type'=>'option','option'=>$this->type, 'req'=>1]),
      'tab'         =>$tab,
      'nav'         =>$req['NAV'],
      'account'     =>Account::getHtmlInfo($req['ACCOUNT'])
    ]]);
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  public static function getDefaulValue($req){
    $optionTenant = [];
    $optionBank   = [];
    $vData = $rProp = [];
    if(!empty($req['prop']) && !empty($req['unit']) && isset($req['tenant'])){
      $valid = V::startValidate([
        'rawReq'          => $req->all(),
        'tablez'          => [T::$tntTrans], 
        'orderField'      => ['prop', 'unit', 'tenant', 'dateRange'], 
        'setting'         => self::_getSetting('defaulValue', $req), 
        'includeCdate'    => 0, 
        'validateDatabase'=>[
          'mustExist'=>[
            'tenant|prop,unit,tenant',
          ]
        ]
      ]);
      $vData = $valid['data'];

      $rTenant = M::getDataFromTable(T::$tenantView, Helper::selectData(['prop', 'unit'], $vData), ['source'=>['tenant', 'tnt_name', 'status'], 'sort'=>['status.keyword'=>'ASC', 'tenant'=>'DESC']]);
      $rTenant = Helper::keyFieldNameElastic($rTenant, 'tenant');

      $rBank   = M::getDataFromTable(T::$bankView, Helper::selectData(['prop'], $vData), ['source'=>['bank', 'cr_acct', 'name']]);
      $rBank   = Helper::keyFieldNameElastic($rBank, 'bank');
      
      $rProp   = M::getDataFromTable(T::$propView, Helper::selectData(['prop'], $vData), ['source'=>['ar_bank']]);
      $rProp   = current(Helper::keyFieldNameElastic($rProp, 'ar_bank'));

      foreach($rTenant as $k=>$v){
        $optionTenant[$k] = $k . ' (' . $v['status'] . ') ' .' - ' . $v['tnt_name'];
      }
      foreach($rBank as $k=>$v){
        $optionBank[$k] =  Format::getBankDisplayFormat($v);
      }
    }
    return [
      'option'     =>['tenant'=>$optionTenant, 'bank'=>$optionBank],
      'value'      =>$vData,
      'defaultBank'=>Helper::getValue('ar_bank', $rProp)
    ];
  }
//------------------------------------------------------------------------------
  public static function getCreateContent($fields, $tab){
    return [
      'html'=>implode('',Form::generateField($fields)), 
      'column'=>[
        camel_case($tab['ledgerCard']) => self::_getColumnButtonReportList(camel_case($tab['ledgerCard']), $tab),
        camel_case($tab['ledgerCardDetail']) => self::_getColumnButtonReportList(camel_case($tab['ledgerCardDetail']), $tab),
        camel_case($tab['detail'])     => self::_getColumnButtonReportList(camel_case($tab['detail']), $tab),
        camel_case($tab['openItem'])   => self::_getColumnButtonReportList(camel_case($tab['openItem']), $tab),
      ],
      'gridTableId'=>[
        camel_case($tab['ledgerCard']) =>'#'.camel_case($tab['ledgerCard']),
        camel_case($tab['ledgerCardDetail']) =>'#'.camel_case($tab['ledgerCardDetail']),
        camel_case($tab['detail'])     =>'#'.camel_case($tab['detail']),
        camel_case($tab['openItem'])   =>'#'.camel_case($tab['openItem']),
      ]
    ];
  }
//------------------------------------------------------------------------------
  private static function _getColumnButtonReportList($type, $tab){
    $data = [];
    $reportList = [
      'tenantStatement'=>Html::icon('fa fa-fw fa-address-card-o').'Print Tenant Statement',
      'ledgerCard'=>Html::icon('fa fa-fw fa-file-text-o').'Print Ledger Card',
      'ledgerCardDetail'=>Html::icon('fa fa-fw fa-file-text-o').'Print Ledger Card Detail',
      'csv'=>Html::icon('fa fa-fw fa-share-square-o').'Export Transaction Detail'
    ];
    $data[] = ['field'=>'date1', 'title'=>'Date','filterControl'=> 'input', 'width'=> 100];
    if($type == camel_case($tab['detail'])){ // FOR DETAIL TRANSACTION
      $data[] = ['field'=>'date2', 'title'=>'Org. Date','filterControl'=> 'input', 'width'=> 100];
    }
    $data[] = ['field'=>'tx_code', 'title'=>'Desc','filterControl'=> 'input', 'width'=> 120];
    $data[] = ['field'=>'batch', 'title'=>'Batch','filterControl'=> 'input', 'width'=> 50];
    if($type == camel_case($tab['detail'])){ // FOR DETAIL TRANSACTION
      $data[] = ['field'=>'job', 'title'=>'job','filterControl'=> 'input', 'width'=> 50];
    }
    $data[] = ['field'=>'check_no', 'title'=>'Check No','filterControl'=> 'input', 'width'=> 50];
//    $data[] = ['field'=>'invoice', 'title'=>'invoice','filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'appyto', 'title'=>'Invoice', 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'gl_acct', 'title'=>'Gl', 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'service_code', 'title'=>'Service', 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'remark', 'title'=>'Remark','filterControl'=> 'input', 'width'=> 300];
    
    if($type == camel_case($tab['detail'])){ // FOR DETAIL TRANSACTION
      $data[] = ['field'=>'amount', 'title'=>'Amount','filterControl'=> 'input', 'width'=> 50];
      $data[] = ['field'=>'balance', 'title'=>'Balance','filterControl'=> 'input', 'width'=> 50];
      $data[] = ['field'=>'journal', 'title'=>'Journal','filterControl'=> 'input', 'width'=> 75];
      $data[] = ['field'=>'cntl_no', 'title'=>'Cntl No','filterControl'=> 'input', 'width'=> 75];
      $data[] = ['field'=>'sys_date', 'title'=>'Sys Date','filterControl'=> 'input'];
    } else if($type == camel_case($tab['ledgerCard'])){ // FOR LEDGER CARD
      $data[] = ['field'=>'inAmount', 'title'=>'Charge','filterControl'=> 'input', 'editableEmptytext'=>'', 'editable'=> ['type'=> 'text', 'isEditableField'=>'isEditable'], 'width'=> 90];
      $data[] = ['field'=>'pAmount', 'title'=>'Payment','filterControl'=> 'input', 'editableEmptytext'=>'',  'editable'=> ['type'=> 'text', 'isEditableField'=>'isEditable'], 'width'=> 90];
      $data[] = ['field'=>'balance', 'title'=>'Balance','filterControl'=> 'input'];
    } else if($type == camel_case($tab['ledgerCardDetail'])){ // FOR LEDGER CARD
      $data[] = ['field'=>'inAmount', 'title'=>'Charge','filterControl'=> 'input', 'width'=> 90];
      $data[] = ['field'=>'pAmount', 'title'=>'Payment','filterControl'=> 'input', 'width'=> 90];
      $data[] = ['field'=>'balance', 'title'=>'Balance','filterControl'=> 'input'];
    } else{ // FOR OPEN ITEM
      $data[] = ['field'=>'inAmount', 'title'=>'Charge','filterControl'=> 'input', 'width'=> 90];
      $data[] = ['field'=>'pAmount', 'title'=>'Payment','filterControl'=> 'input', 'width'=> 90];
      $data[] = ['field'=>'balance', 'title'=>'Balance','filterControl'=> 'input'];
    }    
    
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>['test']];
  }
//------------------------------------------------------------------------------
  private static function _getSetting($fn, $req){
    $data = [
      'defaulValue'=>[
        'field'=>[
        ],
        'rule'=>[
          'dateRange'=>'nullable|string|between:21,23',
        ]
      ],
    ];
    return $data[$fn];
  }
//------------------------------------------------------------------------------
  public static function getUploadForm() {
    $html = Upload::getHtml();
    $ul = Html::ul('', ['class'=>'nav nav-pills nav-stacked', 'id'=>'uploadList']);
    $uploadForm = 
      Html::div(Html::div($html['container'], ['class'=>'col-md-12']), ['class'=>'row fileUpload']) .
      Html::div(Html::div($ul, ['class'=>'col-md-3']) . Html::div('', ['class'=>'col-md-9', 'id'=>'uploadView']),['class'=>'row']) . 
      $html['hiddenForm'];
    return $uploadForm;
  }
//------------------------------------------------------------------------------
  public static function getFileUploadContent($req, $extensionAllow = ['txt']){
    // VALIDATE THE FILE FIRST
    $valid = V::startValidate([
      'rawReq'    => ['qqfilename'=>Upload::getName()] + $req->all(),
      'rule'      => Upload::getRule(),
      'orderField'=> Upload::getOrderField()
    ]);
    $file = Upload::startUpload($valid, $extensionAllow);
    $textList = explode("\r\n", Storage::get('tmp/' . $file['data']['data']['qquuid'] . '/' . $file['data']['data']['file']));
    
    return ['data'=>$textList, 'fileInfo'=>$file];
  }
//------------------------------------------------------------------------------
  public static function getLedgerCardGroupUID($v){
    return ($v['amount'] > 0) ? $v['date1'] . $v['tx_code'] . $v['batch']: $v['date1'] . $v['tx_code'] . $v['batch'] . $v['check_no'];
  }
//------------------------------------------------------------------------------
  public static function getLedgerCardDetailUID($v){
    return $v['date1'] . $v['tx_code'] . $v['batch'] . $v['check_no'] . $v['appyto'] . $v['service_code']; 
  }
//------------------------------------------------------------------------------
  public static function getZeroAppytoUpdateData($vData, $cntlNo){
    $rZeroAppyto = M::getZeroAppyto(Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $vData)), $cntlNo, ['cntl_no']);
    if(!empty($rZeroAppyto)){
      return [T::$tntTrans=>[ 
        'whereData'=>['cntl_no'=>$rZeroAppyto['cntl_no']], 
        'updateData' =>['appyto'=>DB::raw('cntl_no'), 'invoice'=>DB::raw('cntl_no')],
      ]];
    }
    return [];
  }
//------------------------------------------------------------------------------
  public static function renameRPSFileToDone($file){
    if(Storage::disk('RPS')->has(preg_replace('/\.TXT/i', '.done', $file))){
      Storage::disk('RPS')->delete(preg_replace('/\.TXT/i', '.done', $file));
    }
    $doneFile    = preg_replace('/\.TXT/i', '.TXT', $file);
    Storage::disk('RPS')->copy($doneFile, preg_replace('/\.TXT/i', '.done', $file));
    return $doneFile;
  }
}
