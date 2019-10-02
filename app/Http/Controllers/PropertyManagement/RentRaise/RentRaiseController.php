<?php
namespace App\Http\Controllers\PropertyManagement\RentRaise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Elastic, Html, Helper, HelperMysql, Format, GridData, Account, TableName AS T,RentRaiseNotice, GlName AS G, ServiceName AS S};
use App\Http\Models\{Model,RentRaiseModel as M}; // Include the models class
class RentRaiseController extends Controller {
  private $_viewPath        = 'app/PropertyManagement/RentRaise/rentRaise/';
  private $_viewTable       = '';
  private $_indexMain       = '';
  private $_mapping         = [];
  private $_numPastRentCols = 4;
  private static $_instance;
  
  public function __construct(){
    $this->_viewTable   = T::$rentRaiseView;
    $this->_indexMain   = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_mapping     = Helper::getMapping(['tableName'=>T::$rentRaise]);
  }
//------------------------------------------------------------------------------
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
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
        $vData                = $req->all();
        $vData['defaultSort'] = ['group1.keyword:asc','prop.keyword:asc','unit.keyword:asc','tenant:desc'];
        $qData                = GridData::getQuery($vData, $this->_viewTable);
        $r                    = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData, $req); 
      case 'help':
        return $this->_getHelpPage();
      default:
        return view($page, ['data'=>[
          'nav'      => $req['NAV'],
          'account'  => Account::getHtmlInfo($req['ACCOUNT']), 
          'initData' => $initData
        ]]);  
    }
  }
