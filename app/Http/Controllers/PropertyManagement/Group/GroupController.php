<?php
namespace App\Http\Controllers\PropertyManagement\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, HelperMysql, GridData, GlobalVariable, Account, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\PropModel AS M; // Include the models class

class GroupController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Group/group/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingProp   = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_viewTable = T::$groupView;
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
    $op = isset($req['op']) ? $req['op'] : 'init';

    // Get all the props for dropdown option 
    $rows = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   =>$this->_viewTable,
      '_source' =>['prop', 'prop_id'],
      'sort'    =>['prop.keyword'=>'asc'],
    ]), 'prop', 'prop');
    $defaultGroup = ($op === 'init') ? reset($rows) : $op;
   
    $groupOption = Html::buildOption($rows, $defaultGroup, ['class' => 'fm form-control form-control groupOption', 'name' => 'copyGroup']);
    // Get the first matching prop to prefill the form
    $r = Helper::getElasticResultSource(HelperMysql::getGroup(['prop.keyword' => $defaultGroup]),1);
    $formGroup = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createGroup'), 
      'setting'   =>$this->_getSetting('createGroup', $req, $r)
    ]);
    
    $formAcc = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createAccounting'), 
      'setting'   =>$this->_getSetting('createAccounting', $req, $r)
    ]);
    
    $formMgt = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createManagement'), 
      'setting'   =>$this->_getSetting('createManagement', $req, $r),
      'button'    =>$this->_getButton(__FUNCTION__, $req)
    ]);
    return view($page, [
      'data'=>[
        'formGroup'   => $formGroup,
        'formAcc'     => $formAcc,
        'formMgt'     => $formMgt,
        'groupOption' => $groupOption
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
      'includeCdate'    => 0,
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustNotExist'=>[
          'prop|prop'
        ],
        'mustExist'=>[
          'account|account_id'
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    $prop = $vData['group1'] = $vData['prop'];
    $accountId = $vData['account_id'];
    unset($vData['account_id']);
    $newAccount = M::getTableData(T::$account, Model::buildWhere(['account_id'=>$accountId]), ['accessGroup', 'ownGroup'])[0];
    ## Check if Assign To's Account has group assigned to assessGroup and ownGroup
    $checkedNewAccount = $this->_checkNewAccountGroup($newAccount, $prop);
    
    $updateData = [
      T::$account=>['whereData'=>['account_id'=>$accountId],'updateData'=>$checkedNewAccount],
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert([T::$prop=>$vData]);
      $propId = $success['insert:' . T::$prop][0];
    
      $success += Model::update($updateData);

      $elastic = [
        'insert'=>[$this->_viewTable=>['p.prop_id'=>[$propId]]]
      ];
      $response['propId'] = $propId;
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
    $r = Helper::getElasticResultSource(HelperMysql::getGroup(['prop_id' => $id]),1);
    
    $formGroup = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editGroup'), 
      'setting'   =>$this->_getSetting('updateGroup', $req, $r)
    ]);
    
    $formAcc = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editAccounting'), 
      'setting'   =>$this->_getSetting('updateAccounting', $req, $r)
    ]);
    
    $formMgt = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editManagement'), 
      'setting'   =>$this->_getSetting('updateManagement', $req, $r),
      'button'    =>$this->_getButton(__FUNCTION__, $req)
    ]);
   
    return view($page, [
      'data'=>[
        'formGroup' => $formGroup,
        'formAcc'   => $formAcc,
        'formMgt'  => $formMgt,
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
  //    'orderField'  => $this->_getOrderField(__FUNCTION__, $req->all()),
      'includeUsid' => 1,
      'includeCdate'=> 0,
      'validateDatabase'=>[
        'mustExist'=>[
          'prop|prop_id',
//          'account|account_id'
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    $msgKey = count(array_keys($vData)) > 2 ? 'msg' : 'mainMsg';
    $r = Helper::getElasticResultSource(HelperMysql::getGroup(['prop_id' => $id]),1);
    if(empty($r)) {
      Helper::echoJsonError($this->_getErrMsg('noGroupError'));
    }
    $updateData = [];
    $oldGroup = $r['prop'];
    $group    = Helper::getValue('prop', $vData);
    if(!empty($group) && $group != $r['prop']) {
      V::validateionDatabase(['mustNotExist'=>['prop|prop']], $valid);
      $propRow = Helper::getElasticResultSource(HelperMysql::getGroup(['prop.keyword'=>$oldGroup], ['prop_id']));
      $propId = array_column($propRow, 'prop_id');
      $updateData[T::$prop][] = ['whereInData'=>['field'=>'prop_id', 'data'=>$propId],'updateData'=>['group1' => $group]];
    }
    if(!empty($vData['account_id'])) {
      $accountIdList[] = $accountId = $vData['account_id'];
      unset($vData['account_id']);
      $newAccount = M::getTableData(T::$account, Model::buildWhere(['account_id'=>$accountId]), ['accessGroup', 'ownGroup'])[0];
      $oldAccount = M::getAssignedAccount($oldGroup);
      $oldAccount = !empty($oldAccount) ? $this->_findMatchedAccount($oldGroup, $oldAccount) : '';

      ## If the user changes Assign To field to a different user then remove the group from the old account
      if(!empty($oldAccount) && $oldAccount['account_id'] !== $accountId){
        $accountIdList[] = $oldAccount['account_id'];
        $oldAccessArr = explode(', ', $oldAccount['accessGroup']);
        $oldOwnArr = explode(', ', $oldAccount['ownGroup']);
        ## If the old user has more than 1 group assigned then remove the one that matches the prop
        if(count($oldAccessArr) > 1 && count($oldOwnArr) > 1) {
          foreach($oldAccessArr as $key => $value) {
            if(isset($oldAccessArr[$key]) && strtolower($oldAccessArr[$key]) === strtolower($oldGroup)) {
              unset($oldAccessArr[$key]);
            }
            if(isset($oldOwnArr[$key]) && strtolower($oldOwnArr[$key]) === strtolower($oldGroup)) {
              unset($oldOwnArr[$key]);
            }
          }
          $oldAccount['accessGroup'] = count($oldAccessArr) > 1 ? implode(', ', $oldAccessArr) : implode('', $oldAccessArr);
          $oldAccount['ownGroup'] = count($oldOwnArr) > 1 ? implode(', ', $oldOwnArr) : implode('', $oldOwnArr);
        }else {
          $oldAccount['accessGroup'] = '';
          $oldAccount['ownGroup'] = '';
        }
        $updateData[T::$account][] = ['whereData'=>['account_id'=>$oldAccount['account_id']], 'updateData'=>$oldAccount];
      }
      ## Check if Assign To's Account has group assigned to assessGroup and ownGroup
      $checkedNewAccount = $this->_checkNewAccountGroup($newAccount, $group, $oldGroup);
      $updateData[T::$account][] = ['whereData'=>['account_id'=>$accountId], 'updateData'=>$checkedNewAccount];
    }
    
   
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData[T::$prop][] = ['whereData'=>['prop_id'=>$id],'updateData'=>$vData];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[
          $this->_viewTable => ['p.prop_id'=>[$id]]
        ]
      ];
      if(!empty($accountIdList)) {
        $elastic['insert'][T::$accountView] = ['account_id' => $accountIdList];
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
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$prop, T::$account],
      'update' => [T::$prop, T::$account]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'createGroup'      => ['prop', 'prop_name', 'account_id', 'line2', 'street', 'city', 'county', 'state', 'zip', 'prop_class', 'prop_type', 'phone'],
      'createAccounting' => ['start_date', 'fed_id', 'group2', 'cons1', 'cons2', 'post_flg', 'cash_accrual', 'year_end_acct'],
      'createManagement' => ['ap_inv_edit', 'no_post_dt', 'year_end', 'first_month', 'next_year_end', 'start_year', 'last_year_end', 'start_last_year'],
      
      'editGroup'        => ['prop_id', 'prop', 'prop_name', 'account_id', 'line2', 'street', 'city', 'county', 'state', 'zip', 'prop_class', 'prop_type', 'phone'],
      'editAccounting'   => ['start_date', 'fed_id', 'group2', 'cons1', 'cons2', 'post_flg', 'cash_accrual', 'year_end_acct'],
      'editManagement'   => ['ap_inv_edit', 'no_post_dt', 'year_end', 'first_month', 'next_year_end', 'start_year', 'last_year_end', 'start_last_year', 'usid']
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['groupedit']) ? array_merge($orderField['editGroup'],$orderField['editAccounting'],$orderField['editManagement']) : [];
    $orderField['store']  = isset($perm['groupcreate']) ? array_merge($orderField['createGroup'],$orderField['createAccounting'],$orderField['createManagement']) : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $rAccount = Helper::keyFieldName(M::getAccount([], 0), 'account_id', 'name');
    $rAccount[''] = 'Select User';
    if(!empty($default['prop'])){
      $rAssignedAccount = M::getAssignedAccount($default['prop']);
      $matchedAccount = $this->_findMatchedAccount($default['prop'], $rAssignedAccount);
      $matchedAccount = !empty($matchedAccount) ? $matchedAccount : ['account_id' => ''];
    }else {
      $matchedAccount = ['account_id' => ''];
    }
    if($fn === 'createGroup') {
      unset($default['prop']);
    }
    asort($rAccount);
    $disabled = isset($perm['groupupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'createGroup' => [
        'field' => [
          'prop'            => ['label'=>'Group'],
          'prop_name'       => ['label'=>'Group Name'],
          'account_id'      => ['label'=>'Assign To', 'type'=>'option', 'option'=>$rAccount, 'value'=>$matchedAccount['account_id']],  
          'line2'           => ['label'=>'Line 2'],
          'state'           => ['type' =>'option', 'option'=>GlobalVariable::$states],
          'prop_class'      => ['label'=>'Property Class', 'type'=>'option', 'option'=>$this->_mappingProp['prop_class']],
          'prop_type'       => ['label'=>'Property Type', 'type'=>'option', 'option'=>$this->_mappingProp['prop_type']],
        ]
      ],
      'createAccounting'=>[
        'field'=>[
          'start_date'      => ['label'=>'Created Date'],
          'fed_id'          => ['label'=>'Federal Tax ID'],
          'group2'          => ['label'=>'1099 Prop'],
          'cons1'           => ['label'=>'Consolidations1'],
          'cons2'           => ['label'=>'Consolidations2'],
          'post_flg'        => ['label'=>'Post (Y/N)', 'type'=>'option', 'option'=>$this->_mappingProp['post_flg']],
        ]
      ],
      'createManagement'=>[
        'field'=>[
          'ap_inv_edit'     => ['label'=>'Office Hours', 'type'=>'textarea'],
          'no_post_dt'      => ['label'=>'No Post Date'],
        ]
      ],
      'updateGroup' => [
        'field' => [
          'prop_id'         => $disabled + ['type' =>'hidden'],
          'prop'            => $disabled + ['label'=>'Group'],
          'prop_name'       => $disabled + ['label'=>'Group Name'],
          'account_id'      => $disabled + ['label'=>'Assign To', 'type'=>'option', 'option'=>$rAccount, 'value'=>$matchedAccount['account_id']],  
          'line2'           => $disabled + ['label'=>'Line 2'],
          'street'          => $disabled,
          'city'            => $disabled,
          'county'          => $disabled,
          'state'           => $disabled + ['type' =>'option', 'option'=>GlobalVariable::$states],
          'zip'             => $disabled,
          'prop_class'      => $disabled + ['label'=>'Property Class', 'type'=>'option', 'option'=>$this->_mappingProp['prop_class']],
          'prop_type'       => $disabled + ['label'=>'Property Type', 'type'=>'option', 'option'=>$this->_mappingProp['prop_type']],
          'phone'           => $disabled,
        ]
      ],
      'updateAccounting'=>[
        'field'=>[
          'start_date'      => $disabled + ['label'=>'Created Date'],
          'fed_id'          => $disabled + ['label'=>'Federal Tax ID'],
          'group2'          => $disabled + ['label'=>'1099 Prop'],
          'cons1'           => $disabled + ['label'=>'Consolidations1'],
          'cons2'           => $disabled + ['label'=>'Consolidations2'],
          'post_flg'        => $disabled + ['label'=>'Post (Y/N)', 'type'=>'option', 'option'=>$this->_mappingProp['post_flg']],
          'cash_accrual'    => $disabled,
          'year_end_acct'   => $disabled,
        ]
      ],
      'updateManagement'=>[
        'field'=>[
          'ap_inv_edit'     => $disabled + ['label'=>'Office Hours', 'type'=>'textarea'],
          'no_post_dt'      => $disabled + ['label'=>'No Post Date'],
          'year_end'        => $disabled,
          'first_month'     => $disabled,
          'next_year_end'   => $disabled,
          'start_year'      => $disabled,
          'last_year_end'   => $disabled,
          'start_last_year' => $disabled,
          'usid'            => $disabled + ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>'readonly'],
          //'sys_date'        => ['label'=>'Updated Date', 'format'=> 'date']
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['groupedit']) ? array_merge($setting['updateGroup'], $setting['updateAccounting'], $setting['updateManagement']) : [];
    $setting['store'] = isset($perm['groupcreate']) ? array_merge($setting['createGroup'], $setting['createAccounting'], $setting['createManagement']) : [];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['start_date']['value']      = Format::usDate($default['start_date']);
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
      'edit'  =>  isset($perm['groupupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
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
      $source                     = $v['_source']; 
      $statementData              = Helper::getValue('tenant_statement',$source,[]);
      $statement                  = !empty($statementData) ? last($statementData) : [];
      $statementFile              = Helper::getValue('file',$statement);
      $statementPath              = Helper::getValue('path',$statement);
      $uuid                       = Helper::getValue('uuid',$statement);
      $statementLink              = $this->_createLink($statementPath,$statementFile,$uuid);
      $source['num']              = $vData['offset'] + $i + 1;
      $source['action']           = implode(' | ', $actionData['icon']);
      $source['street']           = $this->_addGoogleMapLink($source["street"] .' '. $source["city"] .' '. $source["zip"], $source["street"]);
      $source['groupFile']        = !empty($source['fileUpload']) ? Html::span('View',['class'=>'clickable']) : '';
      $source['tenant_statement'] = !empty($source['tenant_statement']) ? Html::a('Tenant Statement',['class'=>'downloadLink','target'=>'_blank','href'=>$statementLink,'title'=>'Click Here to Download Tenant Statements']) : '';
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
    if(isset($perm['groupExport'])) {
      $reportList['csv'] = 'Export to CSV';
    }
    
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['groupcreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      return $button;
    };
    
    $groupEditable = isset($perm['groupupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    $groupTextAreaEditable = isset($perm['groupupdate']) ? ['editable'=> ['type'=> 'textarea']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'prop', 'title'=>'Group','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75];
    $data[] = ['field'=>'prop_name', 'title'=>'Group Name', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 350] + $groupEditable;
    $data[] = ['field'=>'supervisor', 'title'=>'Supervisor Name', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 125];
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 125] + $groupEditable;
    $data[] = ['field'=>'zip', 'title'=>'Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $groupEditable;
    $data[] = ['field'=>'county', 'title'=>'County','sortable'=> true, 'filterControl'=> 'input', 'width'=> 125] + $groupEditable;
    if(isset($perm['uploadGroupFileindex'])){
      $data[] = ['field'=>'groupFile', 'title'=>'File','width'=> 75];
      $data[] = ['field'=>'tenant_statement','title'=>'Tenant Statement','width'=>75];
    }
    $data[] = ['field'=>'ap_inv_edit', 'title'=>'Office Hours','sortable'=> true, 'filterControl'=> 'input'] + $groupTextAreaEditable;
  
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
      'noGroupError' => Html::errMsg('Group does not exist')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _addGoogleMapLink($adress, $name) {
    return '<a href="https://www.google.com/maps/search/?api=1&query=' . urlencode($adress) . '" target="_blank" >'. $name .'</a>';
  }
//------------------------------------------------------------------------------
  private function _findMatchedAccount($group, $accounts) {
    foreach($accounts as $key => $value) {
      $accessArr = explode(', ', $accounts[$key]['accessGroup']);
      $ownArr = explode(', ', $accounts[$key]['ownGroup']);
      if($this->_in_array_case_insensitive($group, $accessArr) && $this->_in_array_case_insensitive($group, $ownArr)) {
        return $value;
      }
    }
  }
//------------------------------------------------------------------------------
  private function _in_array_case_insensitive($needle, $haystack) {
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['groupedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Group Information"></i></a>';
    }
    if(isset($perm['uploadGroupFilestore'])){
      $actionData[] = '<a class="groupFileUpload" href="javascript:void(0)"><i class="fa fa-upload text-aqua pointer tip" title="Upload Group File"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
//------------------------------------------------------------------------------
  private function _checkNewAccountGroup($newAccount, $group, $oldGroup = '') {
    ## Check if Assign To's Account has group assigned to assessGroup and ownGroup
    if(!empty($newAccount['accessGroup']) && !empty($newAccount['ownGroup'])) {
      $newAccessArr = explode(', ', $newAccount['accessGroup']);
      $newOwnArr = explode(', ', $newAccount['ownGroup']);
      ## If user changes group then remove the old group
      if(!empty($oldGroup)) {
        $newAccessArr = array_diff($newAccessArr, [$oldGroup]);
        $newOwnArr    = array_diff($newOwnArr, [$oldGroup]);
      }
      ## If the Account already have other groups then add the prop to the existing group
      if(!$this->_in_array_case_insensitive($group, $newAccessArr) && !$this->_in_array_case_insensitive($group, $newOwnArr)) {
        $newAccessArr[] = $group;
        $newOwnArr[] = $group;
        $newAccount['accessGroup'] = implode(', ', $newAccessArr);
        $newAccount['ownGroup'] = implode(', ', $newOwnArr);
      }
    }else {
      $newAccount['accessGroup'] = $group;
      $newAccount['ownGroup'] = $group;
    }
    return $newAccount;
  }
//------------------------------------------------------------------------------
  private function _createLink($path,$file,$uuid){
    $pieces    = explode('storage/app',$path);
    $urlPiece  = Helper::getValue(1,$pieces);
    
    $link      = \Storage::disk('public')->url(preg_replace('|public\/|','',preg_replace('/\#/','%23',$urlPiece . (!empty($uuid) ? $uuid . '/' : '') . $file)));
    return $link;
  }
}