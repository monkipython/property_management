<?php
namespace App\Http\Controllers\AccountPayable\GardenHoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Format, GridData, Upload, Account, TableName AS T};
use App\Http\Models\{Model, AccountPayableModel AS M}; // Include the models class

class GardenHoaController extends Controller {
  private $_viewPath          = 'app/AccountPayable/GardenHoa/gardenHoa/';
  private $_viewTable         = '';
  private $_indexMain         = '';
  private $_mapping           = [];
  private $_supervisorMapping = [];
  private static $_instance;
  
  public function __construct(){
    $this->_viewTable = T::$vendorGardenHoaView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_supervisorMapping = $this->_getSupervisorMapping();

    $this->_mapping           = Helper::getMapping(['tableName'=>T::$vendorGardenHoa]);
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
      case 'confirmStop':
        return $this->_getConfirmForm($req);
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
    $formPendingCheck = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $req)
    ]);
    return view($page, [
      'data'=>[
        'formGardenHoa'    => $formPendingCheck,
        'upload'           => Upload::getHtml()
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function edit($id,Request $req){
    $page   = $this->_viewPath . 'edit';
    $r      = Helper::getValue('_source',
      Helper::getElasticResult(Elastic::searchMatch(
        $this->_viewTable,
        ['match'=>['vendor_gardenHoa_id'=>$id]]),
        1),
      []);
    $r['uuid']     = '';
    $uploadFiles   = [];
    $fileUpload        = Helper::getValue('fileUpload',$r,[]);
    foreach($fileUpload as $v){
      if($v['type'] == 'gardenHoa'){
        $uploadFiles[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($uploadFiles, '/uploadGardenHoa');

    $formHtml     = Form::generateForm([
      'tablez'    => $this->_getTable(__FUNCTION__), 
      'button'    => $this->_getButton(__FUNCTION__, $req), 
      'orderField'=> $this->_getOrderField(__FUNCTION__), 
      'setting'   => $this->_getSetting(__FUNCTION__, $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'formGardenHoa'    => $formHtml,
        'upload'           => Upload::getHtml(),
        'fileUploadList'   => $fileUploadList
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id,Request $req){
    $req->merge(['vendor_gardenHoa_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate' => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorGardenHoa . '|vendor_gardenHoa_id',
        ]
      ]
    ]);
    
    $vData                = $valid['dataNonArr'];
    $msgKey               = Helper::getValue('op',$valid) === 'confirmForm' || count($vData) > 3 ? 'msg' : 'mainMsg';
    $r                    = Helper::getValue('_source',Helper::getElasticResult(M::getGardenHoaElastic(['vendor_gardenHoa_id'=>$id],['vendor_gardenHoa_id','prop','gl_acct','vendid']),1),[]);
    $verifyKey            = ['prop','vendid','gl_acct'];
    foreach($verifyKey as $v){
      $valid['data']  += empty($vData[$v]) ? [$v => Helper::getValue($v,$r)] : [];
    }
    V::validateionDatabase(['mustExist'=>[T::$vendor . '|vendid',T::$glChart . '|prop,gl_acct']],$valid);
    $vData['usid'] = Helper::getUsid($req);
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$vendorGardenHoa=>['whereData'=>['vendor_gardenHoa_id'=>$id],'updateData'=>$vData]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vg.vendor_gardenHoa_id'=>[$id]]]
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
      $insertedId          = $success['insert:' . T::$vendorGardenHoa][0];     
      $updateDataSet     += !empty($uuid) ? [T::$fileUpload => ['whereInData'=>['field'=>'uuid','data'=>$uuid],'updateData'=>['foreign_id'=>$insertedId]]] : [];
      $success           += !empty($updateDataSet) ? Model::update($updateDataSet) : [];
      $elastic = [
        'insert'=>[$this->_viewTable=>['vg.vendor_gardenHoa_id'=>[$insertedId]]]
      ];
      $response['vendorPendingCheckId'] = $insertedId;
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
  public function destroy($id, Request $req) {
    $req->merge(['vendor_gardenHoa_id'=>$req['id']]);
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorGardenHoa . '|vendor_gardenHoa_id',
        ]
      ]
    ]);
    $vData     = $valid['data'];
    $deleteIds = $vData['vendor_gardenHoa_id'];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      foreach($deleteIds as $id) {
        $success[T::$vendorGardenHoa][] = M::deleteTableData(T::$vendorGardenHoa,Model::buildWhere(['vendor_gardenHoa_id'=>$id]));
      }
      $commit['success']           = $success;
      $commit['elastic']['delete'] = [T::$vendorGardenHoaView => ['vg.vendor_gardenHoa_id'=>$deleteIds]];
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
      $insertRow                           = HelperMysql::getDataset([T::$vendorGardenHoa=>$v],$usid,$rGlChart,$rService);
      $dataset[T::$vendorGardenHoa][]      = $insertRow[T::$vendorGardenHoa];
    }
    
    return [
      'insertData'   => $dataset,
      'uuid'         => $uuid,
    ];
  }
//------------------------------------------------------------------------------
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getTable($fn){
    $tablez = [
      'create'            => [T::$vendorGardenHoa,T::$fileUpload],
      'edit'              => [T::$vendorGardenHoa,T::$fileUpload],
      'update'            => [T::$vendorGardenHoa,T::$fileUpload],
      'destroy'           => [T::$vendorGardenHoa],
      'store'             => [T::$vendorGardenHoa,T::$fileUpload],
      '_getConfirmForm'   => [T::$vendorGardenHoa],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $perm   = Helper::getPermission($req);
    $button = [
      'create'          => ['submit'=>['id'=>'submit','value'=>'Create Garden HOA','class'=>'col-sm-12']],
      'edit'            => ['submit'=>['id'=>'submit','value'=>'Update Garden HOA','class'=>'col-sm-12']],
      '_getConfirmForm' => ['submit'=>['id'=>'submit','value'=>'Stop Payment','class'=>'col-sm-12']],
    ];
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm         = Helper::getPermission($req);
    $orderField   = [
      'create'          => ['uuid','vendid','invoice','prop','gl_acct','remark','amount','note','account_id'],
      'edit'            => ['vendor_gardenHoa_id','vendid','invoice','prop','gl_acct','remark','amount','note','account_id','stop_pay','usid'],
      '_getConfirmForm' => ['vendor_gardenHoa_id','note','stop_pay'],
    ];
    
    $orderField['store']    = $orderField['create'];
    $orderField['update']   = $orderField['edit'];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $perm         = Helper::getPermission($req);
    $disabled     = [];
    $setting      = [
      'create'    => [
        'field'   => [
          'uuid'      => ['type'=>'hidden'],
          'vendid'    => ['label'=>'Vendor Id','req'=>1,'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'invoice'   => ['req'=>1],
          'prop'      => ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'amount'    => ['req'=>1],
          'gl_acct'   => ['req'=>1,'label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'account_id'=> ['label'=>'Supervisor','req'=>1,'type'=>'option','option'=>$this->_supervisorMapping],
        ],
        'rule'    => [
          'uuid'  => 'nullable',
        ],
      ],
      'edit'       => [
        'field'    => [
          'vendor_gardenHoa_id'     => $disabled + ['type'=>'hidden'],
          'vendid'                  => $disabled + ['label'=>'Vendor Id', 'hint'=>'You can type prop address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'invoice'                 => $disabled + ['req'=>1],
          'amount'                  => $disabled + ['label'=>'Amount','req'=>1],
          'prop'                    => $disabled + ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'gl_acct'                 => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'remark'                  => $disabled + ['req'=>1], 
          'account_id'              => $disabled + ['req'=>1,'label'=>'Supervisor','type'=>'option','option'=>$this->_supervisorMapping],
          'stop_pay'                => $disabled + ['req'=>1,'label'=>'Stop Payment','type'=>'option','option'=>$this->_mapping['stop_pay'],'readonly'=>1],
          'usid'                    => $disabled + ['label'=>'Last Updated By', 'readonly'=>1],
        ]
      ],
      '_getConfirmForm'  => [
        'field'  => [
          'vendor_gardenHoa_id'  => ['type'=>'hidden'],
          'note'                 => ['type'=>'textarea','req'=>1],
          'stop_pay'             => ['label'=>'Stop Payment','type'=>'option','option'=>$this->_mapping['stop_pay'],'readonly'=>1]
        ],
        'rule'   => [
          'note' => 'required|string',
        ]
      ]
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
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
//------------------------------------------------------------------------------
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
      $source['linkIcon']      = Html::getLinkIcon($source,['prop','trust','group1']);
      $source['amount']        = Format::usMoney($source['amount']);
      $source['invoiceFile']   = !empty($source['fileUpload']) ? Html::span('View', ['class'=>'clickable']) : '';
      $source                  = $this->_gatherAggregations($source);
      $rows[]                  = $source;
    }
    return ['rows'=>$rows,'total'=>$total];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){  
    $perm          = Helper::getPermission($req);
    $actionData    = $this->_getActionIcon($perm);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList    = ['csv'=>'Export to CSV'];
    ### BUTTON SECTION ###
    $_getButtons   = function($perm){
      $button  = '';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-plus-square']) . ' New',['id'=>'new','class'=>'btn btn-success']) . ' ';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-paper-plane-o']) . ' Generate Payment',['id'=>'generatePayment','class'=>'btn btn-primary']) . ' ';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-trash']) . ' Delete',['id'=>'delete','class'=>'btn btn-danger','disabled'=>true]) . ' ';
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data           = ['field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      $data          += ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable];
      $data['editable']  = ['type'=>'select','source'=>$source];
      return $data;
    };
    
    $gardenHoaEditable  = ['editable'=>['type'=>'text']];
    $supervisrEditable  = ['editable'=>['type'=>'select','source'=>$this->_supervisorMapping]];
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>110];
    $data[] = ['field'=>'trust','title'=>'Trust','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'name','title'=>'Vendor Name','sortable'=>true,'filterControl'=>'input','width'=>300];
    $data[] = ['field'=>'vendid','title'=>'Vendor','sortable'=>true,'filterControl'=>'input','width'=>25] + $gardenHoaEditable;
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'gl_acct','title'=>'Gl Acct','sortable'=>true,'filterControl'=>'input','width'=>30];
    $data[] = ['field'=>'street','title'=>'Street','sortable'=>true,'filterControl'=>'input','width'=>200];
    $data[] = ['field'=>'remark','title'=>'Remark','sortable'=>true,'filterControl'=>'input','width'=>200] + $gardenHoaEditable;
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true,'filterControl'=>'input','width'=>125];
    $data[] = ['field'=>'amount','title'=>'Amount','sortable'=>true,'filterControl'=>'input','width'=>50] + $gardenHoaEditable;
    $data[] = ['field'=>'account_id','title'=>'Supervisor','sortable'=>true,'filterControl'=>'select','filterData'=>'json:' . json_encode($this->_supervisorMapping),'width'=>150] + $supervisrEditable;
    $data[] = $_getSelectColumn($perm,'stop_pay','Stop Payment',60,$this->_mapping['stop_pay']);
    $data[] = ['field'=>'note','title'=>'Note','sortable'=>true,'filterControl'=>'input','width'=>200] + $gardenHoaEditable;
    $data[] = ['field'=>'invoiceFile', 'title'=>'File','width'=>75];
    $data   = $this->_addDateColumns($data);
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted Garden HOA'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData   = [];
    $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Garden HOA Information"></i></a>';
    $num          = count($actionData);
    return ['width'=>$num * 42,'icon'=>$actionData];
  }
//------------------------------------------------------------------------------
  private function _getSupervisorMapping(){
    $accountRole = Helper::getValue('_source',Helper::getElasticResult(Elastic::searchQuery([
      'index'     => T::$accountRoleView,
      '_source'   => ['accountRole_id','role'],
      'query'     => [
        'must'    => [
          'role'  => 'Supervisor Management'
        ]
      ]
    ]),1),[]);
    $roleId      = Helper::getValue('accountRole_id',$accountRole,50);
    $data        = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$accountView,
      '_source'  => ['account_id','firstname','lastname'],
      'sort'     => ['firstname.keyword'=>'asc','lastname.keyword'=>'asc'],
      'query'    => [
        'must'   => [
          'accountRole_id' => $roleId,
        ]
      ]
    ]),'account_id');

    $options = [];
    foreach($data as $k => $v){
      $options[$k] = title_case(Helper::getValue('firstname',$v) . ' ' . Helper::getValue('lastname',$v));
    }
    asort($options);
    return $options;
  }