################################################################################
#################   UPDATE  AND STORE PROCESSING SECTION   ##################### 
################################################################################
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $valid = V::startValidate([
      'rawReq'        => $req->all(),
      'tablez'        => $this->_getTable('store'),
      'setting'       => $this->_getSetting('store',$req),
      'includeUsid'   => 1,
      'validateDatabase'   => [
        'mustExist'  => [
          T::$tenant . '|tenant_id',
        ]
      ]
    ]); 
    $wasChecked   = !empty($valid['data']['isCheckboxChecked']) ? 1 : 0;
    $vData        = !empty($valid['dataArr']) ? $valid['dataArr'] : [$valid['dataNonArr']];
    $serviceCode  = S::$rent;
    $glAcct       = G::$rent;
    $ids          = array_column($vData,'tenant_id');
    $rTenant      = Helper::keyFieldNameElastic(M::getTenantElastic(['tenant_id'=>$ids],['tenant_id','prop','unit','tenant',T::$billing]),'tenant_id');
    $rRentRaise   = Helper::keyFieldNameElastic(M::getRentRaiseInElastic('tenant_id',$ids,['tenant_id','prop','unit','tenant','rent_raise_id','last_raise_date','rent_raise','rent','prop_type','rent_type','usid','isCheckboxChecked']),'tenant_id');
    $rService     = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>'Z64'])),'service');
    $rGlChart     = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])),'gl_acct');
    $usid         = Helper::getUsid($req);
    $updateData   = $insertData = $insertIds = [];
        
    foreach($vData as $v){
      $rentRow         = $rRentRaise[$v['tenant_id']];
      
      $rentRaise       = !empty($rentRow['rent_raise']) ? last($rentRow['rent_raise']) : [];
      
      $pastRaise          = Helper::getValue('raise',$rentRaise,0);
      $pastPct            = Helper::getValue('raise_pct',$rentRaise,0);
      $pastNotice         = Helper::getValue('notice',$rentRaise);
      $raise              = Helper::getValue('raise',$v,$pastRaise);
      $pct                = Helper::getValue('raise_pct',$v,$pastPct);
      $pastEffectDate     = Helper::getValue('effective_date',$rentRaise,'1000-01-01');
      $pastFile           = Helper::getValue('file',$rentRaise);
      
      $noticeChanged          = !empty($v['notice']) && $v['notice'] != $pastNotice;
      $serviceRemark          = !empty($rService[$serviceCode]['remark']) ? $rService[$serviceCode]['remark'] : 'Rent';
      $formRemark             = Helper::getValue('remark',$v);
      
      $raiseCalc              = $this->_calculateNewRaise($v, $rentRow['rent'], $raise);
      $raisePct               = $this->_calculateNewRaisePct($v, $rentRow['rent'], $pct);
      
      $v['prop']              = $rentRow['prop'];
      $v['unit']              = $rentRow['unit'];
      $v['tenant']            = $rentRow['tenant'];
      $v['foreign_id']        = $v['tenant_id'];
      
      $v['rent']              = Helper::getValue('rent',$v,$rentRow['rent']);
      $v['raise']             = Format::roundDownToNearestHundredthDecimal($raiseCalc);
      $v['raise_pct']         = Format::roundDownToNearestHundredthDecimal($raisePct);
      $tempEffect             = date('Y-m-01',strtotime(date('Y-m-d') . ' +1 month 30 days'));
      $tenantBilling          = Helper::getValue(T::$billing,$rTenant[$v['tenant_id']],[]);
      $v['prop_type']         = $rentRow['prop_type'];
      $calculateNotice        = $this->_calculateNoticeFromBilling($tenantBilling, $v, $tempEffect);
      $newNotice              = $noticeChanged ? $v['notice'] : $calculateNotice;
      
      $v['notice']            = $newNotice;
      $v['gl_acct']           = $glAcct;
      $v['service_code']      = $serviceCode;
      $v['remark']            = !empty($formRemark) ? $formRemark : $serviceRemark;
      $v['submitted_date']    = Helper::getValue('submitted_date',$rentRaise,'1000-01-01');
      $v['effective_date']    = !(isset($v['isCheckboxChecked'])) ? date('Y-m-01',strtotime(date('Y-m-d') . ' +1 month ' . $v['notice'] . ' days')) : $pastEffectDate;
      $v['isCheckboxChecked'] = Helper::getValue('isCheckboxChecked',$v,1);
      $v['last_raise_date']   = Helper::getValue('last_raise_date',$rentRaise,'1000-01-01');
      $v['file']              = $pastFile;
      $this->_validateRentControlTenant($rentRow,$v);
      $tenantId               = $v['tenant_id'];
      unset($v['prop_type'],$v['service'],$v['tenant_id']);
      if(!empty($rentRow['rent_raise_id'])){
        $updateData[T::$rentRaise][] = [
          'whereData'   => ['rent_raise_id'=>$rentRow['rent_raise_id']],
          'updateData'  => $v,
        ];  
      } else {
        $insertData[T::$rentRaise][] = $v;
        $insertIds[]                 = $tenantId;
      }   
      
    }

    $insertData = !empty($insertData) ? HelperMysql::getDataset($insertData,$usid,$rGlChart,$rService) : [];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success       += !empty($insertData) ? Model::insert($insertData) : [];
      $success       += !empty($updateData) ? Model::update($updateData) : [];
      $elastic        = [
        'insert' => [T::$rentRaiseView=>['t.tenant_id'=>$ids]],
      ];
      
      $response      += !($wasChecked) ? ['mainMsg'=>$this->_getSuccessMsg(__FUNCTION__,$vData)] : [];      
      $response['insertIds'] = !empty($insertIds) ? array_combine($insertIds,$success['insert:'.T::$rentRaise]): [];
      
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
  public function store(Request $req){
    set_time_limit(600);
    $req['tenant_id'] = !empty($req['tenant_id']) ? explode(',',$req['tenant_id']) : [];
    $valid = V::startValidate([
      'rawReq'        => $req->all(),
      'tablez'        => $this->_getTable('store'),
      'orderField'    => $this->_getOrderField('submitRentRaise',$req),
      'setting'       => $this->_getSetting('store',$req),
      'includeUsid'   => 1,
    ]);
    $vData        = $valid['dataArr'];
    $ids          = array_column($vData,'tenant_id');
    $rService     = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>'Z64'])),'service');
    $rGlChart     = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])),'gl_acct');
    $rRentRaise   = Helper::keyFieldNameElastic(M::getRentRaiseInElastic('tenant_id',$ids,['tenant_id','tnt_name','rent_raise_id','rent','prop','unit','tenant','rent_raise','effective_date','yearly_pct','last_raise_date','rent_type']),'tenant_id');
    $props        = array_column($rRentRaise,'prop');
    $rUnit        = Helper::keyFieldNameElastic(M::getUnitElastic(['prop.prop.keyword'=>$props],['prop.prop','unit','unit_id']),['prop.prop','unit'],'unit_id');
    $rTenant      = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['tenant_id','prop','unit','tenant','base_rent',T::$billing],
      'query'    => [
        'must'   => [
          'tenant_id' => $ids,
        ]
      ]
    ]),'tenant_id');

    
    $serviceCode  = S::$rent;
    $glAcct       = G::$rent;
    $usid         = Helper::getUsid($req);
    
    $noticeData   = [];
   
    $insertData   = $insertRentRaise = $updateData = $updateBilling = $unitIdArr = [];
    
    $noticeFiles  = $noticePaths = [];
    foreach($vData as $i=>$v){
      $rentRaiseRow  = $rRentRaise[$v['tenant_id']];
      $this->_validateRentControlTenant($rentRaiseRow,['isCheckboxChecked'=>1]);
      $unitId        = !empty($rUnit[$rentRaiseRow['prop'] . $rentRaiseRow['unit']]) ? $rUnit[$rentRaiseRow['prop'] . $rentRaiseRow['unit']] : 0;
      if(!empty($unitId)){
        $rentRaiseData = last($rentRaiseRow['rent_raise']);
        $effectDate    = date('Y-m-01',strtotime(date('Y-m-d') . ' +1 month ' . $rentRaiseData['notice'] . ' days'));
        $newStop       = date('Y-m-d',strtotime($effectDate . ' -1 day'));
        $rentRaiseData['effective_date']  = Helper::getValue('effective_date',$rentRaiseData,$effectDate);

        $fileProps     = Helper::selectData(['tenant_id','tnt_name','rent_raise_id','prop','unit','tenant','rent',T::$rentRaise,'yearly_pct'],$rentRaiseRow);
        $linkData      = RentRaiseNotice::getPdf([$fileProps]);
        $noticeFiles[] = $linkData['file'];
        $noticePaths[] = $linkData['path'];
        $fileLink      = $linkData['link'];
        if(!empty($rTenant[$v['tenant_id']][T::$billing])){
          $updateBilling[T::$billing][] = [
            'whereData'    => Helper::selectData(['prop','unit','tenant'],$rentRaiseRow) + ['schedule'=>'M','stop_date'=>'9999-12-31'],
            'updateData'   => ['stop_date'=>$newStop],
          ];
        }

        if(strtotime($rentRaiseData['effective_date']) <= strtotime(date('Y-m-d'))){
          $updateData[T::$tenant][] = [
            'whereData'    => Helper::selectData(['prop','unit','tenant'],$rentRaiseRow),
            'updateData'   => ['base_rent'=>$rentRaiseData['raise']],
          ];
        }
        if(!empty($unitId)){
          $unitUpdateData  = ['market_rent'=>$rentRaiseData['raise']];
          $unitUpdateData += strtotime($rentRaiseData['effective_date']) <= strtotime(date('Y-m-d')) ? ['rent_rate'=>$rentRaiseData['raise']] : [];
          $updateData[T::$unit][] = [
            'whereData'    => ['unit_id'  => $unitId],
            'updateData'   => $unitUpdateData,
          ]; 
        }

        if($rentRaiseData['raise'] > 0 && $rentRaiseData['raise'] - $rentRaiseData['rent'] > 0){
          $insertData[T::$billing][]= [
            'service_code'   => $serviceCode,
            'remark'         => !empty($rService[$serviceCode]['remark']) ? $rService[$serviceCode]['remark'] : 'Rent',
            'amount'         => $rentRaiseData['raise'],
            'gl_acct'        => $glAcct,
            'prop'           => $rentRaiseRow['prop'],
            'unit'           => $rentRaiseRow['unit'],
            'tenant'         => $rentRaiseRow['tenant'],
            'schedule'       => 'M',
            'seq'            => $i + 1,
            'start_date'     => $effectDate,
            'post_date'      => $effectDate,
            'stop_date'      => '9999-12-31',
          ];
        }
        
        $unitIdArr[] = $unitId;
        $raiseId           = Helper::getValue('rent_raise_id',$rentRaiseData);
        unset($rentRaiseData['rent_raise_id'],$rentRaiseData['submitted_date'],$rentRaiseData['file'],$rentRaiseData['isCheckboxChecked'],$rentRaiseData['last_raise_date'],$rentRaiseData['effective_date'],$rentRaiseData['cdate']);
        $rentRaiseData     += ['active'=>1,'usid'=>$usid,'submitted_date'=>date('Y-m-d'),'file'=>$fileLink,'foreign_id'=>$v['tenant_id'],'isCheckboxChecked'=>0,'last_raise_date'=>$effectDate,'effective_date'=>'1000-01-01'] + Helper::selectData(['prop','unit','tenant'],$rentRaiseRow);    

        if($rentRaiseData['raise'] > 0 && $rentRaiseData['raise'] - $rentRaiseData['rent'] > 0){
          $updateData[T::$rentRaise][]      = [
            'whereData'    => ['rent_raise_id'=>$raiseId],
            'updateData'   => [
              'submitted_date'     => date('Y-m-d'),
              'isCheckboxChecked'  => 0,
              'effective_date'     => '1000-01-01',
              'usid'               => $usid,
              'file'               => $fileLink,
              'last_raise_date'    => $effectDate,
            ]
          ];

          $rentRaiseData['billing_id']      = 0;
          $rentRaiseData['raise_pct']       = 0;
          $insertRentRaise[T::$rentRaise][] = $rentRaiseData;
          $noticeData[]                     = $fileProps;
        }
      }
    }
    $insertData      = !empty($insertData) ? HelperMysql::getDataset($insertData,$usid,$rGlChart,$rService) : [];
    $insertRentRaise = !empty($insertRentRaise) ? HelperMysql::getDataset($insertRentRaise,$usid,$rGlChart,$rService) : [];
    
    $success = $response = $elastic = [];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    try {
      if(!empty($insertRentRaise[T::$rentRaise]) && !empty($insertData[T::$billing])){
        $success                          += !empty($updateBilling) ? Model::update($updateBilling) : [];
        $success                          += Model::insert($insertData);
      
        $updateData[T::$rentRaise]         = $this->_getBillingIds($updateData[T::$rentRaise],$success['insert:' . T::$billing]);
        $success                          += Model::insert($insertRentRaise);
        $success                          += Model::update($updateData);
        $elastic       = [
          'insert'  => [
            T::$tenantView    => ['t.tenant_id'=>$ids],
            T::$rentRaiseView => ['t.tenant_id'=>$ids],
            T::$unitView      => ['u.unit_id'  =>$unitIdArr],
          ]
        ];
        
        Model::commit([
          'success'  => $success,
          'elastic'  => $elastic,
        ]);
       
        $response['mainMsg']          = $this->_getSuccessMsg(__FUNCTION__);
        return RentRaiseNotice::mergePdfNotices([
          'paths'   => $noticePaths,
          'files'   => $noticeFiles,
        ]);
      } else {
        $response['error']['mainMsg'] = $this->_getErrorMsg(__FUNCTION__);
      }
    } catch (\Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################ 
  private function _getTable($fn){
    $tablez = [
      'create'   => [T::$rentRaise],
      'edit'     => [T::$tenant,T::$rentRaise],
      'store'    => [T::$tenant,T::$rentRaise],
      'update'   => [T::$rentRaise],
    ];  
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm        = Helper::getPermission($req);
    $orderField  = [
      'edit'              => ['tenant_id','rent_raise_id','prop','unit','tenant','service_code','remark','raise','raise_pct','rent','notice' ],
      'create'            => ['prop','unit','tenant','service_code','remark','raise','raise_pct','rent'],
      'massEdit'          => ['rent_raise_id','prop','unit','tenant','raise','raise_60','raise_pct2','raise_pct','rent_old','rent','diff','notice','name','usid','billing_id','is_printed','is_rent_raise_completed'],
      'submitRentRaise'   => ['tenant_id'],
    ];
    $orderField['store']         = isset($perm['rentRaisecreate']) ? ['tenant_id','prop','unit','tenant','service','remark','raise','raise_pct','rent'] : [];
    $orderField['update']        = isset($perm['rentRaiseupdate']) ? ['tenant_id','rent_raise_id','prop','unit','tenant','service','remark','raise','raise_pct','rent','is_printed','notice'] : [];
    $orderField['massiveUpdate'] = isset($perm['rentRaiseupdate']) ? $orderField['massEdit'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[],$default=[]){
    $perm        = Helper::getPermission($req);
    $disabled    = isset($perm['rentRaiseupdate']) ? [] : ['disabled'=>1];
    
    $setting     = [
      'edit'  => [
        'field' => [

        ],
        'rule' => [
          'tenant'          => 'required|integer|between:0,65535',
          'type'            => 'nullable|string',
          'rent_raise_id'   => 'nullable',
        ]
      ],
      'create'  => [
        'field' => [

        ],
        'rule'  => [
          'tenant'       => 'required|integer|between:0,65535',
          'rent_raise_id'=> 'nullable',
          'service_code' => 'nullable',
          'service'      => 'required',
        ]
      ],
    ];
    
    $setting['store']   = isset($perm['rentRaisecreate']) ? $setting['create'] : [];
    $setting['update']  = isset($perm['rentRaiseupdate']) ? $setting['edit'] : [];
    if(!empty($default)){
      $rentRaise                                       = !empty($default[T::$rentRaise]) ? last($default[T::$rentRaise]) : [];
      unset($rentRaise['rent_raise_id'],$rentRaise['prop'],$rentRaise['unit'],$rentRaise['tenant']);
      unset($default[T::$rentRaise]);
      $default                                        += $rentRaise;
      foreach($default as $k => $v){
        $tenantR                                         = M::getTenantBillingElastic(Helper::selectData(['prop','unit','tenant'],$default));
        $setting[$fn]['field'][$k]['value']              = $v;
        $setting[$fn]['field']['move_in_date']['value']  = Format::usDate($default['move_in_date']);
        $setting[$fn]['field']['rent']['value']          = !empty($tenantR) ? Helper::getRentFromBilling($tenantR['_source']) : 0;
      }
    }
    return $setting[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r,$vData,$qData,$req){
    $perm          = Helper::getPermission($req);
    $usid          = Helper::getUsid($req);
    $rows          = [];
    $result        = Helper::getElasticResult($r,0,1);
    
    $numCheckData  = $this->_getNumChecks($req,$vData);
    $numChecks     = $numCheckData['count'];
    
    $tenantIds     = array_column(array_column($result['data'],'_source'),'tenant_id');
    $rTenant       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['tenant_id',T::$billing],
      'query'    => [
        'must'   => [
          'status.keyword'  => 'C',
          'tenant_id'       => $tenantIds,
        ]
      ]
    ]),'tenant_id');
    
    foreach($result['data'] as $i => $v){
      $source                      = $v['_source'];
      $source['num']               = $vData['offset'] + ($i + 1);

      $source['linkIcon']          = Html::getLinkIcon($source,['tenant']);
      $source['needsCheck']        = $source['isCheckboxChecked'] ? true : false;
      $source['raise']             = Format::usMoney($source['raise']);         
      $source['submitted_date']    = !empty($source['submitted_date']) && $source['submitted_date'] != '1000-01-01' && $source['submitted_date'] != '1969-12-31' ? $source['submitted_date'] : '';
      $source['last_raise_date']   = !empty($source['last_raise_date']) && $source['last_raise_date'] != '1000-01-01' && $source['last_raise_date'] != '1969-12-31' ? $source['last_raise_date'] : '';
      $source['effective_date']    = !empty($source['effective_date']) && $source['effective_date'] != '1000-01-01' && $source['effective_date'] != '1969-12-31' ? $source['effective_date'] : '';
      $source['isManager']         = !empty($this->_mapping['isManager'][$source['isManager']]) ? $this->_mapping['isManager'][$source['isManager']] : $source['isManager'];
      $source['unit_type']         = !empty($this->_mapping['unit_type'][$source['unit_type']]) ? $this->_mapping['unit_type'][$source['unit_type']] : $source['unit_type'];
      $source['rent_type']         = !empty($this->_mapping['rent_type'][$source['rent_type']]) ? $this->_mapping['rent_type'][$source['rent_type']] : $source['rent_type'];
      $source['rent']              = Format::usMoney($source['rent']);
      $source['raise_pct']         = !empty($source['raise_pct']) ? Format::percent($source['raise_pct']) : 0;
      $source['yearly_pct']        = !empty($source['yearly_pct']) ? Format::percent($source['yearly_pct']) : 0;
      $userPieces                  = explode('@',$source['usid']);
      $source['usid']              = Helper::getValue(0,$userPieces);
      $source['notice']            = !empty($source['notice']) ? (isset($perm['rentRaiseupdate']) ? $source['notice'] : Helper::getValue($source['notice'],$this->_mapping['notice'])) : '';
      $source['numChecks']         = $numChecks;
      
      //$containsNotice              = $this->_containsNotices($source);
      //$source['allFile']           = !empty($containsNotice) ? Html::span('View',['class'=>'clickable']) : '';
      $source                      = $this->_gatherPastRentRaise($source);
      $hud         = $tenantRent = 0;
      $tenantData  = Helper::getValue($source['tenant_id'],$rTenant,[]);
      $billingData = Helper::getValue(T::$billing,$tenantData,[]);
      if(!empty($billingData)) {
        foreach($billingData as $billing) {
          if($billing['stop_date'] == '9999-12-31' && $billing['schedule'] == 'M') {
            if($billing['service_code'] == 'HUD') {
              $hud = $billing['amount'];
            }else if( ($billing['service_code'] == '602' && !preg_match('/MJC[1-9]+/', $source['prop'])) || ($billing['service_code'] == '633' && preg_match('/MJC[1-9]+/', $source['prop'])) ) {
              $tenantRent += $billing['amount'];
            }
          }
        }
      }
      $source['hud']  = Format::usMoney($hud);
      
      $rows[]   = $source;
    }
    return ['rows'=>$rows,'total'=>$result['total'],'numChecks'=>$numCheckData['count'],'selectedIds'=>$numCheckData['data']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm           = Helper::getPermission($req);
    $reportList     = isset($perm['rentRaiseExport']) ? [
      'csvAll'       => 'View All: Export to CSV',
      'printAll'     => 'View All: Generate Notices',
      'pdfAll'       => 'View All: Print Rent Raises',
      'csvPending'   => 'View Pending: Export Proposed to CSV',
      'pdfPending'   => 'View Pending: Print Rent Raises',
      'printPending' => 'View Pending: Generate Notices',
    ] : [];
    $data           = [];
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['rentRaiseupdate'])){
        $button .= Html::repeatChar('&nbsp;',3) . Html::button(' View All Rent Raises', ['id'=>'viewAllRentRaise','class'=>'btn btn-info']) . ' ';
        $button .= Html::repeatChar('&nbsp;',3) . Html::button(' View Pending Rent Raise (0)',['id'=>'pendingRentRaise','class'=>'btn btn-info']) . ' ';
        $button .= Html::repeatChar('&nbsp;',3) . Html::button(' Rent Raise Help   ' . Html::i('',['class'=>'fa fa-question-circle']), ['id'=>'rentRaiseHelp','class'=>'btn btn-info']) . ' ';
        $button .= Html::repeatChar('&nbsp;',3) . Html::button(Html::i('',['class'=>'fa fa-fw fa-clipboard']) . ' Submit Rent Raise',['id'=>'updateTable','class'=>'btn btn-success','style'=>'display:none;']) . ' ';
      }
      return $button;
    };
    
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data           = ['field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      $data          += ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable];
      $editableFields = ['notice','is_printed'];
      if(isset($perm['rentRaiseupdate']) && in_array($field,$editableFields)){
        $data['editable']  = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    
    $rentRaiseEditable = isset($perm['rentRaiseupdate']) ? ['editable'=>['type'=>'text']] : [];
    $noticeEditable    = isset($perm['rentRaiseupdate']) ? ['editable'=>['type'=>'select','source'=>$this->_mapping['notice']]] : [];
    $data[] = ['field'=>'checkbox','checkbox'=>true];
    $data[] = ['field'=>'num','title'=>'#','width'=>25];

    $data[] = ['field'=>'group1','title'=>'Grp','sortable'=>true,'filterControl'=>'input','width'=>15];
    $data[] = ['field'=>'tenant_id','visible'=>false];
    $data[] = ['field'=>'isCheckboxChecked','visible'=>false];
    $data[] = ['field'=>'rent_raise_id','visible'=>false];
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>35];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'filterControl'=>'input','width'=>15];
    $data[] = ['field'=>'unit','title'=>'Unit','sortable'=>true,'filterControl'=>'input','width'=>15];
    $data[] = ['field'=>'tenant','title'=>'Tnt','sortable'=>true,'filterControl'=>'input','width'=>15];
    $data[] = ['field'=>'street','title'=>'Addr.','sortable'=>true,'filterControl'=>'input','width'=>350];
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true,'filterControl'=>'input', 'width'=>125];
    $data[] = ['field'=>'tnt_name','title'=>'Name','sortable'=>true,'filterControl'=>'input','width'=>350];
    $data[] = $_getSelectColumn($perm,'isManager','Mgr',15,$this->_mapping['isManager']);
    $data[] = ['field'=>'rent','title'=>'Org Rnt','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'hud','title'=>'HUD','width'=>25];
    $data[] = ['field'=>'raise','title'=>'Next Rnt','sortable'=>true,'filterControl'=>'input','width'=>25] + $rentRaiseEditable;
    $data[] = ['field'=>'raise_pct','title'=>'Rs%.','sortable'=>true,'filterControl'=>'input','width'=>25] + $rentRaiseEditable;
    $data[] = ['field'=>'yearly_pct','title'=>'Yr. %','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'notice','title'=>'Nt','sortable'=>true,'filterControl'=>'input','width'=>20] + $noticeEditable;
    $data[] = ['field'=>'effective_date','title'=>'Eff Date','sortable'=>true,'filterControl'=>'input','width'=>20,'filterControlPlaceholder'=>'yyyy-mm-dd'];
    $data[] = ['field'=>'last_raise_date','title'=>'Lst Rs Date','sortable'=>true,'filterControl'=>'input','width'=>20,'filterControlPlaceholder'=>'yyyy-mm-dd'];
    $data[] = ['field'=>'submitted_date','title'=>'Sub Date','sortable'=>true,'filterControl'=>'input','width'=>20,'filterControlPlaceholder'=>'yyyy-mm-dd'];
    $data[] = $_getSelectColumn($perm,'unit_type','Unit Type',25,$this->_mapping['unit_type']);
    $data[] = $_getSelectColumn($perm,'rent_type','Rent Type',70,$this->_mapping['rent_type']);
    $data[] = ['field'=>'move_in_date','title'=>'Move In','sortable'=>true,'filterControl'=>'input','filterControlPlaceholder'=>'yyyy-mm-dd','width'=>25];
    $data[] = $_getSelectColumn($perm,'bedrooms','Bds',15,$this->_mapping['bedroom']);
    $data[] = $_getSelectColumn($perm,'bathrooms','Bth', 15,$this->_mapping['bathroom']);
    //$data[] = ['field'=>'allFile','title'=>'Past Nt','sortable'=>true,'width'=>30];
    $data   = $this->_addPastRaiseColumns($data);
    
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>$_getButtons($perm)];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getSuccessMsg($name,$vData=[]){
    $data = [
      'update'             => Html::sucMsg('Successfully Updated.'),
      'store'              => Html::sucMsg('Successfully Submitted.'),
      '_submitRentRaise'   => Html::sucMsg('Successfully Submitted Rent Raise'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store'                    => Html::errMsg('Invalid Selection, please try again'),
      'rentControlRaiseError'    => Html::errMsg('This a Rent Control Property. You cannot raise its rent more than once per year'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getHelpPage(){
    $page = $this->_viewPath . 'help';
    return ['html'=>view($page)->render()];
  }
//------------------------------------------------------------------------------
  public function _calculateNoticeFromBilling($billing,$vData,$effectDate,$field='gl_acct'){
    $raise      = $vData['raise'];
    $propType   = $vData['prop_type'];
    
    if($propType == 'M'){
      return 90;
    } else {
      $lastYear   = strtotime($effectDate . ' -12 months +1 day');
      $billing602 = $billingBeforeYear = $billingAfterYear = [];
      $_sortFn  = function($a,$b){
        $aTime       = strtotime(Helper::getValue('start_date',$a,0));
        $bTime       = strtotime(Helper::getValue('start_date',$b,0));
        $aStopTime   = strtotime(Helper::getValue('stop_date',$a,0));
        $bStopTime   = strtotime(Helper::getValue('stop_date',$b,0));
        return $aTime == $bTime ? ($aStopTime == $bStopTime ? 0 : ($aStopTime < $bStopTime ? -1 : 1)) : ($aTime < $bTime ? -1 : 1);
      };
    
      foreach($billing as $i => $v){
        if($v[$field] == G::$rent && $v['schedule'] == 'M'){
          $billing602[] = $v;
        }
      }
    
      usort($billing602,$_sortFn);
      $lastYearRent = 0;
      foreach($billing602 as $i => $v){
        if(strtotime($v['start_date']) <= $lastYear){
        $lastYearRent = $v['amount'];
        } else {
          break;
        }
      }
    
      
      $firstRent     = !empty($billing602[0]['amount']) ? $billing602[0]['amount'] : 0;
      $lastYearRent  = !empty($lastYearRent) ? $lastYearRent : $firstRent;
      $divisor       = !empty($lastYearRent) ? Format::roundDownToNearestHundredthDecimal($lastYearRent) : 1;
      $difference    = Format::roundDownToNearestHundredthDecimal($raise - $lastYearRent);
      $percentChange = (floatval($difference) / $divisor) * 100.0;
      $notice        = $percentChange > 10.0 ? 60 : 30;
      return $notice;
    }
  }
//------------------------------------------------------------------------------
  private function _getNumChecks($req,$vData=[]){
    $hasFilter = !empty($vData);
    $vData += !empty($vData) ? ['defaultSort'=>['group1.keyword:asc','prop.keyword:asc','unit.keyword:asc','tenant:desc'],'defaultFilter'=>['isCheckboxChecked'=>1],'limit'=>-1] : ['limit'=>-1];
    unset($vData['offset']);
    $qData  = GridData::getQuery($vData,$this->_viewTable);
    
    $rFilter = Helper::getElasticResult(Elastic::gridSearch($this->_indexMain . $qData['query']));
    $r   = Helper::getElasticResult(Elastic::searchQuery([
      'index'    => $this->_viewTable,
      '_source'  => ['tenant_id'],
      'query'    => [
        'must'   => [
          'isCheckboxChecked'  => 1,
        ]
      ]
    ]),0,1);
    
    $data = Helper::getValue('data',$r,[]);
    $ids  = array_column(array_column($hasFilter ? $rFilter : $data,'_source'),'tenant_id');
    return ['data'=>array_combine($ids,$ids),'count'=>Helper::getValue('total',$r,0)];
  }
//------------------------------------------------------------------------------
  private function _getBillingIds($data,$ids){
    foreach($data as $i => $v){
      $data[$i]['updateData']['billing_id'] = $ids[$i];
    }
    return $data;
  }
//------------------------------------------------------------------------------
//  private function _containsNotices($source){
//    $rentRaiseData  = Helper::getValue(T::$rentRaise,$source,[]);
//    foreach($rentRaiseData as $i => $v){
//      if(!empty($v['file'])){
//        return 1;
//      }
//    }
//    return 0;
//  }
//------------------------------------------------------------------------------
  private function _calculateNewRaise($source,$oldRent,$currentAmount){
    //Calculate only if the raise percentage was changed but not the raise amount from the update method
    return empty($source['raise']) && !empty($source['raise_pct']) ? (((Format::roundDownToNearestHundredthDecimal($source['raise_pct']) / 100.0) + 1.0) * $oldRent)  : $currentAmount;
  }
//------------------------------------------------------------------------------
  private function _calculateNewRaisePct($source,$oldRent, $currentPct){
    //Calculate only if the raise amount was change but not the raise percentage from the update method
    return !empty($source['raise']) && empty($source['raise_pct']) ? (( ($source['raise'] - $oldRent) / (!empty($oldRent) ? $oldRent : 1)) * 100.0) : $currentPct;
  }
//------------------------------------------------------------------------------
  private function _addPastRaiseColumns($data){
    for($i = 0; $i < $this->_numPastRentCols; $i++){
      $num     = $i + 1;
      $col     = ['field'=>'last_raise_' . $num,'title'=>'Last Raise ' . $num];
      $col    += $i < ($this->_numPastRentCols - 1) ? ['width'=>100] : [];
      $data[]  = $col;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _gatherPastRentRaise($source){
    $currentId      = $source['rent_raise_id'];
    $rentRaiseData  = Helper::getValue(T::$rentRaise,$source,[]);
    $rentRaiseData  = array_reverse($rentRaiseData);
    $num            = 1;
  
    $data       = [];
    $numRaises  = count($rentRaiseData);
    foreach($rentRaiseData as $i => $v){
      //Ensure that the current or pending rent raise is not added to last raise list
      //Add only records with unique billing ids to ensure that only the submitted rent raise records are added 
      if(!empty($v['billing_id']) && empty($data[$v['billing_id']]) && $v['rent_raise_id'] != $currentId){
        $noticeLink   = $i < ($numRaises - 1) ? Html::repeatChar('&nbsp;',2) .  Html::span(Html::i('',['class'=>'fa fa-file-pdf-o']),['class'=>'clickable','data-hidden-id'=>$v['rent_raise_id'],'data-hidden-tenant-id'=>$source['tenant_id']]) : '';
        $data[$v['billing_id'] . $v['last_raise_date']] = $v['last_raise_date'] . Html::br() . Format::usMoney($v['raise']) . $noticeLink;
      }
    }
    
    foreach($data as $k => $v){
      $source['last_raise_' . $num] = $v;
      $num++;
    }
    return $source;
  }
//------------------------------------------------------------------------------
  private function _validateRentControlTenant($source,$updateData){
    $endDate           = date('m/d/Y',strtotime($source['last_raise_date'] . ' +1 year')); 
    $effectDateTs      = date('Y-m-01',strtotime(date('Y-m-d') . ' +1 month 30 days'));

    if($updateData['isCheckboxChecked'] == 1 && $source['rent_type'] == 'rent_control' && strtotime($effectDateTs) < strtotime($endDate)){
      Helper::echoJsonError($this->_getErrorMsg('rentControlRaiseError'),'popupMsg');
    }
  }
}
