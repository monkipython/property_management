<?php
namespace App\Http\Controllers\AccountPayable\Insurance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, HelperMysql, GridData, Upload, Account, TableName AS T};
use App\Http\Models\{Model, AccountPayableModel AS M}; // Include the models class

class InsuranceController extends Controller{
  private $_viewPath  = 'app/AccountPayable/Insurance/insurance/';
  private $_viewTable = '';
  private $_indexMain = '';
  private static $_instance;

  public function __construct(){
    $this->_mappingInsurance = Helper::getMapping(['tableName'=>T::$vendorInsurance]);
    $this->_viewTable = T::$vendorInsuranceView;
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
   
    $formInsurance = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'orderField'=>self::_getOrderField('createInsurance'), 
      'setting'   =>self::_getSetting('createInsurance', $req)
    ]);
    
    $formInsurance2 = Form::generateForm([
      'tablez'      => self::_getTable(__FUNCTION__),
      'orderField'  => self::_getOrderField('createInsurance2'),
      'setting'     => self::_getSetting('createInsurance2',$req),
    ]);
    
    $formMonthlyPayment = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'button'    =>self::_getButton(__FUNCTION__), 
      'orderField'=>self::_getOrderField('createMonthlyPayment'), 
      'setting'   =>self::_getSetting('createMonthlyPayment', $req)
    ]);
    
    return view($page, [
      'data'=>[
        'formInsurance'      => $formInsurance,
        'formInsurance2'     => $formInsurance2,
        'formMonthlyPayment' => $formMonthlyPayment,
        'upload'             => Upload::getHtml()
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
          T::$vendor.'|vendor_id,vendid',
          T::$glChart.'|gl_acct',
          T::$prop.'|prop'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $uuid = !empty($vData['uuid']) ? explode(',', (rtrim($vData['uuid'], ','))) : [];
    unset($vData['uuid']);

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $updateDataSet = $response = $success = $elastic = [];
    try{
      $success += Model::insert([T::$vendorInsurance=>$vData]);
      $insuranceId = $success['insert:' . T::$vendorInsurance][0];
      
      if(!empty($uuid)) {
        foreach($uuid as $v){
          $updateDataSet[T::$fileUpload][] = ['whereData'=>['uuid'=>$v], 'updateData'=>['foreign_id'=>$insuranceId]];
        }
        $success += Model::update($updateDataSet);
      }
      $elastic = [
        'insert'=>[$this->_viewTable=>['vendor_insurance_id'=>[$insuranceId]]]
      ];
      $response['insuranceId'] = $insuranceId;
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
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['vendor_insurance_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    $r['uuid'] = '';
    $insuranceFiles = [];
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    foreach($fileUpload as $v){
      if($v['type'] == 'insurance'){
        $insuranceFiles[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($insuranceFiles, '/uploadInsurance');
    
    $formInsurance  = Form::generateForm([
      'tablez'    => self::_getTable('update'), 
      'orderField'=> self::_getOrderField('editInsurance'), 
      'setting'   => self::_getSetting('updateInsurance', $req, $r)
    ]);
    
    $formInsurance2 = Form::generateForm([
      'tablez'      => self::_getTable('update'),
      'orderField'  => self::_getOrderField('editInsurance2'),
      'setting'     => self::_getSetting('updateInsurance2',$req,$r),
    ]);
    
    $formMonthlyPayment = Form::generateForm([
      'tablez'    => self::_getTable('update'), 
      'button'    => self::_getButton(__FUNCTION__, $req), 
      'orderField'=> self::_getOrderField('editMonthlyPayment'), 
      'setting'   => self::_getSetting('updateMonthlyPayment', $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'formInsurance'      => $formInsurance,
        'formInsurance2'     => $formInsurance2,
        'formMonthlyPayment' => $formMonthlyPayment,
        'upload'             => Upload::getHtml(),
        'fileUploadList'     => $fileUploadList
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['vendor_insurance_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => self::_getTable(__FUNCTION__),
      'setting'     => self::_getSetting(__FUNCTION__, $req),
      'includeCdate' => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorInsurance.'|vendor_insurance_id'
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    $msgKey  = count(array_keys($vData)) > 3 ? 'msg' : 'mainMsg';
    if(!empty($vData['vendid']) && !empty($vData['gl_acct']) && !empty($vData['prop'])){
      V::validateionDatabase(['mustExist'=>['vendor|vendid', 'gl_chart|gl_acct', 'prop|prop']], $valid);
    }
    $vData['usid'] = Helper::getUsid($req);
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$vendorInsurance=>['whereData'=>['vendor_insurance_id'=>$id],'updateData'=>$vData]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vendor_insurance_id'=>[$id]]]
      ];
      $response[$msgKey] = $this->_getSuccessMsg(__FUNCTION__);
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
    $req->merge(['vendor_insurance_id'=>$req['id']]);
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorInsurance . '|vendor_insurance_id',
        ]
      ]
    ]);
    $vData = $valid['data'];
    
    $insuranceIds = $vData['vendor_insurance_id'];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      foreach($insuranceIds as $id) {
        $success[T::$vendorInsurance][] = HelperMysql::deleteTableData(T::$vendorInsurance, Model::buildWhere(['vendor_insurance_id'=>$id]));
      }
      $commit['success']           = $success;
      $commit['elastic']['delete'] = [T::$vendorInsuranceView => ['vendor_insurance_id'=>$insuranceIds]];
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
    
    $defaultValidateDb = [
      'mustExist'=>[
        T::$vendor.'|vendor_id,vendid',
        T::$glChart.'|gl_acct',
        T::$prop.'|prop'
      ]
    ];
    
    $validateDb    = Helper::getValue('validateDatabase',$data,$defaultValidateDb);
    
    $valid         =  V::startValidate([
      'rawReq'            => $rawReq,
      'tablez'            => $tablez,
      'orderField'        => $orderField,
      'setting'           => $setting,
      'includeCdate'      => $includeCdate,
      'isPopupMsgError'   => $isPopup,
      'validateDatabase'  => $validateDb,
    ]);
    
    $vData      = !empty($valid['dataArr']) ? $valid['dataArr'] : [$valid['dataNonArr']];
    $uuid       = !empty($valid['dataNonArr']['uuid']) ? explode(',',(rtrim($valid['dataNonArr']['uuid'],','))) : [];
    unset($valid['dataNonArr']['uuid']);
    $dataset    = [];
    
    $rVendor    = Helper::keyFieldNameElastic(M::getVendorElastic(['vendid.keyword'=>array_column($vData,'vendid')],['vendor_id','vendid']),'vendid','vendor_id');
    
    foreach($vData as $i => $v){
      $rGlChart                       = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$v['prop']])),'gl_acct');
      $rService                       = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$v['prop']])),'service');
      $v['vendor_id']                 = Helper::getValue($v['vendid'],$rVendor);
      
      $insertRow                      = HelperMysql::getDataset([T::$vendorInsurance => $v],$usid,$rGlChart,$rService);
      $dataset[T::$vendorInsurance][] = $insertRow[T::$vendorInsurance];
    }
    return ['insertData'=>$dataset,'uuid'=>$uuid];
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$vendorInsurance, T::$fileUpload],
      'update' => [T::$vendorInsurance, T::$fileUpload],
      'destroy'=> [T::$vendorInsurance]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'createInsurance'      => ['uuid', 'vendid', 'vendor_id', 'invoice_date', 'amount', 'effective_date', 'policy_num', 'prop', 'bank', 'gl_acct', 'remark', 'auto_renew', 'ins_total', 'ins_building_val', 'ins_rent_val', 'ins_sf', 'payer'],
      'createInsurance2'     => ['broker','carrier','date_insured','occ','building_value','deductible','lor','building_ordinance','general_liability_limit','general_liability_deductible','insurance_company','insurance_premium','down_payment','installments'],
      'createMonthlyPayment' => ['number_payment', 'monthly_payment', 'start_pay_date'],
      'editInsurance'        => ['vendor_insurance_id', 'vendid', 'vendor_id', 'invoice_date', 'amount', 'effective_date', 'policy_num', 'prop', 'bank', 'gl_acct', 'remark', 'auto_renew', 'ins_total', 'ins_building_val', 'ins_rent_val', 'ins_sf', 'payer'],
      'editInsurance2'       => ['broker','carrier','date_insured','occ','building_value','deductible','lor','building_ordinance','general_liability_limit','general_liability_deductible','insurance_company','insurance_premium','down_payment','installments'],
      'editMonthlyPayment'   => ['number_payment', 'monthly_payment', 'start_pay_date', 'usid'],
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['insuranceedit']) ? array_merge($orderField['editInsurance'], $orderField['editInsurance2'], $orderField['editMonthlyPayment']) : [];
    $orderField['store']  = isset($perm['insurancecreate']) ? array_merge($orderField['createInsurance'],$orderField['createInsurance2'], $orderField['createMonthlyPayment']) : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $rBank = !empty($default) ? $this->_keyFieldBank(HelperMysql::getBank(Helper::getPropMustQuery($default,[],0), ['bank', 'cp_acct', 'name'], ['sort'=>'bank.keyword'], 0)) : [''=>'Select Bank'];
    $disabled = isset($perm['insuranceupdate']) ? [] : ['disabled'=>1];

    $setting = [
      'createInsurance' => [
        'field' => [
          'uuid'             => ['type'=>'hidden'],
          'vendid'           => ['label'=>'Vendor Id', 'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'vendor_id'        => ['type'=>'hidden'],
          'policy_num'       => ['label'=>'Policy Number'],
          'prop'             => ['label'=>'Property Number', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete'],
          'bank'             => ['type'=>'option', 'option'=>$rBank],
          'gl_acct'          => ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'auto_renew'       => ['type'=>'option', 'option'=> $this->_mappingInsurance['auto_renew']],
          'ins_total'        => ['label'=>'Total Insured Value'],
          'ins_building_val' => ['label'=>'Building Value'],
          'ins_rent_val'     => ['label'=>'Rents'],
          'ins_sf'           => ['label'=>'Ins SF'],
          'payer'            => ['type'=>'option', 'option'=> $this->_mappingInsurance['payer']]
        ],
        'rule'=>[
          'uuid' => 'nullable|string',
        ]
      ],
      'createInsurance2'     => [
        'field' => [
          'date_insured'                 => ['value'=>date('m/d/Y')],
          'occ'                          => ['label'=>'OCC','type'=>'option','option'=>$this->_mappingInsurance['occ']],
          'lor'                          => ['label'=>'LOR'],
        ],
      ],
      'createMonthlyPayment' => [
        'field' => [
          'number_payment'  => ['label'=>'# of Monthly Payments', 'type'=>'option', 'option'=> $this->_mappingInsurance['number_payment']],
          'monthly_payment' => ['label'=>'Monthly Payment', 'readonly'=>1],
          'start_pay_date'  => ['label'=>'Monthly Start Pay Date', 'readonly'=>1]
        ]  
      ],
      'updateInsurance' => [
        'field' => [
          'vendor_insurance_id' => $disabled + ['type' =>'hidden'],
          'vendid'              => $disabled + ['label'=>'Vendor Id', 'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'vendor_id'           => $disabled + ['type' =>'hidden'],
          'invoice_date'        => $disabled,
          'amount'              => $disabled,
          'effective_date'      => $disabled,
          'policy_num'          => $disabled + ['label'=>'Policy Number'],
          'prop'                => $disabled + ['label'=>'Property Number', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'bank'                => $disabled + ['type'=>'option', 'option'=>$rBank],  
          'gl_acct'             => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'remark'              => $disabled,
          'auto_renew'          => $disabled + ['type'=>'option', 'option'=> $this->_mappingInsurance['auto_renew']],
          'ins_total'           => $disabled + ['label'=>'Total Insured Value'],
          'ins_building_val'    => $disabled + ['label'=>'Building Value'],
          'ins_rent_val'        => $disabled + ['label'=>'Rents'],
          'ins_sf'              => $disabled + ['label'=>'Ins SF'],
          'payer'               => $disabled + ['type'=>'option', 'option'=> $this->_mappingInsurance['payer']]
        ]
      ],
      'updateInsurance2'     => [
        'field' => [
          'date_insured'                 => $disabled +  ['value'=>date('m/d/Y')],
          'occ'                          => $disabled + ['label'=>'OCC','type'=>'option','option'=>$this->_mappingInsurance['occ']],
          'lor'                          => $disabled + ['label'=>'LOR'],
        ],
      ],
      'updateMonthlyPayment' => [
        'field' => [
          'number_payment'  => $disabled + ['label'=>'# of Monthly Payments', 'type'=>'option', 'option'=> $this->_mappingInsurance['number_payment']],
          'monthly_payment' => $disabled + ['label'=>'Monthly Payment', 'readonly'=>1],
          'start_pay_date'  => $disabled + ['label'=>'Monthly Start Pay Date', 'readonly'=>1],
          'usid'            => $disabled + ['label'=>'Last Updated By', 'readonly'=>1]
        ]  
      ]
    ];
    
    $setting['update'] = isset($perm['insuranceupdate']) ? array_merge($setting['updateInsurance'], $setting['updateInsurance2'], $setting['updateMonthlyPayment']) : [];
    $setting['store'] = isset($perm['insurancecreate']) ? array_merge($setting['createInsurance'], $setting['createInsurance2'], $setting['createMonthlyPayment']) : [];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['invoice_date']['value']   = Format::usDate($default['invoice_date']);
        $setting[$fn]['field']['effective_date']['value'] = Format::usDate($default['effective_date']);
        $setting[$fn]['field']['start_pay_date']['value'] = Format::usDate($default['start_pay_date']);
        $setting[$fn]['field']['date_insured']['value']   = Format::usDate($default['date_insured']);
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------s
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit'  =>isset($perm['insuranceupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
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
      $source['num']              = $vData['offset'] + $i + 1;
      $source['action']           = implode(' | ', $actionData['icon']);
      $source['linkIcon']         = Html::getLinkIcon($source, ['prop']);
      $source['po_value']         = isset($source['po_value']) ? Format::usMoney($source['po_value']) : '';
      $source['ins_total']        = Format::usMoney($source['ins_total']);
      $source['ins_building_val'] = Format::usMoney($source['ins_building_val']);
      $source['ins_rent_val']     = Format::usMoney($source['ins_rent_val']);
      $source['amount']           = Format::usMoney($source['amount']);
      $source['monthly_payment']  = Format::usMoney($source['monthly_payment']);
      ## bank_id is used as list of banks for the bank dropdown
      $source['bank_id']          = isset($source['bank_id']) ? Helper::keyFieldName($source['bank_id'], 'bank', 'name') : '';

      $source['start_date']          = $source['start_date'] !== '1000-01-01' && $source['start_date'] !== '1969-12-31' ? $source['start_date'] : '';
      $source['date_insured']        = $source['date_insured'] !== '1000-01-01' && $source['date_insured'] !== '1969-12-31' ? $source['date_insured'] : '';
      $source['building_value']      = Format::usMoney($source['building_value']);
      $source['deductible']          = Format::usMoney($source['deductible']);
      $source['insurance_premium']   = Format::usMoney($source['insurance_premium']);
      $source['down_payment']        = Format::usMoney($source['down_payment']);
      $source['installments']        = Format::usMoney($source['installments']);
      
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
    if(isset($perm['insuranceExport'])) {
      $reportList['csv'] = 'Export to CSV';
    }
    
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['insurancecreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-upload']) . ' Upload Insurance',['id'=>'uploadInsurance','class'=>'btn btn-info']) . ' ';
      }
      
      if(isset($perm['approveInsurance'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-send-o']) . ' Send to Approval',['id'=>'request','class'=>'btn btn-info tip', 'title'=>'Send Check For Approval From Upper Managements', 'disabled'=>true]) . ' ';
      }
      if(isset($perm['insurancedestroy'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-trash']) . ' Delete', ['id'=> 'delete', 'class'=>'btn btn-danger','disabled'=>true]) . ' ';
      }
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if(isset($perm['insuranceupdate'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    $insuranceEditable = isset($perm['insuranceupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    $bankEditable = isset($perm['insuranceupdate']) ? ['editable'=> ['type'=>'select']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=>25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>50];
    $data[] = ['field'=>'entity_name', 'title'=>'Owner','sortable'=> true, 'filterControl'=> 'input', 'width'=>250];
    $data[] = ['field'=>'trust', 'title'=>'Trust','sortable'=> true, 'filterControl'=> 'input', 'width'=>50];
    $data[] = ['field'=>'vendid', 'title'=>'Vendor','sortable'=> true, 'filterControl'=> 'input', 'width'=>50];
    $data[] = ['field'=>'remark', 'title'=>'Remark','sortable'=> true, 'filterControl'=> 'input', 'width'=>250] + $insuranceEditable;
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=>50];
    $data[] = ['field'=>'number_of_units', 'title'=>'# Unit','sortable'=> true, 'filterControl'=> 'input', 'width'=>25];
    $data[] = ['field'=>'gl_acct', 'title'=>'GL','sortable'=> true, 'filterControl'=> 'input', 'width'=>50];
    $data[] = ['field'=>'bank', 'value'=>'bank', 'title'=>'Bank','sortable'=> true, 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Bank #', 'width'=>300] + $bankEditable;
    $data[] = ['field'=>'policy_num', 'title'=>'Policy #','sortable'=> true, 'filterControl'=> 'input', 'width'=>100] + $insuranceEditable;
    $data[] = ['field'=>'po_value', 'title'=>'PO. Price','sortable'=> true, 'filterControl'=> 'input', 'width'=>100];
    $data[] = ['field'=>'ins_total', 'title'=>'Ins Total Val','sortable'=> true, 'filterControl'=> 'input', 'width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'ins_building_val', 'title'=>'Ins Building Val','sortable'=> true, 'filterControl'=> 'input', 'width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'ins_rent_val', 'title'=>'Ins Rent Val','sortable'=> true, 'filterControl'=> 'input', 'width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'ins_sf', 'title'=>'Ins SF Val','sortable'=> true, 'filterControl'=> 'input', 'width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'amount', 'title'=>'First Payment','sortable'=> true, 'filterControl'=> 'input', 'width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'monthly_payment', 'title'=>'Monthly Payment','sortable'=> true, 'filterControl'=> 'input', 'width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'start_pay_date', 'title'=>'Monthly Due Date','sortable'=> true, 'filterControl'=> 'input', 'width'=>75] + $insuranceEditable;
    $data[] = $_getSelectColumn($perm, 'number_payment', '# Payment', 75, $this->_mappingInsurance['number_payment']);
    $data[] = $_getSelectColumn($perm, 'payer', 'Payer', 75, $this->_mappingInsurance['payer']);
    $data[] = ['field'=>'effective_date', 'title'=>'Eff. Date','sortable'=> true, 'filterControl'=> 'input', 'width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'note', 'title'=>'Note','sortable'=> true, 'filterControl'=> 'input','width'=>250] + $insuranceEditable;
    $data[] = ['field'=>'start_date','title'=>'PO. Date','sortable'=>true,'filterControl'=>'input','width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'broker','title'=>'Broker','sortable'=>true,'filterControl'=>'input','width'=>150] + $insuranceEditable;
    $data[] = ['field'=>'carrier','title'=>'Carrier','sortable'=>true,'filterControl'=>'input','width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'date_insured','title'=>'Date Insured','sortable'=>true,'filterControl'=>'input','width'=>75] + $insuranceEditable;
    $data[] = $_getSelectColumn($perm,'occ','OCC',50,$this->_mappingInsurance['occ']);
    $data[] = ['field'=>'building_value','title'=>'Building Val','sortable'=>true,'filterControl'=>'input','width'=>50] + $insuranceEditable;
    $data[] = ['field'=>'deductible','title'=>'Deductible','sortable'=>true,'filterControl'=>'input','width'=>50] + $insuranceEditable;
    $data[] = ['field'=>'lor','title'=>'LOR','sortable'=>true,'filterControl'=>'input','width'=>75] + $insuranceEditable;
    $data[] = ['field'=>'building_ordinance','title'=>'Building Ord.','sortable'=>true,'filterControl'=>'input','width'=>85] + $insuranceEditable;
    $data[] = ['field'=>'general_liability_limit','title'=>'Gen. Liability Limit','sortable'=>true,'filterControl'=>'input','width'=>65] + $insuranceEditable;
    $data[] = ['field'=>'general_liability_deductible','title'=>'Gen. Liability Deductible','sortable'=>true,'filterControl'=>'input','width'=>50] + $insuranceEditable;
    $data[] = ['field'=>'insurance_company','title'=>'Ins Company','sortable'=>true,'filterControl'=>'input','width'=>85] + $insuranceEditable;
    $data[] = ['field'=>'insurance_premium','title'=>'Ins Premium','sortable'=>true,'filterControl'=>'input','width'=>50] + $insuranceEditable;
    $data[] = ['field'=>'down_payment','title'=>'Down Payment','sortable'=>true,'filterControl'=>'input','width'=>50] + $insuranceEditable;
    $data[] = ['field'=>'installments','title'=>'Installments','sortable'=>true,'filterControl'=>'input'] + $insuranceEditable;
    
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update' =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted Insurance'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['insuranceedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Insurance Information"></i></a>';
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