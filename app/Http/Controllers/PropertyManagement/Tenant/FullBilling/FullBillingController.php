<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\FullBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{RuleField, V, Form, Upload, Elastic, Mail, File, Html, Helper,HelperMysql,Format, TableName AS T, GlName AS G, ServiceName AS S};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class
use App\Http\Controllers\PropertyManagement\RentRaise\RentRaiseController;
use PDF;

class FullBillingController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Tenant/fullbilling/';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $perm = Helper::getPermission($req);
    $page = $this->_viewPath . 'edit';
    $valid = V::startValidate([
      'rawReq'      => ['tenant_id'=>$id],
      'tablez'      => $this->_getTable(__FUNCTION__),
      // 'orderField'=>['application_id'],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tenant.'|tenant_id',
        ]
      ]
    ]);
    
    $vData = $valid['dataNonArr'];
    $source = ['prop','unit','tenant','tnt_name','status','move_in_date','dep_held1','base_rent','lease_start_date','billing','mem_tnt','alt_add'];
    $rTenant = M::getTenantFromElasticSearchById($vData, $source);
    $rBilling = isset($rTenant['billing']) ? $rTenant['billing'] : [];
    $rAltAddr = isset($rTenant['alt_add']) ? $rTenant['alt_add'][0] : [];
    $rMember = isset($rTenant['mem_tnt']) ? $rTenant['mem_tnt'] : [];
    unset($rTenant['billing'],$rTenant['alt_add'],$rTenant['mem_tnt']);
    
    $rUtility = M::getTenantUtility(Model::buildWhere(['t.tenant_id'=>$vData['tenant_id']]));
    $rUtility = !empty($rUtility) ? $rUtility : [];
    $rService  = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$rTenant['prop']]), ['remark', 'service']), 'service');
    
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__),
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__, $req), 
      'setting'   =>$this->_getSetting(__FUNCTION__, array_merge($rTenant,$rUtility), $req)
    ]);
    // Build Full billing fields
    $fullBillingField  = $this->_getFullBillingField($rBilling, $rService, Helper::usDate(), $req);
    $fullBillingField['emptyField'] = preg_replace('/ readonly\="1" /', '', $fullBillingField['emptyField']); 

    $altAddressField = $this->_getTenantAlternativeField($rAltAddr, $req); // Build alter address field
    $otherMemberField  = $this->_getOtherMemberField($rMember, $req);  // Build other member field

    return [
      'html'=>view($page, [ 'data'=>[
        'form'=>$form,
        'formTenantAlternative'=> $altAddressField,
        'formOtherMember'=> $otherMemberField['html'],
        'fullBillingForm'=>$fullBillingField['prorateCurrentMonth'],
        'prorateAmount'=>$this->_getProrateAmount($rTenant),
        'tenantName'=> title_case($rTenant['tnt_name']),
        'moreBillingIcon'=>$this->_getMoreAndLessIcon('moreBilling','lessBilling',$req),
        'moreOtherMemberIcon'=>$this->_getMoreAndLessIcon('moreOtherMember','lessOtherMember',$req)
      ]])->render(), 
      'fullBilling'=>$fullBillingField,
      'totalFullBilling'=>count($rBilling),
      'totalOtherMember'=>count($rMember),
      'otherMember'=>$otherMemberField['otherMember'],
    ];
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $valid = V::startValidate([
      'rawReq'          => ['tenant_id'=>$id] + $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => $this->_getOrderField(__FUNCTION__, $req->all()),
      'setting'         => $this->_getSetting(__FUNCTION__),
      'validateDatabase'=>[
        'mustExist'   =>[
          T::$tenant.'|tenant_id',
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    $prop    = $vData['prop'];
    $vData['usid']   = Helper::getUsid($req);
    $perm    = Helper::getPermission($req);
    $tablez  = $this->_getTable(__FUNCTION__);
    $service = Helper::keyFieldName(HelperMysql::getService(['prop'=>$prop]), 'service');
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(['g.prop'=>$prop]), 'gl_acct');
    $rProp   = M::getProp(Model::buildWhere(['prop'=>$prop]),1);
    $rUnit   = HelperMysql::getUnit(['prop.prop.keyword'=>$vData['prop'],'unit.keyword'=>$vData['unit']],['unit_id'],[],1,1);
    $rTenant = HelperMysql::getTenant(['tenant_id'=>$vData['tenant_id']]); 
    $batch   = HelperMysql::getBatchNumber();
    $today   = Helper::date();
    $insertData = $updateData = [];
    $rBilling = isset($rTenant['billing']) ? $rTenant['billing'] : [];
  
    foreach($rBilling as $billing) {
      foreach($valid['dataArr'] as $i => $val) {
        ## Need permission to add monthly 602 
        if(!isset($perm['fullbillingupdateMonthly602']) && $val['service_code'] == '602' && $val['billing_id'] == 0) {
          Helper::echoJsonError($this->_getErrorMsg('insertMonthly602'));
        }
        ## Need permission to update monthly 602
        if(!isset($perm['fullbillingupdateMonthly602']) && $billing['billing_id'] == $val['billing_id'] && ($billing['service_code'] == '602' || $val['service_code'] =='602')) {
          if($val['amount'] != $billing['amount']  || $val['start_date'] != $billing['start_date'] || $val['post_date'] != $billing['post_date'] || $val['stop_date'] != $billing['stop_date'] || $val['service_code'] != $billing['service_code'] || $val['remark'] != $billing['remark'] ) {
            Helper::echoJsonError($this->_getErrorMsg('updateMonthly602'));
          }
        }
        
        if($billing['billing_id'] == $val['billing_id']){
          $valid['dataArr'][$i]['schedule'] = $billing['schedule'];
        }
      }
    }
  
    foreach($tablez as $table){
      $rule = RuleField::generateRuleField(['tablez'=>[$table]])['rule'];
      foreach($valid['dataArr'] as $i=>$val){
        if(empty($val[$table.'_id']) && ($table == T::$memberTnt || $table == T::$billing)){
         // $valid['dataArr'][$i]['schedule'] = 'M';
          if($table == T::$billing && !empty($val['service_code'])  &&  $val['service_code'] == '602') {
            ## New start date must be bigger than the old start and stop date
            if(!empty($updateData[T::$billing])) {
              $newStartDate = strtotime($val['start_date']);
             
              foreach($updateData[T::$billing] as $data) {
                $billing = $data['updateData'];
                if($billing['service_code'] == '602' && $billing['schedule'] == 'M') {
                  if($billing['stop_date'] == '9999-12-31') {
                    Helper::echoJsonError($this->_getErrorMsg('oldStopDate'));
                  }
                  $oldStartDate = strtotime($billing['start_date']);
                  $oldStopDate  = strtotime($billing['stop_date']);
                  if($newStartDate <= $oldStartDate || $newStartDate <= $oldStopDate) {
                    Helper::echoJsonError($this->_getErrorMsg('newStartDate'));
                  }
                  $pastRent = $billing['amount'];
                }
              }
            }

            $pastRent   = !empty($pastRent) ? $pastRent : $rTenant['base_rent'];
            $divisor    = $pastRent > 0 ? $pastRent : 1.0;
            $difference = $val['amount'] - $pastRent;
            $percent    = (floatval($difference) / $divisor) * 100.0;
            $sData = [
              'raise' => $val['amount'],
              'prop_type' => $rProp['prop_type']
            ];
       
            $rentRaiseInsertData[] = [
              'foreign_id'        => $vData['tenant_id'],
              'prop'              => $vData['prop'],
              'unit'              => $vData['unit'],
              'tenant'            => $vData['tenant'],
              'raise'             => $val['amount'],
              'raise_pct'         => $percent,
              'notice'            => RentRaiseController::getInstance()->_calculateNoticeFromBilling($this->_extractBillingData($valid['dataArr']), $sData, $val['start_date'], 'service_code'),
              'service_code'      => $val['service_code'],
              'gl_acct'           => $val['service_code'],
              'remark'            => $val['remark'],
              'rent'              => $pastRent,
              'active'            => 1,
              'usid'              => $vData['usid'],
              'last_raise_date'   => $val['start_date'],
              'submitted_date'    => $today,
              'effective_date'    => $val['start_date'],
              'isCheckboxChecked' => 0,
              'cdate'             => Helper::mysqlDate(),
            ];
          }
   
          foreach($val as $fl=>$v){
            if(isset($rule[$fl])){
              $insertData[$table][$i][$fl]       = $v;
              $insertData[$table][$i]['prop']    = $vData['prop'];
              $insertData[$table][$i]['unit']    = $vData['unit'];
              $insertData[$table][$i]['tenant']  = $vData['tenant'];
              $insertData[$table][$i]['name_key']= $rTenant['tnt_name'];
              $insertData[$table][$i]['bank']    = $rProp['ar_bank'];
              $insertData[$table][$i]['tx_code'] = 'IN';
              $insertData[$table][$i]['batch']   = $batch;
              $insertData[$table][$i]['date1']   = $today;
              $insertData[$table][$i]['date2']   = $today;
              $insertData[$table][$i]['stop_date'] = isset($val['stop_date']) ? $val['stop_date'] : '';
              //$insertData[$table][$i]['schedule']  = isset($val['schedule']) ? $val['schedule'] : 'M';
              $insertData[$table][$i]['seq']     = $i + 1;
              $insertData[$table][$i]['gl_acct'] = !empty($val['service_code']) ? $service[$val['service_code']]['gl_acct'] : '';
            }
          }
          if(!empty($insertData[$table])){
            $insertData[$table] = array_values($insertData[$table]);  // Reset the index.
          }
        }else{
          if($table == T::$memberTnt){
            if(!empty($val['first_name']) && !empty($val['last_name'])){
              $updateData[$table][] = [
                'whereData'   => ['mem_tnt_id' => $val['mem_tnt_id']],
                'updateData'  => [
                  'first_name'    => $val['first_name'],
                  'last_name'     => $val['last_name'],
                  'phone_bis'     => $val['phone_bis'],
                  'phone_ext'     => $val['phone_ext'],
                  'relation'      => $val['relation'],
                  'occupation'    => $val['occupation']
                ]
              ];
            }
          }
          if($table == T::$billing){
            $oldBillingSchedule = 'M';
            foreach($rBilling as $bill) {
              if($val['billing_id'] == $bill['billing_id']){
                $oldBillingSchedule = $bill['schedule'];
              }
                
              if($val['billing_id'] == $bill['billing_id'] && $val['service_code'] == '602') {
                if($bill['service_code'] != '602') {
                  ## Cant change existing billing to service code 602
                  Helper::echoJsonError($this->_getErrorMsg('changeTo602'));
                }
//                else if($bill['schedule'] == 'S') {
//                  ## Cant change existing 602's schedule S to M
//                  Helper::echoJsonError($this->_getErrorMsg('changeSingleToMonthly'));
//                }
              }
            }
            $billingRentRaise = M::getRentRaise(Model::buildWhere(['billing_id'=>$val['billing_id']]),['rent_raise_id','rent']);
            foreach($billingRentRaise as $rentRaiseRow){
              $pastRent   = Helper::getValue('rent',$rentRaiseRow,0);
              $divisor    = !empty($pastRent) && $pastRent > 0 ? $pastRent : 1.0;
              $difference = $val['amount'] - $pastRent;
              $percent    = (floatval($difference) / $divisor) * 100.0;
                
              $updateData[T::$rentRaise][] = [
                'whereData'  => ['rent_raise_id'=>$rentRaiseRow['rent_raise_id']],
                'updateData' => [
                  'raise'            => $val['amount'],
                  'last_raise_date'  => $val['start_date'],
                  'effective_date'   => $val['start_date'],
                  'raise_pct'        => $percent,    
                  'usid'             => $vData['usid'],
                ]
              ];
            }
            $updateData[$table][] = [
              'whereData'   => ['billing_id' => $val['billing_id']], 
              'updateData'  => [
                'service_code'    => $val['service_code'],
                'remark'          => $val['remark'],
                'schedule'        => $oldBillingSchedule,
                'amount'          => $val['amount'],
                'post_date'       => $val['post_date'],
                'start_date'      => $val['start_date'],
                'stop_date'       => $val['stop_date'],
                'gl_acct'         => !empty($val['service_code']) ? $service[$val['service_code']]['gl_acct'] : '',
                'gl_acct_next'    => !empty($val['service_code']) ? $service[$val['service_code']]['gl_acct'] : '',
                'gl_acct_past'    => !empty($val['service_code']) ? $service[$val['service_code']]['gl_acct'] : ''
              ]
            ];
          }
        }
      }
      
      
      switch ($table) {
        case T::$tenantUtility:
          if(!empty($vData['tenant_utility'])){
            $updateData[$table][] = [
              'whereData'   => ['tenant_utility' => $vData['tenant_utility']],
              'updateData'  => [
                'water'           => $vData['water'],
                'gas'             => $vData['gas'],
                'electricity'     => $vData['electricity'],
                'trash'           => $vData['trash'],
                'sewer'           => $vData['sewer'],
                'landscape'       => $vData['landscape']
              ]
            ];
          }else{
            // Insert new tenant utility
            $insertData[$table] = HelperMysql::tenant_utility($vData,$vData['usid']);
          }
          break;
        case T::$alterAddress:
          if($vData['alt_add_id'] != 0){
            if(!empty($vData['street']) && !empty($vData['city']) && !empty($vData['state']) &&  !empty($vData['zip'])){
              $updateData[$table][] = [
                'whereData'   => ['alt_add_id' => $vData['alt_add_id']], 
                'updateData'  => [
                  'street'      => $vData['street'],
                  'city'        => $vData['city'],
                  'state'       => $vData['state'],
                  'zip'         => $vData['zip']
                ]
              ];
            }
          }else{
            foreach($vData as $fl=>$v){
              if(isset($rule[$fl]) && !isset($insertData[$table][0])){
                $insertData[$table][$fl] = $v;
              }
            }
          }
          break;
      }
    }
    // Validate Alternative Address either all empty or all filled out
    if(!empty($insertData[T::$alterAddress]) && !$this->_validateAltAddress($insertData[T::$alterAddress])){
      unset($insertData[T::$alterAddress]);
    }
    if(!empty($insertData[T::$memberTnt]) && !$this->_validateMemberTenant($insertData[T::$memberTnt])){
      unset($insertData[T::$memberTnt]);
    }
    
    if(!empty($insertData[T::$billing])){
      $insertData[T::$billing] = $this->_sortAndVerifyInsertBilling($insertData[T::$billing]);
    }
    $insertData = HelperMysql::getDataSet($insertData,$vData['usid'], $glChart, $service);
    $updatedTenantBaseRent = 0;
    
    $newTenantRent = $this->_fetchLatestBillingAmount($this->_extractBillingData($valid['dataArr']),['prop'=>$rProp['prop']]);
    if(!empty($newTenantRent) && $newTenantRent != $rTenant['base_rent']){
      $updateData[T::$tenant][] = [
        'whereData'   => ['tenant_id'=>$rTenant['tenant_id']],
        'updateData'  => ['base_rent'=>$newTenantRent],
      ];
      if(!empty($rUnit['unit_id'])){
        $updateData[T::$unit][]   = [
          'whereData'   => ['unit_id'=>$rUnit['unit_id']],
          'updateData'  => ['rent_rate'=>$newTenantRent],
        ];
      }
      $updatedTenantBaseRent = 1;
    }

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      if(!empty($insertData)){
        $success += Model::insert($insertData);
        if(!empty($rentRaiseInsertData)){
          foreach($rentRaiseInsertData as $i => $v){
            $rentRaiseInsertData[$i]['billing_id'] = $success['insert:' . T::$billing][$i];
          }
          $pendingRentRaise               = last($rentRaiseInsertData);
          $pendingRentRaise['billing_id'] = 0;
          $rentRaiseInsertData[]          = $pendingRentRaise;
          M::deleteTableData(T::$rentRaise,Model::buildWhere(['foreign_id'=>$vData['tenant_id'],'billing_id'=>0]));
          Model::insert([T::$rentRaise => $rentRaiseInsertData]);
        }
      }
      if(!empty($updateData)){
        $success += Model::update($updateData);
      }
      $elastic['insert'][T::$tenantView] = ['t.tenant_id'=>[$vData['tenant_id']]];
      $elastic['insert']                += (!empty($updateData[T::$unit])) ? [T::$unitView => ['u.unit_id'=>[$rUnit['unit_id']]]] : [];
      $response['msg'] = $this->_getSuccessMsg('update', $vData);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
      
      if($rTenant['status'] === 'C' && ( (!empty($newTenantRent) && $newTenantRent != $rTenant['base_rent']) || !empty($rentRaiseInsertData) || !empty($updateData[T::$rentRaise]))){
        Model::commit([
          'success' => $success,
          'elastic' => ['insert'=>[T::$rentRaiseView=>['t.tenant_id'=>[$vData['tenant_id']]]]],
        ]);
      }
    } catch(Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function create(){
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req){
    $op = $req->all()['op'];
    return method_exists($this, $op) ? $this->$op($id,$req) : [];
  }
//------------------------------------------------------------------------------
  public function billingRemove($id, Request $req){
    $valid = V::startValidate([
      'rawReq' => ['billing_id' => $id]+$req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$billing.'|billing_id',
          T::$tenant.'|tenant_id'
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    $billingRentRaise = M::getRentRaise(Model::buildWhere(['billing_id'=>$vData['billing_id'], 'foreign_id'=>$vData['tenant_id']]),['rent_raise_id'], 1);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success[T::$billing] = M::deleteTableData(T::$billing,Model::buildWhere(['billing_id'=>$vData['billing_id']]));
      $elastic = [
        'insert'=>[	        
          T::$tenantView=>['t.tenant_id'=>[$vData['tenant_id']]]	
        ]	
      ];
      if(!empty($billingRentRaise)) {
        $success[T::$rentRaise] = M::deleteTableData(T::$rentRaise,Model::buildWhere(['billing_id'=>$vData['billing_id'], 'foreign_id'=>$vData['tenant_id']]));
      }
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
      
      if(!empty($billingRentRaise)){
        Model::commit([
          'success'  => $success,
          'elastic'  => ['insert' => [T::$rentRaiseView => ['t.tenant_id'=>[$vData['tenant_id']]]]],
        ]);
      }
    } catch(Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function memberRemove($id, Request $req){
    $valid = V::startValidate([
      'rawReq' => ['mem_tnt_id' => $id]+$req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$memberTnt.'|mem_tnt_id',
          T::$tenant.'|tenant_id'
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      $success[T::$memberTnt] = DB::table(T::$memberTnt)->where(Model::buildWhere(['mem_tnt_id'=>$vData['mem_tnt_id']]))->delete();
      $elastic = [
        'insert'=>[
          T::$tenantView=>['t.tenant_id'=>[$vData['tenant_id']]]
        ]
      ];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
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
      'edit'  =>[T::$tenant, T::$billing, T::$alterAddress, T::$memberTnt, T::$tenantUtility],
      'update'  =>[T::$tenant, T::$billing, T::$alterAddress, T::$memberTnt, T::$tenantUtility],
      'billingRemove' => [T::$billing, T::$tenant],
      'memberRemove' => [T::$memberTnt, T::$tenant]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req=[]){
    $orderField = [
      'edit'  =>['prop','unit', 'lease_start_date','move_in_date', 'status', 'tenant', 'base_rent', 'dep_held1', 'tenant_utility', 'water', 'gas', 'electricity', 'trash', 'sewer', 'landscape'],
      'update' =>[
        'tenant_id', 'prop','unit', 'lease_start_date','move_in_date', 'status', 'tenant', 'base_rent', 'dep_held1',
        'tenant_utility','water','gas','electricity','trash','sewer', 'landscape',
        'alt_add_id','street', 'city', 'state', 'zip'
      ],
    ];
    if($fn=='update'){
      if(isset($req['mem_tnt_id'])){
        array_push($orderField[$fn],'mem_tnt_id','first_name', 'last_name', 'phone_bis', 'phone_ext', 'relation', 'occupation');
      }
      if(isset($req['billing_id'])){
        array_push($orderField[$fn],'billing_id','service_code','remark','amount','start_date','post_date','stop_date');
      }
    }
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$default = [], $req=[]){
    $yesNo = [0=>'No', 1=>'Yes'];
    $disabled = $this->_getDisabledAttr($req);
    $setting = [
      'edit'=>[
        'field'=>[
          'lease_start_date'=>['label'=>'Lease Date', 'readonly'=>1],
          'move_in_date'    =>['label'=>'Move In Date', 'readonly'=>1],
          'dep_held1'      =>['label'=>'Deposit', 'readonly'=>1],
          'prop'            =>['label'=>'Property', 'readonly'=>1],
          'base_rent'       =>['readonly'=>1, 'label'=>'Rents'],
          'unit'            =>['readonly'=>1],
          'status'          =>['readonly'=>1],
          'tenant'          =>['readonly'=>1],
          'tenant_utility'  =>  $disabled + ['type'=>'hidden'],
          'water'           =>  $disabled + ['label'=>'Tnt Pay Water', 'type'=>'option', 'option'=>$yesNo],
          'gas'             =>  $disabled + ['label'=>'Tnt Pay Gas', 'type'=>'option', 'option'=>$yesNo],
          'electricity'     =>  $disabled + ['label'=>'Tnt Pay Elec.', 'type'=>'option', 'option'=>$yesNo],
          'trash'           =>  $disabled + ['label'=>'Tnt Pay Trash', 'type'=>'option', 'option'=>$yesNo],
          'sewer'           =>  $disabled + ['label'=>'Tnt Pay Sewer', 'type'=>'option', 'option'=>$yesNo],
          'landscape'       =>  $disabled + ['label'=>'Tnt Pay Landscape', 'type'=>'option', 'option'=>$yesNo]
        ]
      ],
      'update'=>[
        'rule'=>[
          'billing_id'=>'nullable|integer',
          'alt_add_id'=>'nullable|integer',
          'mem_tnt_id'=>'nullable|integer',
          'amount'=>'nullable|numeric|between:1,999999999999',
          'tenant_utility'=>'nullable|integer',
        ]
      ],
    ];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = ($k=='lease_start_date' || $k=='move_in_date') ? Format::usDate($v) : $v;
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $button = [
      'edit'=>['submit'=>['id'=>'submit', 'value'=>'Save Tenant Full Billing', 'class'=>'col-sm-12']],
    ];
    return isset($perm['fullbillingupdate']) ? $button[$fn] : '';
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getErrorMsg($name){
    $data = [
      'tenantAlert' =>Html::errMsg('There is a problem with the TenantAlert. Please contact administration!'),
      'mysqlError'  =>Html::mysqlError(),
      T::$alterAddress  =>Html::errMsg('Street, City, State, Zip must either be filled out or empty in TENANT ALTERNATIVE ADDRESS section.'),
      T::$memberTnt  =>Html::errMsg('First Name and Last Name must either be filled out or empty in OTHER MEMBER section.'),
      'insertMonthly602'   =>Html::errMsg('Need permission to add Monthly 602 to billing.'),
      'updateMonthly602'   =>Html::errMsg('Need permission to update Monthly 602 in the billing.'),
      'newStartDate' =>Html::errMsg('The new Monthly 602 start date must be bigger than the other billings start and stop date.'),
      'newStopDate'  =>Html::errMsg('The new Monthly 602 stop date must be 12/31/9999.'),
      'oldStopDate'  =>Html::errMsg('Please change all old billing stop date 12/31/9999 to different days.'),
      'changeTo602'  =>Html::errMsg('Can\'t change existing billing to Monthly 602, please add a new row.'),
      'changeSingleToMonthly'=>Html::errMsg('Can\'t change single 602 to monthly 602, please add a new row.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $vData = []){
    $_updateMsg = function($vData){
      if(!empty($vData)){
        $html = Html::createTable([
          ['desc'=>['val'=>'Property'],'prop'=>['val'=>$vData['prop']]],
          ['desc'=>['val'=>'Unit'],'unit'=>['val'=>$vData['unit']]],
          ['desc'=>['val'=>'Tenant'],'tenant'=>['val'=>$vData['tenant']]],
          ['desc'=>['val'=>'Rent'],'base_rent'=>['val'=>Format::usMoney($vData['base_rent'])]],
          ['desc'=>['val'=>'Security Deposit'],'dep_held1'=>['val'=>Format::usMoney($vData['dep_held1'])]],
          ['desc'=>['val'=>'Lease Date'],'lease_start_date'=>['val'=>Format::usDate($vData['lease_start_date'])]],
          ['desc'=>['val'=>'Move In Date'],'move_in_date'=>['val'=>Format::usDate($vData['move_in_date'])]],
        ], ['class'=>'table table-bordered'], 0, 0);
        return Html::sucMsg('Tenant full billing is Successfully Update. Congratulation.') . $html;
      }else{
        return '';
      }
    };
    
    $data = [
      'update'          =>$_updateMsg($vData),
      'billingRemove'   => Html::sucMsg("Tenant's Full Billing remove success!!"),
      'memberRemove'    => Html::sucMsg("Tenant's other member remove success!!")
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getPermission($req){
  }
//------------------------------------------------------------------------------
  public static function _getProrateAmount($r){
    $amount = $r['base_rent'];
    $numDay = 30;
    $prorateDay = Helper::getDateDifference(date('Y-m-d'), date('Y-m-t'));
    $prorate = ($prorateDay >= 30) ? $amount : ($prorateDay * $amount / $numDay);
    return [
      'prorate'       => Format::floatNumberSeperate($prorate),
      'proratePerDay' => Format::usMoney($amount / $numDay),
      'prorateDay'    => Format::floatNumberSeperate($prorateDay),
      'rent'          => Format::floatNumberSeperate($amount),
      'totalDeposit'  => Format::floatNumberSeperate($r['dep_held1'])
    ];
  }
//------------------------------------------------------------------------------
  private function _getFullBillingField($rBilling, $rService, $leaseDate, $req = []){
    $disabled = $this->_getDisabledAttr($req);
    $_getField  = function ($prorateType, $i, $v, $disabled, $req = []){
      $form = '';
      $default = $disabled + ['req'=>1, 'includeLabel'=>0];
      $readonly = $v['service_code'] == '602' && $v['schedule'] == 'M' ? ['readonly'=>1] : [];
      $billingId = $v['billingId'];
      $billingIdField = [
        'id'=>'billing_id[' . $i . ']', 
        'type'=>'hidden',
        'value'=>$billingId
      ];
      $form .= Form::getField($billingIdField);
      $fields = [
        'service_code'=>$default + $readonly + [
          'id'=>'service_code[' . $i . ']', 
          'type'=>'text',
          'class'=>'autocomplete',
//          'readonly'=>1,
          'value'=>$v['service_code']],
        'remark'=> $default + [
          'id'=>'remark[' . $i . ']', 
          'type'=>'text',
          'value'=>$v['title']],
//        'schedule'=> $default + $readonly + [
//          'id'=>'schedule[' . $i . ']', 
//          'type'=>'option',
//          'option'=>['S'=>'Single', 'M'=>'Monthly'], 
//          'value'=>$v['schedule']],
        'amount' =>$default + [
          'id'=>'amount[' . $i . ']', 
          'type'=>'text',
          'class'=>'decimal',
          'value'=>$v['amount']],
        'post_date' => $default + [
          'id' => 'post_date[' . $i . ']',
          'type' => 'text',
          'class' => 'date',
          'value' => Format::usDate($v['post_date']),
        ],
        'start_date'=>$default+ [
          'id'=>'start_date[' . $i . ']', 
          'type'=>'text',
          'class'=>'date', 
          'value'=>Format::usDate($v['leaseStartDate'])],
        'stop_date' =>$default+ [
          'id'=>'stop_date[' . $i . ']',
          'class'=>'date', 
          'type'=>'text', 
          'value'=>Format::usDate($v['moveOutDate'])]
      ];
      $map = [
        'service_code'=>'Service#',
        'remark'=>'Service Description',
        'amount'=>'Amount',
        'start_date'=>'Start Date',
        'post_date' => 'Post Date',
        'stop_date'=>'End Date',
      ];
      foreach($fields as $k=>$v){
        $col = 2;
        if($k == 'service_code' || $k == 'amount') { $col = 2; }
        $removeMark = (empty($disabled) && $k == 'stop_date') ? $this->_getRemoveBtn('billing',$i,$req) : '';
        $colClass = 'col-md-' . $col;
        if(empty($disabled) && $k == 'stop_date'){
          $removeDiv = Html::div(Form::getField($v) . $removeMark, ['class'=>'trashIcon']);
          $form .= Html::div($removeDiv ,['class'=>$colClass]);
        }else{
          $form .= Html::div(Form::getField($v) . $removeMark,['class'=>$colClass]);
        }
      }
      return Html::div($form, ['class'=>'row ' . $prorateType, 'data-key'=>$i]);
    };
    
    $serviceCode = '602';
    $todayDate = $leaseDate;;
    $endMonthDate = date('m/t/Y');

    $billingFormData = [];
    
    foreach($rBilling as $data){
      array_push($billingFormData,[
        'billingId'         => $data['billing_id'],
        'service_code'      => $data['service_code'],
        'title'             => $data['remark'],
        'schedule'          => $data['schedule'],
        'amount'            => Format::floatNumberSeperate($data['amount']),
        'post_date'         => $data['post_date'],
        'leaseStartDate'    => $data['start_date'], 
        'moveOutDate'       => $data['stop_date']
      ]);
    }

    $defaultValue = [
      'prorateCurrentMonth'=>$billingFormData,
      'emptyField'=>[
        ['billingId'=>0, 'service_code'=>$serviceCode, 'title'=>$rService[$serviceCode]['remark'], 'schedule'=>'S', 'amount'=>'$0', 'leaseStartDate'=>$todayDate, 'post_date'=>$todayDate,'moveOutDate'=>$endMonthDate],
      ]
    ];

    foreach($defaultValue as $prorateType=>$val){
      $data[$prorateType] = '';
      foreach ($val as $i=>$v) {

        $data[$prorateType] = !isset($data[$prorateType]) ? $_getField($prorateType, $i, $v, $disabled, $req) : $data[$prorateType] . $_getField($prorateType, $i, $v, $disabled, $req);
      }
    }

    return $data;
  }
//------------------------------------------------------------------------------
  private function _getTenantAlternativeField($altData, $req = []){
    $html = '';
    $disabled = $this->_getDisabledAttr($req);
    $field = RuleField::generateRuleField(['tablez'=>[T::$alterAddress]])['field'];
    $selectedField = ['street', 'city', 'state', 'zip'];
    $altAddIdField = $disabled + [
      'id'=>'alt_add_id', 
      'type'=>'hidden',
      'value'=>!empty($altData) ? $altData['alt_add_id'] : 0
    ];
    $html .= Form::getField($altAddIdField);
    foreach($selectedField as $fl){
      $field[$fl]['includeLabel'] = 0;
      $field[$fl]['value'] = !empty($altData) ? $altData[$fl] : '';
      $field[$fl] = $field[$fl] + $disabled;
      $col = 3;
      $header = Html::tag('h4', title_case($fl));
      $html .= Html::div($header . Form::getField($field[$fl]),['class'=>'col-md-' . $col]);
    }
    return $html;
  }
//------------------------------------------------------------------------------
  private function _getOtherMemberField($memberData, $req = []){
    $data = ['html'=>'', 'otherMember'=>''];
    $disabled = $this->_getDisabledAttr($req);
    $field = RuleField::generateRuleField(['tablez'=>[T::$memberTnt], 'isKeyArray'=>1])['field'];
    $selectedField = ['first_name', 'last_name', 'phone_bis', 'phone_ext', 'relation', 'occupation'];
    $memberIndex = 0;
    if(!empty($memberData)){
      foreach($memberData as $i=>$member){
        $memTntIdField = $disabled + [
          'id'=>'mem_tnt_id[' . $i . ']',  
          'type'=>'hidden',
          'value'=>$member['mem_tnt_id']
        ];
        $data['row'] = Form::getField($memTntIdField);
        foreach($selectedField as $fl){
          $label = title_case(preg_replace_array('/_|bis/', [' ', ' '], $fl));
          $field[$fl]['includeLabel'] = 0;
          $field[$fl]['placeholder'] = $label;
          $field[$fl]['value'] = $member[$fl];
          $field[$fl] = $field[$fl] + $disabled;
          $col = ($fl == 'street' || $fl == 'name') ? 3 : 2;
          $header = ($i == 0) ? Html::tag('h4', $label) : '';
          $removeMark = (empty($disabled) && $fl == 'occupation') ? $this->_getRemoveBtn('member',$memberIndex,$req) : '';
          if(empty($disabled) && $fl == 'occupation'){
            $removeDiv = Html::div(Form::getField($field[$fl]) . $removeMark, ['class'=>'trashIcon']);
            $data['row'] .= Html::div($header . $removeDiv ,['class'=>'col-md-' . $col]);
          }else{
            $data['row'] .= Html::div($header . Form::getField($field[$fl]) . $removeMark,['class'=>'col-md-' . $col]);
          }
        }
        $data['html']        .= Html::div($data['row'] , ['class'=>'row','data-key'=>$memberIndex]);
        $memberIndex++; 
      }
    }
    $newMemTntIdField = $disabled + [
      'id'=>'mem_tnt_id[' . $memberIndex . ']',
      'type'=>'hidden',
      'value'=>0
    ];
    $data['row'] = Form::getField($newMemTntIdField);
    foreach($selectedField as $fl){
      $label = title_case(preg_replace_array('/_|bis/', [' ', ' '], $fl));
      $field[$fl]['includeLabel'] = 0;
      $field[$fl]['placeholder'] = $label;
      $field[$fl]['value'] = '';
      $field[$fl] = $field[$fl] + $disabled;
      $col = ($fl == 'street' || $fl == 'name') ? 3 : 2;
      $header = ($memberIndex == 0) ? Html::tag('h4', $label) : '';
      $removeMark = (empty($disabled) && $fl == 'occupation') ? $this->_getRemoveBtn('member',$memberIndex,$req) : '';
      if(empty($disabled) && $fl == 'occupation'){
        $removeDiv = Html::div(Form::getField($field[$fl]) . $removeMark, ['class'=>'trashIcon']);
        $data['row'] .= Html::div($header . $removeDiv ,['class'=>'col-md-' .$col]);
      }else{
        $data['row'] .= Html::div($header . Form::getField($field[$fl]) . $removeMark,['class'=>'col-md-' .$col]);
      }
    }
    $data['otherMember'] = Html::div($data['row'] , ['class'=>'row otherMemberEmptyField', 'data-key'=>$memberIndex]);
    return $data;
  }
//------------------------------------------------------------------------------
  private function _validateAltAddress($data){
    if(empty($data['street']) && empty($data['city']) && empty($data['state']) &&  empty($data['zip'])){
      return 0;
    } else if(!empty($data['street']) && !empty($data['city']) && !empty($data['state']) &&  !empty($data['zip'])){
      return 1;
    } else{
      echo json_encode(['error'=>$this->_getErrorMsg(T::$alterAddress)]);
      exit;
    }
  }
//------------------------------------------------------------------------------
  private function _validateMemberTenant($data){
    foreach($data as $i=>$v){
      if(empty($v['first_name']) && empty($v['last_name'])){
        return 0;
      } else if(!empty($v['first_name']) && !empty($v['last_name']) ){
        return 1;
      } else{
        echo json_encode(['error'=>$this->_getErrorMsg(T::$memberTnt)]);
        exit;
      }
    }
  }
//------------------------------------------------------------------------------
  private function _getDisabledAttr($req = []){
    $perm = Helper::getPermission($req);
    $disabled = isset($perm['fullbillingupdate']) ? [] : ['disabled'=>1];
    return $disabled;
  }
//------------------------------------------------------------------------------
  private function _getRemoveBtn($key, $index, $req = []){
    $perm = Helper::getPermission($req);
    if($key == 'billing' && isset($perm['fullbillingdestroy'])){
      return '<a class="billingRemove" data-key="'.$index.'" title="Full Billing Remove"><i class="fa fa-trash-o text-red pointer tip" title="Full Billing Remove"></i></a>';
    }
    if($key == 'member' && isset($perm['fullbillingdestroy'])){
      return '<a class="memberRemove" data-key="'.$index.'" title="Member Remove"><i class="fa fa-trash-o text-red pointer tip" title="Member Remove"></i></a>';
    }
    return '';
  }
//------------------------------------------------------------------------------
  private function _getMoreAndLessIcon($moreId, $lessId, $req = []){
    $perm = Helper::getPermission($req);
    $iconData = '';
    if(isset($perm['fullbillingupdate'])){
      $iconData = '<i class="fa fa-fw fa-plus-square text-aqua tip tooltipstered pointer" title="Add More Full Billing Field" id="'.$moreId.'"></i>';
      // $iconData .= '<i class="fa fa-fw fa-minus-square text-danger tip tooltipstered pointer" title="Remove Full Billing Field" id="'.$lessId.'"></i>';
    }
    return $iconData;
  } 
//------------------------------------------------------------------------------
  private function _extractBillingData($vDataArr){
    $data = [];
    $num9999 = $num602 = 0;
    foreach($vDataArr as $i => $v){
      if(!empty($v['amount']) && !empty($v['service_code']) && !empty($v['start_date']) && !empty($v['stop_date'])){
        $v['schedule'] = Helper::getValue('schedule',$v,'M');
        $num602   = isset($v['billing_id']) && $v['billing_id'] == 0 && $v['service_code'] == '602' ? $num602 + 1 : $num602;
        $data[$i] = $v;
        if($v['amount'] > 0 && $v['service_code'] == '602' && $v['stop_date'] == '9999-12-31'){
          ++$num9999;
        }
      }
    }
    
    if($num602 > 0 && $num9999 == 0){
      Helper::echoJsonError($this->_getErrorMsg('newStopDate'));
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _sortAndVerifyInsertBilling($billing){
    $num9999 = $num602 = 0;
    $_sortByStartDate  = function($a,$b){
      $aTime       = strtotime(Helper::getValue('start_date',$a,0));
      $bTime       = strtotime(Helper::getValue('start_date',$b,0));
      $aStopTime   = strtotime(Helper::getValue('stop_date',$a,0));
      $bStopTime   = strtotime(Helper::getValue('stop_date',$b,0));
      return $aTime == $bTime ? ($aStopTime == $bStopTime ? 0 : ($aStopTime < $bStopTime ? -1 : 1)) : ($aTime < $bTime ? -1 : 1);
    };
    
    foreach($billing as $v){
      $num602 = isset($v['billing_id']) && $v['billing_id'] == 0 && Helper::getValue('service_code',$v) == '602' ? $num602 + 1 : $num602;
      if($v['service_code'] == '602' && $v['stop_date'] == '9999-12-31'){
        ++$num9999;
      }
    }
    
    if($num602 > 0 && $num9999 == 0){
      Helper::echoJsonError($this->_getErrorMsg('newStopDate'));
    }
    usort($billing,$_sortByStartDate);
    return $billing;
  }
//------------------------------------------------------------------------------
  private function _fetchLatestBillingAmount($billing,$source){
    $today   = strtotime(Helper::date());
    $amount  = 0;
    $has9999 = $this->_hasCurrentEndBilling($billing,$source);
    if($has9999 == 'Current 9999'){
      foreach($billing as $v){
        if($v['service_code'] != G::$depositI && strtotime($v['start_date']) <= $today && $v['stop_date'] == '9999-12-31' && $v['schedule'] == 'M'){
          if($v['service_code'] == S::$hud){
            $amount  += $v['amount'];  
          } else if( ($v['service_code'] == G::$rent && !preg_match('/MJC[1-9]+/',$source['prop'])) || ($v['service_code'] == G::$resort && preg_match('/MJC[1-9]+/',$source['prop'])) ){
            $amount  += $v['amount'];
          }
        }
      }
    } else if($has9999 == 'Future 9999'){
      $data = $this->_removeFutureBilling($billing,$source);
      foreach($data as $service => $val){
        $recentStart  = strtotime(last($val)['start_date']);
        foreach($val as $i => $v){
          if(strtotime($v['start_date']) == $recentStart){
            $amount += $v['amount'];    
          }
        }
      } 
    }
    return $amount;
  }
//------------------------------------------------------------------------------
  private function _hasCurrentEndBilling($billing,$source){
    $returnCode = 'No 9999';
    $today      = strtotime(Helper::date());
    foreach($billing as $v){
      //Adjust return code if there is a monthly billing item that is not 607 that is HUD or 602
      if($v['service_code'] != G::$depositI && $v['stop_date'] == '9999-12-31' && $v['schedule'] == 'M'){
        if( ($v['service_code'] == S::$hud) || ( ($v['service_code'] == G::$rent && !preg_match('/MJC[1-9]+/',$source['prop'])) ||  ($v['service_code'] == G::$resort && preg_match('/MJC[1-9]+/',$source['prop'])) )){
          //Immediately indicate there is 9999 stop date billing item that is before today to be used for the billing
          if(strtotime($v['start_date']) <= $today){
            return 'Current 9999';
          } else {
            //Indicate that the only 9999 billing items are in the future
            $returnCode = 'Future 9999';
          }
        }
      }
    }
    return $returnCode;
  }
//------------------------------------------------------------------------------
  private function _removeFutureBilling($billing,$source){
    $data = [];
    $today= strtotime(Helper::date());
    foreach($billing as $v){
      if($v['service_code'] != G::$depositI && strtotime($v['start_date']) <= $today && $v['schedule'] == 'M'){
        $data[] = $v;
      }
    }
    
    $_sortByStartDate  = function($a,$b){
      $aTime       = strtotime(Helper::getValue('start_date',$a,0));
      $bTime       = strtotime(Helper::getValue('start_date',$b,0));
      $aStopTime   = strtotime(Helper::getValue('stop_date',$a,0));
      $bStopTime   = strtotime(Helper::getValue('stop_date',$b,0));
      return $aTime == $bTime ? ($aStopTime == $bStopTime ? 0 : ($aStopTime < $bStopTime ? -1 : 1)) : ($aTime < $bTime ? -1 : 1);
    };
    
    usort($data,$_sortByStartDate);
    $groupedData = [];
    foreach($data as $i => $v){
      if($v['service_code'] == S::$hud){
        $groupedData[S::$hud][]  = $v;
      } else if( ($v['service_code'] == G::$rent && !preg_match('/MJC[1-9]+/',$source['prop'])) || ($v['service_code'] == G::$resort && preg_match('/MJC[1-9]+/',$source['prop'])) ){
        $groupedData[G::$rent][] = $v;
      }
    }
    return $groupedData;
  }
}
