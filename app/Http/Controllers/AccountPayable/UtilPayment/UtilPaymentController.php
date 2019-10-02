<?php
namespace App\Http\Controllers\AccountPayable\UtilPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Format, GridData, Upload, Account, TableName AS T};
use App\Http\Models\{Model, AccountPayableModel AS M}; // Include the models class

class UtilPaymentController extends Controller{
  private $_viewPath  = 'app/AccountPayable/UtilPayment/utilPayment/';
  private $_viewTable = '';
  private $_indexMain = '';
  private static $_instance;
  
  public function __construct(Request $req){
    $this->_viewTable = T::$vendorUtilPaymentView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
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
    $page = $this->_viewPath . 'create';
    $formUtilPayment = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $req)
    ]);
    return view($page, [
      'data'=>[
        'form'             => $formUtilPayment,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $storeData  = $this->getStoreData([
      'rawReq'        => $req->all(),
      'tablez'        => $this->_getTable(__FUNCTION__),
      'orderField'    => $this->_getOrderField(__FUNCTION__,$req),
      'setting'       => $this->_getSetting(__FUNCTION__,$req),
      'usid'          => Helper::getUsid($req),
    ]);
    
    $insertData = $storeData['insertData'];
    $uuid       = $storeData['uuid'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $updateDataSet = $response = $success = $elastic = [];
    try{
      $success           += Model::insert($insertData);
      $insertedId          = $success['insert:' . T::$vendorUtilPayment][0];     
      $updateDataSet     += !empty($uuid) ? [T::$fileUpload => ['whereInData'=>['field'=>'uuid','data'=>$uuid],'updateData'=>['foreign_id'=>$insertedId]]] : [];
      $success           += !empty($updateDataSet) ? Model::update($updateDataSet) : [];
      $elastic = [
        'insert'=>[$this->_viewTable=>['up.vendor_util_payment_id'=>[$insertedId]]]
      ];
      $response['vendorUtilPaymentId'] = $insertedId;
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page   = $this->_viewPath . 'edit';
    $r      = M::getUtilPaymentElastic(['vendor_util_payment_id'=>$id],[],1);
    $formHtml     = Form::generateForm([
      'tablez'    => $this->_getTable(__FUNCTION__), 
      'button'    => $this->_getButton(__FUNCTION__, $req), 
      'orderField'=> $this->_getOrderField(__FUNCTION__), 
      'setting'   => $this->_getSetting(__FUNCTION__, $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'form'             => $formHtml,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['vendor_util_payment_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate' => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorUtilPayment . '|vendor_util_payment_id',
        ]
      ]
    ]);
    
    $vData                = $valid['dataNonArr'];
    $msgKey               = count($vData) > 3 ? 'msg' : 'mainMsg';
    $r                    = M::getUtilPaymentElastic(['vendor_util_payment_id'=>$id],['vendor_util_payment_id','prop','gl_acct','vendid'],1);
    $verifyKey            = ['prop','vendid','gl_acct'];
    foreach($verifyKey as $v){
      $valid['data']  += empty($vData[$v]) ? [$v => Helper::getValue($v,$r)] : [];
    }
    V::validateionDatabase(['mustExist'=>[T::$vendor . '|vendid',T::$glChart . '|prop,gl_acct']],$valid);
    $vData['usid'] = Helper::getUsid($req);
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$vendorUtilPayment=>['whereData'=>['vendor_util_payment_id'=>$id],'updateData'=>$vData]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['up.vendor_util_payment_id'=>[$id]]]
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
    $req->merge(['vendor_util_payment_id'=>$req['id']]);
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorUtilPayment . '|vendor_util_payment_id',
        ]
      ]
    ]);
    $vData     = $valid['data'];
    $deleteIds = $vData['vendor_util_payment_id'];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      foreach($deleteIds as $id) {
        $success[T::$vendorUtilPayment][] = M::deleteTableData(T::$vendorUtilPayment,Model::buildWhere(['vendor_util_payment_id'=>$id]));
      }
      $commit['success']           = $success;
      $commit['elastic']['delete'] = [T::$vendorUtilPaymentView => ['vendor_util_payment_id'=>$deleteIds]];
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit($commit);
    } catch (\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function getStoreData($data){
    $rawReq        = Helper::getValue('rawReq',$data,[]);
    $orderField    = Helper::getValue('orderField',$data,[]);
    $setting       = Helper::getValue('setting',$data,[]);
    $tablez        = Helper::getValue('tablez',$data,[]);
    $isPopup       = Helper::getValue('isPopMsgError',$data,0);
    $includeCdate  = Helper::getValue('includeCdate',$data,0);
    $usid          = Helper::getValue('usid',$data);
    $valid         = V::startValidate([
      'rawReq'            => $rawReq,
      'tablez'            => $tablez,
      'orderField'        => $orderField,
      'setting'           => $setting,
      'includeCdate'      => $includeCdate,
      'isPopupMsgError'   => $isPopup,
      'validateDatabase'  => [
        'mustExist' => [
          T::$vendor  . '|vendid',
          T::$glChart . '|prop,gl_acct',
        ]
      ]
    ]);
    
    $vData      = !empty($valid['dataArr']) ? $valid['dataArr'] : [$valid['dataNonArr']];
    $uuid       = !empty($valid['dataNonArr']['uuid']) ? explode(',',(rtrim($valid['dataNonArr']['uuid'],','))) : [];
    unset($valid['dataNonArr']['uuid']);
    $dataset    = [];
    
    $rVendor    = Helper::keyFieldNameElastic(M::getVendorElastic(['vendid.keyword'=>array_column($vData,'vendid')],['vendor_id','vendid']),'vendid','vendor_id');
    foreach($vData as $i => $v){
      $rGlChart                            = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$v['prop']])),'gl_acct');
      $rService                            = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$v['prop']])),'service');
      $v['vendor_id']                      = Helper::getValue($v['vendid'],$rVendor);
      $insertRow                           = HelperMysql::getDataset([T::$vendorUtilPayment=>$v],$usid,$rGlChart,$rService);
      $dataset[T::$vendorUtilPayment][]      = $insertRow[T::$vendorUtilPayment];
    }
    
    return [
      'insertData'   => $dataset,
      'uuid'         => $uuid,
    ];
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create'            => [T::$vendorUtilPayment,T::$fileUpload],
      'edit'              => [T::$vendorUtilPayment,T::$fileUpload],
      'update'            => [T::$vendorUtilPayment,T::$fileUpload],
      'destroy'           => [T::$vendorUtilPayment],
      'store'             => [T::$vendorUtilPayment,T::$fileUpload],
      '_getConfirmForm'   => [T::$vendorUtilPayment],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm         = Helper::getPermission($req);
    $orderField   = [
      'create'          => ['uuid','vendid','prop','gl_acct','invoice','due_date','remark'],
      'edit'            => ['vendor_util_payment_id','vendid','prop','gl_acct','invoice','due_date','remark','usid'],
    ];
    
    $orderField['store']    = $orderField['create'];
    $orderField['update']   = $orderField['edit'];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req = [], $default = []){
    $perm         = Helper::getPermission($req);
    $disabled     = [];
    $setting      = [
      'create'    => [
        'field'   => [
          'uuid'      => ['type'=>'hidden'],
          'vendid'    => ['label'=>'Vendor Id','req'=>1,'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'invoice'   => ['req'=>1],
          'prop'      => ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'remark'    => ['label'=>'Main Remark'],
          'gl_acct'   => ['req'=>1,'label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
        ],
        'rule'    => [
          'uuid'  => 'nullable',
        ],
      ],
      'edit'       => [
        'field'    => [
          'vendor_util_payment_id'  => $disabled + ['type'=>'hidden'],
          'vendid'                  => $disabled + ['label'=>'Vendor Code', 'hint'=>'You can type prop address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'invoice'                 => $disabled + ['req'=>1],
          'prop'                    => $disabled + ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'gl_acct'                 => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'remark'                  => $disabled + ['label'=>'Main Remark','req'=>1], 
          'usid'                    => $disabled + ['label'=>'Last Updated By', 'readonly'=>1],
        ]
      ],
    ];
   
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
    }
    $setting['store']    = $setting['create'];
    $setting['update']   = $setting['edit'];
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm   = Helper::getPermission($req);
    $button = [
      'create'          => ['submit'=>['id'=>'submit','value'=>'Create Utility Payment','class'=>'col-sm-12']],
      'edit'            => ['submit'=>['id'=>'submit','value'=>'Update Utility Payment','class'=>'col-sm-12']],
    ];
    return $button[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm     = Helper::getPermission($req);
    $rows     = [];
    
    $actionData  = $this->_getActionIcon($perm);
    $result      = Helper::getElasticResult($r,0,1);
    $data        = Helper::getValue('data',$result,[]);
    $total       = Helper::getValue('total',$result,0);
    
    foreach($data as $i => $v){
      $source                  = $v['_source'];
      $source['num']           = $vData['offset'] + $i + 1;
      $source['action']        = implode(' | ',$actionData['icon']);
      $source['linkIcon']      = Html::getLinkIcon($source,['trust','prop']);
      $source['invoiceFile']   = !empty($source['fileUpload']) ? Html::span('View', ['class'=>'clickable']) : '';
      $source                  = $this->_utilPaymentAggregations($source);
      $rows[]                  = $source;
    }
    return ['rows'=>$rows,'total'=>$total];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm          = Helper::getPermission($req);
    $actionData    = $this->_getActionIcon($perm);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList    = [];
    ### BUTTON SECTION ###
    $_getButtons   = function($perm){
      $button  = '';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-plus-square']) . ' New',['id'=>'new','class'=>'btn btn-success']) . ' ';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-trash']) . ' Delete',['id'=>'delete','class'=>'btn btn-danger','disabled'=>true]) . ' ';
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $editableText    = ['editable'=>['type'=>'text']];
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>70];
    $data[] = ['field'=>'trust','title'=>'Trust','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'entity_name','title'=>'Entity Name','sortable'=>true,'filterControl'=>'input','width'=>300];
    $data[] = ['field'=>'name','title'=>'Vendor Name','sortable'=>true,'filterControl'=>'input','width'=>300];
    $data[] = ['field'=>'vendid','title'=>'Vendor','sortable'=>true,'filterControl'=>'input','width'=>25] + $editableText;
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'number_of_units','title'=>'Unit #','sortable'=>true,'filterControl'=>'input','width'=>15];
    $data[] = ['field'=>'invoice','title'=>'Invoice','width'=>100,'filterControl'=>'input'] + $editableText;
    $data[] = ['field'=>'gl_acct','title'=>'Gl Acct','sortable'=>true,'filterControl'=>'input','width'=>30] + $editableText;
    $data[] = ['field'=>'remark','title'=>'Main Remark','sortable'=>true,'filterControl'=>'input','width'=>200] + $editableText;
    $data[] = ['field'=>'due_date','title'=>'Due Date','sortable'=>true,'filterControl'=>'input','width'=>50] + $editableText;
    $data   = $this->_addDateColumns($data);
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)];
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted Utility Payment'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData   = [];
    $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Utility Payment Information"></i></a>';
    $num          = count($actionData);
    return ['width'=>$num * 42,'icon'=>$actionData];
  }
