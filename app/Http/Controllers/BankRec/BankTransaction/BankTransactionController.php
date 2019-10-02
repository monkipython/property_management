<?php
namespace App\Http\Controllers\BankRec\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{RuleField, V, Form, Elastic, Mail, File, Html, Helper, Format, GridData, Upload, TenantAlert, Account, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class
use PDF;

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `subController`, `cdate`, `udate`, `active`) VALUES ('0', 'Bank Reconciliation', 'fa fa-fw fa-university', 'bankRec', 'Bank Reconciliation', 'fa fa-fw fa-cog', 'Bank Reconciliation', 'fa fa-fw fa-users', 'bankRec', 'index', 'To Access Bank Reconciliation', 'clearedTrans,bankTrans', '2019-07-03 22:35:23', '2019-07-04 01:58:27', '1');
 */
class BankTransactionController extends Controller {
  private $_viewPath     = 'app/BankRec/bankRec/';
  private $_viewTable    = '';
  private $_indexMain    = '';
  private $_mapping      = [];
  private static $_instance;
//------------------------------------------------------------------------------
  public function __construct(){
    $this->_viewTable    = T::$bankTransView;
    $this->_indexMain    = T::$bankTransView . '/' . T::$bankTransView . '/_search?';
  }
//------------------------------------------------------------------------------
  public static function getInstance(){
    if(is_null( self::$_instance ) ){
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
        $qData = GridData::getQuery($vData, $this->_viewTable);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData, $req);
    return [];
//      case 'modalTable':
//        return $this->_getModalTable($req);
//      default:
//        return view($page, ['data'=>[
//          'nav'=>$req['NAV'],
//          'account'=>Account::getHtmlInfo($req['ACCOUNT']), 
//          'initData'=>$initData
//        ]]);  
    } 
  }
//------------------------------------------------------------------------------
  public function edit($id,Request $req){
      
  }
//------------------------------------------------------------------------------
  public function update($id,Request $req){
      
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
      
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
      
  }
//------------------------------------------------------------------------------
  public function destroy($id,Request $req){
      
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getTable($fn){
    $tablez = [
      'index' => [T::$bankTrans],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $perm   = Helper::getPermission($req);
    $button = [
      'index'          => ['submit'=>['id'=>'submit','value'=>'Search Bank Reconciliation','class'=>'col-sm-12']],
    ];
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm         = Helper::getPermission($req);
    $orderField   = [
      'index'     => ['unreconcilable_record'],
    ];
//    
//    $orderField['store']    = $orderField['create'];
//    $orderField['update']   = $orderField['edit'];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $perm         = Helper::getPermission($req);
    $disabled     = [];
    $setting      = [
      'index'     => [
        'field'   => [
         
        ],
        'rule'    => [
          'unreconcilable_record'  => 'nullable|integer',
        ]
      ]
    ];
   
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
    }
    //$setting['store']    = $setting['create'];
    //$setting['update']   = $setting['edit'];
    return $setting[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r,$vData,$qData,$req){
    $perm    = Helper::getPermission($req);
    $result  = Helper::getElasticResult($r,0,1);
    
    $data    = Helper::getValue('data',$result,[]);
    $total   = Helper::getValue('total',$result,0);
    
    foreach($data as $i => $v){
      $source = $v['_source'];
      
      $source['num']      = $vData['offset'] + $i + 1;
      
      $balance              = Helper::getValue('amount',$source,0);
      
      $source['amount']     = Format::usMoney($balance);
      $source['remark']     = title_case(Helper::getValue('remark',$source));
      
      $rows[]               = $source;
    }
    
    return ['rows'=>$rows,'total'=>$total];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm       = Helper::getPermission($req);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = [];
    
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button  = '';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-money']) . ' Total Sum: ' . Html::span('0',['class'=>'transSum']),['class'=>'btn btn-success']) .  ' ';
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $editableText  = ['editable'=>['type'=>'text']]; 
    
    $data   = [];
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25];
    
    $data[] = ['field'=>'date','title'=>'Date','sortable'=>true,'filterControl'=>'input','width'=>120];
    $data[] = ['field'=>'check_no','title'=>'Check #','sortable'=>true,'filterControl'=>'input','width'=>100];
    //$data[] = ['field'=>'batch','title'=>'Batch','sortable'=>true,'filterControl'=>'input','width'=>110];
    //$data[] = ['field'=>'bank','title'=>'Bank #','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'amount','title'=>'Amount','sortable'=>true,'filterControl'=>'input','width'=>60];
    $data[] = ['field'=>'remark','title'=>'Description','sortable'=>true,'filterControl'=>'input','width'=>250];
    $data[] = ['field'=>'journal','title'=>'Jnl','sortable'=>true,'filterControl'=>'input'];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>$_getButtons($perm)];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################ 
  private function _getModalTable($req){
    $page = $this->_viewPath . 'modalTemplate';
    return view($page,['data'=>[]]);
  }
}
