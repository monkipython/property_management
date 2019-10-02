<?php
namespace App\Http\Controllers\PropertyManagement\Prop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, Account, TableName AS T, HelperMysql};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\PropModel AS M; // Include the models class

class PropController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Prop/prop/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingProp   = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_mappingInfo   = Helper::getMapping(['tableName'=>T::$applicationInfo]);
    $this->_viewTable = T::$propView;
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
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Helper::getElasticResultSource(Elastic::searchQuery([
        'index' =>$this->_viewTable,
        'query' =>['must'=>['prop_id' => $id]]
    ]),1);
    
    $propWarning = Html::div('*To change Prop, do property transfer in the PPM.', ['class'=>'text-red']);
    
    $formProp = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editProperty'), 
      'setting'   =>$this->_getSetting('updateProperty', $req, $r)
    ]);
    
    $formTax = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editTax'), 
      'setting'   =>$this->_getSetting('updateTax', $req, $r)
    ]);
    
    $formAcc = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editAccounting'), 
      'setting'   =>$this->_getSetting('updateAccounting', $req, $r)
    ]);
    
    $formMgt1 = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editManagement1'), 
      'setting'   =>$this->_getSetting('updateManagement1', $req, $r)
    ]);
    
    $formMgt2 = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'button'    =>$this->_getButton(__FUNCTION__, $req),
      'orderField'=>$this->_getOrderField('editManagement2'), 
      'setting'   =>$this->_getSetting('updateManagement2', $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'formProp' => $formProp,
        'formTax'  => $formTax,
        'formAcc'  => $formAcc,
        'formMgt1' => $formMgt1,
        'formMgt2' => $formMgt2,
        'propWarning'=> $propWarning
      ]
    ]);
  }
  //------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['prop_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__, $req),