//------------------------------------------------------------------------------
  private function _utilPaymentAggregations($r){
    $rPayment    = Helper::getValue(T::$vendorPayment,$r,[]);
    $codes       = $printed = [];
    $dateCols    = $this->_getDateColumnFields();
    foreach($rPayment as $i => $v){
      $invoiceDate    = Helper::getValue('invoice_date',$v);
      $print          = Helper::getValue('print',$v,0);
      $code          = T::$vendorPayment . '_hidden_date_field_' . date('M-Y',strtotime($invoiceDate));
      $r[$code]       = $v;
      $codes[$code]   = $code;
      $printed[$code] = $print;
    }
    
    foreach($codes as $v){
      $value          = $r[$v];
      $params         = [
        'href'              =>'#',
        'data-hidden-class' =>'vendor_payment_edit',
        'data-hidden-row-id'=>$r['vendor_util_payment_id'],
        'data-hidden-id'    =>$value['vendor_payment_id'],
        'data-hidden-value' =>Helper::getValue('amount',$value),
        'data-hidden-date'  =>Helper::getValue('invoice_date',$value),
        'class'             =>'payment-clickable clickable'
      ];
      $r[$v]          = Html::a(Format::usMoney(Helper::getValue('amount',$value,0)),$params);
    }
    
    foreach($dateCols as $f){
      $r[$f]   = !empty($r[$f]) ? $r[$f] : Html::a('Empty',[
          'href'               => '#',
          'data-hidden-class'  => 'vendor_payment_edit',
          'data-hidden-row-id' => $r['vendor_util_payment_id'],
          'data-hidden-id'     => 0,
          'data-hidden-date'   => date('Y-m-d',strtotime(preg_replace('/vendor_payment_hidden_date_field_/','',$f))),
          'data-hidden-value'  => 0,
          'class'              => 'payment-clickable clickable text-red',
        ]
      );
    }
    return $r;
  }

  //------------------------------------------------------------------------------
  private function _addDateColumns($data){
    $nowTs       = strtotime(date('M-Y'));
    $pastTs      = strtotime(date('M-Y') . ' -6 months');
    $currentTs   = $nowTs;
    $u = 0;
    $numCols     = 8;
    while($currentTs > $pastTs && $u++ < $numCols){
      $field     = T::$vendorPayment . '_hidden_date_field_' . date('M-Y',$currentTs);
      $code      = date('M-Y',$currentTs);
      $col       = ['field'=>$field,'title'=>$code];
      $col      += $u == $numCols ? [] : ['width'=>50];
      $data[]    = $col;
      $curDate   = date('M-Y',$currentTs);
      $currentTs = strtotime($curDate . ' -1 months');
      
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getDateColumnFields(){
    $nowTs    = strtotime(date('M-Y'));
    $pastTs   = strtotime(date('M-Y') . ' -6 months');
    $currentTs= $nowTs;
    $u        = 0;
    $numCols  = 8;
    $colNames = [];
    
    while($currentTs > $pastTs && $u++ < $numCols){
      $field      = T::$vendorPayment . '_hidden_date_field_' . date('M-Y',$currentTs);
      $colNames[] = $field;
      $curDate    = date('M-Y',$currentTs);
      $currentTs  = strtotime($curDate . ' -1 months');
    }
    return $colNames;
  }
}