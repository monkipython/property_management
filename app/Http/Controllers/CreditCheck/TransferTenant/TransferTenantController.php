<?php
namespace App\Http\Controllers\CreditCheck\TransferTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{RuleField, V, Form, Elastic, Mail, File, Html, Helper,HelperMysql, Auth, GridData, Upload, TenantAlert, GlobalVariable, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class
use PDF;

class TransferTenantController extends Controller{
  private $_viewPath = 'app/CreditCheck/transferTenant/';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
  }
//------------------------------------------------------------------------------
  public function edit($id){
    $page = $this->_viewPath . 'edit';
    
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
    
    $fromForm = Form::generateForm([
      'tablez'    =>$this->_getTable('editFrom'), 
      'orderField'=>$this->_getOrderField('editFrom'), 
      'setting'   =>$this->_getSetting('editFrom', $r)
    ]);
    
    unset($r['prop'], $r['unit']);
    $toForm = Form::generateForm([
      'tablez'    =>$this->_getTable('editTo'), 
      'button'    =>$this->_getButton('editTo'), 
      'orderField'=>$this->_getOrderField('editTo'), 
      'setting'   =>$this->_getSetting('editTo', $r)
    ]);
    $toFrom = [];
    
    return view($page, ['data'=>['from'=>$fromForm, 'to'=>$toForm]])->render();
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['application_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'      =>$this->_getSetting(__FUNCTION__),
      'includeCdate'=>0,
      'validateDatabase'=>[
        'mustExist'=>[
          'application|application_id',
          'unit|prop,unit'
        ]
      ]
    ]);
    
    $vData        = $valid['dataNonArr'];
    $rApp         = M::getApplication(Model::buildWhere(['a.application_id'=>$id]), 1);
    $updateInfo   = 'Transfer from: ' . $rApp['prop'] . '-' . $rApp['unit'] . '-' . $rApp['tenant'];
    
    ### OLD DATA ###
    $oldTrust     = M::getPropBank(Model::buildWhere(['prop'=>$rApp['prop']]))['trust'];
    $oldWhereData = ['prop'=>$rApp['prop'],'unit'=>$rApp['unit'],'tenant'=>$rApp['tenant']];
    $oldPropUnit  = ['prop'=>$rApp['prop'],'unit'=>$rApp['unit']];
    $oldTenant    = $rApp['tenant'];
    ### NEW DATA ###
    $rNewTenant   = M::getTenant(Model::buildWhere(['prop'=>$vData['prop'], 'unit'=>$vData['unit']]));
    $newTrust     = M::getPropBank(Model::buildWhere(['prop'=>$vData['prop']]))['trust'];
    $newPropUnit  = ['prop'=>$vData['prop'], 'unit'=>$vData['unit']];
    $newPastTenant= $rNewTenant['tenant'];
    $newTenant    = ($newPastTenant + 1);
    $newRent      = $vData['new_rent']; 
    $newDeposit   = ($vData['sec_deposit'] + $vData['sec_deposit_add']); 
        
    $newWhereData = ['prop'=>$vData['prop'],'unit'=>$vData['unit'],'tenant'=>$newTenant];
    $rTntTrans    = M::getTntTrans(Model::buildWhere($oldWhereData));
    $rGlTrans     = M::getGlTrans(Model::buildWhere($oldWhereData));
    $cntlNo       = array_values(Helper::keyFieldName($rTntTrans, 'cntl_no', 'cntl_no'));
    $rTmpTrans    = Helper::keyFieldName($rTntTrans, 'tx_code');
    
    // Get Unit ID for both old and new data Will be used for elastic
    $rUnitOld = M::getUnit(Model::buildWhere($oldPropUnit), ['unit_id', 'status']);
    $rUnitNew = M::getUnit(Model::buildWhere($newPropUnit), ['unit_id', 'status']);
    
    
    // The payment is already made and the prop is not the same. we won't let them to go throw
    if(!empty($rTmpTrans['P']) && $oldTrust != $newTrust){
      Helper::echoJsonError($this->_getErrorMsg('updatePaymentExist', $vData));
    } else if($vData['prop'] == $rApp['prop'] && $vData['unit'] == $rApp['unit']){
      Helper::echoJsonError($this->_getErrorMsg('updateSamePropUnit', $vData));
    } else if(empty($rTntTrans)){
      Helper::echoJsonError($this->_getErrorMsg('updateNotYetMovein', $vData));
    } else if($rUnitNew['status'] == 'C'){
      Helper::echoJsonError($this->_getErrorMsg('updateUnitNotVacant', $vData));
    }

    ### DEAL WITH TNT_TRAS ###
    $updateDataSet = [
      T::$tntTrans        =>['whereData'=>$oldWhereData,'updateData'=>$newWhereData],
      T::$application     =>['whereData'=>$oldWhereData,'updateData'=>$newWhereData + ['new_rent'=>$newRent, 'sec_deposit'=>$vData['sec_deposit'], 'sec_deposit_add'=>$vData['sec_deposit_add']]],
      T::$applicationInfo =>['whereData'=>['application_id'=>$id],'updateData'=>['prop'=>$vData['prop']]],
      T::$tenant          =>['whereData'=>$oldWhereData,'updateData'=>$newWhereData + ['base_rent'=>$newRent, 'dep_held1'=>$newDeposit, 'web'=>$updateInfo ]],
      T::$billing         =>['whereData'=>$oldWhereData,'updateData'=>$newWhereData],
      T::$memberTnt       =>['whereData'=>$oldWhereData,'updateData'=>$newWhereData],
      T::$alterAddress    =>['whereData'=>$oldWhereData,'updateData'=>$newWhereData],
      T::$unit            =>[
        ['whereData'=>$oldPropUnit, 'updateData'=>['status'=>'V', 'past_tenant'=>$oldTenant]],
        ['whereData'=>$newPropUnit, 'updateData'=>['status'=>'C', 'curr_tenant'=>$newTenant, 'past_tenant'=>!empty($newPastTenant) ? $newPastTenant : 255]]
      ],
    ];
    if(!empty($rGlTrans)){
      $updateDataSet[T::$glTrans] = ['whereData'=>$oldWhereData,'updateData'=>$newWhereData];
    }
    
