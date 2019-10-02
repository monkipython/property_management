<?php
namespace App\Http\Controllers\AccountPayable\PendingCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Format, GridData, Upload, Account, TableName AS T};
use App\Http\Models\{Model, AccountPayableModel AS M}; // Include the models class

class PendingCheckController extends Controller{
  private $_viewPath  = 'app/AccountPayable/PendingCheck/pendingCheck/';
  private $_viewTable = '';
  private $_indexMain = '';
  private static $_instance;
  
  public function __construct(){
    $this->_mappingPendingCheck = Helper::getMapping(['tableName'=>T::$vendorPendingCheck]);
    $this->_viewTable = T::$vendorPendingCheckView;
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
       // $vData['defaultFilter']   = ['usid'=>Helper::getUsid($req)];
        $vData['defaultFilter'] = ['usid'=>Helper::getUsid($req) . ' AND ( is_submitted:no OR recurring:yes )'];
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
   
    $formPendingCheck = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createPendingCheck'), 
      'setting'   =>$this->_getSetting('createPendingCheck', $req)
    ]);
    
    return view($page, [
      'data'=>[
        'formPendingCheck' => $formPendingCheck,
        'upload'           => Upload::getHtml()
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $storeData  = $this->getStoreData([
      'rawReq'        => $req->all(),
      'tablez'        => $this->_getTable('create'),
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
      $success += Model::insert($insertData);
      $pendingCheckId = $success['insert:' . T::$vendorPendingCheck][0];     
      $updateDataSet[T::$fileUpload] = ['whereInData'=>['field'=>'uuid','data'=>$uuid],'updateData'=>['foreign_id'=>$pendingCheckId]];
      $success += Model::update($updateDataSet);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vendor_pending_check_id'=>[$pendingCheckId]]]
      ];
      $response['vendorPendingCheckId'] = $pendingCheckId;
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
  public function getStoreData($data){
    $rawReq        = Helper::getValue('rawReq',$data,[]);
    $orderField    = Helper::getValue('orderField',$data,[]);
    $setting       = Helper::getValue('setting',$data,[]);
    $tablez        = Helper::getValue('tablez',$data,[]);
    $isPopup       = Helper::getValue('isPopMsgError',$data,0);
    $includeCdate  = Helper::getValue('includeCdate',$data,0);
    $usid          = Helper::getValue('usid',$data);
    $valid      = V::startValidate([
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
      $insertRow                           = HelperMysql::getDataset([T::$vendorPendingCheck=>$v],$usid,$rGlChart,$rService);
      $dataset[T::$vendorPendingCheck][]   = $insertRow[T::$vendorPendingCheck];
    }
    
    return [
      'insertData'   => $dataset,
      'uuid'         => $uuid,
    ];
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['vendor_pending_check_id' => $id]]);
    $r = Helper::getValue('_source',Helper::getElasticResult($r,1),[]);
    $r['uuid'] = '';
    $pendingCheckFiles = [];
    $fileUpload        = Helper::getValue('fileUpload',$r,[]);
    foreach($fileUpload as $v){
      if($v['type'] == 'pending_check'){
        $pendingCheckFiles[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($pendingCheckFiles, '/uploadPendingCheck');

    $formPendingCheck = Form::generateForm([
      'tablez'    => $this->_getTable('update'), 
      'button'    => $this->_getButton(__FUNCTION__, $req), 
      'orderField'=> $this->_getOrderField('editPendingCheck'), 
      'setting'   => $this->_getSetting('updatePendingCheck', $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'formPendingCheck' => $formPendingCheck,
        'upload'           => Upload::getHtml(),
        'fileUploadList'   => $fileUploadList
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['vendor_pending_check_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate' => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorPendingCheck . '|vendor_pending_check_id',
        ]
      ]
    ]);
    $vData                = $valid['dataNonArr'];
    $r                    = Helper::getValue('_source',Helper::getElasticResult(M::getPendingCheckElastic(['vendor_pending_check_id'=>$id],['vendor_pending_check_id','prop','gl_acct','vendid']),1),[]);
    $valid['data']       += empty($vData['prop']) ? ['prop'=>Helper::getValue('prop',$r)] : []; 
    $valid['data']       += empty($vData['vendid']) ? ['vendid'=>Helper::getValue('vendid',$r)] : [];
    $valid['data']       += empty($vData['gl_acct']) ? ['gl_acct' =>Helper::getValue('gl_acct',$r)] : [];
    V::validateionDatabase(['mustExist'=>[T::$vendor . '|vendid',T::$glChart . '|prop,gl_acct']],$valid);
    $vData['usid'] = Helper::getUsid($req);
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$vendorPendingCheck=>['whereData'=>['vendor_pending_check_id'=>$id],'updateData'=>$vData]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vp.vendor_pending_check_id'=>[$id]]]
      ];
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
      $response['mainMsg']          = $this->_getSuccessMsg(__FUNCTION__);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req) {
    $req->merge(['vendor_pending_check_id'=>$req['id']]);
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorPendingCheck . '|vendor_pending_check_id',
        ]
      ]
    ]);
    $vData = $valid['data'];
    
    $pendingCheckIds = $vData['vendor_pending_check_id'];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      foreach($pendingCheckIds as $id) {
        $success[T::$vendorPendingCheck][] = M::deleteTableData(T::$vendorPendingCheck,Model::buildWhere(['vendor_pending_check_id'=>$id]));
      }
      $commit['success']           = $success;
      $commit['elastic']['delete'] = [T::$vendorPendingCheckView => ['vendor_pending_check_id'=>$pendingCheckIds]];
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
      'create' => [T::$vendorPendingCheck, T::$fileUpload],
      'update' => [T::$vendorPendingCheck, T::$prop, T::$fileUpload],
      'destroy'=> [T::$vendorPendingCheck]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $orderField = [
      'createPendingCheck' => ['uuid', 'vendid', 'invoice', 'invoice_date', 'due_date', 'amount', 'prop', 'bank', 'gl_acct', 'is_need_approved','recurring', 'remark'],
      'editPendingCheck'   => ['vendor_pending_check_id', 'vendid', 'invoice', 'invoice_date', 'due_date', 'amount', 'prop','bank', 'gl_acct', 'is_need_approved','recurring', 'remark', 'usid']
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
//    $orderField['update'] = isset($perm['pendingCheckedit']) ? $orderField['editPendingCheck'] : [];
//    $orderField['store']  = isset($perm['pendingCheckcreate']) ? $orderField['createPendingCheck'] : [];
    $orderField['update']   = $orderField['editPendingCheck'];
    $orderField['store']    = $orderField['createPendingCheck'];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req = [], $default = []){
    $perm     = Helper::getPermission($req);
    //$disabled = isset($perm['pendingCheckupdate']) ? [] : ['disabled'=>1];
    $disabled = [];
    //$rBank    = !empty($default['prop']) ? Helper::keyFieldName(M::getBank(Model::buildWhere(['p.prop'=>$default['prop']]), ['b.bank', DB::raw('CONCAT(b.bank, " - ", b.name) AS name')], 0), 'bank', 'name') : [''=>'Select Bank'];
    $rBank    = !empty($default['prop']) ? $this->_keyFieldBank(HelperMysql::getBank(Helper::getPropMustQuery($default,[],0),['bank','cp_acct','name'],['sort'=>'bank.keyword'],0)) : [''=>'Select Bank'];
    $setting = [
      'createPendingCheck' => [
        'field' => [
          'uuid'             => ['type'=>'hidden'],
          'vendid'           => ['label'=>'Vendor Id', 'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'prop'             => ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'due_date'         => ['req'=>1,'value'=>0],
          'invoice_date'     => ['req'=>1,'value'=>date('m/d/Y')],
          'amount'           => ['req'=>1],
          'gl_acct'          => ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'is_need_approved' => ['label'=>'Needs Approval','type'=>'option', 'option'=> $this->_mappingPendingCheck['is_need_approved'],'value'=>'1'],
          'recurring'        => ['type'=>'option', 'option'=> $this->_mappingPendingCheck['recurring'],'value'=>'no'],
          'bank'             => ['type'=>'option', 'option'=>[]],
        ]
      ],
      'updatePendingCheck' => [
        'field' => [
          'vendor_pending_check_id' => $disabled + ['type' =>'hidden'],
          'vendid'                  => $disabled + ['label'=>'Vendor Id', 'hint'=>'You can type prop address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'invoice'                 => $disabled + ['req'=>1],
          'invoice_date'            => $disabled + ['req'=>1], 
          'due_date'                => $disabled + ['req'=>1], 
          'amount'                  => $disabled + ['req'=>1], 
          'prop'                    => $disabled + ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'bank'                    => $disabled + ['label'=>'Bank','type'=>'option','option'=>$rBank,'req'=>1],
          'gl_acct'                 => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'is_need_approved'        => $disabled + ['label'=>'Needs Approval','type'=>'option','option'=>$this->_mappingPendingCheck['is_need_approved']],
          'recurring'               => $disabled + ['type'=>'option', 'option'=> $this->_mappingPendingCheck['recurring'],'req'=>1],
          'remark'                  => $disabled + ['req'=>1], 
          'usid'                    => $disabled + ['label'=>'Last Updated By', 'readonly'=>1],
        ]
      ]
    ];
    
    //$setting['update'] = isset($perm['pendingCheckupdate']) ? $setting['updatePendingCheck'] : [];
    //$setting['store']  = isset($perm['pendingCheckcreate']) ? $setting['createPendingCheck'] : [];
    $setting['update']   = $setting['updatePendingCheck'];
    $setting['store']    = $setting['createPendingCheck'];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['invoice_date']['value'] = Format::usDate($default['invoice_date']);
        ## some datas are all uppercase and others lowercase
        $setting[$fn]['field']['recurring']['value'] = strtolower($default['recurring']);
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit'  =>isset($perm['pendingCheckupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
      'create'=>['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']]
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm = Helper::getPermission($req);
    $rows = [];
    $actionData = $this->_getActionIcon($perm);
    
    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source'];
      $iconList               = ['prop','trust','group1'];
      $iconList               = array_merge($iconList,!empty($source['unit']) && $source['unit'] !== '0000' ? ['unit'] : []);
      $iconList               = array_merge($iconList,!empty($source['unit']) && $source['unit'] !== '0000' && isset($source['tenant']) ? ['tenant'] : []);
      $source['num']          = $vData['offset'] + $i + 1;
      $source['action']       = implode(' | ', $actionData['icon']);
      $source['linkIcon']     = Html::getLinkIcon($source,$iconList);
      $source['amount']       = Format::usMoney($source['amount']);
      $source['recurring']    = strtolower($source['recurring']);
      $source['invoiceFile']  = !empty($source['fileUpload']) ? Html::span('View', ['class'=>'clickable']) : '';
      $source['bankList']     = !empty($source['bank_id']) ? Helper::keyFieldName($source['bank_id'],'bank','name') : [];
      $source['is_submitted'] = Helper::getValue(Helper::getValue('is_submitted',$source),$this->_mappingPendingCheck['is_submitted']);
      $defaultBank            = Helper::getValue('bank',$source);
      $prefix                 = !empty($defaultBank)  ? '(' . $defaultBank . ') ' : $defaultBank;
      $source['bank']         = isset($perm['pendingCheckupdate']) ? $defaultBank : $prefix . Helper::getValue($defaultBank,$source['bankList']);
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $actionData = $this->_getActionIcon($perm);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = [];
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
//      if(isset($perm['pendingCheckcreate'])) {
//        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
//      }
      $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New',['id'=>'new','class'=>'btn btn-success']) . ' ';
      $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-upload']) . ' Upload Pending', ['id'=> 'uploadPending', 'class'=>'btn btn-info']) . ' ';  
      $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-paper-plane-o']) . ' Send to Approval', ['id'=> 'sendApproval', 'class'=>'btn btn-primary','disabled'=>true]) . ' ';
      $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-trash']) . ' Delete',['id'=>'delete','class'=>'btn btn-danger']) . ' ';
//      if(isset($perm['pendingCheckdestroy'])) {
//        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-trash']) . ' Delete', ['id'=> 'delete', 'class'=>'btn btn-danger']) . ' ';
//      }
      return $button;
    };
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      $data['editable'] = ['type'=>'select','source'=>$source];
      return $data;
    };
    $pendingCheckEditable = ['editable'=> ['type'=> 'text']];
    $bankEditable         = ['editable'=> ['type'=> 'select']];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>110];
    $data[] = ['field'=>'invoice_date', 'title'=>'Invoice Date','sortable'=> true, 'filterControl'=> 'input', 'width'=> 100] + $pendingCheckEditable;
    $data[] = ['field'=>'vendid', 'title'=>'Vendor Code','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'tenant', 'title'=>'Tnt #','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'name', 'title'=>'Vendor Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'invoice', 'title'=>'Invoice #','sortable'=> true, 'filterControl'=> 'input', 'width'=> 150] + $pendingCheckEditable;
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'unit', 'title'=>'Unit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'bank','value'=>'bank','title'=>'Bank','sortable'=>true,'filterControl'=>'input','width'=>300] + $bankEditable;
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 125];
    $data[] = ['field'=>'remark', 'title'=>'Remark','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300] + $pendingCheckEditable;
    $data[] = ['field'=>'amount', 'title'=>'Amount','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $pendingCheckEditable;
    $data[] = ['field'=>'gl_acct', 'title'=>'Gl Account','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $pendingCheckEditable;
    $data[] = $_getSelectColumn($perm, 'is_need_approved', 'Needs Approval', 50, $this->_mappingPendingCheck['is_need_approved']);
    $data[] = $_getSelectColumn($perm, 'recurring', 'Recurring', 50, $this->_mappingPendingCheck['recurring']);
    //$data[] = $_getSelectColumn($perm, 'is_submitted','Submitted',50, $this->_mappingPendingCheck['is_submitted']);
    $data[] = ['field'=>'is_submitted','title'=>'Submitted','width'=>50,'filterControl'=>'select','filterData'=>'url:filter/is_submitted:'.$this->_viewTable,'sortable'=>true];
    $data[] = ['field'=>'invoiceFile', 'title'=>'Invoice','width'=> 75];
    $data[] = ['field'=>'trust', 'title'=>'Trust', 'sortable'=>true, 'width'=>40,'filterControl'=>'input'];
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'filterControl'=>'input'];
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted Pending Check'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['pendingCheckedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Pending Check Information"></i></a>';
    }
    $num = count($actionData);       
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
//------------------------------------------------------------------------------
  private function _keyFieldBank($data) {
    $returnData = [];
    foreach($data as $k => $v) {
      $source = $v['_source'];
      $returnData[$source['bank']] = $source['bank'] . ' - ' . preg_replace('/\s+/', ' ', $source['name']);
    }
    return $returnData;
  }
}