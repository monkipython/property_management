<?php
namespace App\Http\Controllers\AccountPayable\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, Account, TableName AS T};
use App\Http\Models\{Model, ApprovalModel AS M};
use \App\Http\Controllers\AccountPayable\AccountPayableController AS P;

class ApprovalController extends Controller {
  private $_viewPath  = 'app/AccountPayable/Approval/approval/';
  private $_viewTable = '';
  private $_indexMain = '';
  private static $_instance;
  public $approvalStatus = [
    'pending'=> 'Pending Submission',
    'waiting'=> 'Waiting For Approval',
    'approved'=>'Approved',
    'rejected'=>'Rejected'
  ];
  public function __construct(){
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$vendorPayment]);
    $this->_viewTable = T::$vendorPaymentView;
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
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . 'index';
    $initData = $this->_getColumnButtonReportList($req);
    switch ($op){
      case 'column':
        return $initData;
      case 'show':
        $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
        $defaultFilter = !empty($vData['defaultFilter']) ? json_decode($vData['defaultFilter'], true) : [];
        $vData['defaultFilter'] = $defaultFilter + ['print'=>0, 'amount'=>'>0'];
        $vData['defaultSort']   = ['vendor_payment_id:desc'];
        
        if($this->isAuthorizedApprovalCheckUser($req)){
          $vData['defaultFilter']['approve'] = $this->approvalStatus['waiting'];
        } else{
          $vData['defaultFilter']['usid'] = Helper::getUsid($req);
        }
        $qData = GridData::getQuery($vData, $this->_viewTable);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData, $req); 
      default:
        return view($page, ['data'=>[
          'nav'=>$req['NAV'],
          'account'=>Account::getHtmlInfo($req['ACCOUNT']), 
          'initData'=>$initData
        ]]);  
    }
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $text = Html::h4(Html::errMsg('Are you sure you want to delete these check(s)?'), ['class'=>'text-center']) . Html::br();
    return ['html'=>$text];
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['vendor_payment_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate' => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorPayment . '|vendor_payment_id',
        ]
      ]
    ]);
    $vData                = $valid['dataNonArr'];
    $msgKey               = count($vData) > 3 ? 'msg' : 'mainMsg';
    $r                    = Helper::getValue('_source',Helper::getElasticResult(M::getVendorApprovalElastic(['vendor_payment_id'=>$id],['vendor_pending_check_id','prop','gl_acct','vendid']),1),[]);
    $valid['data']       += empty($vData['prop']) ? ['prop'=>Helper::getValue('prop',$r)] : []; 
    $valid['data']       += empty($vData['vendid']) ? ['vendid'=>Helper::getValue('vendid',$r)] : [];
    $valid['data']       += empty($vData['gl_acct']) ? ['gl_acct' =>Helper::getValue('gl_acct',$r)] : [];
    
    V::validateionDatabase(['mustExist'=>[T::$vendor . '|vendid',T::$glChart . '|prop,gl_acct']],$valid);
    $vData['usid']        = Helper::getUsid($req);
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData           = [
      T::$vendorPayment   => [
        'whereData'       => ['vendor_payment_id'=>$id],
        'updateData'      => $vData,
      ]
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic  = [
        'insert'=>[$this->_viewTable=>['vp.vendor_payment_id'=>[$id]]]
      ];
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
      $response[$msgKey]            = $this->_getSuccessMsg(__FUNCTION__);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req) {
    $req->merge(['vendor_payment_id'=>$req['id']]);
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorPayment . '|vendor_payment_id',
        ]
      ]
    ]);
    $vData     = $valid['data'];
    $deleteIds = $vData['vendor_payment_id'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      foreach($deleteIds as $id) {
        $success[T::$vendorPayment][] = M::deleteTableData(T::$vendorPayment,Model::buildWhere(['vendor_payment_id'=>$id]));
      }
      $commit['success']           = $success;
      $commit['elastic']['delete'] = [$this->_viewTable => ['vendor_payment_id'=>$deleteIds]];
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit($commit);
    } catch (\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$vendorPayment, T::$fileUpload],
      'store'  => [T::$vendorPayment, T::$fileUpload],
      'edit'   => [T::$vendorPayment, T::$prop, T::$fileUpload],
      'update' => [T::$vendorPayment, T::$prop, T::$fileUpload],
      'destroy'=> [T::$vendorPayment],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req = [], $default = []){
    $perm     = Helper::getPermission($req);
    $disabled = [];
    $rBank    = !empty($default['prop']) ? Helper::keyFieldName(M::getBank(Model::buildWhere(['p.prop'=>$default['prop']]), ['b.bank', DB::raw('CONCAT(b.bank, " - ", b.name) AS name')], 0), 'bank', 'name') : [''=>'Select Bank'];
    $setting = [
      'create'  => [
        'field' => [
          'uuid'      => ['type'=>'hidden'],
          'vendid'    => ['label'=>'Vendor Id', 'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'prop'      => ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'invoice_date' => ['req'=>1,'value'=>date('m/d/Y')],
          'amount'    => ['req'=>1],
          'gl_acct'   => ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'bank'      => ['type'=>'option', 'option'=>[]],
        ]
      ],
      'edit'    => [
        'field' => [
          'vendor_pending_check_id' => $disabled + ['type' =>'hidden'],
          'vendid'                  => $disabled + ['label'=>'Vendor Id', 'hint'=>'You can type prop address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'invoice'                 => $disabled + ['req'=>1],
          'invoice_date'            => $disabled + ['req'=>1], 
          'amount'                  => $disabled + ['req'=>1], 
          'prop'                    => $disabled + ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'bank'                    => $disabled + ['label'=>'Bank','type'=>'option','option'=>$rBank,'req'=>1],
          'gl_acct'                 => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'remark'                  => $disabled + ['req'=>1], 
          'usid'                    => $disabled + ['label'=>'Last Updated By', 'readonly'=>1],
        ]
      ]
    ];

    $setting['update']   = $setting['edit'];
    $setting['store']    = $setting['create'];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['invoice_date']['value'] = Format::usDate($default['invoice_date']);
      }
    }
    return $setting[$fn];  
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm       = Helper::getPermission($req);
    $rows       = [];
    $actionData = $this->_getActionIcon($perm);
    $result     = Helper::getElasticResult($r,0,1);
    $data       = Helper::getValue('data',$result,[]);
    $total      = Helper::getValue('total',$result,0);
 
    $invoiceNum     = array_column(array_column($data, '_source'),'invoice');
    $averageAmounts = P::getAverageAmount($invoiceNum, 1);
    
    foreach($data as $i => $v){
      $source = $v['_source'];
      $key = $source['vendid'] . $source['invoice'] . $source['type'] . $source['gl_acct'];
      $iconList               = array_merge(['trust','prop'],!empty($source['unit']) && $source['unit'] !== '0000' ? ['unit'] : []);
      $source['num']          = $vData['offset'] + $i + 1;
      $source['isCheckable']  = $this->_isEnableCheckBox($source, $req);
      $source['action']       = implode(' | ', $actionData['icon']);
      $source['linkIcon']     = Html::getLinkIcon($source,$iconList);
      $source['amount']       = Format::usMoney($source['amount']);
      $source['invoice_date'] = $source['invoice_date'];
      $source['send_approval_date'] = $source['send_approval_date'] != '1969-12-31' ? Format::usDate($source['send_approval_date']) : '';
      $source['avg_amount']   = isset($averageAmounts[$key]) ? $averageAmounts[$key] : Format::usMoney(0);
      $source['invoiceFile']  = !empty($source['fileUpload']) ? Html::span('View', ['class'=>'clickable']) : '';
      $source['bankList']     = !empty($source['bank_id']) ? Helper::keyFieldName($source['bank_id'],'bank','name') : [];
      $source['prop_class']   = Helper::getValue(Helper::getValue('prop_class',$source),$this->_mapping['prop_class']);
      $defaultBank            = Helper::getValue('bank',$source);
      $prefix                 = !empty($defaultBank)  ? '(' . $defaultBank . ') ' : $defaultBank;
      $source['bank']         = isset($perm['pendingCheckupdate']) ? $defaultBank : $prefix . Helper::getValue($defaultBank,$source['bankList']);
      $rows[] = $source;
    }
    
    $approveSum = $this->_getApprovalSum($vData,$req);
    return ['rows'=>$rows, 'total'=>$total,'approveSum'=>$approveSum];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $actionData = $this->_getActionIcon($perm);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = [];
    ### BUTTON SECTION ###
    $_getButtons = function($perm, $req){
      $button = '';
      if($this->isAuthorizedApprovalCheckUser($req)){
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-check']) . ' Approve',['id'=>'approved','class'=>'btn btn-success tip approvedRejected',  'title'=>'Approval The Requests For Approval From Employee']) . ' ';
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-close']) . ' Reject',['id'=>'rejected','class'=>'btn btn-warning tip approvedRejected',  'title'=>'Reject The Requests For Approval From Employee']) . ' ';
      } else{
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-send-o']) . ' Request For Approval',['id'=>'request','class'=>'btn btn-success tip', 'title'=>'Send Check For Approval From Upper Managements']) . ' ';
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-print']) . ' Print Check', ['id'=> 'print', 'class'=>'btn btn-info tip', 'title'=>'Print Cashier Check Or Check Grouping By Each Transaction, Vendor, Property, Or Trust']) . ' ';  
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-print']) . ' Print Cashier Check', ['id'=> 'printCashierCheck', 'class'=>'btn btn-info tip', 'title'=>'Print Cashier Check Or Check Grouping By Each Transaction, Vendor, Property, Or Trust']) . ' ';  
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-edit']) . ' Record', ['id'=> 'record', 'class'=>'btn btn-info tip','title'=>'Booking/Posting Transactions Only']) . ' ';  
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-trash']) . ' Delete',['id'=>'destroy','class'=>'btn btn-danger tip', 'title'=>'Delete Transaction']) . ' ';
      }
      //$button .= $this->getApprovalRequestDropdown($req);
      $button   .= $this->_getInitialRequestDropdownButton();
      $button   .= ' ' . Html::span(Html::icon('fa fa-fw fa-dollar') . Html::b(' Grand Total: ') . Html::b('0',['id'=>'approveSum','class'=>'text-green']),['class'=>'callout-info']) . ' ';
      return $button;
    };
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data  = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      $data += $field !== 'prop_class' ? ['editable' => ['type'=>'select','source'=>$source]] : [];
      return $data;
    };
    $editable             = ['editable'=> ['type'=> 'text']];
    $selectEditable       = ['editable'=> ['type'=> 'select']];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25, 'isCheckableField'=>'isCheckable'];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>85];
    $data[] = ['field'=>'bank','value'=>'bank','title'=>'Bank','sortable'=>true,'filterControl'=>'input','width'=>250] + $selectEditable;
    $data[] = ['field'=>'approve','title'=>'Approval Status', 'sortable'=>true,'filterControl'=>'input','width'=>150];
    $data[] = ['field'=>'invoice','title'=>'Invoice #','sortable'=>true,'filterControl'=>'input','width'=>90];
    $data[] = ['field'=>'invoice_date', 'title'=>'Inv Date','sortable'=> true, 'filterControl'=> 'input', 'width'=>75];
