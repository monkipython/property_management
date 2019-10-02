<?php
/**
 * 2019-01-08 make the moving change
 */
namespace App\Http\Controllers\CreditCheck\MoveIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{RuleField, V, Form, Upload, Elastic, Mail, File, Html, Helper,HelperMysql,Format, TableName AS T, FullBilling};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class
use PDF;

class MoveInController extends Controller{
  private $_viewPath = 'app/CreditCheck/movein/';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $defaultProrateType = 'prorateNextMonth';
    $valid = V::startValidate([
      'rawReq'=>['application_id'=>$id],
      'tablez'=>[T::$application],
      'orderField'=>['application_id'],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$application . '|application_id', 
        ]
      ]
    ]);
    
    $vData = $valid['dataNonArr'];
    $r = M::getApplication(Model::buildWhere(['a.application_id'=>$vData['application_id']]), 1);
    $rApplicant = M::getApplication(Model::buildWhere(['a.application_id'=>$vData['application_id']]),0);
    $rTenant = M::getTenant(Model::buildWhere(['prop'=>$r['prop'], 'unit'=>$r['unit']]));
    $rUnit = M::getUnit(Model::buildWhere(['prop'=>$r['prop']]),  'unit', 0);
    $isSingleHouse = (count($rUnit) == 1) ? 1 : 0;
    $rUploadAgreement = M::getFileUpload(['a.application_id'=>$vData['application_id'], 'type'=>'agreement']);
    $rUploadCreditCheck = M::getFileUpload(['a.application_id'=>$vData['application_id'], 'type'=>'application']);
    $rGlChat = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$r['prop']]), ['remark', 'service']), 'service');
    
    $r['tenant'] = isset($rTenant['tenant']) ? ++$rTenant['tenant'] : 1;
    $r['status'] = ($rTenant['status'] == 'C') ? 'F' : 'C';
    $r['base_rent'] = $r['new_rent'];
    $r['dep_held1'] = $r['sec_deposit'] + $r['sec_deposit_add'];
    $r['amount']  = $r['new_rent'];
    $prorateAmountData = FullBilling::getProrateAmount($r);
    $fullBillingField  = FullBilling::getFullBillingField($prorateAmountData, $rGlChat, date('m/d/Y'));
    $fullBillingField['emptyField'] = preg_replace('/ readonly\="1" /', '', $fullBillingField['emptyField']); 
    $otherMemberField  = $this->_getOtherMemberField($rApplicant);
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $r,$req, $isSingleHouse)
    ]);
    return [
      'html'=>view($page, [ 'data'=>[
        'form'=>$form,
        'formTenantAlternative'=> $this->_getTenantAlternativeField(),
        'formOtherMember'=> $otherMemberField['html'],
        'formProrate'=>$fullBillingField['prorate'],
        'fullBillingForm'=>$fullBillingField[$defaultProrateType],
        'prorateAmount'=>$prorateAmountData,
        'uploadAgreement'=>Upload::getListFile($rUploadAgreement, '/uploadAgreement'),
        'uploadCreditCheck'=>Upload::getListFile($rUploadCreditCheck, '/uploadAgreement'),
        'tenantName'=> title_case($r['fname'] . ' ' . $r['lname']) 
      ]])->render(), 
      'fullBilling'=>$fullBillingField,
      'otherMember'=>$otherMemberField['otherMember'],
      'otherMemberIndex'=>$otherMemberField['otherMemberIndex'],
    ];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => $this->_getOrderField(__FUNCTION__),
      'isPopupMsgError' => 1,
      'validateDatabase'=>[
        'mustExist'   =>[
          T::$application . '|application_id',
        ],
        'mustNotExist'=>[
          T::$tenant . '|prop,unit,tenant',
          T::$application . '|application_id,moved_in_status:1',
//          'application|moved_in_status:1'
        ],
      ]
    ]);
    $dataset = [];
    $usr     = $req['ACCOUNT']['email'];
    $vData   = $valid['dataNonArr'];
    $prop    = $vData['prop'];
    $id      = $vData['application_id'];
    
    $batch   = HelperMysql::getBatchNumber();
    $tablez  = [T::$tenant, T::$alterAddress, T::$memberTnt, T::$billing, T::$tenantUtility, T::$tntTrans];
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(['g.prop'=>$prop]), 'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(['prop'=>$prop]), 'service');
    $rProp   = M::getProp(Model::buildWhere(['prop'=>$prop]));
    $rApplicationInfo = M::getApplication(Model::buildWhere(['a.application_id'=>$id]), 1);
    
    $vData['tnt_name']  = $rApplicationInfo['fname'] . ' ' . $rApplicationInfo['lname'];
    $vData['phone1']    = $rApplicationInfo['cell'];
    $vData['isManager'] = $rApplicationInfo['isManager'];
    $vData['batch']     = $batch;
    $vData['lease_start_date'] = $vData['move_in_date'];
    
    foreach($tablez as $table){
      $rule = RuleField::generateRuleField(['tablez'=>[$table]])['rule'];
      foreach($valid['dataArr'] as $i=>$val){
        foreach($val as $fl=>$v){
          if(isset($rule[$fl])){
            $dataset[$table][$i][$fl]       = $v;
            $dataset[$table][$i]['prop']    = $vData['prop'];
            $dataset[$table][$i]['unit']    = $vData['unit'];
            $dataset[$table][$i]['tenant']  = $vData['tenant'];
            $dataset[$table][$i]['name_key']= $vData['tnt_name'];
            $dataset[$table][$i]['bank']    = $rProp['ar_bank'];
            $dataset[$table][$i]['tx_code'] = 'IN';
            $dataset[$table][$i]['batch']   = $batch;
            $dataset[$table][$i]['date1']   = Helper::date();
            $dataset[$table][$i]['date2']   = Helper::date();
            $dataset[$table][$i]['stop_date'] = isset($val['stop_date']) ? $val['stop_date'] : '';
            $dataset[$table][$i]['schedule']  = $table == T::$billing ? $val['schedule'] : Helper::getValue('schedule',$val);
            $dataset[$table][$i]['seq']     = $i + 1;
            $dataset[$table][$i]['gl_acct'] = !empty($val['service_code']) ? $service[$val['service_code']]['gl_acct'] : '';
          }
        }
      }
      foreach($vData as $fl=>$v){
        if(isset($rule[$fl]) && !isset($dataset[$table][0])){
          $dataset[$table][$fl] = $v;
        }
      }
    }
    // Validate Alternative Address either all empty or all filled out
    if(!$this->_validateAltAddress($dataset[T::$alterAddress])){
      unset($dataset[T::$alterAddress]);
    }
    if(!$this->_validateMemberTenant($dataset[T::$memberTnt])){
      unset($dataset[T::$memberTnt]);
    }
    // We need only 2 element into the tnt_trans
    foreach($dataset[T::$tntTrans] as $i=>$v){
      if($this->_isInBilling($v)){
        unset($dataset[T::$tntTrans][$i]);
      }
//      if($i != 0 && $i != 1){
//        unset($dataset[T::$tntTrans][$i]);
//      }
    }
    $vData['billing'] = Helper::keyFieldName($dataset[T::$billing], ['service_code','schedule']);
    $insertData = HelperMysql::getDataSet($dataset,$usr, $glChart, $service);
    # DEAL WITH MANAGER MOVE IN 
    if(!empty($rApplicationInfo['isManager']) && !empty($insertData[T::$billing])){
      $managerBilling = Helper::keyFieldName($insertData[T::$billing], ['schedule', 'service_code'], 'amount');
      $insertData[T::$managerMoveinList] = ['prop'=>$vData['prop'], 'unit'=>$vData['unit'], 'tenant'=>$vData['tenant'], 'monthlyRent'=>!empty($managerBilling['M602']) ? $managerBilling['M602'] : 0];
    }
    
    $tenantPaid = !empty($vData['billing']['HUDM']['amount']) ? $vData['billing']['HUDM']['amount'] :  0;
    $tenantPaid += !empty($vData['billing']['602M']['amount']) ? $vData['billing']['602M']['amount'] :  0;
    if(!$rApplicationInfo['isManager'] && $tenantPaid < $vData['base_rent']){
      $this->_getEmail('storeRentIncrease', $req, ['vData'=>$vData]);
    }
    # IT'S POSSIBLE TO NOT HAVE BILLING WHEN THERE IS ONLY SINGLE ROW WITH ZERO DOLLAR
    if(!empty($insertData[T::$billing])){
      foreach($insertData[T::$billing] as $i=>$v){
//        if($v['service_code'] == '602' && $vData['move_in_date'] == $v['start_date'] && $v['stop_date'] != '9999-12-31'){
//          unset($insertData[T::$billing][$i]);
//        }
//        # NEED TO DELETE THE DEPOSIT AS WELL, BUT WE ALREADY BUILD THEM
//        if($v['service_code'] == '607'){
//          unset($insertData[T::$billing][$i]);
//        }
        if(!$this->_isInBilling($v)){
          unset($insertData[T::$billing][$i]);
        }
//        if($v['schedule'] =='M' || ($v['stop_date'] == '9999-12-31' && $v['gl_acct'] != '607') || trim($v['stop_date']) == date('Y-m-d', strtotime('last day of next month'))){
//        } else{
//          unset($insertData[T::$billing][$i]);
//        }
      }
      $insertData[T::$billing] = array_values($insertData[T::$billing]);
    }
    // The index to zero
    if(!empty($insertData[T::$tntTrans])){
      $insertData[T::$tntTrans] = array_values($insertData[T::$tntTrans]);
    }
    
    $unitId                   = M::getUnit(Model::buildWhere(['prop'=>$vData['prop'],'unit'=>$vData['unit']]),['unit_id'])['unit_id'];
    
    $updateData = [
      T::$tntTrans=>[ 
        'whereData'=>['prop'=>$vData['prop'], 'unit'=>$vData['unit'],'tenant'=>$vData['tenant']], 
        'updateData'=>['appyto'=>DB::raw('cntl_no'), 'invoice'=>DB::raw('cntl_no')],
      ],
      T::$application=>[
        'whereData'=>['application_id'=>$id], 
        'updateData'=>['moved_in_status'=>1, 'tenant'=>$vData['tenant']]
      ],
      T::$unit=>[ 
        'whereData'=>['unit_id'=>$unitId], 
        'updateData'=>['curr_tenant'=>$vData['tenant'], 'usid'=>$usr, 'past_tenant'=>--$vData['tenant'], 'status'=>'C','move_in_date'=>$vData['move_in_date'],'move_out_date'=>'9999-12-31','rent_rate'=>$vData['base_rent']],
      ],
    ];
    # DEAL WITH DEPOSIT BILLING
    if(!empty($insertData[T::$tntTrans])){
      $billingDeposit = Helper::keyFieldName($insertData[T::$tntTrans], 'gl_acct', 'amount');
      $vData['billingDeposit'] = isset($billingDeposit['607']) ? $billingDeposit['607'] : 0;
    }
    
