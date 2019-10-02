<?php
namespace App\Http\Controllers\PropertyManagement\RentRaise\PastRentRaiseNotice;
use Illuminate\Http\Request;
use App\Library\{V, TableName AS T, RentRaiseNotice,Helper};
use App\Http\Controllers\Controller;
use App\Http\Models\{Model, RentRaiseModel as M};

class PastRentRaiseNoticeController extends Controller {
  public function __construct(){
      
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid  = V::startValidate([
      'rawReq'           => $req->all(),
      'tablez'           => $this->_getTable(__FUNCTION__),
      'orderField'       => $this->_getOrderField(__FUNCTION__),
      'setting'          => $this->_getSetting(__FUNCTION__),
      'includeCdate'     => 0,
      'validateDatabase' => [
        'mustExist'      => [
          T::$tenant . '|tenant_id',
          T::$rentRaise . '|rent_raise_id',
        ]
      ]
    ]);    
    
    $vData        = $valid['data'];
    $rRentRaise   = Helper::getElasticResultSource(M::getRentRaiseElastic(['tenant_id'=>$vData['tenant_id']],[],0));
    return RentRaiseNotice::getPdf($rRentRaise,true,0,$vData['rent_raise_id'],true);
  }
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $tablez  = [
      'index'  => [T::$tenant,T::$rentRaise],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField  = [
      'index'   => ['tenant_id','rent_raise_id'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $setting  = [
      'index'  => [
        'field' => [
          
        ],
        'rule'  => [
          'tenant_id'      => 'required|integer',
          'rent_raise_id'  => 'required|integer',
        ]
      ]
    ];
    return $setting[$fn];
  }
}