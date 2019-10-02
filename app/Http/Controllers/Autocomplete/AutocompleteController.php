<?php
namespace App\Http\Controllers\Autocomplete;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Library\{RuleField};
use \Elasticsearch;
use App\Library\{RuleField, V, Form, GlobalRuleField, GlobalVariable, Mail, Html, Helper, Auth};
use App\Http\Models\AutocompleteModel AS M; // Include the models class


class AutocompleteController extends Controller{
  private $_data	  = [];
  private $_fieldData = [];
  private $_viewPath  = 'app/creditCheck/';
  private $_viewGlobalAjax  = 'global/ajax';
  private $_viewGlobalHtml  = 'global/html';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $noResult = [['value'=>'No Result', 'data'=>'']];
    $method = preg_replace('/^to|_code\[[0-9]+\]|\[[0-9]+\]/', '', $id);
    preg_match('~\[([0-9]+)+\]~', $id, $match);
    $data['arrayIndex'] = isset($match[1]) ? preg_replace('/(\[|\])/', '\\\\$1',$match[1])  : '';
    
    try{
      $vData    = [
        'query' =>!empty($req['query']) ? $req['query'] : '',
        'prop'  =>!empty($req['prop']) && !preg_match('/\-/', $req['prop']) ? $req['prop'] : 'Z64'
      ];

      $response = [['value'=>'No Result', 'data'=>'']];
      $response = $this->$method($vData, $data);
      $response = !empty($response[0]) ? $response : $noResult;
    }
    catch (Exception $e) {
      $response = $noResult;
    }
    return json_encode(['suggestions'=>$response] );
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function prop($vData, $data = []){
    return M::prop(['query'=>$vData['query']], '
      CONCAT(p.prop, " (Trust: ", pb.trust, ") ", p.street, ", ", p.city, ", ", p.state) AS value, p.prop AS data, 
      GROUP_CONCAT(unit,"~",CONCAT(unit," - ",u.street)  SEPARATOR "|") AS list_unit,
      GROUP_CONCAT(unit,"~",CONCAT(u.rent_rate," - ",u.sec_dep)  SEPARATOR "|") AS detail_unit,
      CONCAT(p.street, ", ", p.city, ", ", p.state) AS append_prop');
//          GROUP_CONCAT(DISTINCT b.bank,"~",CONCAT(b.bank, " - ", b.name) SEPARATOR "|") AS list_bank,
  }
//------------------------------------------------------------------------------
  private function unit($vData, $data = []){
    return M::unit($vData, 
//        'CONCAT(u.unit, " - ", u.street) AS value,u.unit AS data');
    
      'CONCAT(u.unit, " - ", u.street) AS value,u.unit AS data, 
      GROUP_CONCAT(DISTINCT t.tenant,"~",CONCAT(t.tenant," - ",t.status)  SEPARATOR "|") AS list_tenant');

  }
//------------------------------------------------------------------------------
  private function trust($vData, $data = []){
    return M::trust(['query'=>$vData['query']], '
      CONCAT(p.trust, " (Prop: ", p.prop, ") ") AS value,p.trust AS data,
      GROUP_CONCAT(DISTINCT p.prop,"~",p.prop  SEPARATOR "|") AS list_prop');
  }
//------------------------------------------------------------------------------
  private function group1($vData, $data = []){
    return M::group(['query'=>$vData['query']], 'p.group1 AS value, p.group1 AS data');
  }
//------------------------------------------------------------------------------
  private function vendid($vData, $data = []) {
    return M::vendor(['query'=>$vData['query']], '
      CONCAT(v.vendid, " (Vendor Name: ", v.name, ") ") AS value,v.vendid AS data, 
      v.vendor_id AS field_vendor_id,
      GROUP_CONCAT(v.name SEPARATOR "|") AS append_vendid');
  }
//------------------------------------------------------------------------------
  private function gl_acct($vData, $data = []) {
    $key = $data['arrayIndex'] != '' ? $data['arrayIndex'] : '';
    return M::glAcct($vData, '
      CONCAT(g.gl_acct, " (GL Title: ", g.title, ") ") AS value,g.gl_acct AS data,
      g.title AS append_gl_acct, 
      g.title AS field'.$key.'_remark');
  }
//------------------------------------------------------------------------------
  private function service($vData, $data = []){
    $key = !empty($data['arrayIndex']) ? $data['arrayIndex'] : '';
    return M::service($vData, '
      CONCAT(service, " - ", remark) AS value,service AS data,
      remark AS field'.$key.'_remark');
  }
//------------------------------------------------------------------------------
//  private function batch($vData, $data = []){
//    return M::service(['query'=>$vData['query']], '
//      CONCAT(service, " - ", remark) AS value,service AS data,
//      remark AS field'.$key.'_remark');
//  }
}
