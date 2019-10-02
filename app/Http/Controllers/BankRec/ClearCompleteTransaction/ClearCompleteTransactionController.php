<?php
namespace App\Http\Controllers\BankRec\ClearCompleteTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController as P;
use App\Library\{RuleField, V, Form, Elastic, Html, Helper, Format, GridData, Account, TableName AS T};
use App\Http\Models\{Model, BankRecModel AS M}; // Include the models class

class ClearCompleteTransactionController extends Controller {
  private $_viewPath     = 'app/BankRec/clearCompleteTransaction/';
  private $_viewTable    = '';
  private $_indexMain    = '';
  private $_mapping      = [];
  private static $_instance;
//------------------------------------------------------------------------------
  public function __construct(){
    $this->_viewTable    = T::$clearedTransView;
    $this->_indexMain    = T::$clearedTransView . '/' . T::$clearedTransView . '/_search?';
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
    $op     = isset($req['op']) ? $req['op'] : 'index';
    $page   = $this->_viewPath . 'index';
    $perm   = Helper::getPermission($req);
    $totalField = [
      'total' => ['id'=>'total','label'=>'Selected Total','readonly'=>1,'type'=>'text','class'=>'decimal','value'=>'0','req'=>0],
    ];
    
    $initData = $this->_getColumnButtonReportList($req);
    switch($op){
      case 'column':
        return $initData;
      case 'show':
        return $this->getData($req);
      default:
        return view($page, ['data'=>[
          'reportHeader' => 'Clear Transactions', 
          'form'         => $this->_getFields($req),
          'nav'          => $req['NAV'],
          'account'      => Account::getHtmlInfo($req['ACCOUNT']),
          'dropdownData' => '',
          'unclearButton'=> Html::button('Unclear',['id'=>'unclear','class'=>'btn btn-info pull-right btn-sm col-md-12 margin-bottom']),
          'totalDisplay' => Form::getField($totalField['total']),
        ]]);
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
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      //'orderField'   => $this->_getOrderField(__FUNCTION__,$req),
      'setting'      => $this->_getSetting(__FUNCTION__,$req),
      'includeCdate' => 0,
    ]);
    
    $vData = $valid['data'];
    
    $clearTransIds = Helper::getValue('cleared_trans_id',$vData,[]);
    $bankIds       = Helper::getValue('bank_trans_id',$vData,[]);
    
    
    $usid  = Helper::getUsid($req);
    
    $updateData    = [];
    
    if(!empty($clearTransIds)){
      $updateData[T::$clearedTrans] = [
        'whereInData'  => ['field'=>'cleared_trans_id','data'=>$clearTransIds],
        'updateData'   => ['match_id' => 0,'usid'=>$usid],
      ];
    }
    
