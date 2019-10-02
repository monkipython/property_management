<?php
namespace App\Http\Controllers\PropertyManagement\Trust;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, GlobalVariable, Mail, Account, TableName AS T, HelperMysql};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\PropModel AS M; // Include the models class

class TrustController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Trust/trust/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingProp   = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_viewTable = T::$trustView;
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
  public function create( Request $req){
    $page = $this->_viewPath . 'create';
    $op = isset($req['op']) ? $req['op'] : 'init';
    /*
    // Get all the props for dropdown option 
    $rows = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   =>$this->_viewTable,
      '_source' =>['prop', 'prop_id'],
      'sort'    =>['prop.keyword'=>'asc'],
    ]), 'prop_id', 'prop');
    $defaultTrust = ($op === 'init') ? $this->_arrayKeyFirst($rows) : $op;
    $trustOption  = Html::buildOption($rows, $defaultTrust, ['class' => 'fm form-control form-control trustOption', 'name' => 'copyTrust']);
     * 
     */
    $trustWarning = Html::div('*After creating a new trust, please create the banks before assigning properties to this trust.', ['class'=>'col-sm-12 text-red']);

    /*
    // Get the first matching prop to prefill the form
    $r = Helper::getElasticResultSource(Elastic::searchQuery([
        'index' =>$this->_viewTable,
        'query' =>['must'=>['prop_id' => $defaultTrust]]
    ]),1);
    unset($r['prop']);
     * 
     */
    $formTrust = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createTrust'), 
      'setting'   =>$this->_getSetting('createTrust', $req)
    ]);
    
    $formTax = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createTax'), 
      'setting'   =>$this->_getSetting('createTax', $req),
      'button'    =>$this->_getButton(__FUNCTION__),
    ]);
    
    $formAcc = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createAccounting'), 
      'setting'   =>$this->_getSetting('createAccounting', $req)
    ]);
    
    $formMgt = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createManagement'), 
      'setting'   =>$this->_getSetting('createManagement', $req)
    ]);
    return view($page, [
      'data'=>[
        'formTrust'   => $formTrust,
        'formTax'     => $formTax,
        'formAcc'     => $formAcc,
        'formMgt'     => $formMgt,
     //   'trustOption' => $trustOption,
        'trustWarning'=> $trustWarning
      ]
    ]);
  }
    //------------------------------------------------------------------------------
  public function store( Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable('create'), 
      'orderField'      => $this->_getOrderField(__FUNCTION__, $req),
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeCdate'    => 0,
      'validateDatabase'=>[
        'mustNotExist'=>[
          'prop|prop'
        ]
      ]
    ]);
    $vData          = $valid['dataNonArr'];
    $vData['usid']  = Helper::getUsid($req);
    $vData['trust'] = $vData['prop'];
    //$copyTrustId    = $vData['copyTrust'];
    //$copyTrustData  = Helper::getElasticResultSource(HelperMysql::getTrust(['prop_id'=>$copyTrustId]), 1);
    //$propBankFields = ['prop', 'bank', 'gl_acct', 'trust', 'recon_prop', 'usid', 'rock'];
    //$bankFields     = ['prop', 'bank', 'name', 'br_name', 'street', 'city', 'state', 'zip', 'phone', 'last_check_no', 'bank_bal', 'bank_reg', 'transit_cp', 'cp_acct', 'transit_cr', 'cr_acct', 'print_bk_name', 'print_prop_name', 'two_sign', 'void_after', 'remark', 'rock', 'dump_group'];
    //$serviceFields  = ['prop', 'service', 'bill_seq', 'remark', 'remarks', 'amount', 'service_type', 'schedule', 'tax_cd', 'gl_acct', 'gl_acct_past', 'gl_acct_next', 'cam_exp_gl_acct', 'next_service', 'pm_post', 'usid', 'sys_date', 'mangt_cd', 'comm_cd', 'rock'];
    //$glChartFields  = ['prop', 'gl_acct', 'title', 'acct_type', 'no_post', 'type1099', 'remarks', 'usid', 'sys_date', 'rock'];
    
    ## Will use getServiceElastic After releasing the service module
    ##$copiedService = HelperMysql::getServiceElastic(['prop.keyword'=>$copyTrustData['prop']], $serviceFields);
    ##$copiedService = !empty($copiedService) ? array_column($copiedService, '_source') : [];
    //$copiedService = M::getTableData(T::$service, Model::buildWhere(['prop' => $copyTrustData['prop']]), $serviceFields);
    //$copiedGlChart = M::getTableData(T::$glChart, Model::buildWhere(['prop' => $copyTrustData['prop']]), $glChartFields);
    //$copyPropBank  = M::getTableData(T::$propBank, Model::buildWhere(['prop' => $copyTrustData['prop']]), $propBankFields);
    //$copyBank      = M::getTableData(T::$bank, Model::buildWhere(['prop' => $copyTrustData['prop']]), $bankFields);
    //$propBankData   = $this->_replaceArrayValue($copyPropBank, ['prop', 'trust', 'recon_prop'], $vData['trust'], $vData['usid']);
    //$bankData       = $this->_replaceArrayValue($copyBank, ['prop'], $vData['trust'], $vData['usid']);
    //$serviceData    = $this->_replaceArrayValue($copiedService, ['prop'], $vData['trust'], $vData['usid']);
    //$glChartData    = $this->_replaceArrayValue($copiedGlChart, ['prop'], $vData['trust'], $vData['usid']);
    //unset($vData['copyTrust']);
    
    //if(empty($propBankData) || empty($bankData) || empty($serviceData) || empty($glChartData)) {
    //  Helper::echoJsonError($this->_getErrMsg('copiedTrustError'));
    //}
    $insertData = [
      T::$prop     => $vData,
    //  T::$propBank => $propBankData,
    //  T::$bank     => $bankData,
    //  T::$service  => $serviceData,
    //  T::$glChart  => $glChartData
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert($insertData);
      $propId = $success['insert:' . T::$prop][0];
      $success['insert:'.T::$service] = $success['insert:'.T::$glChart] = 1;
      $elastic = [
        'insert'=>[
          $this->_viewTable=>['p.prop_id'=>[$propId]],
       //   T::$serviceView  =>['service_id' => $success['insert:'.T::$service]]
        ]
      ];
      $response['propId'] = $propId;
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
//      $this->sendEmail(['action'=>'Created Trust', 'data' => $vData], $vData['usid']);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Helper::getElasticResultSource(Elastic::searchQuery([
        'index' =>$this->_viewTable,
        'query' =>['must'=>['prop_id' => $id]]
    ]), 1);
    
    $formTrust = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editTrust'), 
      'setting'   =>$this->_getSetting('updateTrust', $req, $r)
    ]);
    
    $formTax = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editTax'), 
      'setting'   =>$this->_getSetting('updateTax', $req, $r),
      'button'    =>$this->_getButton(__FUNCTION__,$req),
    ]);
    
    $formAcc = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editAccounting'), 
      'setting'   =>$this->_getSetting('updateAccounting', $req, $r)
    ]);
    
    $formMgt = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editManagement'), 
      'setting'   =>$this->_getSetting('updateManagement', $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'formTrust' => $formTrust,
        'formTax'   => $formTax,
        'formAcc'   => $formAcc,
        'formMgt'   => $formMgt,
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
  //    'orderField'  => $this->_getOrderField(__FUNCTION__, $req),
      'includeCdate'=> 0,
      'validateDatabase'=>[
        'mustExist'=>[
          'prop|prop_id',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $vData['usid'] = Helper::getUsid($req);
    $rPropId = M::getPropIdTrust($vData['prop']);
    $propId = array_keys($rPropId);
    $msgKey = count(array_keys($vData)) > 2 ? 'msg' : 'mainMsg';
    $orderFields = $this->_getOrderField(__FUNCTION__, $req);
    $originalTrustData  = Helper::getElasticResultSource(HelperMysql::getTrust(['prop_id'=>$id], $orderFields), 1);
    if(!empty($vData['prop'])){
      $vData['trust'] = $vData['prop'];
    }
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$prop=>['whereData'=>['prop_id'=>$id],'updateData'=>$vData],
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['p.prop_id'=>[$id]]]
      ];
      if(!empty($propId)) {
        $elastic['insert'][T::$propView] = ['p.prop_id'=>$propId];
      }
      $response[$msgKey] = $this->_getSuccessMsg(__FUNCTION__);
      
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
      $this->sendEmail(['action'=>'Updated Trust', 'data' => $vData, 'prevData'=>$originalTrustData], $vData['usid']);
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
      'createTrust'       => ['prop', 'prop_name', 'line2', 'street', 'city', 'county', 'state', 'zip', 'prop_class', 'phone'],
      'createTax'         => ['fed_id', 'state_id'],
      'createAccounting'  => ['group1', 'group2', 'cons1', 'cons2', 'cons_flg', 'post_flg', 'ar_contra', 'ar_sum_acct', 'ar_sum_contra', 'ap_contra', 'ap_sum_acct', 'ap_sum_contra', 'cash_accrual', 'year_end_acct'],
      'createManagement'  => ['dep_int_code1', 'dep_int_code2', 'dep_int_pay', 'year_end', 'first_month', 'next_year_end', 'start_year', 'last_year_end', 'start_last_year'],
      'editTrust'         => ['prop_id', 'prop', 'prop_name', 'line2', 'street', 'city', 'county', 'state', 'zip', 'prop_class', 'phone'],
      'editTax'           => ['fed_id', 'state_id'],
      'editAccounting'    => ['group1', 'group2', 'cons1', 'cons2', 'cons_flg', 'post_flg', 'ar_contra', 'ar_sum_acct', 'ar_sum_contra', 'ap_contra', 'ap_sum_acct', 'ap_sum_contra', 'cash_accrual', 'year_end_acct'],
      'editManagement'    => ['dep_int_code1', 'dep_int_code2', 'dep_int_pay', 'year_end', 'first_month', 'next_year_end', 'start_year', 'last_year_end', 'start_last_year', 'usid'],
    //  'storeTrust'        => ['copyTrust']
    ];
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['trustedit']) ? array_merge($orderField['editTrust'],$orderField['editTax'],$orderField['editAccounting'],$orderField['editManagement']) : [];
    $orderField['store']  = isset($perm['trustcreate']) ? array_merge($orderField['createTrust'],$orderField['createTax'],$orderField['createAccounting'],$orderField['createManagement']) : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $disabled = isset($perm['trustupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'createTrust' => [
        'field' => [
          'prop'            => ['label'=>'Trust'],
          'prop_name'       => ['label'=>'Trust Name'],
          'line2'           => ['label'=>'Line 2','req'=>0],
          'state'           => ['type' =>'option', 'option'=>GlobalVariable::$states],
          'prop_class'      => ['label'=>'Property Class', 'type'=>'option', 'option'=>$this->_mappingProp['prop_class']],
        ],
      ],
      'createTax'=>[
        'field' => [
          'fed_id'          => ['label'=>'Federal Tax ID'],
          'state_id'        => ['label'=>'State Tax ID'],
        ]
      ],
      'createAccounting'=>[
        'field'=>[
          'group1'          => ['label'=>'Groups','req'=>0],
          'group2'          => ['label'=>'1099 Prop'],
          'cons1'           => ['label'=>'Consolidations1'],
          'cons2'           => ['label'=>'Consolidations2'],
          'cons_flg'        => ['label'=>'Cons. (Y/N)', 'req'=>0],
          'post_flg'        => ['label'=>'Post (Y/N)', 'type'=>'option', 'option'=>$this->_mappingProp['post_flg']],
          'ar_sum_acct'     => ['label'=>'AR Sum'],
          'ap_sum_acct'     => ['label'=>'AP Sum Account'],
          'trans_code_1099' => ['req'=>0],
          'dep_int_pct'     => ['label'=>'Deposit Interest %'],
          'ownergroup'      => ['label'=>'Owner Group', 'req'=>0]
        ]
      ],
      'createManagement'=>[
        'field'=>[
          'dep_int_code1'   => ['req'=>0, 'type'=>'hidden'],
          'dep_int_code2'   => ['req'=>0, 'type'=>'hidden'],
          'dep_int_pay'     => ['label'=>'Deposit Interest Pay', 'req'=>0, 'type'=>'hidden'],
        ],
        'rule' => [
     //     'copyTrust'     => 'required|string|between:1,6',
          'line2'         => 'nullable|string|between:1,30',
          'group1'        => 'nullable|string|between:1,6',
          'dep_int_code1' => 'nullable|string|between:1,1',
          'dep_int_code2' => 'nullable|string|between:1,1',
          'dep_int_pay'   => 'nullable|string|between:1,1',
        ]
      ],
      'updateTrust' => [
        'field' => [
          'prop_id'         => $disabled + ['type' =>'hidden'],
          'prop'            => $disabled + ['label'=>'Trust', 'readonly'=>1],
          'prop_name'       => $disabled + ['label'=>'Property Name'],
          'line2'           => $disabled + ['label'=>'Line 2','req'=>0],
          'street'          => $disabled,
          'city'            => $disabled,
          'county'          => $disabled,
          'state'           => $disabled + ['type' =>'option', 'option'=>GlobalVariable::$states],
          'zip'             => $disabled,
          'prop_class'      => $disabled + ['label'=>'Property Class', 'type'=>'option', 'option'=>$this->_mappingProp['prop_class']],
          'phone'           => $disabled,
        ],
      ],
      'updateTax'=>[
        'field' => [
          'fed_id'          => $disabled + ['label'=>'Federal Tax ID'],
          'state_id'        => $disabled + ['label'=>'State Tax ID'],
        ]
      ],
      'updateAccounting'=>[
        'field'=>[
          'group1'          => $disabled + ['label'=>'Groups','req'=>0],
          'group2'          => $disabled + ['label'=>'1099 Prop'],
          'cons1'           => $disabled + ['label'=>'Consolidations1'],
          'cons2'           => $disabled + ['label'=>'Consolidations2'],
          'cons_flg'        => $disabled + ['label'=>'Cons. (Y/N)', 'req'=>0],
          'post_flg'        => $disabled + ['label'=>'Post (Y/N)', 'type'=>'option', 'option'=>$this->_mappingProp['post_flg']],
          'ar_contra'       => $disabled,
          'ar_sum_acct'     => $disabled + ['label'=>'AR Sum'],
          'ar_sum_contra'   => $disabled,
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
        ],
      ],
      'updateManagement'=>[
        'field'=>[
          'dep_int_code1'   => $disabled + ['req'=>0, 'type'=>'hidden'],
          'dep_int_code2'   => $disabled + ['req'=>0, 'type'=>'hidden'],
          'dep_int_pay'     => $disabled + ['label'=>'Deposit Interest Pay', 'req'=>0, 'type'=>'hidden'],
          'year_end'        => $disabled,
          'first_month'     => $disabled,
          'next_year_end'   => $disabled,
          'start_year'      => $disabled,
          'last_year_end'   => $disabled,
          'start_last_year' => $disabled,
          'usid'            => $disabled + ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>'readonly'],
          //'sys_date'        => ['label'=>'Updated Date', 'format'=> 'date']
        ],
        'rule' => [
          'line2'         => 'nullable|string|between:1,30',
          'group1'        => 'nullable|string|between:1,6',
          'dep_int_code1' => 'nullable|string|between:1,1',
          'dep_int_code2' => 'nullable|string|between:1,1',
          'dep_int_pay'   => 'nullable|string|between:1,1',
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['trustedit']) ? array_merge($setting['updateTrust'], $setting['updateTax'], $setting['updateAccounting'],$setting['updateManagement']) : [];
    $setting['store'] = isset($perm['trustcreate']) ? array_merge($setting['createTrust'], $setting['createTax'], $setting['createAccounting'], $setting['createManagement']) : [];
    
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
      'edit'  =>  isset($perm['trustupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
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
      $source['num'] = $vData['offset'] + $i + 1;
      $source['action'] = implode(' | ', $actionData['icon']);
      $source['street'] = $this->_addGoogleMapLink($source["street"] .' '. $source["city"] .' '. $source["zip"], $source["street"]);
      $source['prop_class'] = isset($perm['trustupdate']) ? $source['prop_class'] :  $this->_mappingProp['prop_class'][$source['prop_class']];
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
    if(isset($perm['trustExport'])) {
      $reportList['csv'] = 'Export to CSV';
    }
    
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['trustcreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if(isset($perm['trustupdate'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    $trustEditable = isset($perm['trustupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'prop', 'title'=>'Trust','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = ['field'=>'cons1', 'title'=>'Owner','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = ['field'=>'group1', 'title'=>'Group','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = $_getSelectColumn($perm, 'prop_class', 'Status', 150, $this->_mappingProp['prop_class']);
    $data[] = ['field'=>'prop_name', 'title'=>'Trust Name', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 375] + $trustEditable;
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $trustEditable;
    $data[] = ['field'=>'zip', 'title'=>'Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 100] + $trustEditable;
    $data[] = ['field'=>'county', 'title'=>'County','sortable'=> true, 'filterControl'=> 'input'] + $trustEditable;
    
    return ['columns'=>$data, 'button'=>$_getButtons($perm), 'reportList'=>$reportList]; 
  }   
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update' =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrMsg($name){
    $data = [
      'copiedTrustError' =>Html::errMsg('Please select different trust to copy, this trust doesn\'t have the required data')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _addGoogleMapLink($adress, $name) {
    return '<a href="https://www.google.com/maps/search/?api=1&query=' . urlencode($adress) . '" target="_blank" >'. $name .'</a>';
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['trustedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Trust Information"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
//------------------------------------------------------------------------------
  private function _replaceArrayValue($array, $findKey, $newValue, $usid) {
    foreach($findKey as $key) {
      foreach($array as $k => $v){
        $array[$k][$key]   = $newValue;
        $array[$k]['usid'] = $usid;
      }
    }
    return $array;
  }
//------------------------------------------------------------------------------
  private function _arrayKeyFirst(array $arr) {
    foreach($arr as $key => $unused) {
        return $key;
    }
    return NULL;
  }
//------------------------------------------------------------------------------
  public function sendEmail($data, $usid) {
    $action      = $data['action'];
    $newData     = isset($data['data']) ? $data['data'] : [];
    $prevData    = isset($data['prevData']) ? $data['prevData'] : [];
    $bodyMsg     = 'New Data: <br>';
    $prevBodyMsg = '';
    foreach($newData as $field => $value) {
      $bodyMsg .= $field . ': ' . $value . '<br>';
    }
    foreach($prevData as $field => $value) {
      $prevBodyMsg .= $field . ': ' . $value . '<br>';
    }
    $bodyMsg .=  !empty($prevBodyMsg) ? '<hr>Previous Data: <br>'.$prevBodyMsg : '';
    Mail::send([
      'to'      => 'nevin@pamamgt.com,sean@pamamgt.com,Kary@pamamgt.com,ryan@pamamgt.com,cindy@pamamgt.com',
      'from'    => 'admin@pamamgt.com',
      'subject' => $action . ' By '.$usid.' on ' . date("F j, Y, g:i a"),
      'msg'     => $usid . ' ' . $action . ' on ' . date("F j, Y, g:i a") . ': <br><hr>' . $bodyMsg
    ]);
  }
}
