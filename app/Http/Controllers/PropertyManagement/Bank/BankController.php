<?php
namespace App\Http\Controllers\PropertyManagement\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, GridData, Account, Mail, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\BankModel AS M; // Include the models class

class BankController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Bank/bank/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingBank   = Helper::getMapping(['tableName'=>T::$bank]);
    $this->_mappingInfo   = Helper::getMapping(['tableName'=>T::$applicationInfo]);
    $this->_viewTable = T::$bankView;
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
        $vData['defaultSort'] = ['prop.keyword:asc', 'bank.keyword:asc'];
        $qData = GridData::getQuery($vData, $this->_viewTable);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData, $req); 
      default:
        return view($page, ['data'=>[
          'nav'     =>$req['NAV'],
          'account' =>Account::getHtmlInfo($req['ACCOUNT']),
          'initData'=>$initData
        ]]);  
    }
  } 
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
    $op = isset($req['op']) ? $req['op'] : 'init';

    // Get all the banks for dropdown option 
    $query = [
      'index'   => $this->_viewTable, 
      'type'    => $this->_viewTable, 
      'size'    => '100000', 
      '_source' => ['bank_id', 'prop_bank_id', 'trust', 'prop', 'bank', 'name'],
      'sort'    => ['trust.keyword:asc','prop.keyword:desc', 'bank.keyword:asc']
    ];
    $rows = Elastic::search($query);
    $defaultBank = ($op === 'init') ? $rows['hits']['hits'][0]['_source']['bank_id'] : $op;

    $rowsBank = $this->_keyMultiFieldNameElastic($rows, 'bank_id', ['trust', 'prop', 'bank', 'name']);
   
    $bankOption = Html::buildOption($rowsBank, $defaultBank, ['class' => 'fm form-control form-control bankOption', 'name' => 'copyBank']);
    
    // Get the first matching bank to prefill the form
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['bank_id' => $defaultBank]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    
    $formBank = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createBank'), 
      'setting'   =>$this->_getSetting('createBank', $req, $r),
    ]);
    
    $formAcc = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createAccounting'), 
      'setting'   =>$this->_getSetting('createAccounting', $req, $r),
      'button'    =>$this->_getButton(__FUNCTION__)
    ]);
    
    return view($page, [
      'data'=>[
        'formBank'   => $formBank,
        'formAcc'    => $formAcc,
        'bankOption' => $bankOption
       ]
    ]);
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['prop_bank_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    
    $formBank = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editBank'), 
      'setting'   =>$this->_getSetting('updateBank', $req, $r)
    ]);
    
    $formAcc = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editAccounting'), 
      'setting'   =>$this->_getSetting('updateAccounting', $req, $r),
      'button'    =>$this->_getButton(__FUNCTION__, $req)
    ]);
   
    return view($page, [
      'data'=>[
        'formBank' => $formBank,
        'formAcc'  => $formAcc
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    unset($req['copyBank']);
    $op  = isset($req['op']) ? $req['op'] : 'init';
    if($op === 'proceed') {
      ## If proceed, json_decode the json bank data and merge it with the request
      $req->merge(json_decode($req['bData'], true));
      $bankId = $req['bank_id'];
      unset($req['bank_id']);
    }
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable('create'), 
      'orderField'      => $this->_getOrderField(__FUNCTION__, $req),
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeCdate'    => 0,
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          'prop|trust',
          'gl_chart|gl_acct'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $prop = $vData['prop'];
    $trust = $vData['trust'];
    $bank = $vData['bank'];
    $glAcct = $vData['gl_acct'];
    ## If trust has properties then check to see if it exists
    if($prop == 0){
      $prop = $trust;
    }else {
      V::validateionDatabase(['mustExist'=>['prop|prop']], $valid);
    }
    ## Check to see if there's duplicate bank in the database
    $rBanks = M::getBankData(Model::buildWhere(['b.prop'=>$trust, 'b.bank'=>$bank]));
    if(!empty($rBanks) && $op === 'init') {
      $response['trust'] = Html::h3('Trust: ' . $rBanks[0]['trust'], ['class'=>'box-title']);
      $response['bank'] = Html::h3('Bank #: ' . $rBanks[0]['bank'], ['class'=>'box-title']);
      $response['bankName'] = Html::h3('Bank Name: ' . $rBanks[0]['name'], ['class'=>'box-title']);
      $response['street'] = Html::h3('Address: ' . $rBanks[0]['street'] . ' ' . $rBanks[0]['city']. ', ' . $rBanks[0]['state'] . ' ' . $rBanks[0]['zip'], ['class'=>'box-title']);
      $response['title'] = 'PROCEED TO REPLACE BANK #' . $rBanks[0]['bank'] . ' AND ADD THESE PROPS TO THE NEW BANK';
      $includeFields = ['prop', 'bank', 'name', 'street', 'cp_acct', 'cr_acct'];
      $tableData = [];
      foreach($rBanks as $key => $val){
        foreach($includeFields as $fl){
          if(isset($val[$fl])) {
            $rData[$fl] = ['header' => ['val'=>ucfirst($fl)], 'val'=>$val[$fl]];
          }
        }
        $tableData['data'][] = $rData;
      }
      $tableData['tableParam'] = ['class'=>'table table-bordered'];
      $response['table'] = Html::buildTable($tableData);
      $bankId = M::getTableData(T::$bank, ['bank'=>$rBanks[0]['bank'], 'prop'=>$rBanks[0]['trust']], 'bank_id')[0]['bank_id'];
      $vData['bank_id'] = $bankId;
      ## Save bank data in div to use it after user proceeds
      $response['jsonData'] = Html::div(json_encode($vData), ['style'=>'display: none', 'id'=>'bankData']);
      return ['html'=>view($this->_viewPath . 'validateBank', ['data'=>$response])->render()];
    }  

    ## If there's no duplicate bank copy prop_bank for insert
    if(empty($rBanks)) {
      ## Get prop_banks that are equal to the trust
      $rPropBanks = M::getTableData(T::$propBank ,Model::buildWhere(['trust'=>$trust]),['prop', 'bank', 'gl_acct', 'trust', 'recon_prop', 'usid', 'sys_date', 'rock']);
      if(!empty($rPropBanks)) {
        // Remove duplicate prop entries
        $tempArr = array_unique(array_column($rPropBanks, 'prop'));
        $copiedPropBanks = array_intersect_key($rPropBanks, $tempArr);
        // Change all the copied bank values to the new bank value
        foreach($copiedPropBanks as $key => $value) {
          $copiedPropBanks[$key]['bank'] = $bank;
          $copiedPropBanks[$key]['gl_acct'] = $glAcct;
        }
      }else {
        ## if the trust has no prop_bank then just insert new one
        $copiedPropBanks = [
          'prop'       => $trust,
          'bank'       => $bank,
          'gl_acct'    => $glAcct,
          'trust'      => $trust,
          'recon_prop' => $trust,
          'usid'       => $vData['usid'],
        ];
      }
    }
    ## Assign $vData['prop'] to trust for the bank table since trust = prop in bank table
    $vData['prop'] = $trust;
    unset($vData['trust'], $vData['gl_acct']);
    ## Get prop_id to reindex prop
    $propId = M::getPropElastic(['trust.keyword'=>$trust], ['prop_id']);
    $propId = array_column($propId, 'prop_id');
    $rPropBankId = M::getTableData(T::$propBank, Model::buildWhere(['bank'=>$bank, 'trust'=>$trust]), ['prop_bank_id']);
    $rPropBankCheck = M::getTableData(T::$propBank, Model::buildWhere(['prop'=>$prop, 'bank'=>$bank, 'trust'=>$trust]), ['prop_bank_id']);
    $propBankId =  !empty($rPropBankId) ? array_column($rPropBankId, 'prop_bank_id') : [];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      ## If there's duplicate bank, then replace the old bank with the new bank
      if(!empty($rBanks) && $op === 'proceed') {
        $updateData = [
          T::$bank    => ['whereData'=>['bank_id'=>$bankId],'updateData'=>$vData],
          T::$propBank=>['whereData'=>['trust'=>$trust, 'bank'=>$bank], 'updateData'=>['gl_acct'=>$glAcct]]
        ];
        $success += Model::update($updateData);
        ## If the old bank doesnt have the new prop in the prop_bank table then insert
        if(empty($rPropBankCheck)) {
          $newPropBank = [
            'prop'       => $prop,
            'bank'       => $bank,
            'gl_acct'    => $glAcct,
            'trust'      => $trust,
            'recon_prop' => $trust,
            'usid'       => $vData['usid']
          ];
          $success += Model::insert([T::$propBank=>$newPropBank]);
          $propBankId = array_merge($propBankId, $success['insert:' . T::$propBank]);
        }
      }else {
        $success += Model::insert([T::$bank=>$vData, T::$propBank=>$copiedPropBanks]);
        $propBankId = !empty($copiedPropBanks) ? $success['insert:' . T::$propBank] : $propBankId;
      }
      ## If adding a bank to a trust with no prop_bank then don't reindex bank_view
      if(!empty($propBankId)) {
        $elastic['insert'][$this->_viewTable]['pb.prop_bank_id'] = $propBankId;
      }
      if(!empty($propId)) {
        $elastic['insert'][T::$propView]['prop_id'] = $propId;
      }
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
      $this->sendEmail(['action'=>'Created Bank', 'data' => $vData], $vData['usid']);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['prop_bank_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__, $req),
  //    'orderField'  => $this->_getOrderField(__FUNCTION__, $req),
      'includeCdate'=> 0,
      'includeUsid' => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          'bank|bank_id',
          'prop_bank|prop_bank_id'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $isGridEdit = count(array_keys($vData)) > 4 ? false : true;
    $msgKey = !$isGridEdit ? 'msg' : 'mainMsg';
    if(!empty($vData['gl_acct'])){
      V::validateionDatabase(['mustExist'=>['gl_chart|gl_acct']], $valid);
    }
    $bankId = $vData['bank_id'];
    $orderFields = ['pb.prop_bank_id', 'b.bank_id', 'pb.trust', 'pb.prop', 'pb.bank', 'b.name', 'pb.gl_acct', 'b.br_name', 'b.street', 'b.city', 'b.state', 'b.zip', 'b.phone', 'b.remark', 'b.last_check_no', 'b.bank_bal', 'b.bank_reg', 'b.transit_cp', 'b.cp_acct', 'b.transit_cr', 'b.cr_acct', 'b.print_bk_name', 'b.print_prop_name', 'b.two_sign', 'b.void_after', 'b.dump_group', 'b.usid'];
    $originalBankData = M::getBankData(Model::buildWhere(['prop_bank_id' => $id]), $orderFields)[0];
    if(!$isGridEdit) {
      $glAcct = $vData['gl_acct'];
      ## Assign $vData['prop'] to trust for the bank table since trust = prop in bank table
      if(!empty($vData['trust'])) {
        $vData['prop'] = $vData['trust'];
      }   
      # PREPARE THE DATA FOR UPDATE AND INSERT
      $updateData = [
        T::$propBank=>['whereData'=>['trust'=>$vData['prop'], 'bank'=>$originalBankData['bank']], 'updateData'=>['gl_acct'=>$glAcct]]
      ];
      if($vData['bank'] != $originalBankData['bank']) {
        $validData['data'] = [
          'prop' => $vData['prop'],
          'bank' => $vData['bank']
        ];
        V::validateionDatabase(['mustNotExist'=>['bank|prop,bank']], $validData);
        $updateData[T::$propBank]['updateData']['bank'] = $vData['bank'];
      }
    }
    unset($vData['trust'], $vData['prop_bank_id'], $vData['gl_acct']);
    $updateData[T::$bank] = ['whereData'=>['bank_id'=>$bankId],'updateData'=>$vData];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['b.bank_id'=>[$bankId]]]
      ];
      $response[$msgKey] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
      $this->sendEmail(['action'=>'Updated Bank', 'data' => $vData, 'prevData'=>$originalBankData], $vData['usid']);
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
      'create' => [T::$bank, T::$propBank, T::$prop],
      'update' => [T::$bank, T::$propBank, T::$prop]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'createBank'       => ['trust', 'prop', 'bank', 'name', 'gl_acct', 'br_name', 'street', 'city', 'state', 'zip', 'phone', 'remark'],
      'createAccounting' => ['last_check_no', 'bank_bal', 'bank_reg', 'transit_cp', 'cp_acct', 'transit_cr', 'cr_acct', 'print_bk_name', 'print_prop_name', 'two_sign','void_after', 'dump_group'],
      'editBank'         => ['prop_bank_id', 'bank_id', 'trust', 'prop', 'bank', 'name', 'gl_acct', 'br_name', 'street', 'city', 'state', 'zip', 'phone', 'remark'],
      'editAccounting'   => ['last_check_no', 'bank_bal', 'bank_reg', 'transit_cp', 'cp_acct', 'transit_cr', 'cr_acct', 'print_bk_name', 'print_prop_name', 'two_sign','void_after', 'dump_group']
    ];

        # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['bankedit']) ? array_merge($orderField['editBank'],$orderField['editAccounting']) : [];
    $orderField['store']  = isset($perm['bankcreate']) ? array_merge($orderField['createBank'],$orderField['createAccounting']) : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $rProp = !empty($default) ? Helper::keyFieldName(M::getPropData(Model::buildWhere(['pb.trust'=>$default['trust']]), 'pb.prop', 0), 'prop', 'prop') : [''=>'Select Prop'];
    $disabled = isset($perm['bankupdate']) ? [] : ['disabled'=>1];
    ## On create, unset the default prop to select the first option of the prop dropdown
    if($fn == 'createBank') {
      unset($default['prop']);
    }
    $setting = [
      'createBank' => [
        'field' => [
          'trust'           => ['hint'=>'You can type prop address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],        
          'prop'            => ['type'=>'option', 'option'=>$rProp],     
          'gl_acct'         => ['label'=>'Cash GL', 'class'=>'autocomplete'],
          'br_name'         => ['label'=>'Branch Name'],
          'state'           => ['type' =>'option', 'value'=>'CA', 'option'=>$this->_mappingInfo['states']]
        ]
      ],
      'createAccounting' => [
        'field' => [
          'last_check_no'   => ['label'=>'Last Check No'],
          'bank_bal'        => ['label'=>'Bank Balance'],
          'transit_cp'      => ['label'=>'Transit Cash Payable'],
          'cp_acct'         => ['label'=>'Cash Payable Account'],
          'transit_cr'      => ['label'=>'Transit Cash Receivable'],
          'cr_acct'         => ['label'=>'Cash Receivable Account'],
          'print_bk_name'   => ['label'=>'Print Bank Name', 'type'=>'option', 'option'=>$this->_mappingBank['print_bk_name']],
          'print_prop_name' => ['label'=>'Print Property', 'type'=>'option', 'option'=>$this->_mappingBank['print_prop_name']],
          'two_sign'        => ['label'=>'2 Signers', 'type'=>'option', 'option'=>$this->_mappingBank['two_sign']],
          'dump_group'      => ['req'  =>0]
        ]  
      ],
      'updateBank' => [
        'field' => [
          'prop_bank_id'    => $disabled + ['type'=>'hidden'],
          'bank_id'         => $disabled + ['type'=>'hidden'],
          'trust'           => $disabled + ['readonly'=>1],
          'prop'            => $disabled + ['readonly'=>1],
          'bank'            => $disabled,
          'name'            => $disabled,
          'gl_acct'         => $disabled + ['label'=>'Cash GL', 'class'=>'autocomplete'],
          'br_name'         => $disabled + ['label'=>'Branch Name'],
          'street'          => $disabled,
          'city'            => $disabled,
          'state'           => $disabled + ['type' =>'option', 'value'=>'CA', 'option'=>$this->_mappingInfo['states']],
          'zip'             => $disabled,
          'phone'           => $disabled,
          'remark'          => $disabled
        ]  
      ],
      'updateAccounting' => [
        'field' => [
          'last_check_no'   => $disabled + ['label'=>'Last Check No'],
          'bank_bal'        => $disabled + ['label'=>'Bank Balance'],
          'bank_reg'        => $disabled,
          'transit_cp'      => $disabled + ['label'=>'Transit Cash Payable'],
          'cp_acct'         => $disabled + ['label'=>'Cash Payable Account'],
          'transit_cr'      => $disabled + ['label'=>'Transit Cash Receivable'],
          'cr_acct'         => $disabled + ['label'=>'Cash Receivable Account'],
          'print_bk_name'   => $disabled + ['label'=>'Print Bank Name', 'type'=>'option', 'option'=>$this->_mappingBank['print_bk_name']],
          'print_prop_name' => $disabled + ['label'=>'Print Property', 'type'=>'option', 'option'=>$this->_mappingBank['print_prop_name']],
          'two_sign'        => $disabled + ['label'=>'2 Signers', 'type'=>'option', 'option'=>$this->_mappingBank['two_sign']],
          'void_after'      => $disabled,
          'dump_group'      => $disabled + ['req'  =>0],
          'usid'            => $disabled + ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>1]
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['bankedit']) ? array_merge($setting['updateBank'], $setting['updateAccounting']) : [];
    $setting['store'] = isset($perm['bankcreate']) ? array_merge($setting['createBank'], $setting['createAccounting']) : [];
    
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
      'edit'  =>  isset($perm['bankupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
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
    if(isset($perm['bankExport'])) {
      $reportList['csv'] = 'Export to CSV';
    }
    
    ### BUTTON SECTION ###
    $_getCreateButton = function($perm){
      $button = '';
      if(isset($perm['bankcreate'])) {
        $button =  Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if(isset($perm['bankupdate'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    $bankEditable = isset($perm['bankupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=>$actionData['width']];
    }
    $data[] = ['field'=>'cons1', 'title'=>'Owner','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'trust', 'title'=>'Trust','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'bank', 'title'=>'Bank', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 25];
    $data[] = ['field'=>'gl_acct', 'title'=>'Cash GL', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 25];
    $data[] = ['field'=>'entity_name', 'title'=>'Entity Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'name', 'title'=>'Bank Name', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 300] + $bankEditable;
    $data[] = ['field'=>'br_name', 'title'=>'Branch Name', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 200] + $bankEditable;
    $data[] = ['field'=>'street', 'title'=>'Address','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250] + $bankEditable;
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 150] + $bankEditable;
    $data[] = $_getSelectColumn($perm, 'state', 'State', 25, $this->_mappingInfo['states']);
    $data[] = ['field'=>'transit_cp', 'title'=>'Routing #','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $bankEditable;
    $data[] = ['field'=>'cr_acct', 'title'=>'Account #','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $bankEditable;
    $data[] = ['field'=>'remark', 'title'=>'Remark','sortable'=> true, 'filterControl'=> 'input'] + $bankEditable;
    
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getCreateButton($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------  
  private function _keyMultiFieldNameElastic($r, $key_field, $val_field){
    $r = $r['hits']['hits'];
    
    /********************** ANONYMOUS FUNCTION ********************************/
    $getId = function($v, $key_field){
      $id = (gettype($key_field) == 'string') ? $v[$key_field] : '';
      if(gettype($key_field) == 'array'){
        foreach($key_field as $fl ){
          $id .= $v[$fl];
        }
      }
      return $id;
    };
    /**********************@ENd ANONYMOUS FUNCTION ****************************/
    $data = [];
    foreach($r as $v){
      $v = $v['_source'];
      $fieldCount = count($val_field);
      $key = $getId($v, $key_field);
      $fieldValue = '';
      for($i = 0; $i < $fieldCount; $i++){
        if($i === 0) {
          $fieldValue .= $v[$val_field[$i]];
        }else {
          $fieldValue .= ' - '. ucfirst($val_field[$i]) . ': ' . $v[$val_field[$i]];
        }
      }
      
      $data[$key] = $fieldValue;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['bankedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)"><i class="fa fa-edit text-aqua pointer tip" title="Edit Bank Information"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
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
      'to'      => 'nevin@pamamgt.com,sean@pamamgt.com,Kary@pamamgt.com,ryan@pamamgt.com,cindy@pamamgt.com,jonathan@dateworkers.com',
      'from'    => 'admin@pamamgt.com',
      'subject' => $action . ' By '.$usid.' on ' . date("F j, Y, g:i a"),
      'msg'     => $usid . ' ' . $action . ' on ' . date("F j, Y, g:i a") . ': <br><hr>' . $bodyMsg
    ]);
  }
}