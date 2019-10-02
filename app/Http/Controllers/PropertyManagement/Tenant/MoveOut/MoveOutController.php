<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\MoveOut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Html, Form, Helper, HelperMysql, Mail, Format, TableName AS T};
use App\Http\Models\{Model, TenantModel AS M}; // Include the models class

class MoveOutController extends Controller{
  private $_viewPath = 'app/PropertyManagement/Tenant/moveOut/';
  
  public function __construct(Request $req){
    $this->_mappingTntMoveOutProcess = Helper::getMapping(['tableName'=>T::$tntMoveOutProcess]);
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    
    $formMoveOut = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $id),
      'button'    =>$this->_getButton(__FUNCTION__),
    ]);
    return view($page, [
      'data'=>[
        'formMoveOut' => $formMoveOut
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => $this->_getOrderField(__FUNCTION__),
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'   =>[
          T::$tenant . '|tenant_id'
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    $usr     = $vData['usid'];
    $rTenant = HelperMysql::getTenant(['tenant_id'=>$vData['tenant_id']]);
    ## Make sure tenant doesnt exists in the tnt_move_out_process
    $valid['data']['prop']   = $rTenant['prop'];
    $valid['data']['unit']   = $rTenant['unit'];
    $valid['data']['tenant'] = $rTenant['tenant'];
    V::validateionDatabase(['mustNotExist'=>[T::$tntMoveOutProcess.'|prop,unit,tenant']], $valid);
    $rUnit   = HelperMysql::getUnit(['prop.prop.keyword'=>$rTenant['prop'], 'unit.keyword'=>$rTenant['unit']], ['unit_id']);
    $today       = strtotime(Helper::date());
    $moveOutDate = strtotime($vData['move_out_date']);

    if(empty($rUnit)) {
      Helper::echoJsonError($this->_getErrorMsg('noUnit', $rTenant));
    }
    
    $futureTenant = HelperMysql::getTenant(['prop.keyword'=>$rTenant['prop'], 'unit.keyword'=>$rTenant['unit'], 'status.keyword'=>'F']);
    $futureMoveindate = !empty($futureTenant) ? strtotime($futureTenant['move_in_date']) : [];
    $tntMoveOutProcess[T::$tntMoveOutProcess] = [
      'prop'   => $rTenant['prop'],
      'unit'   => $rTenant['unit'],
      'tenant' => $rTenant['tenant'],
      'status' => 0,
      'cdate'  => $vData['cdate']
    ];
    $insertData = HelperMysql::getDataSet($tntMoveOutProcess, $usr);
    $tenantId[] = $vData['tenant_id'];
    $updateData = [
      T::$tenant => [[
        'whereData' =>['tenant_id'=>$vData['tenant_id']], 
        'updateData'=>['status'=> $today < $moveOutDate ? 'C' : 'P', 'move_out_date'=>$vData['move_out_date'], 'usid'=>$usr]
      ]],
      T::$unit => [ 
        'whereData' =>['unit_id'=>$rUnit['unit_id']], 
        'updateData'=>['status'=> $today < $moveOutDate ? 'C' : 'V', 'move_out_date'=>$vData['move_out_date'], 'usid'=>$usr],
      ]
    ];
    ## Change unit past_tenant if move_out_date = today
    if($today == $moveOutDate) {
      $updateData[T::$unit]['updateData']['past_tenant'] = $rTenant['tenant'];
    }
    ## Only change future tenant if future move in date is equal to today
    if(!empty($futureTenant) && $futureMoveindate == $today) {
      $updateData[T::$tenant][] = [
        'whereData' =>['tenant_id'=>$futureTenant['tenant_id']], 
        'updateData'=>['status'=>'C', 'usid'=>$usr]
      ];
      $updateData[T::$unit] = [
        'whereData' => ['unit_id'=>$rUnit['unit_id']],
        'updateData'=> ['past_tenant' => $rTenant['tenant'], 'curr_tenant'=>$futureTenant['tenant'],'move_out_date'=>'9999-12-31', 'usid' => $usr]
      ]; 
      $tenantId[] = $futureTenant['tenant_id'];
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic1 = $elastic2 = $response = [];
    try{
      $success += Model::insert($insertData);
      $success += Model::update($updateData);
      $elastic1 = [
        'insert'=>[
          T::$tenantView => ['tenant_id' => $tenantId],
          T::$unitView   => ['unit_id'   => $success['update:'.T::$unit]]
        ]
      ];
      $elastic2 = [
        'insert'=>[
          T::$tntMoveOutProcessView => ['tnt_move_out_process_id' => $success['insert:'.T::$tntMoveOutProcess]]
        ] 
      ];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__, $rTenant);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic1,
      ]);
      Model::commit([
        'success' =>$success,
        'elastic' => $elastic2 
      ]);
//      $this->sendEmail(['action'=>'Moved Out Tenant with Rent Change', 'data' => ['new_rent'=>Format::usMoney($vData['rent_rate']), 'move_out_date'=>Format::usDate($vData['move_out_date'])], 'prevData'=>['prop'=>$rTenant['prop'], 'unit'=>$rTenant['unit'], 'tenant'=>$rTenant['tenant'], 'tnt_name'=>$rTenant['tnt_name'], 'old_rent'=>Format::usMoney($rUnit['rent_rate'])]], $usrName);
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
      'edit'  => [T::$tenant, T::$tntMoveOutProcess],
      'store' => [T::$tenant, T::$tntMoveOutProcess]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'edit'  => [
        'tenant_id', 'move_out_date'
      ],
      'store' => [
        'tenant_id', 'move_out_date'
      ]
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $id){
    $setting = [
      'edit' => [
        'field' => [
          'tenant_id'     => ['type'=>'hidden'],
          'move_out_date' => ['value'=>Helper::usDate()]
        ],
      ]
    ];
 
    if(!empty($id)){
      $setting[$fn]['field']['tenant_id']['value'] = $id;
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn){
    $buttton = [
      'edit'  => ['submit'=>['id'=>'submit', 'value'=>'Move Out Tenant', 'class'=>'col-sm-12']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name, $tenant){
    $data = [
      'store' => Html::sucMsg(Html::a('Tenant was Moved Out Successfully.', ['href' => action('PropertyManagement\TenantMoveOutProcess\TenantMoveOutProcessController@index', ['prop'=> $tenant['prop'], 'unit'=>$tenant['unit'], 'tenant'=>$tenant['tenant']]), 'target'=>'_blank']))
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []) {
    $data = [
      'noUnit' => Html::errMsg('Property ' . $vData['prop'] . ' and Unit ' . $vData['unit'] . ' does not exist for this tenant.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
//  private function sendEmail($data, $usrName) {
//    $action      = $data['action'];
//    $newData     = isset($data['data']) ? $data['data'] : [];
//    $prevData    = isset($data['prevData']) ? $data['prevData'] : [];
//    $bodyMsg     = 'New Data: <br>';
//    $prevBodyMsg = '';
//    foreach($newData as $field => $value) {
//      $bodyMsg .= title_case($field) . ': ' . $value . '<br>';
//    }
//    foreach($prevData as $field => $value) {
//      $prevBodyMsg .= title_case($field) . ': ' . $value . '<br>';
//    }
//    $bodyMsg .=  !empty($prevBodyMsg) ? '<hr>Previous Data: <br>'.$prevBodyMsg : '';
//    Mail::send([
//      'to'      => 'nevin@pamamgt.com,sean@pamamgt.com,jimmy@pamamgt.com,ryan@pamamgt.com,luciano@pamamgt.com,mike@pamamgt.com,everet@pamamgt.com',
//      'from'    => 'admin@pamamgt.com',
//      'subject' => $action . ' By '.$usrName.' on ' . date("m/d/Y, g:i a"),
//      'msg'     => $usrName . ' ' . $action . ' on ' . date("m/d/Y, g:i a") . ': <br><hr>' . $bodyMsg
//    ]);
//  }
}
