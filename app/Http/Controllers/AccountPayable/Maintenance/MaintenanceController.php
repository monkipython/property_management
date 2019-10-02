<?php
namespace App\Http\Controllers\AccountPayable\Maintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Format, GridData, Upload, Account, TableName AS T};
use App\Http\Models\{Model, AccountPayableModel AS M}; // Include the models class

class MaintenanceController extends Controller {
  private $_viewPath          = 'app/AccountPayable/Maintenance/maintenance/';
  private $_viewTable         = '';
  private $_indexMain         = '';
  private $_numMonths         = 3;
  private static $_instance;
  
  public function __construct(){
    $this->_viewTable = T::$vendorMaintenanceView;
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
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $req)
    ]);
    return view($page, [
      'data'=>[
        'form'             => $form,
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
        ['match'=>['vendor_maintenance_id'=>$id]]),
        1),
      []);
    $r['uuid']     = '';
    $uploadFiles   = [];
    $fileUpload        = Helper::getValue('fileUpload',$r,[]);
    foreach($fileUpload as $v){
      if($v['type'] == 'maintenance'){
        $uploadFiles[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($uploadFiles, '/uploadMaintenance','upload',['includeDeleteIcon'=>1]);

    $formHtml     = Form::generateForm([
      'tablez'    => $this->_getTable(__FUNCTION__), 
      'button'    => $this->_getButton(__FUNCTION__, $req), 
      'orderField'=> $this->_getOrderField(__FUNCTION__), 
      'setting'   => $this->_getSetting(__FUNCTION__, $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'form'             => $formHtml,
        'upload'           => Upload::getHtml(),
        'fileUploadList'   => $fileUploadList
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id,Request $req){
    $req->merge(['vendor_maintenance_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate' => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorMaintenance . '|vendor_maintenance_id',
        ]
      ]
    ]);
    
    $vData                = $valid['dataNonArr'];
    $msgKey               = count($vData) > 3 ? 'msg' : 'mainMsg';
    $r                    = Helper::getValue('_source',Helper::getElasticResult(M::getMaintenanceElastic(['vendor_maintenance_id'=>$id],['vendor_maintenance_id','prop','gl_acct','vendid']),1),[]);
    $verifyKey            = ['prop','vendid','gl_acct'];
    foreach($verifyKey as $v){
      $valid['data']  += empty($vData[$v]) ? [$v => Helper::getValue($v,$r)] : [];
    }
    V::validateionDatabase(['mustExist'=>[T::$vendor . '|vendid',T::$glChart . '|prop,gl_acct']],$valid);
    $vData['usid'] = Helper::getUsid($req);
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$vendorMaintenance=>['whereData'=>['vendor_maintenance_id'=>$id],'updateData'=>$vData]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vm.vendor_maintenance_id'=>[$id]]]
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
      $insertedId         = $success['insert:' . T::$vendorMaintenance][0];     
      $updateDataSet     += !empty($uuid) ? [T::$fileUpload => ['whereInData'=>['field'=>'uuid','data'=>$uuid],'updateData'=>['foreign_id'=>$insertedId]]] : [];
      $success           += !empty($updateDataSet) ? Model::update($updateDataSet) : [];
      $elastic = [
        'insert'=>[$this->_viewTable=>['vm.vendor_maintenance_id'=>[$insertedId]]]
      ];
      $response['vendorMaintenanceId'] = $insertedId;
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
    $req->merge(['vendor_maintenance_id'=>$req['id']]);
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorMaintenance . '|vendor_maintenance_id',
        ]
      ]
    ]);
    $vData     = $valid['data'];
    $deleteIds = $vData['vendor_maintenance_id'];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      foreach($deleteIds as $id) {
        $success[T::$vendorMaintenance][] = M::deleteTableData(T::$vendorMaintenance,Model::buildWhere(['vendor_maintenance_id'=>$id]));
      }
      $commit['success']           = $success;
      $commit['elastic']['delete'] = [T::$vendorMaintenanceView => ['vendor_maintenance_id'=>$deleteIds]];
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
      $insertRow                           = HelperMysql::getDataset([T::$vendorMaintenance=>$v],$usid,$rGlChart,$rService);
      $dataset[T::$vendorMaintenance][]    = $insertRow[T::$vendorMaintenance];
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
      'create'            => [T::$vendorMaintenance,T::$fileUpload],
      'edit'              => [T::$vendorMaintenance,T::$fileUpload],
      'update'            => [T::$vendorMaintenance,T::$fileUpload],
      'destroy'           => [T::$vendorMaintenance],
      'store'             => [T::$vendorMaintenance,T::$fileUpload],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $perm   = Helper::getPermission($req);
    $button = [
      'create'          => ['submit'=>['id'=>'submit','value'=>'Create Maintenance','class'=>'col-sm-12']],
      'edit'            => ['submit'=>['id'=>'submit','value'=>'Update Maintenance','class'=>'col-sm-12']],
    ];
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm         = Helper::getPermission($req);
    $orderField   = [
      'create'          => ['uuid','vendid','monthly_amount','prop','control_unit','gl_acct'],
      'edit'            => ['vendor_maintenance_id','vendid','monthly_amount','prop','control_unit','gl_acct','usid'],
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
          'uuid'            => ['type'=>'hidden'],
          'vendid'          => ['label'=>'Vendor Code','req'=>1,'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'prop'            => ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'control_unit'    => ['value'=>1,'req'=>1],
          'monthly_amount'  => ['req'=>1],
          'gl_acct'         => ['req'=>1,'label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
        ],
        'rule'    => [
          'uuid'  => 'nullable',
        ],
      ],
      'edit'       => [
        'field'    => [
          'vendor_maintenance_id'   => $disabled + ['type'=>'hidden'],
          'vendid'                  => $disabled + ['label'=>'Vendor Code', 'hint'=>'You can type prop address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'monthly_amount'          => $disabled + ['label'=>'Amount','req'=>1],
          'control_unit'            => $disabled + ['req'=>1],
          'prop'                    => $disabled + ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'gl_acct'                 => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
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
      $source                     = $v['_source'];
      $source['num']              = $vData['offset'] + $i + 1;
      $source['action']           = implode(' | ',$actionData['icon']);
      $source['linkIcon']         = Html::getLinkIcon($source,['prop','trust','group1']);
      $source['monthly_amount']   = Format::usMoney($source['monthly_amount']);
      $source['paid_period']      = !empty($source['paid_period']) && is_numeric($source['paid_period']) ? Format::usMoney($source['paid_period']) : '';
      $source['invoiceFile']      = !empty($source['fileUpload']) ? Html::span('View', ['class'=>'clickable']) : '';
      $source                     = $this->_gatherAggregations($source);
      $rows[]                     = $source;
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
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-window-restore']) . ' Reset Control Unit',['id'=>'resetControl','class'=>'btn btn-primary']) . ' ';
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
    
    $textEditable  = ['editable'=>['type'=>'text']];
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon','title'=>'Link' . Html::repeatChar('&nbsp;',15),'width'=>150];
    $data[] = ['field'=>'trust','title'=>'Trust','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'name','title'=>'Vendor Name','sortable'=>true,'filterControl'=>'input','width'=>350];
    $data[] = ['field'=>'vendid','title'=>'Vendor','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'number_of_units','title'=>'Unit#','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'gl_acct','title'=>'Gl Acct','sortable'=>true,'filterControl'=>'input','width'=>30];
    $data[] = ['field'=>'street','title'=>'Street','sortable'=>true,'filterControl'=>'input','width'=>200];
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true,'filterControl'=>'input','width'=>125];
    $data[] = ['field'=>'control_unit','title'=>'Control Unit','sortable'=>true,'filterControl'=>'input','width'=>30] + $textEditable;
    $data[] = ['field'=>'monthly_amount','title'=>'Montly Pymt','sortable'=>true,'filterControl'=>'input','width'=>40] + $textEditable;
    $data[] = ['field'=>'paid_period','title'=>'Per Pay Period','sortable'=>true,'width'=>40];
    $data[] = ['field'=>'invoiceFile', 'title'=>'File','width'=>75];
    $data   = $this->_addDateColumns($data);
    return ['columns'=>$data, 'reportList'=>[], 'button'=>$_getButtons($perm)]; 
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted Maintenance(s)'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData   = [];
    $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Maintenance Information"></i></a>';
    $num          = count($actionData);
    return ['width'=>$num * 42,'icon'=>$actionData];
  }
//------------------------------------------------------------------------------
  private function _getDateField(){
    $delta       = intval($this->_numMonths / 2);
    $startTs     = date('Y-m-01',strtotime(' -' . $delta . ' months'));
    $stopTs      = date('Y-m-01',strtotime(' +' . $delta . ' months'));
    $fields      = [];
    $dateTs      = $startTs;
    for($i = 0; $i < $this->_numMonths; $i++){
      $fields[]  = date('Y-m-d',strtotime($dateTs));
      $fields[]  = date('Y-m-d',strtotime($dateTs . ' +15 days'));
      $dateTs    = date('Y-m-01',strtotime($dateTs . ' +1 month'));
    }
    return array_reverse($fields);
  }
//------------------------------------------------------------------------------
  private function _addDateColumns($data){
    $fields      = $this->_getDateField();
    $num         = count($fields);

    foreach($fields as $i => $v){
      $day      = date('d',strtotime($v));
      $lastDay  = $day === '01' ? '15' : date('d',strtotime(date('Y-m-t',strtotime($v))));
      
      $col      = ['field'=>'vendor_payment_hidden_date_' . $v,'title'=>$day . ' - ' . $lastDay . ' ' . date('F Y',strtotime($v))];
      $col     += ($i < ($num - 1)) ? ['width'=>50] : [];
      $data[]   = $col;
    }

    return $data;
  }
//------------------------------------------------------------------------------
  private function _gatherAggregations($r){
    $rPayment    = Helper::getValue(T::$vendorPayment,$r,[]);
    $fieldPrefix = 'vendor_payment_hidden_date_';
    $codes       = $printed = [];
    foreach($rPayment as $i => $v){
      $invoiceDate    = Helper::getValue('invoice_date',$v,'1969-12-31');
      $amount         = Helper::getValue('amount',$v,0);
      $print          = Helper::getValue('print',$v,0);
      
      $day            = intval(date('d',strtotime($invoiceDate)));
      $fieldDay       = $day > 15 ? '16' : '01';
      
      $code           = $fieldPrefix . date('Y-m-'.$fieldDay,strtotime($invoiceDate));
      $r[$code]       = $amount;
      $codes[$code]   = $code;
      $printed[$code] = $print;
    }
    
    foreach($codes as $v){
      $r[$v]          = !empty($printed[$v]) ? Html::span(Format::usMoney($r[$v]),['class'=>'text-red']) : Format::usMoney($r[$v]);
    }
    return $r;
  }
}