//      'orderField'  => $this->_getOrderField(__FUNCTION__, $req),
      'includeUsid' => 1,
      'includeCdate'=> 0,
      'validateDatabase'=>[
        'mustExist'=>[
          'prop|prop_id',
//          'prop|trust'
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    $insertData = $updateData = $deletePropId = [];
    $msgKey = count(array_keys($vData)) > 4 ? 'msg' : 'mainMsg';
    $prop   = Helper::getValue('prop', $vData); 
    $trust  = Helper::getValue('trust', $vData); 
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$prop=>['whereData'=>['prop_id'=>$id],'updateData'=>$vData],
    ];
    $rUnit            = HelperMysql::getUnit(['prop.prop.keyword'=>$prop], ['unit_id']);
    $rRentRaise       = Helper::getElasticResultSource(M::getRentRaiseElastic(['prop.keyword'=>$prop],['tenant_id']));
    $originalPropData = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'   =>$this->_viewTable,
      '_source' =>['prop', 'trust'],
      'query'   =>['must'=>['prop_id'=>$id]]
    ]), 1);
    if(!empty($trust) && $trust != $originalPropData['trust'] ) {
      ## Get prop_bank_id using original data to delete it 
      $result       = M::getTableData(T::$propBank, Model::buildWhere(['prop'=>$originalPropData['prop'], 'trust'=>$originalPropData['trust']]), 'prop_bank_id');
      $deletePropId = array_column($result, 'prop_bank_id');
      ## Get prop_bank data using the new trust to insert it
      $propBankFields  = ['prop', 'bank', 'gl_acct', 'trust', 'recon_prop', 'usid', 'rock'];
      $copiedPropBanks = M::getTableData(T::$propBank, Model::buildWhere(['prop'=>$trust, 'trust'=>$trust]), $propBankFields);
      if(empty($copiedPropBanks)) {
        Helper::echoJsonError($this->_getErrMsg('changeTrustError'));
      }
      $insertData[T::$propBank] = $this->_replaceArrayValue($copiedPropBanks, ['prop'], $prop, $vData['usid']);
    }
    /*
    ## If user changes prop
    if($vData['prop'] != $originalPropData['prop']) {
      V::validateionDatabase(['mustNotExist'=>['prop|prop']], $valid);
      $tntTrans = HelperMysql::getTntTrans(['prop.keyword'=>$originalPropData['prop']], ['prop']);
      $glTrans  = HelperMysql::getGlTrans(['prop.keyword'=>$originalPropData['prop']], ['prop']);
      if(!empty($tntTrans) || !empty($glTrans)) {
        Helper::echoJsonError($this->_getErrMsg('changePropError'));
      }
      $updateData[T::$service] = ['whereData'=>['prop'=>$originalPropData['prop']], 'updateData'=>['prop'=>$vData['prop']]];
      $updateData[T::$glChart] = ['whereData'=>['prop'=>$originalPropData['prop']], 'updateData'=>['prop'=>$vData['prop']]];
      ## Will use getServiceElastic After releasing the service module
      ##$serviceId = HelperMysql::getServiceElastic(['prop.keyword'=>$originalPropData['prop']], ['service_id']);
      ##$serviceId = !empty($serviceId) ? array_column(array_column($serviceId, '_source'), 'service_id') : [];
      ##$glChartId = HelperMysql::getGlChart(['prop.keyword'=>$originalPropData['prop']], ['gl_chart_id']);
      ##$glChartId = !empty($glChartId) ? array_column(array_column($glChartId, '_source'), 'gl_chart_id') : [];
      $serviceId = M::getTableData(T::$service, Model::buildWhere(['prop' => $originalPropData['prop']]), 'service_id');
      $serviceId = !empty($serviceId) ?  array_column($serviceId, 'service_id') : [];
      $glChartId = M::getTableData(T::$glChart, Model::buildWhere(['prop' => $originalPropData['prop']]), 'gl_chart_id');
      $glChartId = !empty($glChartId) ? array_column($glChartId, 'gl_chart_id') : [];
    }
    if( $vData['prop'] != $originalPropData['prop'] || $vData['trust'] != $originalPropData['trust'] ) {
      $result      = M::getTableData(T::$propBank, Model::buildWhere(['prop'=>$originalPropData['prop'], 'trust'=>$originalPropData['trust']]), 'prop_bank_id');
      $propBankId  = array_column($result, 'prop_bank_id');
      $updateData[T::$propBank] = ['whereInData'=>['field'=>'prop_bank_id', 'data'=>$propBankId], 'updateData'=>['prop' => $vData['prop'], 'trust' => $vData['trust']]];
    }
    */
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      if(!empty($copiedPropBanks)) {
        $success += Model::insert($insertData);
      }
      $elastic = [
        'insert'=>[
          $this->_viewTable => ['p.prop_id'=>[$id]]
        ]
      ];
      if(!empty($rUnit)) {
        $elastic['insert'][T::$unitView] = ['u.prop'   =>[$prop]];
      }
      if(!empty($copiedPropBanks)) {
        $elastic['insert'][T::$bankView] = ['pb.prop_bank_id' => $success['insert:'.T::$propBank]];
      }
      if(!empty($deletePropId)) {
        $success[T::$propBank] = DB::table(T::$propBank)->whereIn('prop_bank_id', $deletePropId)->delete();
        $elastic['delete'][T::$bankView] = ['prop_bank_id'=>$deletePropId];
      }
      /* Will uncomment After releasing the service module
      if(!empty($serviceId)) {
        $elastic['insert'][T::$serviceView] = ['service_id'=>$serviceId];
      }
      if(!empty($glChartId)) {
        $elastic['insert'][T::$glChartView] = ['gl_chart_id'=>$glChartId];
      }
       * 
       */
      $response[$msgKey] = $this->_getSuccessMsg(__FUNCTION__);

      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
      
      if(!empty($rRentRaise)){
        Model::commit([
          'success'  => $success,
          'elastic'  => ['insert'=>[T::$rentRaiseView=>['t.tenant_id'=>array_column($rRentRaise,'tenant_id')]]],
        ]);
      }
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
  //------------------------------------------------------------------------------
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
    $op = isset($req['op']) ? $req['op'] : 'init';

    // Get all the props for dropdown option 
    $rows = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   =>$this->_viewTable,
      '_source' =>['prop'],
      'sort'    =>['prop.keyword'=>'asc'],
    ]), 'prop', 'prop');
    $defaultProp = ($op === 'init') ? reset($rows) : $op;
    
    $propOption = Html::buildOption($rows, $defaultProp, ['class' => 'fm form-control form-control propOption', 'name' => 'copyProp']);
    $propWarning = Html::div('*Please make sure to create the trust first if it doesn\'t exist.', ['class'=>'text-red']);

    // Get the first matching prop to prefill the form
    $r = Helper::getElasticResultSource(Elastic::searchQuery([
        'index' =>$this->_viewTable,
        'query' =>['must'=>['prop.keyword' => $defaultProp]]
    ]),1);
    unset($r['prop']);
    
    $formProp = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createProperty'), 
      'setting'   =>$this->_getSetting('createProperty', $req, $r)
    ]);
    
    $formTax = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createTax'), 
      'setting'   =>$this->_getSetting('createTax', $req, $r)
    ]);
    
    $formAcc = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createAccounting'), 
      'setting'   =>$this->_getSetting('createAccounting', $req, $r)
    ]);
    
    $formMgt1 = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createManagement1'), 
      'setting'   =>$this->_getSetting('createManagement1', $req, $r)
    ]);
    
    $formMgt2 = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__),
      'orderField'=>$this->_getOrderField('createManagement2'), 
      'setting'   =>$this->_getSetting('createManagement2', $req, $r)
    ]);
    return view($page, [
      'data'=>[
        'formProp'   => $formProp,
        'formTax'    => $formTax,
        'formAcc'    => $formAcc,
        'formMgt1'   => $formMgt1,
        'formMgt2'   => $formMgt2,
        'propOption' => $propOption,
        'propWarning'=> $propWarning
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
      'includeUsid'     => 1,
      'includeCdate'    => 0,
      'validateDatabase'=>[
        'mustNotExist'=>[
          'prop|prop'
        ],
        'mustExist'=>[
          'prop|trust'
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    $trust   = $vData['trust'];
    $newProp = $vData['prop'];
    $copyProp= $vData['copyProp'];
//    $unitFields = ['prop', 'unit', 'building', 'floor', 'street', 'unit_no', 'remark', 'curr_tenant', 'future_tenant', 'owner', 'past_tenant', 'rent_rate', 'market_rent', 'sec_dep', 'sq_feet', 'sq_feet2', 'count_unit', 'move_in_date', 'move_out_date', 'usid', 'sys_date', 'status', 'status2', 'unit_type', 'style', 'bedrooms', 'bathrooms', 'rock', 'cd_enforce_dt1', 'cd_enforce_dt2', 'pad_size', 'mh_owner', 'must_pay', 'mh_serial_no'];
    $propBankFields = ['prop', 'bank', 'gl_acct', 'trust', 'recon_prop', 'usid', 'sys_date', 'rock'];
//    $bankFields = ['prop', 'bank', 'name', 'br_name', 'street', 'city', 'state', 'zip', 'phone', 'last_check_no', 'bank_bal', 'bank_reg', 'transit_cp', 'cp_acct', 'transit_cr', 'cr_acct', 'usid', 'sys_date', 'print_bk_name', 'print_prop_name', 'two_sign', 'void_after', 'rock', 'dump_group'];
    $serviceFields = ['prop', 'service', 'bill_seq', 'remark', 'remarks', 'amount', 'service_type', 'schedule', 'tax_cd', 'gl_acct', 'gl_acct_past', 'gl_acct_next', 'cam_exp_gl_acct', 'next_service', 'pm_post', 'usid', 'sys_date', 'mangt_cd', 'comm_cd', 'rock'];
    $glChartFields = ['prop', 'gl_acct', 'title', 'acct_type', 'no_post', 'type1099', 'remarks', 'usid', 'sys_date', 'rock'];
    
    $copiedPropBanks = M::getTableData(T::$propBank, Model::buildWhere(['prop' => $trust]), $propBankFields);
//    Pluck out the bank values from prop_bank to use in the where statement for banks table
//    $bankValues = array_pluck($copiedPropBanks, 'bank');
//    $copiedBanks = M::getTableData(T::$bank, Model::buildWhere(['prop' => $copiedPropBanks[0]['trust'], 'bank' => $bankValues]), $bankFields);
//    $copiedUnits = M::getTableData(T::$unit, Model::buildWhere(['prop' => $trust]), $unitFields);
   
    ## Will use getServiceElastic After releasing the service module
    ##$copiedService = HelperMysql::getServiceElastic(['prop.keyword'=>$trust], $serviceFields);
    ##$copiedService = !empty($copiedService) ? array_column($copiedService, '_source') : [];
    $copiedService = M::getTableData(T::$service, Model::buildWhere(['prop' => $copyProp]), $serviceFields);
    $copiedGlChart = M::getTableData(T::$glChart, Model::buildWhere(['prop' => $copyProp]), $glChartFields);
    
    if(empty($copiedPropBanks)) {
      Helper::echoJsonError($this->_getErrMsg('trustEmpty'));
    }
    //$copiedService   = !empty($copiedService) ? $copiedService : M::getTableData(T::$service, Model::buildWhere(['prop' => $copyProp]), $serviceFields);
    //$copiedGlChart   = !empty($copiedGlChart) ? $copiedGlChart : M::getTableData(T::$glChart, Model::buildWhere(['prop' => $copyProp]), $glChartFields);
 
    ## Check if service, glChart, propBank exists under the new prop
    $rCheckNewService  = M::getTableData(T::$service, Model::buildWhere(['prop' => $newProp]), ['service_id']);
    $rCheckNewGlChart  = M::getTableData(T::$glChart, Model::buildWhere(['prop' => $newProp]), ['gl_chart_id']);
    $rCheckNewPropBank = M::getTableData(T::$propBank, Model::buildWhere(['prop' => $newProp]), ['prop_bank_id']);

    unset($vData['copyProp']);
    if(empty($copiedPropBanks) || empty($copiedService) || empty($copiedGlChart)) {
      $errorMsg = [];
      $errorMsg[] = empty($copiedPropBanks) ? 'Bank' : '';
      $errorMsg[] = empty($copiedService)   ? 'Service' : '';
      $errorMsg[] = empty($copiedGlChart)   ? 'GL Chart' : '';
      $errorMsg = array_filter($errorMsg);
      $errorMsg = implode($errorMsg, ', ');
      Helper::echoJsonError($this->_getErrMsg('copiedTrustError', $errorMsg));
    }else if(!empty($rCheckNewService) || !empty($rCheckNewGlChart) || !empty($rCheckNewPropBank)) {
      $errorMsg = [];
      $errorMsg[] = !empty($rCheckNewPropBank) ? 'Bank' : '';
      $errorMsg[] = !empty($rCheckNewService)   ? 'Service' : '';
      $errorMsg[] = !empty($rCheckNewGlChart)   ? 'GL Chart' : '';
      $errorMsg = array_filter($errorMsg);
      $errorMsg = implode($errorMsg, ', ');
      Helper::echoJsonError($this->_getErrMsg('newPropError', $errorMsg));
    }
    ## Get bank id to reindex bank
//    $rBankId = M::getTableData(T::$bank, Model::buildWhere(['prop'=>$copiedPropBanks[0]['trust']]), 'bank_id');
//    $bankId = array_column($rBankId, 'bank_id');
    // Replace the copied trust with the new prop and remove the IDs
    $newPropBanks = $this->_replaceArrayValue($copiedPropBanks, ['prop'], $newProp, $vData['usid']);
    $newGlChart   = $this->_replaceArrayValue($copiedGlChart, ['prop'], $newProp, $vData['usid']);
    $newService   = $this->_replaceArrayValue($copiedService, ['prop'], $newProp, $vData['usid']);
    $insertData = [
      T::$prop     => $vData,
      T::$propBank => $newPropBanks,
      T::$service  => $newService,
      T::$glChart  => $newGlChart
    ];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert($insertData); 
//      dd($success, $insertData);
      $success['insert:'.T::$service] = 1;
//      $newUnits = $this->_replaceSingleValue($copiedUnits, 'prop', $newProp);
//      $success += Model::insert([T::$unit=>$newUnits, T::$propBank=>$newPropBanks, T::$bank=>$copiedBanks]);
//      $success += Model::insert([T::$propBank=>$newPropBanks, T::$bank=>$copiedBanks, T::$service=>$newServices, T::$glChart=>$newGlChart]);
      $elastic = [
        'insert'=>[
          $this->_viewTable => ['p.prop_id'   => $success['insert:'.T::$prop]],
          T::$bankView      => ['pb.prop_bank_id'   => $success['insert:' . T::$propBank]],
       //   T::$serviceView   => ['service_id'      => $success['insert:'.T::$service]],
          T::$glChartView   => ['gl_chart_id' => $success['insert:'.T::$glChart]]  
        ]
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
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$prop],
      'update' => [T::$prop]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'storeTrust'        => ['copyProp'],
      'createProperty'    => ['trust', 'prop', 'prop_name', 'line2', 'street', 'city', 'county', 'state', 'zip', 'prop_class', 'prop_type', 'rent_type','phone', 'last_raise_date'],
      'createTax'         => ['start_date', 'po_value', 'sq_feet', 'fed_id', 'state_id', 'tax_rate', 'tax_date', 'map_code'],
      'createAccounting'  => ['group1', 'group2', 'cons1', 'cons2', 'cons_flg', 'post_flg', 'ar_bank', 'ar_contra', 'ar_sum_acct', 'ar_sum_contra', 'ap_bank', 'ap_contra', 'ap_sum_acct', 'ap_sum_contra', 'trans_code_1099', 'state_ui_pct', 'dep_int_pct', 'cash_accrual', 'name_1099', 'ownergroup', 'year_end_acct'],
      'createManagement1' => ['mangtgroup', 'mangtgroup2', 'man_fee_date', 'mangt_pct', 'man_flat_amt', 'man_pct', 'comm_pct', 'late_max_amt', 'late_min_amt', 'late_int_pct', 'late_day2', 'late_day3', 'vendor_list', 'no_post_dt', 'ap_inv_edit', 'partner_no', 'man_flg', 'bank_reserve', 'raise_pct', 'raise_pct2'],
      'createManagement2' => ['dep_int_code1', 'dep_int_code2', 'dep_int_pay', 'late_rate_code', 'late_rate', 'late_lump_amt', 'year_end', 'first_month', 'next_year_end', 'start_year', 'last_year_end', 'start_last_year', 'isFreeClear'],
      'editProperty'    => ['prop_id', 'trust', 'prop', 'prop_name', 'line2', 'street', 'city', 'county', 'state', 'zip', 'prop_class', 'prop_type', 'rent_type','phone', 'last_raise_date'],
      'editTax'         => ['start_date', 'po_value', 'sq_feet', 'fed_id', 'state_id', 'tax_rate', 'tax_date', 'map_code'],
      'editAccounting'  => ['group1', 'group2', 'cons1', 'cons2', 'cons_flg', 'post_flg', 'ar_bank', 'ar_contra', 'ar_sum_acct', 'ar_sum_contra', 'ap_bank', 'ap_contra', 'ap_sum_acct', 'ap_sum_contra', 'trans_code_1099', 'state_ui_pct', 'dep_int_pct', 'cash_accrual', 'name_1099', 'ownergroup', 'year_end_acct'],
      'editManagement1' => ['mangtgroup', 'mangtgroup2', 'man_fee_date', 'mangt_pct', 'man_flat_amt', 'man_pct', 'comm_pct', 'late_max_amt', 'late_min_amt', 'late_int_pct', 'late_day2', 'late_day3', 'vendor_list', 'no_post_dt', 'ap_inv_edit', 'partner_no', 'man_flg', 'bank_reserve', 'raise_pct', 'raise_pct2'],
      'editManagement2' => ['dep_int_code1', 'dep_int_code2', 'dep_int_pay', 'late_rate_code', 'late_rate', 'late_lump_amt', 'year_end', 'first_month', 'next_year_end', 'start_year', 'last_year_end', 'start_last_year', 'isFreeClear', 'usid']
    ];
 
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['propedit']) ? array_merge($orderField['editProperty'],$orderField['editTax'],$orderField['editAccounting'],$orderField['editManagement1'],$orderField['editManagement2']) : [];
    $orderField['store']  = isset($perm['propcreate']) ? array_merge($orderField['storeTrust'],$orderField['createProperty'],$orderField['createTax'],$orderField['createAccounting'],$orderField['createManagement1'],$orderField['createManagement2']) : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $rGroups  = Helper::keyFieldNameElastic(HelperMysql::getGroup([], ['prop'], ['sort'=>['prop.keyword'=>'asc']]), 'prop', 'prop');
    $rTrusts  = Helper::keyFieldNameElastic(HelperMysql::getTrust([], ['prop'], ['sort'=>['prop.keyword'=>'asc']], 0, 0), 'prop', 'prop');
    $rCompany = Helper::keyFieldNameElastic(HelperMysql::getCompany(['company_code', 'company_code'], 0, 0), 'company_code', 'company_code');
    $disabled = isset($perm['propupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'createProperty' => [
        'field' => [
          'trust'           => ['req'=>1, 'type'=>'option', 'option'=>$rTrusts],
          'prop'            => ['label'=>'Property Number'],
          'prop_name'       => ['label'=>'Property Name'],
          'line2'           => ['label'=>'Line 2'],
          'state'           => ['type' =>'option', 'option'=>$this->_mappingInfo['states']],
          'prop_class'      => ['label'=>'Property Class', 'type'=>'option', 'option'=>$this->_mappingProp['prop_class']],
          'prop_type'       => ['label'=>'Property Type', 'type'=>'option', 'option'=>$this->_mappingProp['prop_type']],
          'rent_type'       => ['label'=>'Rent Type','type'=>'option','option'=>$this->_mappingProp['rent_type']],
          'last_raise_date' => ['req'=>0]
        ],
        'rule' => [
          'trust' =>'required|string|between:1,6',
          'copyProp'=>'required|string|between:1,6',
          'rent_type'=>'nullable|string',
        ]
      ],
      'createTax'=>[
        'field' => [
          'start_date'      => ['label'=>'Purchase Date'],
          'po_value'        => ['label'=>'Purchase Price'],
          'sq_feet'         => ['label'=>'Square Footage'],
          'fed_id'          => ['label'=>'Federal Tax ID'],
          'state_id'        => ['label'=>'State Tax ID'],
          'tax_date'        => ['req'=>0],
          'map_code'        => ['label'=>'Parcel #', 'type'=>'textarea']
        ]
      ],
      'createAccounting'=>[
        'field'=>[
          'group1'          => ['label'=>'Groups', 'type'=>'option', 'option'=>$rGroups],
          'group2'          => ['label'=>'1099 Prop'],
          'cons1'           => ['label'=>'Consolidations1'],
          'cons2'           => ['label'=>'Consolidations2'],
          'cons_flg'        => ['label'=>'Cons. (Y/N)', 'req'=>0],
          'post_flg'        => ['label'=>'Post (Y/N)', 'type'=>'option', 'option'=>$this->_mappingProp['post_flg']],
          'ar_bank'         => ['label'=>'AR Default Bank'],
          'ar_sum_acct'     => ['label'=>'AR Sum'],
          'ap_bank'         => ['label'=>'AP Default Bank'],
          'ap_sum_acct'     => ['label'=>'AP Sum Account'],
          'trans_code_1099' => ['req'=>0],
          'dep_int_pct'     => ['label'=>'Deposit Interest %'],
          'ownergroup'      => ['label'=>'Owner Group', 'req'=>0]
        ]
      ],
      'createManagement1'=>[
        'field'=>[
          'mangtgroup'      => ['label'=>'Mgt Group', 'type'=>'option', 'option'=>$rCompany],
          'mangtgroup2'     => ['label'=>'Mgt Group2', 'req'=>0],
          'man_fee_date'    => ['req'=>0],
          'mangt_pct'       => ['label'=>'Mgt %'],
          'man_flat_amt'    => ['label'=>'Mgt Flat Rate $'],
          'man_pct'         => ['label'=>'Mgt Fee %'],
          'comm_pct'        => ['label'=>'Commission %'],
          'late_max_amt'    => ['label'=>'Late Maximum $'],
          'late_min_amt'    => ['label'=>'Late Minimum $'],
          'vendor_list'     => ['req'=>0],
          'no_post_dt'      => ['label'=>'No Post Date'],
          'ap_inv_edit'     => ['label'=>'Unincorp Area', 'req'=>0, 'type'=>'hidden'],
          'man_flg'         => ['req'=>0, 'type'=>'hidden'],
          'bank_reserve'    => ['type'=>'hidden'],
          'raise_pct'       => ['label'=>'Raise %'],
          'raise_pct2'      => ['label'=>'Raise % 2']
        ]
      ],
      'createManagement2'=>[
        'field'=>[
          'dep_int_code1'   => ['req'=>0, 'type'=>'hidden'],
          'dep_int_code2'   => ['req'=>0, 'type'=>'hidden'],
          'dep_int_pay'     => ['label'=>'Deposit Interest Pay', 'req'=>0, 'type'=>'hidden'],
          'late_lump_amt'   => ['label'=>'Late Lump Sum $'],
          'isFreeClear'     => ['label'=>'Free & Clear', 'type' =>'option', 'option'=>$this->_mappingProp['isFreeClear'], 'req'=>0]
        ]
      ],
      'updateProperty' => [
        'field' => [
          'trust'           => $disabled + ['req'=>1, 'type'=>'option', 'option'=>$rTrusts],
          'prop'            => $disabled + ['label'=>'Property Number', 'readonly'=>1],
          'prop_id'         => $disabled + ['type' =>'hidden'],
          'prop_name'       => $disabled + ['label'=>'Property Name'],
          'line2'           => $disabled + ['label'=>'Line 2'],
          'street'          => $disabled,
          'city'            => $disabled,
          'county'          => $disabled,
          'state'           => $disabled + ['type' =>'option', 'option'=>$this->_mappingInfo['states'], 'value'=>'CA'],
          'zip'             => $disabled,
          'prop_class'      => $disabled + ['label'=>'Property Class', 'type'=>'option', 'option'=>$this->_mappingProp['prop_class']],
          'prop_type'       => $disabled + ['label'=>'Property Type', 'type'=>'option', 'option'=>$this->_mappingProp['prop_type']],
          'phone'           => $disabled,
          'rent_type'       => $disabled + ['label'=>'Rent Type','type'=>'option','option'=>$this->_mappingProp['rent_type']],
          'last_raise_date' => $disabled + ['req'=>0]
        ],
        'rule' => [
          'rent_type'=>'nullable|string',
          'trust' =>'required'
        ]
      ],
      'updateTax'=>[
        'field' => [
          'start_date'      => $disabled + ['label'=>'Purchase Date'],
          'po_value'        => $disabled + ['label'=>'Purchase Price'],
          'sq_feet'         => $disabled + ['label'=>'Square Footage'],
          'fed_id'          => $disabled + ['label'=>'Federal Tax ID'],
          'state_id'        => $disabled + ['label'=>'State Tax ID'],
          'tax_rate'        => $disabled,
          'tax_date'        => $disabled + ['req'=>0],
          'map_code'        => $disabled + ['label'=>'Parcel #', 'type'=>'textarea']
        ]
      ],
      'updateAccounting'=>[
        'field'=>[
          'group1'          => $disabled + ['label'=>'Groups', 'type'=>'option', 'option'=>$rGroups],
          'group2'          => $disabled + ['label'=>'1099 Prop'],
          'cons1'           => $disabled + ['label'=>'Consolidations1'],
          'cons2'           => $disabled + ['label'=>'Consolidations2'],
          'cons_flg'        => $disabled + ['label'=>'Cons. (Y/N)', 'req'=>0],
          'post_flg'        => $disabled + ['label'=>'Post (Y/N)', 'type'=>'option', 'option'=>$this->_mappingProp['post_flg']],
          'ar_bank'         => $disabled + ['label'=>'AR Default Bank'],
          'ar_contra'       => $disabled,
          'ar_sum_acct'     => $disabled + ['label'=>'AR Sum'],
          'ar_sum_contra'   => $disabled,
          'ap_bank'         => $disabled + ['label'=>'AP Default Bank'],
          'ap_contra'       => $disabled,
          'ap_sum_acct'     => $disabled + ['label'=>'AP Sum Account'],
          'ap_sum_contra'   => $disabled,
          'trans_code_1099' => $disabled + ['req'=>0],
          'state_ui_pct'    => $disabled,
          'dep_int_pct'     => $disabled + ['label'=>'Deposit Interest %'],
          'cash_accrual'    => $disabled,
          'name_1099'       => $disabled,
          'ownergroup'      => $disabled + ['label'=>'Owner Group', 'req'=>0],
          'year_end_acct'   => $disabled,
        ]
      ],
      'updateManagement1'=>[
        'field'=>[
          'mangtgroup'      => $disabled + ['label'=>'Mgt Group', 'type'=>'option', 'option'=>$rCompany],
          'mangtgroup2'     => $disabled + ['label'=>'Mgt Group2', 'req'=>0],
          'man_fee_date'    => $disabled + ['req'=>0],
          'mangt_pct'       => $disabled + ['label'=>'Mgt %'],
          'man_flat_amt'    => $disabled + ['label'=>'Mgt Flat Rate $'],
          'man_pct'         => $disabled + ['label'=>'Mgt Fee %'],
          'comm_pct'        => $disabled + ['label'=>'Commission %'],
          'late_max_amt'    => $disabled + ['label'=>'Late Maximum $'],
          'late_min_amt'    => $disabled + ['label'=>'Late Minimum $'],
          'late_int_pct'    => $disabled,
          'late_day2'       => $disabled,
          'late_day3'       => $disabled,
          'vendor_list'     => $disabled + ['req'=>0],
          'no_post_dt'      => $disabled + ['label'=>'No Post Date'],
          'partner_no'      => $disabled,
          'ap_inv_edit'     => $disabled + ['label'=>'Unincorp Area', 'req'=>0, 'type'=>'hidden'],
          'man_flg'         => $disabled + ['req'=>0,'type'=>'hidden'],
          'bank_reserve'    => $disabled + ['type'=>'hidden'],
          'raise_pct'       => $disabled + ['label'=>'Raise %'],
          'raise_pct2'      => $disabled + ['label'=>'Raise % 2']
        ]
      ],
      'updateManagement2'=>[
        'field'=>[
          'dep_int_code1'   => $disabled + ['req'=>0, 'type'=>'hidden'],
          'dep_int_code2'   => $disabled + ['req'=>0, 'type'=>'hidden'],
          'dep_int_pay'     => $disabled + ['label'=>'Deposit Interest Pay', 'req'=>0, 'type'=>'hidden'],
          'late_rate_code'  => $disabled,
          'late_rate'       => $disabled,
          'late_lump_amt'   => $disabled + ['label'=>'Late Lump Sum $'],
          'year_end'        => $disabled,
          'first_month'     => $disabled,
          'next_year_end'   => $disabled,
          'start_year'      => $disabled,
          'last_year_end'   => $disabled,
          'start_last_year' => $disabled,
          'isFreeClear'     => $disabled + ['label'=>'Free & Clear', 'type' =>'option', 'option'=>$this->_mappingProp['isFreeClear'], 'req'=>0],
          'usid'            => $disabled + ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>1],
          //'sys_date'        => ['label'=>'Updated Date', 'format'=> 'date']
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['propedit']) ? array_merge($setting['updateProperty'], $setting['updateTax'], $setting['updateAccounting'], $setting['updateManagement1'], $setting['updateManagement2']) : [];
    $setting['store'] = isset($perm['propcreate']) ? array_merge($setting['createProperty'], $setting['createTax'], $setting['createAccounting'], $setting['createManagement1'], $setting['createManagement2']) : [];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['last_raise_date']['value'] = Format::usDate($default['last_raise_date']);
        $setting[$fn]['field']['start_date']['value']      = Format::usDate($default['start_date']);
        $setting[$fn]['field']['tax_date']['value']        = Format::usDate($default['tax_date']);
        $setting[$fn]['field']['man_fee_date']['value']    = Format::usDate($default['man_fee_date']);
        $setting[$fn]['field']['no_post_dt']['value']      = Format::usDate($default['no_post_dt']);
        $setting[$fn]['field']['next_year_end']['value']   = Format::usDate($default['next_year_end']);
        $setting[$fn]['field']['start_year']['value']      = Format::usDate($default['start_year']);
        $setting[$fn]['field']['last_year_end']['value']   = Format::usDate($default['last_year_end']);
        $setting[$fn]['field']['start_last_year']['value'] = Format::usDate($default['start_last_year']);
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit'  =>  isset($perm['propupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
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
    $reportList = ['rentRoll', 'tenantStatus', 'vacancy'];
    
    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source']; 
      $source['num'] = $vData['offset'] + $i + 1;
      $source['action'] = implode(' | ', $actionData['icon']);
      $source['reportIcon']  = Html::getReportIcon($source,$reportList);
      $source['number_of_units'] = Html::a($source['number_of_units'], ['href' => action('PropertyManagement\Unit\UnitController@index', ['prop.prop'=> $source['prop']]), 'target'=>'_blank']);
      $source['bank_bal'] = Html::a($source['bank_bal'], ['href' => action('PropertyManagement\Bank\BankController@index', ['prop'=> $source['prop']]), 'target'=>'_blank']);
      $source['rent_rate'] = Format::usMoney($source['rent_rate']);
      $source['po_value'] = Format::usMoney($source['po_value']);
      $source['street'] = $this->_addGoogleMapLink($source["street"] .' '. $source["city"] .' '. $source["zip"], $source["street"]);
      $source['prop_class'] = isset($perm['propupdate']) ? $source['prop_class'] :  $this->_mappingProp['prop_class'][$source['prop_class']];
      $source['rent_type']  = isset($perm['propupdate']) ? $source['rent_type']  : $this->_mappingProp['rent_type'][$source['rent_type']];
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
    if(isset($perm['propExport'])) {
      $reportList['csv'] = 'Export to CSV';
      $reportList['pdf'] = 'Property List';
    }
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['propcreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      if(isset($perm['massiveProp'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-edit']) . ' Massive Edit', ['id'=>'massiveEdit', 'class'=>'btn btn-info']) . ' ';
      }
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if(isset($perm['propupdate'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    $propEditable = isset($perm['propupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'reportIcon', 'title'=>'Report', 'width'=> 150];
    $data[] = ['field'=>'cons1', 'title'=>'Owner','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'trust', 'title'=>'Trust','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'group1', 'title'=>'Group','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'mangtgroup', 'title'=>'Mgt Group','sortable'=> true, 'filterControl'=> 'input', 'width'=> 100];
    $data[] = ['field'=>'entity_name', 'title'=>'Entity Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 25];
    $data[] = $_getSelectColumn($perm, 'prop_class', 'Prop Class', 75, $this->_mappingProp['prop_class']);
    $data[] = ['field'=>'prop_name', 'title'=>'Property Name', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 300] + $propEditable;
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $propEditable;
    $data[] = ['field'=>'zip', 'title'=>'Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $propEditable;
    $data[] = ['field'=>'county', 'title'=>'County','sortable'=> true, 'filterControl'=> 'input', 'width'=> 150] + $propEditable;
    $data[] = $_getSelectColumn($perm,'state','State',60,$this->_mappingInfo['states']);
    $data[] = $_getSelectColumn($perm, 'prop_type', 'Prop Type', 70, $this->_mappingProp['prop_type']);
    $data[] = $_getSelectColumn($perm, 'rent_type', 'Rent Type', 70, $this->_mappingProp['rent_type']);
    $data[] = ['field'=>'number_of_units', 'title'=>'Total Unit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'rent_rate', 'title'=>'Rent Rate','sortable'=> true, 'filterControl'=> 'input', 'width'=> 100]; 
    $data[] = ['field'=>'bank_bal', 'title'=>'Total Bank','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = $_getSelectColumn($perm, 'isFreeClear', 'isFreeClear', 75, $this->_mappingProp['isFreeClear']);
    $data[] = ['field'=>'po_value', 'title'=>'PO. Price','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = ['field'=>'start_date', 'title'=>'PO. Date','sortable'=> true, 'filterControl'=> 'input', 'filterControlPlaceholder'=>'yyyy-mm-dd'];
    
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrMsg($name, $msg = ''){
    $data = [
      'copiedTrustError' => Html::errMsg('Please assign a different trust, this trust doesn\'t have the ' . $msg . ' data.'),
      'changePropError'  => Html::errMsg('Please use PPM Property transfer to change this prop'),
      'changeTrustError' => Html::errMsg('This trust does not have a bank, please create a new bank to this trust first or choose a different trust.'),
      'newPropError'     => Html::errMsg('Please use a different Prop number, this prop already contains the ' . $msg . ' data.'),
      'trustEmpty'       => Html::errMsg('This trust has no bank, please add a bank to this trust before adding a prop.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _addGoogleMapLink($adress, $name) {
    return Html::a($name, ['href'=>'https://www.google.com/maps/search/?api=1&query=' . urlencode($adress), 'target'=>'_blank' ]);
  }
//------------------------------------------------------------------------------
  private function _replaceArrayValue($array, $findKey, $newValue, $usid) {
    if(empty($array)) {
      return [];
    }
    foreach($findKey as $key) {
      foreach($array as $k => $v){
        $array[$k][$key]   = $newValue;
        $array[$k]['usid'] = $usid;
      }
    }
    return $array;
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['propedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)"><i class="fa fa-edit text-aqua pointer tip" title="Edit Property Information"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
}