//    $data[] = ['field'=>'send_approval_date', 'title'=>'Approval Date','sortable'=> true, 'filterControl'=> 'input', 'width'=>75];
    $data[] = ['field'=>'remark', 'title'=>'Remark','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250];
    $data[] = ['field'=>'vendid', 'title'=>'Vendor','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'amount', 'title'=>'Amount','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'avg_amount','title'=>'Avg Amount','width'=>50];
    $data[] = $_getSelectColumn($perm,'high_bill','H Bill',25,$this->_mapping['high_bill']);
    $data[] = ['field'=>'name', 'title'=>'Vendor Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250];
    $data[] = ['field'=>'trust', 'title'=>'Trust', 'sortable'=>true, 'width'=>40,'filterControl'=>'input'];
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = $_getSelectColumn($perm,'prop_class','Prop Class',25,$this->_mapping['prop_class']);
    $data[] = ['field'=>'unit', 'title'=>'Unit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'number_of_units', 'title'=>'Unit #','sortable'=> true, 'filterControl'=> 'input', 'width'=> 35];
    $data[] = ['field'=>'gl_acct', 'title'=>'Gl','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'type','title'=>'Type', 'sortable'=>true,'filterControl'=>'input','width'=>100];
    $data[] = ['field'=>'invoiceFile', 'title'=>'File','width'=> 75];
    $data[] = ['field'=>'usid', 'title'=>'Usid', 'sortable'=>true, 'filterControl'=>'input'];
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm, $req)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted Approval'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    //$actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Approval Information"></i></a>';
    
    $num = count($actionData);       
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
//------------------------------------------------------------------------------
  private function _isEnableCheckBox($source, $req){
    if($this->isAuthorizedApprovalCheckUser($req)) { 
      return ($source['approve'] == $this->approvalStatus['pending']) ? false : true;
    } else {
      return ($source['approve'] == $this->approvalStatus['waiting']) ? false : true;
    }
  }
//------------------------------------------------------------------------------
  private function _getInitialRequestDropdownButton(){
    $count = 0;
    $count     = Html::span($count, ['class'=>'badge bg-yellow']);
    $caret     = Html::span('',['class'=>'caret']);
    $btn       = Html::button($count . ' Approval Request ' . $caret, ['type'=>'button','class'=>'btn btn-info dropdown-toggle tip','title'=>'View All The Requests For Approval','data-toggle'=>'dropdown','aria-haspopup'=>'true','aria-expanded'=>'false']);
    $ul        = Html::ul('', ['class'=>'dropdown-menu']);
    $container = Html::div($btn . $ul, ['class'=>'btn-group','id'=>'requestDropdownList']);
    return $container;
  }
//------------------------------------------------------------------------------
  private function _getApprovalSum($vData,$req){
    $mustParams     = [
      'print'=>0,
      'range'=>[
        'amount' => [
          'gt' => 0,
        ]
      ]
    ];
    $defaultFilter  = Helper::getValue('defaultFilter',$vData,[]);
    
    $mustParams += !empty($defaultFilter['usid']) ? ['usid.keyword'=>$defaultFilter['usid']] : [];
    $mustParams += !empty($defaultFilter['approve']) ? ['approve.keyword' => $defaultFilter['approve']] : [];
    $rAgg        = Elastic::searchQuery([
      'index'    => T::$vendorPaymentView,
      'size'     => 0,
      'query'    => [
        'must'   => $mustParams
      ],
      'aggs'     => [
        'approve_sum' => [
          'sum'       => [
            'field'   => 'amount',
          ]
        ]
      ]
    ]);
    
    $approveSum  = !empty($rAgg['aggregations']['approve_sum']['value']) ? Format::roundDownToNearestHundredthDecimal($rAgg['aggregations']['approve_sum']['value']) : 0;
    return $approveSum;
  }
//------------------------------------------------------------------------------
  public function isAuthorizedApprovalCheckUser($req){
    $usidApprovalCheck = ['mike@pamamgt.com', 'sean@pamamgt.com'];
//    $usidApprovalCheck = ['heagnang@gmail.com'];
    $usid = Helper::getUsid($req);
    return in_array($usid, $usidApprovalCheck) ? 1 : 0; // RIGHT ONE
  }
//------------------------------------------------------------------------------
  public function getBatchGroupLink($batchGroup){
    return env('APP_URL') . '/approval?batch_group=' . $batchGroup;
  }
//------------------------------------------------------------------------------
  public function getApprovedResult($valid){
    $usid  = $valid['data']['usid'];
    $query = [ 'belongTo.keyword'=>$usid, 'active'=>1, 'print'=>0, 'void'=>0, 'approve.keyword'=>$this->approvalStatus['approved'], 'range'=>['amount'=>['gt'=>0]]];
    $query = !empty($valid['dataArr']) ? $query + ['vendor_payment_id'=>array_column($valid['dataArr'],'vendor_payment_id')] : $query;
    $r     = M::getVendorApprovalElastic($query, ['is_with_signature', 'invoice_date', 'vendor_payment_id', 'noLimit', 'batch_group', 'amount', 'vendor', 'bank', 'prop', 'vendor_id', 'invoice', 'remark', 'gl_acct', 'vendid', 'trust', 'approve','unit','tenant']);
    $r     = Helper::getElasticResult($r);
    
    if(empty($r)){
      Helper::echoJsonError(Html::errMsg('There is no approved check. Please double check it again.'), 'popupMsg');
    }
    
    return array_column($r, '_source');
  }
//------------------------------------------------------------------------------
  public function getGlTransData($v, $vData, $batch, $glChart){
    $v['amount']  = isset($v['amount']) ? $v['amount'] : $v['balance'];
    $v['unit']    = Helper::getValue('unit',$v);
    $v['tenant']  = Helper::getValue('tenant',$v,255);
    $v['date1']   = isset($vData['date1']) ? $vData['date1'] : $vData['posted_date'];
    $v['tx_code'] = $v['journal'] = 'CP';
    $v['batch']   = $batch;
    $v['vendor']  = $v['vendid'];
    $v['inv_remark']   = $v['remarks'] = $v['remark'];
    $v['service_code'] = isset($glChart[$v['gl_acct']]) ? $glChart[$v['gl_acct']]['service'] : '';
    return $v;
  }
//------------------------------------------------------------------------------
  public function validatePropVendorTrustBank($r){
    
  }
}
