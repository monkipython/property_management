<?php
namespace App\Http\Controllers\Filter;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Library\{RuleField};
use App\Library\{RuleField, V, Form, Elastic, Mail, Html, Helper, Auth, TableName AS T};
use App\Http\Models\Autocomplete AS M; // Include the models class
use Illuminate\Support\Facades\DB;

class OptionFilterController extends Controller{
  private $_data	  = [];
  private $_fieldData = [];
  private $_viewPath  = 'app/creditCheck/';
  private $_viewGlobalAjax  = 'global/ajax';
  private $_viewGlobalHtml  = 'global/html';
  private static $_instance;
  
  public function __construct(){
  }
//------------------------------------------------------------------------------  
/**
 * @desc this getInstance is important because to activate __contract we need to call getInstance() first
 */
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }  
//------------------------------------------------------------------------------
  public function show($id){
    list($key, $index) = explode(':', $id);
    return json_encode($this->getOptionFilter($key, $index));
  }
//------------------------------------------------------------------------------
  public function getOptionFilter($id, $index, $isIncludeCount = 1){
    $response = [];
    $map      = $this->_getMap($id);
    $r        = Elastic::search(['index'=>$index, 'type'=>$index, 'size'=>0, 'body'=>['aggs'=>$this->_getAggs($index,$id)]]);
    $buckets  = $r['aggregations']['groupBy']['buckets'];
    foreach($buckets as $v){
      $label = isset($map[$v['key']]) ? $map[$v['key']] : $v['key'];
      $response[$v['key']] =  $label . ($isIncludeCount ? ' ('. $v['doc_count'] .')' : '');
    }
    return $response;
  } 
//------------------------------------------------------------------------------
  public function getOptionFilterDB($table, $groupByField, $keyField, $valueField){
    $response = [];
    $r = DB::table($table)->groupBy($groupByField)->get()->toArray();
    foreach($r as $v){
      $response[$v[$keyField]] =  $v[$valueField];
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getAggs($index,$id){
    $cls  = '\App\Console\InsertToElastic\ViewTableClass\\' . $index;
    $field = RuleField::generateRuleField(['tablez'=>$cls::getTableOfView()])['field'];
    if(preg_match('/\./', $id)){
      list($table, $key) = explode('.', $id);
      $type = $field[$key]['class'];
    } else{
      $type = $field[$id]['class'];
    }
    
    $data = [
      'groupBy' => [ 
        'terms' => [
          'field'=> ($type == 'integer' || $type == 'decimal') ? $id : $id . '.keyword',
        ]
      ]
    ];
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getMap($id){
    $map = [
      'prop_type'=>['A'=>'Apartment', 'C'=>'Condo', 'H'=>'House', 'I'=>'Inventory', 'M'=>'Mobile Home', 'N'=>'Convalescent Home', 'O'=>'Office', 'S'=>'Shopping Center', 'W'=>'Warehouse', '?'=>'Other'],
      'prop_class'=>[''=>'', 'A'=>'Accounting', 'C'=>'Consolidation', 'D'=>'Default', 'L'=>'Land', 'M'=>'Management', 'P'=>'Property', 'G'=>'Group','T'=>'Trust','X'=>'Inactive' ],
      'application.app_fee_recieved'=>[0=>'No', 1=>'Yes'],
      'moved_in_status'=>[0=>'No Completed', 1=>'Completed'],
      'status' => [''=>'Wrong Input','C'=>'Current','V'=>'Vacant'],
      'is_rent_raise_completed' => [0=>'No','1'=>'Yes'],
      'is_printed' => ['0'=>'Ready to Print','1'=>'Printed'],
      'unit_type' => ['A'=>'Apartment','B'=>'Commercial','C'=>'Condo','DW'=>'Double Wide','G'=>'Garage','H'=>'House','I'=>'Industrial','L'=>'Laundry', 'M'=>'Mobile Home','O'=>'Office','P'=>'Parking','PM'=>'Park Model','Q'=>'Space', 'S'=>'Storage','SW'=>'Single Wide', 'R'=>'Studio','T'=>'Trailer/RV','W'=>'Warehouse'],
    ];
    return !empty($map[$id]) ? $map[$id] : [];
  }
}