    if(!empty($bankIds)){
      $updateData[T::$bankTrans]    = [
        'whereInData'  => ['field'=>'bank_trans_id','data'=>$bankIds],
        'updateData'   => ['match_id'=>0,'usid'=>$usid],
      ];
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      if(!empty($bankIds) || !empty($clearTransIds)){
        $success += Model::update($updateData);
        $elastic  = ['insert'=>[]];
        
        $elastic['insert'] += !empty($bankIds) ? [
          T::$bankTransView => ['bt.bank_trans_id'=>$bankIds],
        ] : [];
        
        $elastic['insert'] += !empty($clearTransIds) ? [
          T::$clearedTransView => ['c.cleared_trans_id' => $clearTransIds],
        ] : [];
        
        Model::commit([
          'success' => $success,
          'elastic' => $elastic,
        ]);
        
        $response['mainMsg']  = $this->_getSuccessMsg(__FUNCTION__,$vData);
      }
    } catch (\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
    
  }
//------------------------------------------------------------------------------
  public function destroy($id,Request $req){
      
  }
//------------------------------------------------------------------------------
  public function getData($req){
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'rule'         => $this->_getRule(),
      'includeCdate' => 0,
    ]);
    
    $vData   = $valid['data'];
    $vData  += Helper::splitDateRate($vData['dateRange'],'cleared_date');
    
    unset($vData['dateRange']);
    
    $queryParam = [
      '_source'  => ['cleared_trans_id','bank_trans_id','bank','bank_id','prop','trust','match_id','date','date1','check_no','batch','amount','usid','remark'],
      'sort'     => ['match_id'=>'asc'],
    ];
    
    $queryBody  = [
      'must'  => [
        [
          'term' => [
            'trust.keyword'  => $vData['trust'],
           ]
        ],
        [
          'term' => [
            'bank.keyword' => $vData['bank'],
          ]
        ],
        [
          'range'  => [
            'cleared_date' => [
              'gte' => $vData['cleared_date'],
              'lte' => $vData['tocleared_date'],
            ]
          ]
        ],
        [
          'range'  => [
            'match_id' => [
              'gte'  => $vData['match_id'],
              'lte'  => $vData['tomatch_id'],
            ]
          ]
        ]
      ]
    ];
    
    $rClearedTrans   = M::getTableDataElastic(T::$clearedTransView,$queryBody,$queryParam,0,1);
    $rBankTrans      = M::getTableDataElastic(T::$bankTransView,$queryBody,$queryParam,0,1);

    $clearedTransRows = $this->_getGridData($rClearedTrans,$vData,[],$req,0);
    $bankTransRow     = $this->_getGridData($rBankTrans,$vData,[],$req,2);
    $allRows          = array_merge($clearedTransRows,$bankTransRow);
    return $this->_mergeRows($allRows);
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $table = [
      'store' => [T::$clearedTrans,T::$bankTrans],
    ];
    return $table[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm = Helper::getPermission($req);
    $orderField = [
      'store' => ['bank_trans_id','cleared_trans_id'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $perm = Helper::getPermission($req);
    $setting = [
      'store'  => [
        'field' => [],
        'rule'  => [
          'bank_trans_id'     => 'nullable',
          'cleared_trans_id'  => 'nullable',
        ]
      ]
    ];
    
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getFields($req=[]){
    $fields = [
      'trust'            => ['id'=>'trust','label'=>'Trust','type'=>'text','class'=>'autocomplete','req'=>1],
      //'bank'             => ['id'=>'bank','label'=>'Bank','type'=>'text','req'=>1],
      'bank'             => ['id'=>'bank','label'=>'Bank','type'=>'option','option'=>[''=>'Select Bank']],
      'dateRange'        => ['id'=>'dateRange','label'=>'From/To Clean Date','type'=>'text','class'=>'daterange','req'=>1],
      'match_id'         => ['id'=>'match_id','label'=>'From Match Id','type'=>'text','value'=>'1','req'=>1],
      'tomatch_id'       => ['id'=>'tomatch_id','label'=>'To Match Id','type'=>'text','value'=>'999999999','req'=>1],
    ];
    $html = implode('',Form::generateField($fields));
    return $html;
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'trust'          => 'required|string',
      'bank'           => 'required|integer',
      'dateRange'      => 'required|string|between:21,23',
      'match_id'       => 'required|integer',
      'tomatch_id'     => 'required|integer',
      'total'          => 'nullable',
    ] + GridData::getRule();
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($fn,$data=[]){
    $data = [
      'store'  => Html::sucMsg('Successfully cleared ' . (count(Helper::getValue('cleared_trans_id',$data,[])) + count(Helper::getValue('bank_trans_id',$data,[]))) . ' transactions'),
    ];
    return $data[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r,$vData,$qData,$req,$transType=0){
    $perm    = Helper::getPermission($req);
    $result  = Helper::getElasticResult($r,0,1);
    
    $data    = Helper::getValue('data',$result,[]);
    $total   = Helper::getValue('total',$result,0);
    $rows    = [];
    foreach($data as $i => $v){
      $source = $v['_source'];

      $source['linkIcon'] = Html::getLinkIcon($source,['trust','prop']);
      
      $balance              = Helper::getValue('amount',$source,0);
      
      $source['date1']      = $transType == 0 ? Helper::getValue('date1',$source) : Helper::getValue('date',$source);
      $source['amount']     = Format::usMoney($balance);
      $source['trans_type'] = $transType;
      
      $source['cleared_trans_id']  = $transType == 0 ? $source['cleared_trans_id'] : 0;
      $source['bank_trans_id']     = $transType == 2 ? $source['bank_trans_id'] : 0;
      $rows[]               = $source;
    }
    return $rows;
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm       = Helper::getPermission($req);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = [];
    
    ### BUTTON SECTION ###
    $button     = '';
    $_getButton = function($perm){
      $button  = '';
      $button .= Html::repeatChar('&nbsp;',3) . Html::button('Unclear',['id'=>'unclear','class'=>'btn btn-success']) . Html::repeatChar('&nbsp;',3) . '|';
      $button .= Html::repeatChar('&nbsp;',3) . Html::span(Html::i('',['class'=>'fa fa-fa-dollar']) . Html::b(' Total: ' . Html::span(Format::usMoney(0),['id'=>'totalSum','class'=>'text-green'])),['id'=>'total']) . ' ';
      return $button;
    };
    ### COLUMNS SECTION ###
    $editableText  = ['editable'=>['type'=>'text']]; 
    
    $data   = [];
    $data[] = ['field'=>'num','title'=>'#','width'=>25];
    $data[] = ['field'=>'checkbox','checkbox'=>true,'width'=>25];
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>70];
    $data[] = ['field'=>'match_id','title'=>'Match Id','width'=>100];
    $data[] = ['field'=>'cleared_date','title'=>'Clean Date','width'=>80];
    $data[] = ['field'=>'date1','title'=>'Trans Date','sortable'=>true,'filterControl'=>'input','width'=>80];
    $data[] = ['field'=>'check_no','title'=>'Check #','sortable'=>true,'filterControl'=>'input','width'=>90];
    $data[] = ['field'=>'batch','title'=>'Batch','sortable'=>true,'filtrControl'=>'input','width'=>90];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'filterControl'=>'input','width'=>75];
    $data[] = ['field'=>'amount','title'=>'Amount','width'=>100];
    $data[] = ['field'=>'usid','title'=>'Usid','sortable'=>true,'filterControl'=>'input','width'=>120];
    $data[] = ['field'=>'remark','title'=>'Remark'];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>$_getButton($perm)];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################ 
  private function _mergeRows($data){
    $matchGroup = Helper::groupBy($data,'match_id');
    $orderedData= !empty($matchGroup) ? call_user_func_array("array_merge",array_values($matchGroup)) : [];
    
    foreach($orderedData as $i => $v){
      $orderedData[$i]['num'] = $i + 1;
    }
    return $orderedData;
  }
}