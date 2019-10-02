<?php
namespace App\Http\Controllers\AccountPayable\Maintenance\ResetControlUnit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Form, Html, Helper, V, TableName AS T};
use Illuminate\Support\Facades\DB;
use App\Http\Models\{Model,AccountPayableModel AS M}; // Include the models class
class MaintenanceResetControlUnitController extends Controller{
  private $_viewTable = '';
  private $_viewPath  = 'app/AccountPayable/Maintenance/resetControlUnit/';
  
  public function __construct(){
    $this->_viewTable  = T::$vendorMaintenanceView;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page     = $this->_viewPath . 'create';
    $form     = implode('',Form::generateField($this->_getFields()));
    
    return view($page,[
        'data'  => [
          'resetForm'  => $form
        ]
     ]);

  }
//------------------------------------------------------------------------------
  public function store(Request $req){  
      $valid = V::startValidate([
        'rawReq'        => $req->all(),
        'rule'          => $this->_getRule(),
        'includeCdate'  => 0,
      ]);
    
    $vData      = $valid['data'];
    
    $usid       = Helper::getUsid($req);
    $vendidArr  = $this->_explodeFieldInput($vData['vendid']);
    $must       = !empty($vendidArr) ? ['vendid.keyword'=>$vendidArr] : [];
    $r          = Helper::getElasticResult(M::getMaintenanceElastic($must,['vendor_maintenance_id','vendid']));
    
    $ids        = [];
    foreach($r as $i => $v){
      $source = $v['_source'];
      $ids[]  = $source['vendor_maintenance_id'];
    }
    
    $updateData = [T::$vendorMaintenance=>['whereInData'=>['field'=>'vendor_maintenance_id','data'=>$ids],'updateData'=>['usid'=>$usid,'control_unit'=>0]]];
    ############### DATABASE SECTION ######################
    $response = $success = $elastic = [];
    try {
      if(!empty($ids)){
        DB::beginTransaction();
        $success   += Model::update($updateData);
        $elastic    = ['insert'=>[T::$vendorMaintenanceView=>['vm.vendor_maintenance_id'=>$ids]]];
      
        Model::commit([
          'success' => $success,
          'elastic' => $elastic,
        ]);
        $response['msg']              = $this->_getSuccessMsg(__FUNCTION__,$ids);
      } else {
        $response['msg']              = $this->_getErrorMsg(__FUNCTION__);
      } 
    } catch(\Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _getFields(){
    return [
      'vendid'   => ['id'=>'vendid','type'=>'textarea','label'=>'Vendid','value'=>'ALL','req'=>1],
    ];
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'vendid'   => 'required|string',
    ];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$ids=[]){
    $num  = !empty($ids) ? count($ids) : 1;
    $data = [
      'store'  => Html::sucMsg($num . ' Maintenance(s) were reset'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store'  => Html::errMsg('Error resetting control units, please try again'),
    ];
    return $data[$name];
  }
################################################################################
############################ HELPER FUNCTIONS ##################################
################################################################################
//------------------------------------------------------------------------------
  private function _explodeFieldInput($inputStr,$allToken='all'){
    return trim(strtolower($inputStr)) !== $allToken ? explode(',',preg_replace('/\s+|\n+/','',$inputStr)) : [];
  }
}
