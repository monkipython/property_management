<?php
namespace App\Http\Controllers\AccountPayable\PropTax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, Upload, Account, TableName AS T, HelperMysql};
use App\Http\Models\Model; // Include the models class

class PropTaxController extends Controller{
  private $_viewPath  = 'app/AccountPayable/PropTax/propTax/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingPropTax = Helper::getMapping(['tableName'=>T::$vendorPropTax]);
    $this->_viewTable = T::$vendorPropTaxView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
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
   
    $formPropTax = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'button'    =>self::_getButton(__FUNCTION__), 
      'orderField'=>self::_getOrderField('createPropTax'), 
      'setting'   =>self::_getSetting('createPropTax', $req)
    ]);
    
    return view($page, [
      'data'=>[
        'formPropTax' => $formPropTax,
        'upload'      => Upload::getHtml()
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => self::_getTable('create'), 
      'orderField'      => self::_getOrderField(__FUNCTION__, $req),
      'setting'         => self::_getSetting(__FUNCTION__, $req),
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          'vendor|vendid',
          'gl_chart|gl_acct',
          'prop|prop'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];

    $uuid = explode(',', (rtrim($vData['uuid'], ',')));
    unset($vData['uuid']);
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $updateDataSet = $response = $success = $elastic = [];
    try{
      $success += Model::insert([T::$vendorPropTax=>$vData]);
      $propTaxId = $success['insert:' . T::$vendorPropTax][0];
      
      foreach($uuid as $v){
        $updateDataSet[T::$fileUpload][] = ['whereData'=>['uuid'=>$v], 'updateData'=>['foreign_id'=>$propTaxId]];
      }
      $success += Model::update($updateDataSet);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vendor_prop_tax_id'=>[$propTaxId]]]
      ];
      $response['propTaxId'] = $propTaxId;
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
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['vendor_prop_tax_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    $r['uuid'] = '';
    $propTaxFiles = [];
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    foreach($fileUpload as $v){
      if($v['type'] == 'prop_tax'){
        $propTaxFiles[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($propTaxFiles, '/uploadPropTax');
    $formPropTax = Form::generateForm([
      'tablez'    => self::_getTable('update'), 
      'button'    => self::_getButton(__FUNCTION__, $req), 
      'orderField'=> self::_getOrderField('editPropTax'), 
      'setting'   => self::_getSetting('updatePropTax', $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'formPropTax'    => $formPropTax,
        'upload'         => Upload::getHtml(),
        'fileUploadList' => $fileUploadList
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['vendor_prop_tax_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => self::_getTable(__FUNCTION__),
      'setting'      => self::_getSetting(__FUNCTION__, $req),
      'includeCdate' => 0,
      'includeUsid'  => 1,
      'validateDatabase' => [
        'mustExist' => [
          'vendor_prop_tax|vendor_prop_tax_id'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    if(!empty($vData['vendid']) && !empty($vData['gl_acct']) && !empty($vData['prop'])){
      V::validateionDatabase(['mustExist'=>['vendor|vendid', 'gl_chart|gl_acct', 'prop|prop']], $valid);
    }
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$vendorPropTax=>['whereData'=>['vendor_prop_tax_id'=>$id],'updateData'=>$vData]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vendor_prop_tax_id'=>[$id]]]
      ];
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
    $req->merge(['vendor_prop_tax_id'=>$req['id']]);
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorPropTax . '|vendor_prop_tax_id',
        ]
      ]
    ]);
    $vData = $valid['data'];
    $vendorPropTaxId = $vData['vendor_prop_tax_id'];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      foreach($vendorPropTaxId as $id) {
        $success[T::$vendorPropTax][] = HelperMysql::deleteTableData(T::$vendorPropTax, Model::buildWhere(['vendor_prop_tax_id'=>$id]));
      }
      $commit['success']           = $success;
      $commit['elastic']['delete'] = [T::$vendorPropTaxView => ['vendor_prop_tax_id'=>$vendorPropTaxId]];
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
      'create' => [T::$vendorPropTax, T::$fileUpload],
      'update' => [T::$vendorPropTax, T::$fileUpload],
      'destroy'=> [T::$vendorPropTax]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $orderField = [
      'createPropTax' => ['uuid', 'prop', 'vendid', 'gl_acct', 'apn', 'assessed_val', 'amount1', 'amount2', 'amount3', 'bill_num', 'payer'],
      'editPropTax'   => ['vendor_prop_tax_id', 'prop', 'vendid', 'gl_acct', 'apn', 'assessed_val', 'amount1', 'amount2', 'amount3', 'bill_num', 'remark1', 'remark2', 'payer', 'usid']
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['propTaxedit']) ? $orderField['editPropTax'] : [];
    $orderField['store']  = isset($perm['propTaxcreate']) ? $orderField['createPropTax'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req = [], $default = []){
    $perm = Helper::getPermission($req);
    $disabled = isset($perm['propTaxupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'createPropTax' => [
        'field' => [
          'uuid'      => ['type'=>'hidden'],
          'prop'      => ['label'=>'Property Number', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'vendid'    => ['label'=>'Vendor Id', 'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'gl_acct'   => ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'apn'       => ['label'=>'APN'],
          'amount1'   => ['label'=>'1st Installment'],
          'amount2'   => ['label'=>'2nd Installment'],
          'amount3'   => ['label'=>'Supplemental'],
          'payer'     => ['type'=>'option', 'option'=> $this->_mappingPropTax['payer']]
        ]
      ],
      'updatePropTax' => [
        'field' => [
          'vendor_prop_tax_id' => $disabled + ['type' =>'hidden'],
          'prop'               => $disabled + ['label'=>'Property Number', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'vendid'             => $disabled + ['label'=>'Vendor Id', 'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'gl_acct'            => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'apn'                => $disabled + ['label'=>'APN'],
          'assessed_val'       => $disabled,
          'amount1'            => $disabled + ['label'=>'1st Installment'],
          'amount2'            => $disabled + ['label'=>'2nd Installment'],
          'amount3'            => $disabled + ['label'=>'Supplemental'],
          'bill_num'           => $disabled,
          'remark1'            => $disabled,
          'remark2'            => $disabled,
          'payer'              => $disabled + ['type'=>'option', 'option'=> $this->_mappingPropTax['payer']],
          'usid'               => $disabled + ['label'=>'Last Updated By', 'readonly'=>1]
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['propTaxupdate']) ? $setting['updatePropTax'] : [];
    $setting['store']  = isset($perm['propTaxcreate']) ? $setting['createPropTax'] : [];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit'  =>isset($perm['propTaxupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
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
      $source['num']          = $vData['offset'] + $i + 1;
      $source['action']       = implode(' | ', $actionData['icon']);
      $source['linkIcon']     = Html::getLinkIcon($source,['trust', 'prop']);
      $source['assessed_val'] = Format::usMoney($source['assessed_val']);
      $source['po_value']     = Format::usMoney($source['po_value']);
      $source['amount1']      = Format::usMoney($source['amount1']);
      $source['amount2']      = Format::usMoney($source['amount2']);
      $source['amount3']      = Format::usMoney($source['amount3']);
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
    if(isset($perm['propTaxExport'])) {
      $reportList['csv'] = 'Export to CSV';
    }
    
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['propTaxcreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      if(isset($perm['approvePropTax'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-paper-plane-o']) . ' Send 1st Installment to Approval', ['id'=> 'firstInstallment', 'class'=>'btn btn-info', 'disabled'=>true]) . ' ';  
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-paper-plane-o']) . ' Send 2nd Installment to Approval', ['id'=> 'secondInstallment', 'class'=>'btn btn-info','disabled'=>true]) . ' ';
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-location-arrow']) . ' Send Supplemental to Approval', ['id'=> 'supplemental', 'class'=>'btn btn-primary','disabled'=>true]) . ' ';
      }
      if(isset($perm['propTaxdestroy'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-trash']) . ' Delete', ['id'=> 'delete', 'class'=>'btn btn-danger','disabled'=>true]) . ' ';
      }
      return $button;
    };
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if(isset($perm['propTaxupdate'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    $propTaxEditable = isset($perm['propTaxupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>75];
    $data[] = ['field'=>'trust', 'title'=>'Trust','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'entity_name', 'title'=>'Entity Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 150];
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'number_of_units', 'title'=>'# Unit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 150];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = ['field'=>'state', 'title'=>'State','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'zip', 'title'=>'Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'county', 'title'=>'County','sortable'=> true, 'filterControl'=> 'input', 'width'=> 100];
    $data[] = ['field'=>'apn', 'title'=>'APN','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $propTaxEditable;
    $data[] = ['field'=>'assessed_val', 'title'=>'Assessed Val','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $propTaxEditable;
    $data[] = ['field'=>'po_value', 'title'=>'PO. Price','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = ['field'=>'start_date', 'title'=>'PO. Date','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = ['field'=>'amount1', 'title'=>'1st Installment','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $propTaxEditable;
    $data[] = ['field'=>'amount2', 'title'=>'2nd Installment','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $propTaxEditable;
    $data[] = ['field'=>'remark1', 'title'=>'Remark1','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $propTaxEditable;
    $data[] = ['field'=>'amount3', 'title'=>'Supplemental','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $propTaxEditable;
    $data[] = ['field'=>'remark2', 'title'=>'Remark2','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $propTaxEditable;
    $data[] = ['field'=>'bill_num', 'title'=>'Bill Number','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $propTaxEditable;
    $data[] = $_getSelectColumn($perm, 'payer', 'Payer', 75, $this->_mappingPropTax['payer']);
//    $data[] = ['field'=>'active', 'title'=>'Active','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update' =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['propTaxedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Property Tax Information"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
}