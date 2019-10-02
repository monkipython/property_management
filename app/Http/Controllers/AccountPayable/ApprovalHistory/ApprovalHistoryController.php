<?php
namespace App\Http\Controllers\AccountPayable\ApprovalHistory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, Elastic, Html, Helper, Format, GridData, Account, TableName AS T};
use \App\Http\Controllers\AccountPayable\AccountPayableController AS P;

/*
UPDATE `ppm`.`accountProgram` SET `subController` = 'uploadApprovalHistory,approvalHistoryExport' WHERE `classController`='approvalHistory';
 */

class ApprovalHistoryController extends Controller {
  private $_viewPath  = 'app/AccountPayable/ApprovalHistory/approvalHistory/';
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping   = [];
  private static $_instance;
  
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
//        $vData                  = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
        $vData                  = $req->all();
        $vData['defaultFilter'] = ['print' => 1];
        $qData                  = GridData::getQuery($vData, $this->_viewTable);
        $r                      = Elastic::gridSearch($this->_indexMain . $qData['query']);
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

  }
//------------------------------------------------------------------------------
  public function store(Request $req){
  }

//------------------------------------------------------------------------------
  public function edit($id, Request $req){
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req) {
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm       = Helper::getPermission($req);
    $rows       = [];
    $result     = Helper::getElasticResult($r,0,1);
    $data       = Helper::getValue('data',$result,[]);
    $total      = Helper::getValue('total',$result,0);
    
    
    $invoiceNum     = array_column(array_column($data, '_source'),'invoice');
    $averageAmounts = P::getAverageAmount($invoiceNum);

    foreach($data as $i => $v){
      $iconList   = ['trust','prop'];
      $source = $v['_source'];
      $key = $source['vendid'] . $source['invoice'] . $source['type'] . $source['gl_acct'];
      $iconList               = array_merge($iconList,!empty($source['unit']) && $source['unit'] !== '0000' ? ['unit'] : [],!empty($source['check_pdf']) ? ['checkCopypdf']: []);
      $source['num']          = $vData['offset'] + $i + 1;
      $source['linkIcon']     = Html::getLinkIcon($source,$iconList);
      $source['amount']       = Format::usMoney($source['amount']);
      $source['invoice_date'] = $source['invoice_date'];
      $source['send_approval_date'] = $source['send_approval_date'] != '1969-12-31' ? Format::usDate($source['send_approval_date']) : '';
      $source['approve']      = Helper::getValue($source['approve'],$this->_mapping['approve']);
      $source['high_bill']    = Helper::getValue($source['high_bill'],$this->_mapping['high_bill']);
      $source['avg_amount']   = isset($averageAmounts[$key]) ? $averageAmounts[$key] : Format::usMoney(0);
      $source['type']         = Helper::getValue(Helper::getValue('type',$source),$this->_mapping['type']);
      $source['invoiceFile']  = !empty($source['fileUpload']) ? Html::span('View', ['class'=>'clickable']) : '';
      $source['bankList']     = !empty($source['bank_id']) ? Helper::keyFieldName($source['bank_id'],'bank','name') : [];
      $source['prop_class']   = Helper::getValue(Helper::getValue('prop_class',$source),$this->_mapping['prop_class']);
      $defaultBank            = Helper::getValue('bank',$source);
      $prefix                 = !empty($defaultBank)  ? '(' . $defaultBank . ') ' : $defaultBank;
      $source['bank']         = $prefix . Helper::getValue($defaultBank,$source['bankList']);
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$total];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = ['csv'=>'Export to CSV','pdf'=>'Export to PDF'];
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data  = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      return $data;
    };
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>85];
    $data[] = ['field'=>'approve_date', 'title'=>'Approval Date','sortable'=> true, 'filterControl'=> 'input', 'width'=>75];
    $data[] = ['field'=>'usid', 'title'=>'Usid','sortable'=> true, 'filterControl'=> 'input','width'=> 125];
    $data[] = ['field'=>'bank','value'=>'bank','title'=>'Bank','sortable'=>true,'filterControl'=>'input','width'=>250];
    $data[] = $_getSelectColumn($perm,'approve','Approve Status',100,$this->_mapping['approve']);
    $data[] = ['field'=>'invoice','title'=>'Invoice #','sortable'=>true,'filterControl'=>'input','width'=>90];
    $data[] = ['field'=>'invoice_date', 'title'=>'Invoice Date','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = ['field'=>'remark', 'title'=>'Remark','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'vendid', 'title'=>'Vendor','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'amount', 'title'=>'Amount','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'avg_amount','title'=>'Avg Amount','width'=>50];
    $data[] = $_getSelectColumn($perm,'high_bill','H Bill',25,$this->_mapping['high_bill']);
    $data[] = ['field'=>'name', 'title'=>'Vendor Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'trust', 'title'=>'Trust', 'sortable'=>true, 'width'=>40,'filterControl'=>'input'];
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = $_getSelectColumn($perm,'prop_class','Prop Class',25,$this->_mapping['prop_class']);
    $data[] = ['field'=>'unit', 'title'=>'Unit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'number_of_units', 'title'=>'Unit #','sortable'=> true, 'filterControl'=> 'input', 'width'=> 35];
    $data[] = ['field'=>'gl_acct', 'title'=>'Gl Account','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = $_getSelectColumn($perm,'type','Type',60,$this->_mapping['type']);
    $data[] = ['field'=>'invoiceFile', 'title'=>'File'];
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>'']; 
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
}
