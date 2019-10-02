<?php
namespace App\Http\Controllers\CreditCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CreditCheck\Agreement\RentalAgreement;
use App\Library\{RuleField, V, Form, Elastic, Mail, File, Html, Helper, Format, GridData, Upload, TenantAlert, Account, TableName AS T};
use \App\Console\InsertToElastic\ViewTableClass\creditcheck_view;
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class
use PDF;

class CreditCheckController extends Controller{
  private $_viewPath  = 'app/CreditCheck/creditcheck/';
  private $_viewTable = '';
  private $_indexMain = '';
  private $_location  = [];
  private $_emailList = [];
  private $_mapping   = [];
  public function __construct(Request $req){
    $this->_mapping             = Helper::getMapping(['tableName'=>T::$applicationInfo]);
    $this->_applicationMapping  = Helper::getMapping(['tableName'=>T::$application]);
    $this->_location            = File::getLocation(__CLASS__);
    $this->_emailList           = Mail::emailList(__CLASS__);
    $this->_viewTable           = T::$creditCheckView;
    $this->_indexMain           = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
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
        $vData['defaultSort'] = ['cdate:desc'];
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
    $tabData = [];
    $isCreateApp = !empty($req['isCreateApp']) ? 1 : 0;
    $page = $this->_viewPath . 'showTenantAlert';
    $r = M::getApplicationInfo('application_info_id', explode(',', trim($id, ',')));
    $fields = [
      'sec_deposit_add'=>['id'=>'sec_deposit_add','label'=>'Additional Deposit','class'=>'decimal', 'type'=>'text','req'=>0],
      'sec_deposit_note'=>['id'=>'sec_deposit_note','label'=>'Deposit Note','type'=>'text','req'=>0],
    ];
    $secAdd  = $isCreateApp ? Form::getField($fields['sec_deposit_add']) . Html::br(2) : '';
    $secNote = $isCreateApp ? Form::getField($fields['sec_deposit_note']) : '';
    foreach($r as $v){
      $name = title_case($v['fname'] . ' ' . $v['lname']);
      if(empty($v['run_credit'])){
        $tabData[$name] = Html::sucMsg('Credit Check is not run for this application (' . $name . '). No credit history is available to view.');  
      } else{
        $status = 0;
        $num = 0;
        while (!$status) {
          $rTenantAlert = TenantAlert::getInstance()->viewCreditReport($v['application_num']);
          $status = (!empty($rTenantAlert['result']) || ++$num > 15) ? 1 : 0;
          
          if(!empty($rTenantAlert['result'])){
            $result = $rTenantAlert['result']->result;
            
            $tabData[$name] = $result->applicant_html;  
            $tabData[$name] .= $result->decision_results;  
            $tabData[$name] .= $result->scores->score;  
            
            for ($i = 0; $i < count($result->report); $i++) {
              $tabData[$name] .= Html::h3($result->report[$i]->service_name) . Html::div($result->report[$i]->html);
            }
          } else {
            $tabData[$name] = $this->_getErrorMsg('showTenantAlert'); 
            sleep(3);
          }
        }
      }
    }
    return view($page, ['data'=>['secAdd'=>$secAdd,'secNote'=>$secNote, 'tab'=>Html::buildTab($tabData)]])->render();
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $perm = Helper::getPermission($req);
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['application_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    $r['uuid'] = '';
    $applicationFile = [];
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
    $isMovein   = isset($r['moved_in_status']) ? $r['moved_in_status'] : 0;
    
    foreach($fileUpload as $v){
      if($v['type'] == 'application'){
        $applicationFile[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($applicationFile, '/uploadApplication');
    $formApp = Form::generateForm([
      'tablez'    =>$this->_getTable('create'), 
      'button'    =>$isMovein ? '' : (isset($perm['creditCheckupdate']) ? $this->_getButton('edit', $req) : ''), 
      'orderField'=>$this->_getOrderField('create', $req), 
      'setting'   =>$this->_getSetting('edit', $r, $req)
    ]);
    
    $settingAppInfo = $this->_getSetting('formAppInfo')['field'];
    $accordionData  = [];
    $excludeField   = ['run_credit', 'app_fee', 'tnt_unit'];
    
    foreach($r[T::$application] as $i=>$val){
      $tableData = [];
      foreach($this->_getOrderField('formAppInfo') as $fl){
        if(isset($val[$fl]) && !in_array($fl, $excludeField)){
          $label = isset($settingAppInfo[$fl]['label']) ? $settingAppInfo[$fl]['label'] : title_case(str_replace_first('_', ' ', $fl));
          $v     = $val[$fl];
          $tableData[]  = ['desc'=>['val'=>$label],$fl=>['val'=>$v]];
        }
      }
      $accordionData['Tenant #' . ($i+1)] = Html::createTable($tableData, ['class'=>'table table-bordered'], 0, 0);
    }
    return view($page, [
      'data'=>[
        'alreadyMoveinMsg'=>$isMovein ? $this->_getSuccessMsg('editAlreadyMovein') : '', 
        'formApp'=>$formApp, 
        'formAppInfo'=>Html::buildAccordion($accordionData, 'accordion', ['class'=>'panel box']), 
        'upload'=>Upload::getHtml(),
        'fileUploadList'=>$fileUploadList,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $valid = V::startValidate([
      'rawReq'      => ['application_id'=>$id] + $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
//      'orderField'  => $this->_getOrderField(__FUNCTION__, $req), 
      'setting'     => $this->_getSetting(__FUNCTION__),
      'includeCdate'=>0,
      'validateDatabase'=>[
        'mustExist'=>[
          'application|application_id',
        ]
      ]
    ]);
    $unitId = '';
    $vData = $valid['dataNonArr'];
    
    $msgKey= count(array_keys($vData)) > 3 ? 'msg' : 'mainMsg';
    # VALIDATION IN SPECIAL CASE
    if(!empty($vData['prop']) && !empty($vData['unit'])){
      V::validateionDatabase(['mustExist'=>['unit|prop,unit']], $valid);
    } else if(!empty($vData['prop'])){
      V::validateionDatabase(['mustExist'=>['prop|prop']], $valid);
    }
    
    $_formRentalAgreementName = function($appId){
       return 'Tenant_Lease_Agreement_' . $appId . '.pdf';
    };
    $updateData        = [];
    $removeRental      = false;
    $oldApplication    = M::getTableData(T::$application,Model::buildWhere(['application_id'=>$id]),['application_id','moved_in_status','status','raw_agreement']);
    if($oldApplication['moved_in_status']){
      Helper::echoJsonError(Html::errMsg('This application is already moved in. You cannot change the application status.'), 'popupMsg');
    }
    
    $rawData           = !empty($oldApplication['raw_agreement']) ? json_decode($oldApplication['raw_agreement'],true) : [];
    $rentalName        = $_formRentalAgreementName($id);
    $oldFile           = M::getApplicationUpload(Model::buildWhere(['foreign_id'=>$id,'file'=>$rentalName]));
    $newStatus         = !empty($vData['status']) ? $vData['status'] : '';
    if(!empty($oldFile) && $oldApplication['status'] !== 'Approved' && $newStatus === 'Approved'){
      $newPdf       = RentalAgreement::addAgentSig($id,$rawData);
      RentalAgreement::generatePdfFile(['application_id'=>$id,'agreement'=>$newPdf]);
      $usid         = Helper::getUsid($req);

      $vData['raw_agreement'] = $newPdf;
      $updateData[T::$fileUpload]  = [
        'whereData'   => ['foreign_id'=>$id,'file'=>$rentalName,'type'=>'agreement'],
        'updateData'  => [
          'usid'     => $usid,
          'active'   => 1,
          'ext'      => 'pdf',
        ]
      ];
    }
    
    if($newStatus === 'Rejected' || (isset($vData['application_status']) && $vData['application_status'] == 'Rejected')){
       $vData['raw_agreement']        = '';
       $vData['is_upload_agreement']  = 0;
       $removeRental           = true;
       
      if(!empty($oldFile['path']) && !empty($oldFile['uuid']) && !empty($oldFile['file'])){
        $path = $oldFile['path'] . $oldFile['uuid'] . '/' . $oldFile['file'];
        $flag = file_exists($path) ? unlink($path) : 0;
        $flag = file_exists($oldFile['path'] . $oldFile['uuid']) ? rmdir($oldFile['path'] . $oldFile['uuid']) : 0; 
      }
       
    }
    # PREPARE THE DATA FOR UPDATE AND INSERT
    if(!empty($vData['application_info_id']) && is_numeric($vData['application_info_id'])){
      unset($vData['application_info']);
      if(isset($vData['app_fee_recieved'])){
        $vData['app_fee_recieved_date'] = !empty($vData['app_fee_recieved']) ? Helper::mysqlDate() : '1000-01-01';
      }
      $updateData[T::$applicationInfo] = ['whereData'=>['application_info_id'=>$vData['application_info_id']],'updateData'=>$vData];
    } else{
      $updateData[T::$application]     = ['whereData'=>['application_id'=>$id],'updateData'=>$vData];

      if(isset($vData['prop']) && isset($vData['unit']) && $vData['new_rent']){
        $unitId = M::getUnit(Model::buildWhere(['prop'=>$vData['prop'], 'unit'=>$vData['unit']]))['unit_id'];
        $updateData[T::$unit] =[
          'whereData'=>['prop'=>$vData['prop'], 'unit'=>$vData['unit']],
          'updateData'=>['rent_rate'=>$vData['new_rent']]
        ];
      }
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      if($removeRental && !isset($vData['sec_deposit_note'])){
        $success[T::$fileUpload] = M::deleteTableData(T::$fileUpload,Model::buildWhere(['foreign_id'=>$id,'type'=>'agreement']));
      }
      $elastic = [
        'insert'=>[T::$creditCheckView=>['a.application_id'=>[$id]]]
      ];
      if(!empty($unitId)){
        $elastic['insert'][T::$unitView] = ['u.unit_id'=>[$unitId]];
      }
      
      $response[$msgKey]         = $this->_getSuccessMsg(__FUNCTION__);
      $response['updateCreateMsg'] = view($this->_viewPath . 'successUpdateCreateMsg', [])->render();
      
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
    $isKeyArray = 1;
    $page = $this->_viewPath . 'create';
    $formApp = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__)
    ]);
    $formAppInfo = Form::generateForm([
      'tablez'    =>$this->_getTable('formAppInfo'),
      'button'    =>$this->_getButton('formAppInfo'),
      'orderField'=>$this->_getOrderField('formAppInfo'), 
      'setting'   =>$this->_getSetting('formAppInfo', [], $req->all()),
      'isKeyArray'=>$isKeyArray
    ]);
    return view($page, [
      'data'=>[
        'formApp'=>$formApp, 
        'formAppInfo'=>$formAppInfo, 
        'upload'=>Upload::getHtml(),
        'nav'=>$req['NAV'],
        'account'=>Account::getHtmlInfo($req['ACCOUNT'])
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => array_merge($this->_getTable('create'), $this->_getTable('formAppInfo')), 
      'orderField'      => array_merge($this->_getOrderField('create'), $this->_getOrderField('formAppInfo')),
      'setting'         =>$this->_getSetting('create'),
      'validateDatabase'=>[
        'mustExist'   =>[
          'unit|prop,unit', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $r = M::getUnit(Model::buildWhere(['prop'=>$vData['prop'], 'unit'=>$vData['unit']]));
    $rOldRent = M::getTenantOldRent(Model::buildWhere(['prop'=>$vData['prop'], 'unit'=>$vData['unit']]), 0);
    
    if(!empty($rOldRent)){
      $rOldRentTmp = Helper::keyFieldName($rOldRent, 'service_code', 'amount');
      if(isset($rOldRentTmp['HUD'])){
        $oldRent  = isset($rOldRentTmp[602]) ? $rOldRentTmp[602] : 0;
        $oldRent += isset($rOldRentTmp['HUD']) ? $rOldRentTmp['HUD'] : 0;
      } else{
        $oldRent = $rOldRent[0]['amount'];
      } 
      $vData['old_rent'] = $oldRent;
    } else{
      $rTenant = M::getTenant(Model::buildWhere(['prop'=>$vData['prop'], 'unit'=>$vData['unit']]));
      $vData['old_rent'] =  isset($rTenant['base_rent']) ? $rTenant['base_rent'] : $r['rent_rate'];
    }
    $vData['cdate']  = Helper::mysqlDate();
    $vData['ran_by'] = $req['ACCOUNT']['account_id'];
    $vData['usid']   = Helper::getUsid($req);
    $uuid    = explode(',', (rtrim($vData['uuid'], ',')));
    $dataArr = $valid['dataArr'];
    unset($vData['uuid']);
//    if($vData['new_rent'] < $vData['old_rent']){
//      Helper::echoJsonError($this->_getErrorMsg('storeRentLower',$vData), 'mainMsg');
//    }
    
    # VALID AGAINT COMPANY DATABASE FOR EVICT
    if(isset($valid['op']) && $valid['op'] == 'validateSSN'){
      return $this->_validateAgainstDatabase($dataArr);
    } else{
      foreach($dataArr as $i=>$v){
        ## Validate date of birth 18+
        if(!$this->isAgeOver18($dataArr[$i]['dob'])) {
          Helper::echoJsonError($this->_getErrorMsg('ageRestriction'), 'popupMsg');
        }
        if(empty($v['run_credit'])){ // No need to run credit check
          $dataArr[$i]['application_num'] = 0;
        } else{
          $r = TenantAlert::getInstance()->processCredit($v);
          if(!empty($r->status) && $r->status == 'OK' && !empty($r->result->application_id)){
            $applicationNum = (array)$r->result->application_id;
            $dataArr[$i]['application_num'] = $applicationNum[0];
            $response['applicationNum'][]  = $applicationNum[0];
          } else{
             $valid['error'] = $this->_getErrorMsg('showTenantAlert');
          }
        }
      }
      ############### DATABASE SECTION ######################
      DB::beginTransaction();
      $updateDataSet = $insertDataSet = $success = $elastic = [];
      try{
        $success += Model::insert([T::$application=>$vData]);
        $applicationId = $success['insert:' . T::$application][0];
        foreach($dataArr as $i=>$v){
          $dataArr[$i]['prop'] = $vData['prop'];
          $dataArr[$i]['application_id'] = $applicationId;
        }
        foreach($uuid as $v){
          $updateDataSet[T::$fileUpload][] = ['whereData'=>['uuid'=>$v], 'updateData'=>['foreign_id'=>$applicationId]];
        }
        $success += Model::insert([T::$applicationInfo=>$dataArr]);
        $success += Model::update($updateDataSet);

        $elastic = [
          'insert'=>[$this->_viewTable=>['application_id'=>[$applicationId]]]
        ];
        $response['id'] = implode(',', $success['insert:application_info']);
        $response['appId'] = $applicationId;
        
        Model::commit([
          'success' =>$success,
          'elastic' =>$elastic
        ]);
      } catch(\Exception $e){
        $response['error']['mainMsg'] = Model::rollback($e);
      }
      return isset($valid['error']) ? $valid :$response;
    }
  }  
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create'     =>[T::$application, T::$fileUpload],
      'formAppInfo'=>[T::$applicationInfo],
      'update'     =>[T::$application, T::$applicationInfo]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm = Helper::getPermission($req);
    $orderField = [
      'create'     =>['uuid', 'isManager', 'prop', 'unit', 'new_rent', 'sec_deposit', 'sec_deposit_add', 'sec_deposit_note', 'ordered_by', 'section8'],
      'formAppInfo'=>['run_credit', 'fname', 'mname', 'lname', 'suffix', 'email', 'cell', 'dob', 'social_security', 'driverlicense', 'driverlicensestate', 'street_num', 'street_name','tnt_unit',  'city', 'state', 'zipcode', 'app_fee']
    ];
    $orderField['update'] = isset($perm['creditCheckupdate']) ? $orderField['create'] + ['status','app_fee_recieved'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$default = [], $req=[]){
    $perm = Helper::getPermission($req);
    $runCreditCheck = $this->_mapping['run_credit'];
    $rAccount = Helper::keyFieldName(M::getAccount(Model::buildWhere(['active'=>1]), 0), 'account_id', 'name') ;
    $rUnit = !empty($default) ? Helper::keyFieldName(M::getUnit(Model::buildWhere(['prop'=>$default['prop']]), ['unit'], 0), 'unit', 'unit') : [''=>'Select Unit'];
    if(!isset($perm['creditCheckcreatemanagerMovein'])){
      unset($runCreditCheck[0]);
    }
    $isMovein = isset($default['moved_in_status']) ? $default['moved_in_status'] : 0;
    $disabled = $isMovein ? ['disabled'=>1] : [];
    $disabled = empty($disabled) && isset($perm['creditCheckupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'create'=>[
        'field'=>[
          'uuid'            =>['type'=>'hidden'],
          'sec_deposit'     =>['label'=>'Deposit', 'readonly'=>1],
          'sec_deposit_add' =>['label'=>'Deposit Add', 'req'=>0],
          'sec_deposit_note'=>['label'=>'Deposit Note'],
          'new_rent'        =>['readonly'=>1],
          'prop'            =>['label'=>'Property', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'unit'            =>['type'=>'option', 'option'=>$rUnit],
          'isManager'       =>['type'=>'option', 'option'=>['0'=>'No', '1'=>'Yes'], 'label'=>'Manager?', 'req'=>1],
          'ordered_by'      =>['type'=>'option', 'option'=>$rAccount],
          'section8'        =>['type'=>'option', 'option'=>$this->_mapping['section8']]
        ],
        'rule'=>[
          'new_rent'        =>'required|numeric|between:0,65000',
          'sec_deposit_add' =>'nullable|numeric|between:0,65000',
          'sec_deposit'     =>'required|numeric|between:0,65000',
        ]
      ],
      'edit'=>[
        'field'=>[
          'uuid'            =>$disabled + ['type'=>'hidden'],
          'sec_deposit'     =>$disabled + ['label'=>'Deposit'],
          'sec_deposit_add' =>$disabled + ['label'=>'Deposit Add', 'req'=>0],
          'sec_deposit_note'=>$disabled + ['label'=>'Deposit Note'],
          'new_rent'        =>$disabled,
          'prop'            =>$disabled + ['label'=>'Property', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'unit'            =>$disabled + ['type'=>'option', 'option'=>$rUnit],
          'isManager'       =>$disabled + ['type'=>'option', 'option'=>['0'=>'No', '1'=>'Yes'], 'label'=>'Manager?', 'req'=>1],
          'ordered_by'      =>$disabled + ['type'=>'option', 'option'=>$rAccount],
          'section8'        =>$disabled + ['type'=>'option', 'option'=>$this->_mapping['section8']]
        ],
        'rule'=>[
          'sec_deposit_add' =>'nullable|numeric|between:0,65000',
        ]
      ],
      'update'=>[
        'rule'=>[
          'sec_deposit_add' =>'nullable|numeric|between:0,65000',
          'sec_deposit' =>'required|numeric|between:0,65000',
          'new_rent' =>'required|numeric|between:0,65000',
        ]
      ],
      'formAppInfo'=>[
        'field'=>[
          'fname'     =>['label'=>'First Name'],
          'mname'     =>['label'=>'Middle Name'],
          'lname'     =>['label'=>'Last Name'],
          'suffix'    =>['type'=>'option','option'=>$this->_mapping['suffix']],
          'cell'      =>['label'=>'Phone#'],
          'dob'       =>['label'=>'Date Of Birth'],
          'driverlicense'      =>['label'=>'Driver License'],
          'street_num'         =>['label'=>'Street#'],
          'street_name'        =>['label'=>'Street Name'],
          'tnt_unit'           =>['label'=>'Unit #'],
          'social_security'    =>['class'=>'ssn'],
          'email'              =>['class'=>'email'],
          'app_fee'            =>['type'=>'hidden', 'value'=>35],
          'direct'             =>['type'=>'option', 'option'=>$this->_mapping['direct']],
          'prop_type'          =>['label'=>'Property Type', 'type'=>'option', 'option'=>$this->_mapping['prop_type']],
          'state'              =>['type'=>'option', 'option'=>$this->_mapping['states'], 'value'=>'CA'],
          'driverlicensestate' =>['label'=>'License Issue', 'type'=>'option', 'option'=>$this->_mapping['states'], 'value'=>'CA'],
          'run_credit'         =>['type'=>'option', 'option'=> $runCreditCheck]
        ]
      ]
    ];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn){
    $buttton = [
      'edit'=>['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']],
      'create'=>['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']],
      'formAppInfo'=>[]
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm       = Helper::getPermission($req);
    $rows       = [];
    $total      = !empty($r['hits']['total']) ? $r['hits']['total'] : 0;
    $result     = !empty($r['hits']['hits']) ? $r['hits']['hits'] : [];
    $actionData = $this->_getActionIcon($perm);
    
    foreach($result as $i=>$v){
      $source = $v['_source']; 
      $source['num']             = $vData['offset'] + $i + 1;
      $source['action']          = $actionData['icon'];
      $source['singature']       = Html::span('Signature', ['class'=>'clickable']);
      $appStatus                 = Helper::getValue('application_status',$source,'Rejected');
      $source['isEditable']      = !($source['moved_in_status']) && !empty($this->_checkAgreementExist($source)) && $appStatus !== 'Rejected' ? true : false;
      
      $source['moved_in_status'] = $this->_getMoveIn($source, $req);
//      $agreementLink             = isset($perm['signAgreement']) && empty($source['is_upload_agreement']) ? Html::a('Complete Rental Agreement',['target'=>'_blank','title'=>'Digital Rental Agreement','href'=>action('CreditCheck\Agreement\SignAgreementController@edit',$source['application_id'])]) : '';
//      $source['agreement']       = !empty($source['is_upload_agreement']) && !empty($this->_checkAgreementExist($source)) && $appStatus !== 'Rejected' ? Html::span('View', ['class'=>'clickable']) : '';
//      $source['rental_agreement']= !empty($source['is_upload_agreement']) || $appStatus === 'Rejected' ? '' : $agreementLink;
      $source['cdate']           = date('Y-m-d', strtotime($source['cdate']));
      $source['udate']           = date('Y-m-d', strtotime($source['udate']));
      $source['new_rent']        = Format::usMoney($source['new_rent']);
      $source['sec_deposit']     = Format::usMoney($source['sec_deposit']);
      
      ###### APPLICATION SECTION ######
      $source['application.tenant'] = '';
      $source['application.social_security'] = '';
      $source['application.app_fee_recieved'] = '';
      $source['application.app_fee_recieved_date'] = '';
      $source['application.tnt_name'] = '';
      $source['application.run_credit'] = '';
      $source[T::$application] = isset($source[T::$application]) ? $source[T::$application] : [];
      
      $linkParams = [];
      foreach($source[T::$application] as $j=>$app){
        $tenantClickable = isset($perm['creditCheckshow']) ? ' clickable' : '';
        
        $feeRecieved = $app['run_credit'] ? Html::buildCheckbox($app['app_fee_recieved'], ['class'=>'pointer fee', 'data-id'=>$app['application_id'], 'data-key'=>$app['application_info_id']]) . Html::br() : '' . Html::br();
        $source['application.app_fee_recieved'] .= isset($perm['creditCheckupdatefee']) ? $feeRecieved : ($app['app_fee_recieved'] ? 'Yes' : 'No') . Html::br();
        $source['application.tenatNameClickable'] = isset($perm['creditCheckshow']) ? 1 : 0;
        $source['application.tnt_name']         .= Html::span(title_case($app['tnt_name']), ['data-toggle'=>'tooltip', 'class'=>'hint' . $tenantClickable]) . Html::br();
        $source['application.social_security']  .= Html::span($app['social_security']) . Html::br();
        $source['application.run_credit']       .= ($app['run_credit'] ? 'Yes' : 'No') . Html::br();
        $source['application.app_fee_recieved_date']  .= Html::span($app['app_fee_recieved_date']) . Html::br();
        
        $linkParams['social_security']   = substr($app['social_security'],-4);
        $linkParams['fname']             = $app['fname'];
        $linkParams['lname']             = $app['lname'];
      }
      
      $agreementLink               = isset($perm['signAgreement']) && empty($source['is_upload_agreement'])  ? Html::a('Complete Rental Agreement',['target'=>'_blank','title'=>'Digital Rental Agreement','href'=>action('CreditCheck\Agreement\SignAgreementController@edit',$source['application_id']) . '?' . http_build_query($linkParams)])  : '';
      $source['agreement']         = !empty($source['is_upload_agreement']) && !empty($this->_checkAgreementExist($source)) && $appStatus !== 'Rejected' ? Html::span('View',['class'=>'clickable']) : '';
      $source['rental_agreement']  = !empty($source['is_upload_agreement']) || $appStatus === 'Rejected' ? '' : $agreementLink;
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$total];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $actionData = $this->_getActionIcon($perm);
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = [];
    if(isset($perm['creditCheckReportindexFeeReport'])){
      $reportList['FeeReport']   = 'Credit Check Fee Report';
    }
    if(isset($perm['creditCheckReportindexDailyReport'])){
      $reportList['DailyReport'] = 'Credit Check Daily Report';
    }
    if(isset($perm['creditCheckReportindexMoveinReport'])){
      $reportList['MoveinReport'] = 'Move In Report';
    }
    
    ### BUTTON SECTION ###
    $_getCreateButton = function($perm){
      $button =  Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=>'new', 'class'=>'btn btn-success']);
      return isset($perm['creditCheckcreate']) ? Html::a($button, ['href'=>'/creditCheck/create', 'style'=>'color:#fff;']) : '';
    };
    
    ### COLUMNS SECTION ###
    $_getStatus =function ($perm){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/status:' . T::$creditCheckView,'field'=>'status','title'=>'Spvr. Status','sortable'=> true,'width'=> 25];
      if(isset($perm['creditCheckupdatesupervisorApproval'])){
        $optionStatus = OptionFilter::getInstance()->getOptionFilter('status', T::$creditCheckView, 0);
        $data['editable'] = ['type'=>'select','source'=>$optionStatus,'isEditableField'=>'isEditable'];      
      }
      return $data;
    };
    
    $_getApplicationStatus = function($perm,$source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/application_status:' . T::$creditCheckView,'field'=>'application_status','title'=>'App. Status','sortable'=> true,'width'=> 25];
      if(isset($perm['creditCheckupdateapproval'])){
        $data['editable'] = ['type'=>'select','source'=>$source];      
      }
      return $data;
    };
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=>$actionData['width']];
    }
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 50];
    $data[] = ['field'=>'unit', 'title'=>'Unit', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'tenant', 'title'=>'Tnt', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'new_rent', 'title'=>'Rent', 'sortable'=> true, 'filterControl'=> 'input','width'=> 75];
    $data[] = ['field'=>'sec_deposit', 'title'=>'Dep.', 'sortable'=> true, 'filterControl'=> 'input','width'=> 25];
    $data[] = ['field'=>'street', 'title'=>'Address','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'group1', 'title'=>'Group','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = $_getApplicationStatus($perm,$this->_applicationMapping['application_status']);
    $data[] = $_getStatus($perm);
    $data[] = ['field'=>'ordered_by', 'title'=>'Order By','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'ran_by', 'title'=>'Run By', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'application.tnt_name', 'title'=>'Applicant','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'application.social_security', 'title'=>'SSN','sortable'=> true, 'filterControl'=> 'input', 'width'=> 100];
    $data[] = ['field'=>'application.app_fee_recieved', 'title'=>'Fee','filterControl'=>'select','filterData'=> 'url:/filter/application.app_fee_recieved:'. T::$creditCheckView,'sortable'=> true,'width'=> 25];
    $data[] = ['field'=>'application.run_credit', 'title'=>'Run Credit?','sortable'=> true, 'width'=> 50];
    if(isset($perm['uploadAgreementindex'])){
      $data[] = ['field'=>'agreement', 'title'=>'Agreement','width'=> 75];
    }
    if(isset($perm['signAgreement'])){
      $data[] = ['field'=>'rental_agreement','title'=>'Rental Agreement','width'=>150];
    }
    if(isset($perm['movein'])){
      $data[] = ['field'=>'moved_in_status', 'title'=>'MoveIn','filterControl'=> 'select','filterData'=> 'url:/filter/moved_in_status:' . T::$creditCheckView,'sortable'=> true, 'width'=> 150];
    }
    $data[] = ['field'=>'application.app_fee_recieved_date', 'title'=>'Paid Fee Date','filterControl'=> 'input','filterControlPlaceholder'=>'yyyy-mm-dd','sortable'=> true, 'width'=> 100];
    $data[] = ['field'=>'cdate', 'title'=>'Scan Date','filterControl'=> 'input','filterControlPlaceholder'=>'yyyy-mm-dd','sortable'=> true];
    $data[] = ['field'=>'move_in_date', 'title'=>'Movein Date','filterControl'=> 'input','filterControlPlaceholder'=>'yyyy-mm-dd','sortable'=> true];
    $data[] = ['field'=>'housing_dt2', 'title'=>'Sys date','filterControl'=> 'input','filterControlPlaceholder'=>'yyyy-mm-dd','sortable'=> true];
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getCreateButton($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getMoveIn($source, $req) {
    $perm = $req['PERM'];
    $data = 'No Move In';
    $isFeeRecieved = $runCredit = $fileUploadExtNum = 0;
    $isAgreementRecieved = $this->_checkAgreementExist($source);
    foreach($source[T::$application] as $v){
      $isFeeRecieved = $v['app_fee_recieved'];
      if(empty($isFeeRecieved)){
        break;
      }
    }
    foreach($source[T::$application] as $v){
      $runCredit = $v['run_credit'];
      if(empty($runCredit)){
        break;
      }
    }
    if(!empty($source['fileUpload'])){
      $fileUpload = (Helper::groupBy($source['fileUpload'], 'type'));
      if(!empty($fileUpload['agreement'])){
        $fileUploadExtNum = count(Helper::groupBy($fileUpload['agreement'], 'ext'));
      }
    }
    
    if(!$isFeeRecieved && !$source['moved_in_status'] && $runCredit){
      $data = 'No Fee Received';
    } else {
      ### IF THE FILE EXTENTION $fileUploadExtNum IS MORE THAN 2, WE KNOW THAT THEY UPLOAD PDF AND PICTURE
      if($source['application_status'] === 'Approved'  && $isAgreementRecieved && preg_match('/Approved/',$source['status'])){
        $data = $source['moved_in_status'] ? 'Completed' : Html::span('Move In',['class'=>'clickable']);
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'showTenantAlert' =>Html::errMsg('There is a problem with the TenantAlert. Please have last 4 digits of SSN ready and call (866) 272-8400 to resolve the issue.'),
      'mysqlError'  =>Html::mysqlError(),
      'storeRentLower' =>Html::errMsg('New Rent is lower than the old rent (' . (isset($vData['old_rent']) ? Format::usMoney($vData['old_rent']) : '' ) .' ). Please ask permission from Mike before you proceed.'),
      'ageRestriction' =>Html::errMsg('All Applicants age must be between 18-100 years.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'editAlreadyMovein' =>Html::sucMsg('Tenant is Successfully Moved In. Congratulation.'),
      'update' =>Html::sucMsg('Successfully Update.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _validateAgainstDatabase($dataArr){
    $where = [];
    $repsonse = ['li'=>'', 'div'=>'', 'report'=>''];
    foreach($dataArr as $i=>$v){
      $where['ai.social_security'] = $v['social_security'];
    }
    
    # GET ALL THE RESULT THAT USE TO RUN THE LAST 30 DAYS
    $period = date('Y-m-d H:i:s', strtotime('-30 days'));
    $r = M::getTenantInfo(Model::buildWhere($where), $period);
    
    # IF NOTHING FINE, TRY TO FIND TO GET THE EVICTION
    if(empty($r)){
      $where['t.status'] = 'E';
      $r = M::getTenantInfo(Model::buildWhere($where), '');
    }
    $map = $this->_getSetting('formAppInfo')['field'];
    $select = array_flip(['fname', 'lname', 'dob', 'social_security', 'status']);
    if(!empty($r)){
      $s = count($r) > 1 ? 's were' : ' was';
      $titleStatus = empty($r[0]['status']) ? 'application ' . $s .' ran once in the last 30 days' : 'applicant ' . $s .' evicted previously from property #' . $r[0]['prop'];
      $repsonse['title'] = 'The following ' . $titleStatus . '. Do you still want to run the credit check?' . Html::br();
      foreach($r as $i=>$val){
        $tab = 'tab_' . ($i + 1);
        $active = ($i == 0) ? 'active' : '';
        $applicationName = $val['fname'] . ' ' . $val['lname'];
        
        # TAB HEADER
        $a = Html::a(($val['status'] == 'E') ? Html::span($applicationName, ['class'=>'text-red']) : $applicationName, ['href'=>'#'.$tab, 'data-toggle'=>'tab']);
        $repsonse['li'] .= Html::li($a, ['class'=>$active]);
        
        # TAB BODY
        $tableData = [];
        $val['status'] = $val['status'] == 'E' ? Html::b('EVICTED on ' . date('m/d/Y', strtotime($val['move_out_date'])), ['class'=>'text-red']) 
                                               : 'Run once on ' . date('m/d/Y', strtotime($val['cdate']));
        foreach($val as $k=>$v){
          if(isset($select[$k])){
            $tableData[] = [
              'label'=>['val'=>isset($map[$k]['label']) ? $map[$k]['label'] : title_case(preg_replace('/_/', ' ', $k))],
              'value'=>['val'=>$v],
            ];
          }
        }
        $repsonse['div'] .= Html::div(Html::createTable($tableData, ['class'=>'table table-bordered'], 0), ['class'=>'tab-pane ' . $active, 'id'=>$tab]);
      }
      return ['html'=>view($this->_viewPath . 'validateApplication', ['data'=>$repsonse])->render()];
    } else{
      return []; // NEED TO RETURN EMPTY STRING
    }
  }
//------------------------------------------------------------------------------
  private function _checkAgreementExist($source){
    if(!empty($source['fileUpload'])){
      foreach($source['fileUpload'] as $v){
        if($v['type'] == 'agreement'){
          return 1;
        }
      }
    }
    return 0;
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['creditCheckupdate']) || isset($perm['creditCheckedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)"><i class="fa fa-edit text-aqua pointer tip" title="Edit Application Information"></i></a>';
    }
    if(isset($perm['uploadAgreementstore'])){
      $actionData[] = '<a class="agreementUpload" href="javascript:void(0)"><i class="fa fa-upload text-aqua pointer tip" title="Upload An Agreement"></i></a>';
    }
    if(isset($perm['transferTenant'])){
      $actionData[] = '<a class="transferTenant" href="javascript:void(0)"><i class="fa fa-exchange text-aqua pointer tip" title="Transfer Tenant To Different Property/Unit"></i></a>';
    }
    $num = count($actionData);
    return ['icon'=>implode(Html::space(2), $actionData), 'width'=>$num * 42];
  }
//------------------------------------------------------------------------------
  private function isAgeOver18($birthday){
    return time() > strtotime('+18 years', strtotime($birthday)) && time() < strtotime('+100 years', strtotime($birthday));
  }
}
