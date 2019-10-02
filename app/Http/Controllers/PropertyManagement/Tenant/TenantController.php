<?php
namespace App\Http\Controllers\PropertyManagement\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Upload,Html, GridData, Account, TableName AS T, Format, Helper, HelperMysql};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class

class TenantController extends Controller{
  private $_viewPath        = 'app/PropertyManagement/Tenant/tenant/';
  private $_viewTable       = '';
  private $_indexMain       = '';
  
  public function __construct(Request $req){
    $this->_viewTable = T::$tenantView;
    // $this->perm       = $this->_getPermission($req);
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_mappingTenant = Helper::getMapping(['tableName'=>T::$tenant]);
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $op   = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . 'index';
    $initData = $this->_getColumnButtonReportList($req);
    switch ($op){
      case 'column':
        return $initData;
      case 'show':
//        $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
        $vData = $req->all();
        $vData['defaultSort'] = ['prop.keyword:asc', 'unit.keyword:asc', 'tenant:desc'];
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
  public function show($id, Request $req){
    
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['tenant_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    $formTenant = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editTenantInfo', $req),
      'setting'   =>$this->_getSetting('updateTenant', $req, $r)
    ]);
    
    $formRent1 = Form::generateForm([
      'tablez'    =>$this->_getTable('update'),
      'orderField'=>$this->_getOrderField('editRentInfo1', $req),
      'setting'   =>$this->_getSetting('updateTenant', $req, $r)
    ]);
    
    $formRent2 = Form::generateForm([
      'tablez'    =>$this->_getTable('update'),
      'orderField'=>$this->_getOrderField('editRentInfo2', $req),
      'setting'   =>$this->_getSetting('updateTenant', $req, $r)
    ]);
    
    $formRent3 = Form::generateForm([
      'tablez'    =>$this->_getTable('update'),
      'orderField'=>$this->_getOrderField('editRentInfo3', $req),
      'setting'   =>$this->_getSetting('updateTenant', $req, $r),
      'button'    =>$this->_getButton(__FUNCTION__, $req)
    ]);
    
    return view($page, [
      'data'=>[
        'formTenant' => $formTenant,
        'formRent1'  => $formRent1,
        'formRent2'  => $formRent2,
        'formRent3' => $formRent3
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['tenant_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting('updateTenant', $req),
  //    'orderField'  => $this->_getOrderField('update', $req),
      'includeCdate'=> 0,
      'includeUsid' => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tenant.'|tenant_id',
        ]
      ]
    ]);
    $perm = Helper::getPermission($req);
    $updateData = $insertData = $updateUnitData = [];
    $vData = $valid['dataNonArr'];
    $rTenant = M::getTenant(Model::buildWhere(['tenant_id'=>$vData['tenant_id']]));
    $rUnit   = M::getUnit(Model::buildWhere(Helper::selectData(['prop', 'unit'], $rTenant)));
 
    ## If tenant spec_code is changed from evicted to something else
    if(isset($vData['spec_code']) && $rTenant['spec_code'] == 'E' && $vData['spec_code'] != 'E') {
      ## If the user doesnt have permission then return error
      if(!isset($perm['tenantEvictionProcessdestroy'])) {
        Helper::echoJsonError($this->_getErrorMsg('evictionPerm'), 'popupMsg');
      }else {
        $rTntEvictionProcess = Helper::getElasticResult(Elastic::searchQuery([
          'index'   =>T::$tntEvictionProcessView,
          '_source' =>['tnt_eviction_process_id', 'tnt_eviction_event'],
          'query'   =>['must'=>['prop.keyword'=>$rTenant['prop'], 'unit'=>$rTenant['unit'], 'tenant'=>$rTenant['tenant']]]
        ]), 1);
        if(!empty($rTntEvictionProcess)) {
          $rTntEvictionProcess  = $rTntEvictionProcess['_source'];
          $tntEvictionProcessId = $rTntEvictionProcess['tnt_eviction_process_id'];
          $tntEvictionEventId   = array_column($rTntEvictionProcess['tnt_eviction_event'], 'tnt_eviction_event_id');
        }
      }
    }else if(isset($vData['spec_code']) && $vData['spec_code'] != $rTenant['spec_code'] && $vData['spec_code'] == 'E') {
      ## If tenant spec_code is changed to eviction, insert to tnt_eviction_process
      $insertData[T::$tntEvictionProcess] = [
        'tenant_id'      => $vData['tenant_id'],
        'process_status' => 0,
        'result'         => '',
        'attorney'       => Helper::getValue('attorney', $vData),
        'cdate'          => Helper::mysqlDate(),
        'usid'           => $vData['usid'] 
      ];
      unset($vData['attorney']);
    }
    $msgKey  = count(array_keys($vData)) > 3 ? 'msg' : 'mainMsg';
    if(!empty($vData['prop']) && !empty($vData['unit'])){
      V::validateionDatabase(['mustExist'=>[T::$unit . '|prop,unit']], $valid);
    } else if(!empty($vData['prop'])){
      V::validateionDatabase(['mustExist'=>[T::$prop . '|prop']], $valid);
    }  
    $vData['status']  = Helper::getValue('status',$vData,$rTenant['status']);
    $updateData = [
      T::$tenant=>[
        'whereData'=>['tenant_id'=>$id],'updateData'=>$vData
      ]
    ];
    
    $unitField       = ['move_in_date'];
    $updateUnitData  += !empty($vData['move_in_date']) ? ['move_in_date'=>$vData['move_in_date']]  : [];
    $updateUnitData  += isset($vData['base_rent']) ? ['rent_rate'=>$vData['base_rent']] : [];
    
    if(!empty($updateUnitData)){
      $updateData[T::$unit][] = [
        'whereData' => ['unit_id'=>$rUnit['unit_id']],
        'updateData'=> $updateUnitData + ['status'=>$vData['status'] == 'C' || $vData['status'] == 'F' ? 'C' : 'V'],
      ];
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $insertEvent = [];
    try{
      $success += Model::update($updateData);
      $elastic['insert'][T::$tenantView] = ['t.tenant_id'=>[$id]];
      $elastic['insert'][T::$unitView]   = ['u.unit_id'=>[$rUnit['unit_id']]];
      if(!empty($insertData)) {
        $success += Model::insert($insertData);
        $tntEvictionProcessId = $success['insert:' . T::$tntEvictionProcess][0];
        $insertEvent[T::$tntEvictionEvent] = [
          'tnt_eviction_process_id' => $tntEvictionProcessId,
          'status'  => 0,
          'subject' => 'Eviction Start',
          'remark'  => 'Eviction Process Start',
          'date'    => Helper::date(),
          'cdate'   => Helper::mysqlDate(),
          'usid'    => $vData['usid']
        ];
        $success += Model::insert($insertEvent);
        $elastic['insert'][T::$tntEvictionProcessView] = ['ep.tnt_eviction_process_id'=>[$tntEvictionProcessId]];
      }else if(!empty($rTntEvictionProcess)) {
        ## Delete tnt_eviction_process if the spec_code changes from evicted to something else
        $success[T::$tntEvictionProcess] = DB::table(T::$tntEvictionProcess)->where(Model::buildWhere(['tnt_eviction_process_id'=>$tntEvictionProcessId]))->delete();
        $success[T::$tntEvictionEvent] = DB::table(T::$tntEvictionEvent)->whereIn('tnt_eviction_event_id',$tntEvictionEventId)->delete();
        $elastic[] = Elastic::delete(T::$tntEvictionProcessView, $tntEvictionProcessId);
      }
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
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
    
    $formTenant = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createTenantInfo', $req),
      'setting'   =>$this->_getSetting('createTenant', $req)
    ]);
    
    $formUnit = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createUnitInfo', $req),
      'setting'   =>$this->_getSetting('createTenant', $req),
      'button'    =>$this->_getButton(__FUNCTION__, $req)
    ]);
    return view($page, [
      'data'=>[
        'formTenant' => $formTenant,
        'formUnit'  => $formUnit,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable('create'), 
      'orderField'      => $this->_getOrderField(__FUNCTION__, $req),
      'setting'         => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate'    =>0,
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          'prop|prop',
          'unit|prop,unit'
        ],
        'mustNotExist'=>[
          'tenant|prop,unit,tenant'
        ]
      ]
    ]);
    $usr     = $req['ACCOUNT']['email'];
    $vData   = $valid['dataNonArr'];
    $prop    = $vData['prop'];
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(['g.prop'=>$prop]), 'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(['prop'=>$prop]), 'service');
    
    $dataset[T::$tenant] = $vData;
    $insertData = HelperMysql::getDataSet($dataset,$usr, $glChart, $service);
    
    $unitId = HelperMysql::getUnit(['prop.prop.keyword'=>$prop, 'unit'=>$vData['unit']], ['unit_id'])['unit_id'];
    $updateData[T::$unit] = [
      'whereData'=>['unit_id'=>$unitId],'updateData'=>['status'=>'C', 'move_out_date'=>'9999-12-31']
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert($insertData);
      $success += Model::update($updateData);
      $tenantId = $success['insert:' . T::$tenant][0];
      $elastic = [
        'insert'=>[
          T::$tenantView => ['t.tenant_id'=>[$tenantId]],
          T::$unitView   => ['u.unit_id'=>[$unitId]]
        ]
      ];
      $response['tenantId'] = $tenantId;
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
  public function destroy($id){
    $valid = V::startValidate([
      'rawReq' => [
        'tenant_id' => $id,
      ],
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          'tenant|tenant_id',
        ]
      ]
    ]);
    
    $vData = $valid['dataNonArr'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      $success[T::$tenant] = DB::table(T::$tenant)->where(Model::buildWhere(['tenant_id'=>$vData['tenant_id']]))->delete();
      $commit['success'] = $success;
      $commit['elastic'] = [
        'delete'=>[$this->_viewTable => ['tenant_id'=>$vData['tenant_id']]]
      ];
      
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
      'update'     =>[T::$tenant, T::$tntEvictionProcess],
      'create'     =>[T::$tenant],
      'destroy'    =>[T::$tenant]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $changeRent = isset($perm['tenantcreatechangeRent']) ? [] : ['readonly'=>1];
    $rUnit = !empty($default) ? Helper::keyFieldName(M::getUnit(Model::buildWhere(['prop'=>$default['prop']]), ['unit'], 0), 'unit', 'unit') : [''=>'Select Unit'];
    $disabled = isset($perm['tenantupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'updateTenant' => [
        'field' => [
          'tenant'            => $disabled + ['label'=>'Tenant #'],
          'tenant_id'         => $disabled + ['type' =>'hidden'],
          'prop'              => $disabled + ['label'=>'Property', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'unit'              => $disabled + ['type'=>'option', 'option'=>$rUnit],
          'tnt_name'          => $disabled + ['label'=>'Tenant Name'],
          'move_in_date'      => $disabled,
          'move_out_date'     => $disabled + ['readonly'=>1],
          'base_rent'         => $disabled + $changeRent + ['label'=>'Current Rent'],
          'dep_held1'         => $disabled + ['label'=>'Deposit'] + $changeRent,
          'late_after'        => $disabled,
          'late_rate_code'    => $disabled,
          'last_late_date'    => $disabled,
          'lease_start_date'  => $disabled,
          'lease_exp_date'    => $disabled,
          'lease_opt_date'    => $disabled,
          'lease_esc_date'    => $disabled,
          'housing_dt1'       => $disabled,
          'housing_dt2'       => $disabled + ['readonly'=>1],
          'comm_pct'          => $disabled,
          'dep_date'          => $disabled,
          'dep_int_last_date' => $disabled,
          'status'            => $disabled + ['readonly'=>1],
          'spec_code'         => $disabled + ['type'=>'option', 'option'=>$this->_mappingTenant['spec_code']],
          'isManager'         => $disabled + ['label'=>'Manager', 'type'=>'option', 'option'=>$this->_mappingTenant['isManager']],
          'phone1'            => $disabled + ['req'=>0, 'label'=>'Phone'],
          'fax'               => $disabled + ['req'=>0],
          'e_mail'            => $disabled + ['req'=>0, 'label'=>'E-Mail'],
          'web'               => $disabled + ['req'=>0],
          'cash_rec_remark'   => $disabled + ['req'=>0],
          'times_late'        => $disabled + ['req'=>0],
          'times_nsf'         => $disabled + ['req'=>0],
          'tax_code'          => $disabled + ['req'=>0],
          'dep_pct'           => $disabled + ['req'=>0, 'label'=>'Deposit %'],
          'ytd_int_paid'      => $disabled + ['req'=>0],
          'dep_held_int_amt'  => $disabled + ['req'=>0],
          'sales_off'         => $disabled + ['req'=>0],
          'sales_agent'       => $disabled + ['req'=>0],
          'return_no'         => $disabled + ['req'=>0, 'readonly'=>1],
          'passcode'          => $disabled + ['req'=>0],
          'billed_deposit'    => $disabled + ['req'=>0],
          'co_signer'         => $disabled + ['req'=>0],
          'statement'         => $disabled + ['req'=>0],
          'terms'             => $disabled + ['req'=>0],
          'tenant_class'      => $disabled + ['req'=>0],
          'bank_acct_no'      => $disabled + ['req'=>0],
          'late_rate1'        => $disabled + ['req'=>0],
          'late_amount'       => $disabled + ['req'=>0],
          'bank_transit'      => $disabled + ['req'=>0],
          'bal_code'          => $disabled + ['req'=>0],
          'last_check_date'   => $disabled + ['req'=>0],
          'last_check'        => $disabled + ['req'=>0],
          'appl_inseq'        => $disabled + ['req'=>0],
          'bill_day'          => $disabled + ['req'=>0],
          'tax_rate'          => $disabled + ['req'=>0],
          'usid'              => $disabled + ['label'=>'Last Updated By', 'readonly'=>1]
        ],
        'rule'=>[
          'phone1'            => 'nullable|string|between:9,255',
          'fax'               => 'nullable|string|between:9,255',
          'e_mail'            => 'nullable|string|between:1,50',
          'web'               => 'nullable|string|between:1,255',
          'cash_rec_remark'   => 'nullable|string|between:1,20',
          'times_late'        => 'nullable|numeric|between:0,999999999999',
          'times_nsf'         => 'nullable|numeric|between:0,999999999999',
          'tax_code'          => 'nullable|string|between:1,1',
          'dep_pct'           => 'nullable|numeric|between:0,999999999999',
          'ytd_int_paid'      => 'nullable|numeric|between:0,999999999999',
          'dep_held_int_amt'  => 'nullable|numeric|between:0,999999999999',
          'sales_off'         => 'nullable|string|between:1,2',
          'comm_pct'          => 'nullable',
          'sales_agent'       => 'nullable|string|between:1,2',
          'return_no'         => 'nullable|string|between:1,10',
          'passcode'          => 'nullable|string|between:1,12',
          'billed_deposit'    => 'nullable|numeric|between:0,999999999999',
          'co_signer'         => 'nullable|string|between:1,1',
          'statement'         => 'nullable|string|between:1,1',
          'terms'             => 'nullable|string|between:1,1',
          'tenant_class'      => 'nullable|string|between:1,4',
          'bank_acct_no'      => 'nullable|string|between:1,18',
          'late_rate1'        => 'nullable|numeric|between:0,999999999999',
          'late_amount'       => 'nullable|numeric|between:0,999999999999',
          'bank_transit'      => 'nullable|string|between:1,9',
          'bal_code'          => 'nullable|string|between:1,1',
          'last_check_date'   => 'nullable|string|between:10,10',
          'last_check'        => 'nullable|string|between:1,8',
          'appl_inseq'        => 'nullable',
          'bill_day'          => 'nullable',
          'tax_rate'          => 'nullable',
          'attorney'          => 'nullable'
        ]
      ],
      'createTenant' => [
        'field' => [
          'tnt_name'          => ['label'=>'Tenant Name'],
          'base_rent'         => ['label'=>'Current Rent'],
          'tenant'            => ['label'=>'Tenant #', 'value'=>1, 'readonly'=>'readonly'],
          'prop'              => ['label'=>'Property', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'unit'              => ['type'=>'option', 'option'=>$rUnit],
          'status'            => ['type'=>'option', 'option'=>$this->_mappingTenant['status'], 'value'=>'C'],
          'spec_code'         => ['type'=>'option', 'option'=>$this->_mappingTenant['spec_code'], 'value'=>'R'],
          'isManager'         => ['label'=>'Manager', 'type'=>'option', 'option'=>$this->_mappingTenant['isManager'], 'value'=>'0'],
          'phone1'            => ['label'=>'Phone', 'req'=>0],
          'fax'               => ['req'=>0],
          'e_mail'            => ['label'=>'E-Mail', 'req'=>0],
          'web'               => ['req'=>0],
          'dep_held1'         => ['label'=>'Deposit'],
          'late_after'        => ['value'=>'3'],
          'late_rate_code'    => ['value'=>'M'],
          'move_in_date'      => ['value'=>Helper::usDate()]
        ],
        'rule'=>[
          'phone1'            => 'nullable|string|between:9,255',
          'fax'               => 'nullable|string|between:9,255',
          'e_mail'            => 'nullable|string|between:1,50',
          'web'               => 'nullable|string|between:1,255',
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['tenantedit']) ? $setting['updateTenant'] : [];
    $setting['store'] = isset($perm['tenantcreate']) ? $setting['createTenant'] : [];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['move_in_date']['value']     = Format::usDate($default['move_in_date']);
        $setting[$fn]['field']['move_out_date']['value']    = Format::usDate($default['move_out_date']);
        $setting[$fn]['field']['last_late_date']['value']   = Format::usDate($default['last_late_date']);
        $setting[$fn]['field']['lease_start_date']['value'] = Format::usDate($default['lease_start_date']);
        $setting[$fn]['field']['lease_exp_date']['value']   = Format::usDate($default['lease_exp_date']);
        $setting[$fn]['field']['lease_opt_date']['value']   = Format::usDate($default['lease_opt_date']);
        $setting[$fn]['field']['lease_esc_date']['value']   = Format::usDate($default['lease_esc_date']);
        $setting[$fn]['field']['housing_dt1']['value']      = Format::usDate($default['housing_dt1']);
        $setting[$fn]['field']['housing_dt2']['value']      = Format::usDate($default['housing_dt2']);
        $setting[$fn]['field']['dep_date']['value']         = Format::usDate($default['dep_date']);
        $setting[$fn]['field']['dep_int_last_date']['value']= Format::usDate($default['dep_int_last_date']);
        $setting[$fn]['field']['last_check_date']['value']  = Format::usDate($default['last_check_date']);
      }
    }
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $button = [
      'edit'  =>  isset($perm['tenantupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
      'create'=>['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']]
    ];
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'createTenantInfo' => ['tenant', 'tnt_name', 'move_in_date', 'status', 'spec_code', 'isManager', 'phone1', 'fax', 'e_mail'],
      'createUnitInfo'  => ['prop', 'unit', 'base_rent', 'dep_held1', 'late_after', 'late_rate_code'],
      'editTenantInfo' => ['tenant_id', 'tenant', 'prop', 'unit', 'tnt_name', 'move_in_date', 'move_out_date', 'status', 'spec_code', 'isManager', 'phone1', 'fax', 'e_mail', 'web', 'cash_rec_remark'],
      'editRentInfo1'  => ['base_rent', 'dep_held1', 'late_after', 'late_rate_code', 'late_rate1', 'times_late', 'times_nsf', 'late_amount', 'last_late_date', 'lease_start_date', 'lease_exp_date', 'lease_opt_date', 'lease_esc_date', 'tax_code', 'tax_rate'],
      'editRentInfo2'  => ['housing_dt1', 'housing_dt2', 'dep_pct', 'ytd_int_paid', 'dep_held_int_amt', 'comm_pct', 'dep_date', 'dep_int_last_date', 'sales_off', 'sales_agent' ],
      'editRentInfo3'  => ['passcode', 'billed_deposit', 'appl_inseq', 'co_signer', 'statement', 'terms', 'tenant_class', 'bank_acct_no', 'bank_transit', 'bill_day', 'bal_code', 'last_check_date', 'last_check', 'usid']
    ];
    $orderField['update'] = isset($perm['tenantedit']) ? array_merge($orderField['editTenantInfo'],$orderField['editRentInfo1'],$orderField['editRentInfo2'],$orderField['editRentInfo3']) : [];
    $orderField['store'] = isset($perm['tenantcreate']) ? array_merge($orderField['createTenantInfo'],$orderField['createUnitInfo']) : [];

    # IF USER DOES NOT HAVE PERMISSION, DELETE ALL THE RESTRICTED FIELD
    if(!isset($perm['tenantcreatechangeRent'])){
      $orderField['update'] = Helper::arrayDelete(['base_rent', 'dep_held1'], $orderField['update']);
    }
    return $orderField[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm     = Helper::getPermission($req);
    $rows     = $tntMoveOutProcess = $props = [];
    $iconList = ['prop','unit', 'ledgercard', 'tenantstatement', 'ledgercardpdf'];
    
    ## Get tenant move out process to check the status for the undo moveout icon
    foreach($r['hits']['hits'] as $val) {
      $source = $val['_source']; 
      $props[$source['prop']] = $source['prop'];
    }
    $props = array_values($props);
    $rTntMoveOutProcess = Elastic::searchQuery([
      'index'   => T::$tntMoveOutProcessView,
      '_source' => ['prop', 'unit', 'tenant', 'status'],
      'query'   => ['must'=>['prop.keyword'=> $props, 'status'=>'0']]
    ]);
    $tntMoveOutProcess  = Helper::keyFieldNameElastic($rTntMoveOutProcess, ['prop', 'unit', 'tenant'], 'status');

    foreach($r['hits']['hits'] as $i=>$v){
      $source        = $v['_source']; 
      $actionData    = $this->_getActionIcon($perm, $source, $tntMoveOutProcess);
      $id            =  $source['tenant_id'];
      $source['num'] = $vData['offset'] + $i + 1;
      $source['linkIcon']  = Html::getLinkIcon($source,$iconList);
      $source['action']    = implode(' | ', $actionData['icon']);
//      $prop                = $source['prop'];
//      $unit                = $source['unit'];
//      $source['prop']      = Html::a($prop,['href'=>action('PropertyManagement\Prop\PropController@index',['prop'=>$prop]),'target'=>'_blank','title'=>'View Property']);
//      $source['unit']      = Html::a($unit,['href'=>action('PropertyManagement\Unit\UnitController@index',['prop.prop'=>$prop,'unit'=>$unit]),'target'=>'_blank','title'=>'View Unit']);
      $source['status']    = $this->_mappingTenant['status'][$source['status']];
      $source['spec_code'] = isset($perm['tenantupdate']) ? $source['spec_code'] :  $this->_mappingTenant['spec_code'][$source['spec_code']];
  
      $rent = self::getTenantRent($source);
      
      $source['tnt_rent']  = Format::usMoney($rent['tntRent']);
      $source['hud']       = Format::usMoney($rent['hud']);
      $source['base_rent'] = Format::usMoney($source['base_rent']);
      $source['dep_held1'] = Format::usMoney($source['dep_held1']);
      
      $source['application'] = '';
      $source['agreement']   = '';
      
      if(!empty($source['fileUpload'])){
        foreach($source['fileUpload'] as $i=>$v){
          $source['agreement']   = !empty($source['agreement']) || (!empty($source['application_id']) && $v['type'] == 'agreement') ? Html::span('View',['class'=>'clickable']) : '';
          $source['application'] = !empty($source['application']) || (!empty($source['application_id']) && $v['type'] == 'application') ? Html::span('View',['class'=>'clickable']) : '';
        }
      }

      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $actionData = $this->_getActionIcon($perm);
    $changeRent = isset($perm['tenantcreatechangeRent']) ? ['editable'=> ['type'=> 'text']] : [];
    $tenantEditable = isset($perm['tenantupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    
    ### REPORT SECTION ###
    $reportList = isset($perm['tenantExport']) ? ['csv' => 'Export to CSV'] : [];
    
    ### BUTTON SECTION ###
    $_getCreateButton = function($perm){
      $button = '';
      if(isset($perm['tenantcreate'])) {
        $button .=  Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=>'new', 'class'=>'btn btn-success']) . ' ';
      }
      if(isset($perm['massivebilling'])) {
        $button .=  Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' Massive Billing', ['id'=>'massive', 'class'=>'btn btn-primary']) . ' ';
      }
      if(isset($perm['latecharge'])) {
        $button .=  Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' Late Charge', ['id'=>'lateCharge', 'class'=>'btn btn-warning']) . ' ';
      }
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if(isset($perm['tenantupdate'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon', 'title'=>'Link', 'width'=> 150];
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'unit', 'title'=>'Unit', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'tenant', 'title'=>'Tnt#', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'tnt_name', 'title'=>'Tenant Name', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 250] + $tenantEditable;
    $data[] = ['field'=>'street', 'title'=>'Address','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250];
    $data[] = ['field'=>'city', 'title'=>'City', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 150];
    $data[] = ['field'=>'status', 'title'=>'Status', 'filterControl'=> 'input', 'sortable'=> true, 'width'=>50];
    $data[] = $_getSelectColumn($perm, 'spec_code', 'Spec Code', 50, $this->_mappingTenant['spec_code']);
    $data[] = ['field'=>'bedrooms', 'title'=>'Beds', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 50];
    $data[] = ['field'=>'bathrooms', 'title'=>'Bath', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 50];
    $data[] = ['field'=>'lease_start_date', 'title'=>'Last Raise', 'sortable'=> true, 'filterControl'=> 'input', 'filterControlPlaceholder'=>'yyyy-mm-dd','width'=> 50];
    $data[] = ['field'=>'base_rent', 'title'=>'Current Rent','sortable'=> true, 'filterControl'=> 'input','width'=> 50] + $changeRent;
    $data[] = ['field'=>'tnt_rent', 'title'=>'Tnt Rent', 'width'=> 50];
    $data[] = ['field'=>'hud', 'title'=>'HUD', 'width'=> 50];  
    $data[] = ['field'=>'dep_held1', 'title'=>'Deposit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $changeRent;
    
//      ['field'=>'base_rent', 'title'=>'Base Rent','sortable'=> true, 'filterControl'=> 'input', 'editable'=> ['type'=> 'text'], 'width'=> 50],
//      ['field'=>'dep_held1', 'title'=>'Deposit','sortable'=> true, 'filterControl'=> 'input', 'editable'=> ['type'=> 'text'], 'width'=> 50],
//      ['field'=>'lease_start_date', 'title'=>'Lease Start Date','sortable'=> true, 'filterControl'=> 'input', 'editable'=> ['type'=> 'text'], 'width'=> 25],
//      ['field'=>'lease_exp_date', 'title'=>'Lease Expired Date','sortable'=> true, 'filterControl'=> 'input', 'editable'=> ['type'=> 'text'], 'width'=> 25],
    $data[] = ['field'=>'move_in_date', 'title'=>'Move In Date', 'sortable'=> true, 'filterControl'=> 'input', 'filterControlPlaceholder'=>'yyyy-mm-dd','width'=> 50];
    $data[] = ['field'=>'move_out_date', 'title'=>'Move Out Date','sortable'=> true, 'filterControl'=> 'input', 'filterControlPlaceholder'=>'yyyy-mm-dd','width'=> 50];
    if(isset($perm['tenantUploadAgreementindex'])){
      $data[] = ['field'=>'agreement','title'=>'Agreement','width'=>50];
    }
    
    if(isset($perm['tenantUploadApplicationindex'])){
      $data[] = ['field'=>'application','title'=>'Application','width'=>50];
    }
    $data[] = $_getSelectColumn($perm, 'isManager', 'Manager', 50, $this->_mappingTenant['isManager']);
    $data[] = ['field'=>'return_no', 'title'=>'Return NO.', 'filterControl'=> 'input', 'sortable'=> true];
    
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getCreateButton($perm)];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted Tenant'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name) {
    $data = [
      'evictionPerm' => Html::errMsg('You don\'t have the permission to undo eviction.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm, $r = [], $tntMoveOutProcess = []){
    $actionData = [];
    if(isset($perm['tenantedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Tenant"></i></a>';
    }
    
    if(isset($perm['tenantRemarkedit'])){
      $actionData[] = '<a class="editRemark" title="Add/Edit Remark"><i class="fa fa-book text-aqua pointer tip" title="Add/Edit Remark"></i></a>';
    }
    
    if(isset($perm['fullbillingedit'])){
      $actionData[] = '<a class="fullBilling" href="javascript:void(0)" title="Tenant Full Billing"><i class="fa fa-list text-aqua pointer tip" title="Tenant Full Billing"></i></a>';
    }
    
    if(isset($perm['tenantUploadAgreementedit']) && !empty($r) && $r['application_id'] > 0){
      $actionData[] = '<a class="documentUpload" href="javascript:void(0)"><i class="fa fa-upload text-aqua pointer tip" title="Upload Document File"></i></a>';
    }
    
    if(isset($perm['moveOut']) && isset($r['status']) && ($r['status'] == 'C' || $r['status'] == 'F') && $r['move_out_date'] == '9999-12-31'){
      $actionData[] = '<a class="moveOut" href="javascript:void(0)" title="Move Out Tenant"><i class="fa fa-share-square-o text-red pointer tip" title="Move Out Tenant"></i></a>';
    }else if(isset($perm['moveOutUndo']) && !empty($r) && isset($tntMoveOutProcess[$r['prop'] . $r['unit'] . $r['tenant']]) && $tntMoveOutProcess[$r['prop'] . $r['unit'] . $r['tenant']] == '0') {
      $actionData[] = '<a class="moveOutUndo" href="javascript:void(0)" title="Undo Move Out"><i class="fa fa-reply text-green pointer tip" title="Undo Move Out"></i></a>';
    }
    
    if(isset($perm['tenantdestroy'])){
      $actionData[] = '<a class="delete" href="javascript:void(0)" title="Delete Unit"><i class="fa fa-trash-o text-red pointer tip" title="Delete this Tenant"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
//------------------------------------------------------------------------------
  public static function getTenantRent($source) {
    $today = strtotime(Helper::date());
    $hud   = $tenantRent = 0;
    if(isset($source['billing'])) {
      foreach($source['billing'] as $billing) {
        if($billing['service_code'] != '607' && $billing['stop_date'] == '9999-12-31' && $billing['schedule'] == 'M') {
          if($billing['service_code'] == 'HUD') {
            $hud += $billing['amount'];
          }else if( ($billing['gl_acct'] == '602' && !preg_match('/MJC[1-9]+/', $source['prop'])) || ($billing['service_code'] == '633' && preg_match('/MJC[1-9]+/', $source['prop'])) ) {
            $tenantRent += $billing['amount'];
          }
        }
      }
    }
    return ['tntRent'=> $tenantRent, 'hud'=>$hud];
  }
}
