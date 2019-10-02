<?php
namespace App\Http\Controllers\PropertyManagement\RentRaise\Autocomplete;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, Elastic, TableName AS T,Html, Helper};

class RentRaiseTenantInfoController extends Controller{
  private static $_instance;
  /**
  * @desc this getInstance is important because to activate __contract we need to call getInstance() first
  */
  public function __construct(){
      
  }
  
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }
//------------------------------------------------------------------------------  
  public function index(Request $req){
    $valid   = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$tenant], 
      'orderField'      => ['prop', 'unit'], 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          T::$unit . '|prop,unit',
        ]
      ]
    ]);
    $vData       = $valid['data'];
    $selected    = '';
    $rTenant     = Helper::getElasticResult(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['prop','unit','tenant','tnt_name','base_rent','status','billing'],
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      'size'     => 1,
      'query'    => [
        'must' => Helper::selectData(['prop','unit'],$vData) + ['status.keyword'=>'C'],
      ]
    ]),1);
    
    $source      = !empty($rTenant['_source']) ? $rTenant['_source'] : [];
    $optionMap   = !empty($source) ? Html::tag('option',$source['tenant'] . ' - (' . $source['status'] . ' ) ' . title_case($source['tnt_name']),['value'=>$source['tenant']]) : '';
    $rent        = !empty($source) ? Helper::getRentFromBilling($source) : 0;
    return ['options'=>$optionMap,'rent'=>$rent];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
}