//    dd($insertData, $updateData);
    $successMsg = $this->_getSuccessMsg('store', $vData);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::insert($insertData);
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[
          T::$creditCheckView=>['a.application_id'=>[$id]],
          T::$tenantView=>['t.tenant_id'=>[$success['insert:tenant'][0]]],
          T::$unitView  =>['u.unit_id' => [$unitId]],
        ]
      ];
      if(!empty($success['insert:' . T::$tntTrans])) {
        $elastic['insert'][T::$tntTransView] = ['tt.cntl_no'=>$success['insert:' . T::$tntTrans]];
      }
      $response['msg'] = $successMsg;
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
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
      'edit'  =>[T::$application, T::$applicationInfo, T::$tenant, T::$tenantUtility],
      'store' =>[T::$application, T::$applicationInfo, T::$tenant, T::$tenantUtility, T::$service, T::$billing, T::$alterAddress, T::$memberTnt],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'edit'  =>['application_id','move_in_date', 'prop','unit', 'status', 'tenant', 'base_rent', 'dep_held1', 'water','gas','electricity','trash','sewer','landscape'],
      'store' =>[
        'application_id', 'status','tenant', 'base_rent', 'prop', 'unit', 'tenant', 'service_code','schedule','start_date', 'stop_date', 
        'move_in_date',  'water','gas','electricity','trash','sewer','landscape', 'amount', 'remark', 'dep_held1',
        'street','city','state','zip','first_name','last_name','phone_bis','phone_ext','relation','occupation'
      ],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = [],$req= [], $isSingleHouse = 0){
    $perm = Helper::getPermission($req);
//    dd($perm['creditCheckupdate']);
    $yesNo = [0=>'No', 1=>'Yes'];
    $singleHouseParam = $isSingleHouse ? ['value'=>1] : [];
    $baserentParam = isset($perm['creditCheckupdate']) ? [] : ['readonly'=>1];
    $setting = [
      'edit'=>[
        'field'=>[
          'application_id'=>['type'=>'hidden'],
          
          'water'=>['label'=>'Tnt Pay Water', 'type'=>'option', 'option'=>$yesNo] + $singleHouseParam,
          'gas'=>['label'=>'Tnt Pay Gas', 'type'=>'option', 'option'=>$yesNo] + $singleHouseParam,
          'electricity'=>['label'=>'Tnt Pay Elec.', 'type'=>'option', 'option'=>$yesNo] + $singleHouseParam,
          'trash'=>['label'=>'Tnt Pay Trash', 'type'=>'option', 'option'=>$yesNo] + $singleHouseParam,
          'sewer'=>['label'=>'Tnt Pay Sewer', 'type'=>'option', 'option'=>$yesNo] + $singleHouseParam,
          'landscape'=>['label'=>'Tnt Pay Lanscape', 'type'=>'option', 'option'=>$yesNo] + $singleHouseParam,
          
          'lease_start_date'=>['label'=>'Contract Sign Date','class'=>'date datepicker', 'value'=>date('m/d/Y')],
          'move_in_date'=>['label'=>'Move In Date','class'=>'date datepicker', 'value'=>date('m/d/Y')],
          'dep_held1'=>['label'=>'Deposit', 'readonly'=>1],
          'prop'=>['label'=>'Property', 'readonly'=>1],
          'new_rent'=>['readonly'=>1],
          'base_rent'=>$baserentParam + ['label'=>'Rents'],
          'unit'=>['readonly'=>1],
          'sec_deposit'=>['label'=>'Deposit', 'readonly'=>1],
          'sec_deposit_add'=>['label'=>'Deposit Addt.', 'readonly'=>1],
          'sec_deposit_note'=>['label'=>'Deposit Note', 'readonly'=>1],
          'status'=>['readonly'=>1],
          'tenant'=>['readonly'=>1],
        ]
      ],
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
      'edit'=>['submit'=>['id'=>'submit', 'value'=>'Move Tenant In', 'class'=>'col-sm-12']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER FUNCTIcd ON   #################################  
################################################################################  
  private function _getErrorMsg($name){
    $data = [
      'tenantAlert' =>Html::errMsg('There is a problem with the TenantAlert. Please contact administration!'),
      'mysqlError'  =>Html::mysqlError(),
      T::$alterAddress  =>Html::errMsg('Street, City, State, Zip must either be filled out or empty in TENANT ALTERNATIVE ADDRESS section.'),
      T::$memberTnt  =>Html::errMsg('First Name and Last Name must either be filled out or empty in OTHER MEMBER section.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $vData = []){
    $_storeMsg = function($vData){ 
      $html = Html::createTable([
        ['desc'=>['val'=>'Batch Number'],'batch'=>['val'=>$vData['batch']]],
        ['desc'=>['val'=>'Property'],'prop'=>['val'=>$vData['prop']]],
        ['desc'=>['val'=>'Unit'],'unit'=>['val'=>$vData['unit']]],
        ['desc'=>['val'=>'Tenant'],'tenant'=>['val'=>$vData['tenant']]],
        ['desc'=>['val'=>'Tenant Name'],'tnt_name'=>['val'=> title_case($vData['tnt_name'])]],
        ['desc'=>['val'=>'Rent'],'base_rent'=>['val'=>Format::usMoney($vData['base_rent'])]],
        ['desc'=>['val'=>'Security Deposit'],'dep_held1'=>['val'=>Format::usMoney(isset($vData['billingDeposit']) ? $vData['billingDeposit'] : $vData['dep_held1'])]],
        ['desc'=>['val'=>'Lease Date'],'lease_start_date'=>['val'=>Format::usDate($vData['lease_start_date'])]],
        ['desc'=>['val'=>'Move In Date'],'move_in_date'=>['val'=>Format::usDate($vData['move_in_date'])]],
      ], ['class'=>'table table-bordered'], 0, 0);
      return Html::sucMsg('Tenant is Successfully Moved In. Congratulation.') . $html;
    };
    
    $data = [
      'store'=>$_storeMsg($vData)
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getEmail($name, $req, $data = []){
    if($name == 'storeRentIncrease'){
      $application = Helper::getElasticResult(Elastic::searchQuery([
        'index'=>T::$creditCheckView, 
        'query'=>['must'=>['application_id'=>$data['vData']['application_id']]]
      ]), 1);
      $rProp  = M::getProp(Model::buildWhere(['prop'=>$data['vData']['prop']]));
      $amount = !empty($data['vData']['billing']['602M']['amount']) ? $data['vData']['billing']['602M']['amount'] : 0;
      $msg    = 'Hi Mike,<br>The followings tenant is moved in with the tenant paying the rent less than the base rent. Please take look.<br><br>';
      $msg .= Html::buildTable([
        'isHeader'=>0,
        'isOrderList'=>0,
        'tableParam'=>['border'=>1],
        'data'=>[
          ['col1' => ['val'=>'Person Who did the MoveIn:'],'col2' => ['val'=>Helper::getUsidName($req)]],
          ['col1' => ['val'=>'Prop:'],'col2' => ['val'=>$data['vData']['prop']]],
          ['col1' => ['val'=>'Unit:'],'col2' => ['val'=>$data['vData']['unit']]],
          ['col1' => ['val'=>'Tenant:'],'col2' => ['val'=>$data['vData']['tenant']]],
          ['col1' => ['val'=>'Address:'],'col2' => ['val'=>title_case($rProp['street'])]],
          ['col1' => ['val'=>'City:'],'col2' => ['val'=>title_case($rProp['city'])]],
          ['col1' => ['val'=>'Old Rent:'],'col2' => ['val'=>Format::usMoney($application['_source']['old_rent'])]],
          ['col1' => ['val'=>'New Base Rent:'],'col2' => ['val'=>Format::usMoney($data['vData']['base_rent'])]],
          ['col1' => ['val'=>'New Tenant Pays Rent Only:'],'col2' => ['val'=>Format::usMoney($amount), 'param'=>['style'=>'color:red;font-weight:bold;']]],
          ['col1' => ['val'=>'Deposit:'],'col2' => ['val'=>Format::usMoney($data['vData']['dep_held1'])]],
        ]
      ]);
      Mail::send([
        'to'=>'mike@pamamgt.com,sean@pamamgt.com,luciano@ierentalhomes.com,ryan@pamamgt.com',
        'from'=>'admin@pamamgt.com',
        'subject' =>'Rent Decrease During Move In on ' . date("F j, Y, g:i a"),
        'msg'=>$msg
      ]);
    }
  }
//------------------------------------------------------------------------------
  public static function _getProrateAmount($r){
    $amount = $r['new_rent'];
    $numDay = 30;
    $prorateDay = Helper::getDateDifference(date('Y-m-d'), date('Y-m-t'));
    $prorate = ($prorateDay >= 30) ? $amount : ($prorateDay * $amount / $numDay);
    return [
      'prorate'       => Format::floatNumberSeperate($prorate),
      'proratePerDay' => Format::usMoney($amount / $numDay),
      'prorateDay'    => Format::floatNumberSeperate($prorateDay),
      'rent'          => Format::floatNumberSeperate($amount),
      'totalDeposit'  => Format::floatNumberSeperate($r['sec_deposit'] + $r['sec_deposit_add'])
    ];
  }
//------------------------------------------------------------------------------
  private function _getTenantAlternativeField(){
    $html = '';
    $field = RuleField::generateRuleField(['tablez'=>[T::$alterAddress]])['field'];
    $selectedField = ['street', 'city', 'state', 'zip'];
    foreach($selectedField as $fl){
      $field[$fl]['includeLabel'] = 0;
      $col = 3;
      $header = Html::tag('h4', title_case($fl));
      $html .= Html::div($header . Form::getField($field[$fl]),['class'=>'col-md-' . $col]);
    }
    return $html;
  }
//------------------------------------------------------------------------------
  private function _getOtherMemberField($rApplicant){
    $data = ['html'=>'', 'otherMember'=>''];
//    $_getHtml = function(){
//      $field = RuleField::generateRuleField(['tablez'=>[T::$memberTnt], 'isKeyArray'=>1])['field'];
//      $selectedField = ['first_name', 'last_name', 'phone_bis', 'phone_ext', 'relation', 'occupation'];
//      
//    };
    
    $field = RuleField::generateRuleField(['tablez'=>[T::$memberTnt], 'isKeyArray'=>1])['field'];
    $selectedField = ['first_name', 'last_name', 'phone_bis', 'phone_ext', 'relation', 'occupation'];
    
    if(count($rApplicant) > 1){
      unset($rApplicant[0]);
      $rApplicant = array_values($rApplicant);
      foreach($rApplicant as $i=>$val){
        $val['first_name'] = title_case($val['fname']);
        $val['last_name'] = title_case($val['lname']);
        foreach($selectedField as $fl){
          $label = title_case(preg_replace_array('/_|bis/', [' ', ' '], $fl));
          $field[$fl]['includeLabel'] = 0;
          $field[$fl]['placeholder'] = $label;
          $field[$fl]['value'] = isset($val[$fl]) ? $val[$fl] : '';
          $field[$fl]['id'] = $fl . '['.$i.']';
          $col = ($fl == 'street' || $fl == 'name') ? 3 : 2;
         
          $header = ($i == 0) ? Html::tag('h4', $label) : '';
          $data['html'] .= Html::div($header . Form::getField($field[$fl]),['class'=>'col-md-' . $col]);
          
          $field[$fl]['value'] = '';
          $data['otherMember'] .= ($i == 0) ? Html::div(Form::getField($field[$fl]),['class'=>'col-md-' . $col]) : '';
        }
      }
    } else{
      foreach($selectedField as $fl){
        $label = title_case(preg_replace_array('/_|bis/', [' ', ' '], $fl));
        $field[$fl]['includeLabel'] = 0;
        $field[$fl]['placeholder'] = $label;
        $col = ($fl == 'street' || $fl == 'name') ? 3 : 2;
        $header = Html::tag('h4', $label);
        $data['html'] .= Html::div($header . Form::getField($field[$fl]),['class'=>'col-md-' . $col]);
        $data['otherMember'] .= Html::div(Form::getField($field[$fl]),['class'=>'col-md-' . $col]);
      }
    }
    
    $data['html']        = Html::div($data['html'] , ['class'=>'row']);
    $data['otherMember'] = Html::div($data['otherMember'] , ['class'=>'row otherMemberEmptyField', 'data-key'=>0]);
    $data['otherMemberIndex'] = count($rApplicant) - 1;
    
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
  private function _isInBilling($v){
    return ($v['schedule'] =='M' || ($v['stop_date'] == '9999-12-31' && $v['gl_acct'] != '607') || trim($v['stop_date']) == date('Y-m-d', strtotime('last day of next month')));
  }
}