    # IF ANYTHING WRONG STOP HERE AND PRINT OUT THE ERROR
    Helper::checkErrorExit($vData);
    ################ DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateDataSet);
      $rTenant = M::getTenant(['prop'=>$vData['prop'], 'unit'=>$vData['unit'], 'tenant'=>$newTenant]);
      $elastic = [
        'insert'=>[
          T::$creditCheckView=>['a.application_id'=>[$id]],
          T::$unitView       =>['u.unit_id'=>[$rUnitOld['unit_id'], $rUnitNew['unit_id']]],
          T::$tenantView     =>['t.tenant_id'=>[$rTenant['tenant_id']]],
          T::$tntTransView   =>['tt.cntl_no'=>$cntlNo],
        ]
      ];
      
      $vData['oldWhereData'] = $oldWhereData;
      $vData['newWhereData'] = $newWhereData;
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__, $vData);
      
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
  public function create(){
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
  }  

################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'editFrom'   =>[T::$application, T::$applicationInfo, T::$tenant],
      'editTo'     =>[T::$application, T::$applicationInfo, T::$tenant],
      'update'     =>[T::$application, T::$applicationInfo, T::$tenant],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'editFrom'     =>['prop', 'unit', 'tenant',  'new_rent', 'sec_deposit', 'sec_deposit_add', 'fname', 'mname', 'lname', ],
      'editTo'     =>['prop', 'unit',  'new_rent', 'sec_deposit', 'sec_deposit_add','fname', 'mname', 'lname', ],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $rUnit = !empty($default['prop']) ? Helper::keyFieldName(M::getUnit(Model::buildWhere(['prop'=>$default['prop']]), ['unit'], 0), 'unit', 'unit') : [''=>'Select Unit'];
 
    $setting = [
      'editFrom'=>[
        'field'=>[
          'fname' =>['id'=>'disabledfname','label'=>'First Name', 'disabled'=>1, 'req'=>0],
          'mname' =>['id'=>'disabledmname','label'=>'Middle Name', 'disabled'=>1, 'req'=>0],
          'lname' =>['id'=>'disabledlname','label'=>'Last Name', 'disabled'=>1, 'req'=>0],
          'prop'=>['id'=>'disabledProp', 'label'=>'Property', 'disabled'=>1, 'req'=>0],
          'unit'=>['id'=>'disabledUnit', 'disabled'=>1, 'req'=>0],
          'tenant'=>['id'=>'disabledUnit', 'disabled'=>1, 'req'=>0],
          'new_rent'=>['id'=>'disablednew_rent','disabled'=>1, 'req'=>0],
          'sec_deposit'=>['id'=>'disabledsec_deposit','label'=>'Deposit', 'disabled'=>1, 'req'=>0],
          'sec_deposit_add'    =>['id'=>'disabledsec_deposit_add','label'=>'Deposit Add', 'disabled'=>1, 'req'=>0],
        ]
      ],
      'editTo'=>[
        'field'=>[
          'fname' =>['label'=>'First Name', 'disabled'=>1, 'req'=>0],
          'mname' =>['label'=>'Middle Name', 'disabled'=>1, 'req'=>0],
          'lname' =>['label'=>'Last Name', 'disabled'=>1, 'req'=>0],
          'unit'=>['type'=>'option', 'option'=>$rUnit],
          'prop'=>['label'=>'Property', 'class'=>'autocomplete'],
          'sec_deposit'    =>['label'=>'Deposit'],
          'sec_deposit_add'    =>['label'=>'Deposit Add', 'req'=>0],
        ],
        'rule'=>[
          'sec_deposit_add' =>'nullable|numeric|between:0,65000',
        ]
      ],
      'update'=>[
        'rule'=>[
          'sec_deposit_add' =>'nullable|numeric|between:0,65000',
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
      'editTo'=>['submit'=>['id'=>'submit', 'value'=>'Transfer Tenant', 'class'=>'col-sm-12']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER SECTION  ###################################  
################################################################################
  private function _getErrorMsg($name, $vData){
    $data = [
      'updatePaymentExist'  =>Html::errMsg('The payment was already made for this tenant. You can only transfer the same Trust if the payment is already made.'),
      'updateSamePropUnit'  =>Html::errMsg('The current Property and Unit is the same as the Transfer ones.'),
      'updateNotYetMovein'  =>Html::errMsg('This application is not yet moved in. Please make sure it moves in first before it can transfer.'),
      'updateUnitNotVacant' =>Html::errMsg('The Unit ' . $vData['unit'] . ' is not vacant. Please make sure the unit is vacant before you transfer.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $vData = []){
    $data = [
      'update'=>Html::sucMsg('Successfully Transfered Tenant From ' . implode('-', $vData['oldWhereData']) . ' To ' . implode('-', $vData['newWhereData']))
    ];
    return $data[$name];
  }
}