//------------------------------------------------------------------------------
  private function _addDateColumns($data){
    $nowTs       = strtotime(date('M-Y'));
    $pastTs      = strtotime(date('M-Y') . ' -6 months');
    $currentTs   = $nowTs;
    
    while($currentTs > $pastTs){
      $code      = date('M-Y',$currentTs);
      $data[]    = ['field'=>$code,'title'=>$code,'width'=>50];
      $curDate   = date('M-Y',$currentTs);
      $currentTs = strtotime($curDate . ' -1 months');
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _gatherAggregations($r){
    $rPayment    = Helper::getValue(T::$vendorPayment,$r,[]);
    $codes       = $printed = [];
    foreach($rPayment as $i => $v){
      $invoiceDate    = Helper::getValue('invoice_date',$v);
      $amount         = Helper::getValue('amount',$v,0);
      $print          = Helper::getValue('print',$v,0);
      $code           = date('M-Y',strtotime($invoiceDate));
      $pastAmount     = Helper::getValue($code,$r,0);
      $r[$code]       = $amount;
      $codes[$code]   = $code;
      $printed[$code] = $print;
    }
    
    foreach($codes as $v){
      $r[$v]          = !empty($printed[$v]) ? Html::span(Format::usMoney($r[$v]),['class'=>'text-red']) : Format::usMoney($r[$v]);
    }
    return $r;
  }
//------------------------------------------------------------------------------
  private function _getConfirmForm($req){
    $page         = $this->_viewPath . 'formWrapper';
    $valid        = V::startValidate([
      'rawReq'            => $req->all(),
      'tablez'            => $this->_getTable(__FUNCTION__),
      'validateDatabase'  => [
        'mustExist' => [
          T::$vendorGardenHoa . '|vendor_gardenHoa_id',
        ]
      ]
    ]);
    $vData        = $valid['data'];
    $r            = Helper::getValue('_source',Helper::getElasticResult(Elastic::searchMatch(T::$vendorGardenHoaView,['match'=>['vendor_gardenHoa_id'=>$vData['vendor_gardenHoa_id']]]),1));
    $r['stop_pay']= $vData['stop_pay'];
    $form         = Form::generateForm([
      'tablez'    => $this->_getTable(__FUNCTION__), 
      'button'    => $this->_getButton(__FUNCTION__, $req), 
      'orderField'=> $this->_getOrderField(__FUNCTION__), 
      'setting'   => $this->_getSetting(__FUNCTION__, $req,$r),
    ]);
    return view($page,['data'=>[
      'formGardenHoa' => $form
    ]]);
  }
}