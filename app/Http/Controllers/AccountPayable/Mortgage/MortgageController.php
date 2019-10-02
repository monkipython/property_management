<?php
namespace App\Http\Controllers\AccountPayable\Mortgage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Format, GridData, Upload, Account, TableName AS T};
use App\Http\Models\{Model, AccountPayableModel AS M}; // Include the models class

class MortgageController  extends Controller {
  private $_viewPath          = 'app/AccountPayable/Mortgage/mortgage/';
  private $_viewTable         = '';
  private $_indexMain         = '';
  private $_mapping           = [];
  private static $_instance;
  
  public function __construct(){
    $this->_viewTable = T::$vendorMortgageView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$vendorMortgage]);
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
    $page   = $this->_viewPath . 'create';
    $form   = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField('createForm1'), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $req)
    ]);
    
    $form2  = Form::generateForm([
      'tablez'     => $this->_getTable(__FUNCTION__),
      'button'     => $this->_getButton(__FUNCTION__),
      'orderField' => $this->_getOrderField('createForm2'),
      'setting'    => $this->_getSetting(__FUNCTION__,$req),
    ]);
    return view($page, [
      'data'=>[
        'form'             => $form,
        'form2'            => $form2,
        'upload'           => Upload::getHtml(),
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function edit($id,Request $req){
    $page   = $this->_viewPath . 'edit';
    $r      = M::getMortgageElastic(['vendor_mortgage_id'=>$id],[],1);
    $formHtml     = Form::generateForm([
      'tablez'    => $this->_getTable(__FUNCTION__),  
      'orderField'=> $this->_getOrderField('editForm1',$req), 
      'setting'   => $this->_getSetting(__FUNCTION__, $req, $r)
    ]);
   
    $formHtml2    = Form::generateForm([
      'tablez'      => $this->_getTable(__FUNCTION__),
      'button'      => $this->_getButton(__FUNCTION__,$req),
      'orderField'  => $this->_getOrderField('editForm2',$req),
      'setting'     => $this->_getSetting(__FUNCTION__,$req,$r),
    ]);
    $r['uuid']     = '';
    $uploadFiles   = [];
    $fileUpload        = Helper::getValue('fileUpload',$r,[]);
    foreach($fileUpload as $v){
      if($v['type'] == 'mortgage'){
        $uploadFiles[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($uploadFiles, '/uploadMortgage','upload',['includeDeleteIcon'=>1]);

    return view($page, [
      'data'=>[
        'form'             => $formHtml,
        'form2'            => $formHtml2,
        'upload'           => Upload::getHtml(),
        'fileUploadList'   => $fileUploadList,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id,Request $req){
    $req->merge(['vendor_mortgage_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate' => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorMortgage . '|vendor_mortgage_id',
        ]
      ]
    ]);
    
    $vData                = $valid['dataNonArr'];
    $msgKey               = count($vData) > 3 ? 'msg' : 'mainMsg';
    $r                    = M::getMortgageElastic(['vendor_mortgage_id'=>$id],['vendor_mortgage_id','prop','gl_acct_ap','vendid'],1);
    $verifyKey            = ['prop','vendid','gl_acct'];
    foreach($verifyKey as $v){
      $valid['data']  += empty($vData[$v]) ? [$v => Helper::getValue($v,$r)] : [];
    }
    $valid['data']['gl_acct']  = Helper::getValue('gl_acct_ap',$r);
    V::validateionDatabase(['mustExist'=>[T::$vendor . '|vendid',T::$glChart . '|prop,gl_acct']],$valid);
    $vData['usid'] = Helper::getUsid($req);
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$vendorMortgage=>['whereData'=>['vendor_mortgage_id'=>$id],'updateData'=>$vData]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vm.vendor_mortgage_id'=>[$id]]]
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
    $req->merge(['gl_acct'=>Helper::getValue('gl_acct_ap',$req)]);
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
      $insertedId          = $success['insert:' . T::$vendorMortgage][0];     
      $updateDataSet     += !empty($uuid) ? [T::$fileUpload => ['whereInData'=>['field'=>'uuid','data'=>$uuid],'updateData'=>['foreign_id'=>$insertedId]]] : [];
      $success           += !empty($updateDataSet) ? Model::update($updateDataSet) : [];
      $elastic = [
        'insert'=>[$this->_viewTable=>['vm.vendor_mortgage_id'=>[$insertedId]]]
      ];
      $response['vendorMortgageId'] = $insertedId;
      $response['msg']              = $this->_getSuccessMsg(__FUNCTION__);
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
    $req->merge(['vendor_mortgage_id'=>$req['id']]);
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$vendorMortgage . '|vendor_mortgage_id',
        ]
      ]
    ]);
    $vData     = $valid['data'];
    $deleteIds = $vData['vendor_mortgage_id'];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      foreach($deleteIds as $id) {
        $success[T::$vendorMortgage][] = HelperMysql::deleteTableData(T::$vendorMortgage,Model::buildWhere(['vendor_mortgage_id'=>$id]));
      }
      $commit['success']           = $success;
      $commit['elastic']['delete'] = [T::$vendorMortgageView => ['vendor_mortgage_id'=>$deleteIds]];
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
    $orderField    = array_merge(Helper::getValue('orderField',$data,[]),['gl_acct']);
    $setting       = Helper::getValue('setting',$data,[]);
    $tablez        = Helper::getValue('tablez',$data,[]);
    $isPopup       = Helper::getValue('isPopMsgError',$data,0);
    $includeCdate  = Helper::getValue('includeCdate',$data,0);
    $usid          = Helper::getValue('usid',$data);
    $validateDb    = !empty($data['validateDatabase']) ? $data['validateDatabase'] : [
      'mustExist'  => [
        T::$vendor . '|vendid',
        T::$glChart . '|prop,gl_acct',
      ]
    ];
    $valid         = V::startValidate([
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
      $rGlChart                            = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$v['prop']])),'gl_acct');
      $rService                            = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$v['prop']])),'service');
      $v['vendor_id']                      = Helper::getValue($v['vendid'],$rVendor);
      $v['paid_off_loan']                  = 0;
      $insertRow                           = HelperMysql::getDataset([T::$vendorMortgage=>$v],$usid,$rGlChart,$rService);

      $dataset[T::$vendorMortgage][]       = $insertRow[T::$vendorMortgage];
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
      'create'            => [T::$vendorMortgage,T::$fileUpload],
      'edit'              => [T::$vendorMortgage,T::$fileUpload],
      'update'            => [T::$vendorMortgage,T::$fileUpload],
      'destroy'           => [T::$vendorMortgage],
      'store'             => [T::$vendorMortgage,T::$fileUpload],
      '_getConfirmForm'   => [T::$vendorMortgage],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $perm   = Helper::getPermission($req);
    $button = [
      'create'          => ['submit'=>['id'=>'submit','value'=>'Create Mortgage','class'=>'col-sm-12']],
      'edit'            => ['submit'=>['id'=>'submit','value'=>'Update Mortgage','class'=>'col-sm-12']],
    ];
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm         = Helper::getPermission($req);
    $orderField   = [
      'create'          => ['uuid','prop','bank','allocation','vendid','invoice','amount','init_principal','interest_rate','loan_term','gl_acct_ap','gl_acct_liability','due_date','loan_date','journal_entry_date','maturity_date','loan_option','loan_type','payment_option','payment_type','recourse','index_title','index','margin','dcr','last_payment','prepaid_penalty','prop_tax_impound','escrow','reserve','additional_principal','note'],
      'edit'            => ['vendor_mortgage_id','bank','allocation','vendid','invoice','amount','interest_rate','loan_term','gl_acct_ap','gl_acct_liability','due_date','loan_date','loan_type','payment_type','recourse','index_title','index','margin','dcr','last_payment','prepaid_penalty','prop_tax_impound','escrow','reserve','additional_principal','note'],
      'createForm1'     => ['uuid','prop','bank','allocation','vendid','invoice','amount','init_principal','interest_rate','loan_term','gl_acct_ap','gl_acct_liability','due_date','loan_date','journal_entry_date','maturity_date','dcr'],
      'createForm2'     => ['loan_option','loan_type','payment_option','payment_type','recourse','index_title','index','margin','last_payment','prepaid_penalty','prop_tax_impound','escrow','reserve','additional_principal','note'],
      'editForm1'       => ['vendor_mortgage_id','bank','allocation','vendid','invoice','amount','interest_rate','loan_term','gl_acct_ap','gl_acct_liability','due_date','loan_date','dcr','last_payment'],
      'editForm2'       => ['loan_type','payment_type','recourse','index_title','index','margin','prepaid_penalty','prop_tax_impound','escrow','reserve','additional_principal','note'],
    ];
    
    $orderField['store']    = $orderField['create'];
    $orderField['update']   = $orderField['edit'];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $perm         = Helper::getPermission($req);
    $rBank        = !empty($default['prop']) ? $this->_keyFieldBank(HelperMysql::getBank(Helper::getPropMustQuery($default,[],0),['bank','cp_acct','name'],['sort'=>'bank.keyword'],0)) : [''=>'Select Bank'];
    $disabled     = [];
    
    $setting      = [
      'create'    => [
        'field'   => [
          'uuid'                   => ['type'=>'hidden'],
          'vendid'                 => ['label'=>'Vendor Id','req'=>1,'hint'=>'You can type Vendor ID or Name for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'bank'                   => ['label'=>'Bank','type'=>'option','option'=>[],'req'=>1],
          'amount'                 => ['label'=>'Monthly Payment','value'=>0,'req'=>1],
          'invoice'                => ['label'=>'Loan #','req'=>1],
          'init_principal'         => ['label'=>'Principal','value'=>0],
          'loan_term'              => ['req'=>1],
          'loan_option'            => ['type'=>'option','option'=>$this->_mapping['loan_option'],'value'=>'Variable','req'=>1],
          'loan_type'              => ['type'=>'option','option'=>$this->_mapping['loan_type'],'value'=>'Secured','req'=>1],
          'prop'                   => ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'remark'                 => ['label'=>'Main Remark'],
          'gl_acct_ap'             => ['req'=>1,'label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'gl_acct_liability'      => ['req'=>1,'label'=>'GL Acct Liability','req'=>1],
          'last_payment'           => ['req'=>1,'label'=>'Last Payment','value'=>'Fully Amortized'],
          'due_date'               => ['req'=>1],
          'dcr'                    => ['label'=>'DCR','req'=>1],
          'margin'                 => ['req'=>1],
          'index_title'            => ['req'=>1],
          'recourse'               => ['req'=>1],
          'index'                  => ['label'=>'Index Value','req'=>1],
          'loan_date'              => ['value'=>date('m/d/Y'),'req'=>1],
          'journal_entry_date'     => ['value'=>date('m/d/Y'),'req'=>1],
          'maturity_date'          => ['value'=>date('m/d/Y'),'req'=>1],
          'payment_option'         => ['type'=>'option','option'=>$this->_mapping['payment_option'],'value'=>'Interest Only','req'=>1],
          'payment_type'           => ['type'=>'option','option'=>$this->_mapping['payment_type'],'value'=>'Check','req'=>1],
          'recourse'               => ['type'=>'option','option'=>$this->_mapping['recourse'],'value'=>'Recourse','req'=>1],
          'prepaid_penalty'        => ['label'=>'Prepayment Penalty','value'=>0,'req'=>0],
          'prop_tax_impound'       => ['label'=>'Prop Tax Escrow','value'=>0,'req'=>0],
          'escrow'                 => ['label'=>'Insurance Escrow','value'=>0,'req'=>0],
          'additional_principal'   => ['label'=>'Addt Principal','value'=>0,'req'=>0],
          'reserve'                => ['req'=>0],
          'allocation'             => ['req'=>1],
          'interest_rate'          => ['label'=>'Int Rate%','class'=>'percent-mask','req'=>1],
        ],
        'rule'    => [
          'uuid'                   => 'nullable',
          'note'                   => 'nullable|string',
          'prepaid_penalty'        => 'nullable',
          'additional_principal'   => 'nullable',
          'prop_tax_impound'       => 'nullable',
          'escrow'                 => 'nullable',
          'reserve'                => 'nullable',
          'gl_acct'                => 'required|string',
        ],
      ],
      'edit'       => [
        'field'    => [
          'vendor_mortgage_id'      => $disabled + ['type'=>'hidden'],
          'vendid'                  => $disabled + ['label'=>'Vendor Code', 'hint'=>'You can type prop address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'prop'                    => $disabled + ['readonly'=>1],
          'bank'                    => $disabled + ['label'=>'Bank','type'=>'option','option'=>$rBank,'req'=>1],
          'invoice'                 => $disabled + ['label'=>'Loan #','req'=>1],
          'prop'                    => $disabled + ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'loan_date'               => $disabled + ['req'=>1],
          'loan_term'               => $disabled + ['req'=>1],
          'due_date'                => $disabled + ['req'=>1],
          'gl_acct_ap'              => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'gl_acct_liability'       => $disabled + ['label'=>'GL Acct Liability','req'=>1],
          'remark'                  => $disabled + ['label'=>'Main Remark','req'=>1], 
          'paid_off_loan'           => $disabled + ['label'=>'Paid Off','type'=>'option','option'=>$this->_mapping['paid_off_loan'],'req'=>1],
          'index'                   => $disabled + ['label'=>'Index Value','req'=>1],
          'index_title'             => $disabled + ['req'=>1],
          'margin'                  => $disabled + ['req'=>1],
          'recourse'                => $disabled + ['type'=>'option','option'=>$this->_mapping['recourse'],'req'=>1],
          'dcr'                     => $disabled + ['label'=>'DCR','req'=>1],
          'loan_type'               => $disabled + ['type'=>'option','option'=>$this->_mapping['loan_type'],'req'=>1],
          'payment_type'            => $disabled + ['type'=>'option','option'=>$this->_mapping['payment_type'],'req'=>1],
          'amount'                  => $disabled + ['label'=>'Monthly Payment','req'=>1],
          'prepaid_penalty'         => $disabled + ['label'=>'Prepayment Penalty','req'=>0],
          'prop_tax_impound'        => $disabled + ['label'=>'Prop Tax Escrow','req'=>0],
          'escrow'                  => $disabled + ['label'=>'Insurance Escrow','req'=>0],
          'reserve'                 => $disabled + ['kabel'=>'Reserve','req'=>0],
          'interest_rate'           => $disabled + ['label'=>'Int Rate%','class'=>'percent-mask','req'=>1],
          'additional_principal'    => $disabled + ['label'=>'Addt Principal','req'=>0],
          'reserve'                 => $disabled + ['req'=>0],
          'allocation'              => $disabled + ['req'=>1],
          'usid'                    => $disabled + ['label'=>'Last Updated By', 'readonly'=>1],
        ],
        'rule'  => [
          'uuid'                   => 'nullable',
          'note'                   => 'nullable|string',
          'prepaid_penalty'        => 'nullable',
          'additional_principal'   => 'nullable',
          'prop_tax_impound'       => 'nullable',
          'escrow'                 => 'nullable',
          'reserve'                => 'nullable',
          'gl_acct'                => 'required|string',
        ]
      ],
    ];

    $dateFields  = [
      'maturity_date'      => 'maturity_date',
      'loan_date'          => 'loan_date',
      'journal_entry_date' => 'journal_entry_date',
    ];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = !empty($dateFields[$k]) ? Format::usDate($v) : $v;
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
      $source                         = $v['_source'];
      $source['num']                  = $vData['offset'] + $i + 1;
      $source['action']               = implode(' | ',$actionData['icon']);
      $source['linkIcon']             = Html::getLinkIcon($source,['prop']);
      $source['invoiceFile']          = !empty($source['fileUpload']) ? Html::span('View', ['class'=>'clickable']) : '';
      $defaultBank                    = Helper::getValue('bank',$source);
      $source['bankList']             = !empty($source['bank_id']) ? Helper::keyFieldName($source['bank_id'],'bank','name') : [];
      $source['bank']                 = $defaultBank;
      $source['amount']               = isset($source['amount']) && is_numeric($source['amount']) ? Format::usMoney($source['amount']) : '';
      $source['interest_rate']        = isset($source['interest_rate']) && is_numeric($source['interest_rate']) ? Format::percent($source['interest_rate']) : '';
      $source['prop_tax_impound']     = isset($source['prop_tax_impound']) && is_numeric($source['prop_tax_impound']) ? Format::usMoney($source['prop_tax_impound']) : '';
      $source['escrow']               = isset($source['escrow']) && is_numeric($source['escrow']) ? Format::usMoney($source['escrow']) : '';
      $source['reserve']              = isset($source['reserve']) && is_numeric($source['reserve']) ? Format::usMoney($source['reserve']) : '';
      $source['additional_principal'] = isset($source['additional_principal']) && is_numeric($source['additional_principal']) ? Format::usMoney($source['additional_principal']) : '';
      $source['principal_bal']        = isset($source['principal_bal']) && is_numeric($source['principal_bal']) ? Format::usMoney($source['principal_bal']) : '';
      $rows[]                  = $source;
    }
    return ['rows'=>$rows,'total'=>$total];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){  
    $perm          = Helper::getPermission($req);
    $actionData    = $this->_getActionIcon($perm);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList    = ['csv' => 'Export to CSV'];
    ### BUTTON SECTION ###
    $_getButtons   = function($perm){
      $button  = '';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-plus-square']) . ' New',['id'=>'new','class'=>'btn btn-success']) . ' ';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-upload']) . ' Upload Mortgage',['id'=>'mortgageUpload','class'=>'btn btn-info']) .  ' ';
      $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-paper-plane-o']) . ' Send to Approval', ['id'=> 'sendApproval', 'class'=>'btn btn-primary']) . ' ';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-trash']) . ' Delete',['id'=>'delete','class'=>'btn btn-danger','disabled'=>true]) . ' ';
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      $data['editable'] = ['type'=>'select','source'=>$source];
      return $data;
    };
    
    $editableText    = ['editable'=>['type'=>'text']];
    $bankEditable    = ['editable'=>['type'=>'select']];
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'checkbox', 'checkbox'=>true, 'width'=>25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>50];
    $data[] = ['field'=>'entity_name','title'=>'Entity Name' . Html::repeatChar('&nbsp;',25),'sortable'=>true,'filterControl'=>'input','width'=>300];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'number_of_units','title'=>'Unit #','sortable'=>true,'filterControl'=>'input','width'=>15];
    $data[] = ['field'=>'street','title'=>'Street' . Html::repeatChar('&nbsp;',35),'sortable'=>true,'filterControl'=>'input','width'=>80];
    $data[] = ['field'=>'city','title'=>'City' . Html::repeatChar('&nbsp;',15),'sortable'=>true,'filterControl'=>'input','input'=>65];
    $data[] = ['field'=>'name','title'=>'Vendor Name'. Html::repeatChar('&nbsp;',35),'sortable'=>true,'filterControl'=>'input','width'=>300];
    $data[] = ['field'=>'invoice','title'=>'Loan#','width'=>100,'filterControl'=>'input'] + $editableText;
    $data[] = ['field'=>'interest_rate','title'=>'Int Rate%','filterControl'=>'input','sortable'=>true,'width'=>25]+ $editableText;
    $data[] = ['field'=>'loan_date','title'=>'Loan Date','sortable'=>true,'filterControl'=>'input','width'=>45]+ $editableText;
    $data[] = ['field'=>'amount','title'=>'Amount','sortable'=>true,'filterControl'=>'input','width'=>45]+ $editableText;
    $data[] = ['field'=>'dcr','title'=>'DCR','sortable'=>true,'filterControl'=>'input','width'=>25]+ $editableText;
    $data[] = ['field'=>'index_title','title'=>'Idx Title' . Html::repeatChar('&nbsp;',25),'sortable'=>true,'filterControl'=>'input','width'=>200]+ $editableText;
    $data[] = ['field'=>'index','title'=>'Idx','sortable'=>true,'filterControl'=>'input','width'=>30]+ $editableText;
    $data[] = $_getSelectColumn($perm,'loan_option','Rate Type',70,$this->_mapping['loan_option']);
    $data[] = ['field'=>'loan_term','title'=>'Loan Term','sortable'=>true,'filterControl'=>'input','width'=>25]+ $editableText;
    $data[] = ['field'=>'last_payment','title'=>'Last Payment' . Html::repeatChar('&nbsp;',20),'sortable'=>true,'filterControl'=>'input','width'=>100]+ $editableText;
    $data[] = ['field'=>'prepaid_penalty','title'=>'Prepaid Penalty','sortable'=>true,'filterControl'=>'input','width'=>25]+ $editableText;
    $data[] = ['field'=>'prop_tax_impound','title'=>'Prop Tax Escrow','sortable'=>true,'filterControl'=>'input','width'=>25]+ $editableText;
    $data[] = ['field'=>'escrow','title'=>'Escrow','sortable'=>true,'filterControl'=>'input','width'=>25]+ $editableText;
    $data[] = ['field'=>'reserve','title'=>'Reserve','sortable'=>true,'filterControl'=>'input','width'=>25] + $editableText;
    $data[] = ['field'=>'additional_principal','title'=>'Add Prin.','sortable'=>true,'filterControl'=>'input','width'=>25] + $editableText;
    $data[] = ['field'=>'due_date','title'=>'Due Date','sortable'=>true,'filterControl'=>'input','width'=>50] + $editableText;
    $data[] = $_getSelectColumn($perm,'payment_type','Pay Type',50,$this->_mapping['payment_type']);
    $data[] = ['field'=>'bank','value'=>'bank','title'=>'Bank' . Html::repeatChar('&nbsp;',50),'sortable'=>true,'filterControl'=>'input','width'=>300] + $bankEditable;
    $data[] = ['field'=>'vendid','title'=>'Vendor','sortable'=>true,'filterControl'=>'input','width'=>25] + $editableText;
    $data[] = $_getSelectColumn($perm,'loan_type','Loan Type',75,$this->_mapping['loan_type']);
    $data[] = $_getSelectColumn($perm,'recourse','Recourse' . Html::repeatChar('&nbsp;',12),75,$this->_mapping['recourse']);
    $data[] = ['field'=>'allocation','title'=>'Alloc','sortable'=>true,'filterControl'=>'input','width'=>25] + $editableText;
    $data[] = ['field'=>'gl_acct_ap','title'=>'Gl Acct','sortable'=>true,'filterControl'=>'input','width'=>30] + $editableText;
    $data[] = ['field'=>'gl_acct_liability','title'=>'Gl Liability','sortable'=>true,'filterControl'=>'input','width'=>25] + $editableText;
    $data[] = ['field'=>'principal_bal','title'=>'Principal Bal','sortable'=>true,'filterControl'=>'input','width'=>35];
    $data[] = ['field'=>'note','title'=>'Note' . Html::repeatChar('&nbsp;',45),'sortable'=>true,'filterControl'=>'input','width'=>300] + $editableText;
    $data[] = $_getSelectColumn($perm,'paid_off_loan','Paid Off',25,$this->_mapping['paid_off_loan']);
    $data[] = ['field'=>'invoiceFile', 'title'=>'Invoice','width'=> 75];
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted Mortgage(s)'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData   = [];
    $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Mortgage Information"></i></a>';
    $num          = count($actionData);
    return ['width'=>$num * 42,'icon'=>$actionData];
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