<?php
namespace App\Http\Controllers\Autocomplete;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{RuleField, V, GlobalRuleField, Html, Helper, Elastic, TableName AS T};
use App\Http\Models\AutocompleteModel AS M; // Include the models class

class AutocompleteV2Controller extends Controller{
  private $_data	  = [];
  private $_fieldData = [];
  private $_viewPath  = 'app/creditCheck/';
  private $_viewGlobalAjax  = 'global/ajax';
  private $_viewGlobalHtml  = 'global/html';
  private $_size = 50;
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $data = [];
    $noResult = [['value'=>'No Result', 'data'=>'']];
    $method = preg_replace('/^to|_code\[[0-9]+\]/', '', $id);
    preg_match('~\[([0-9])+\]~', $id, $match);
    $data['arrayIndex'] = isset($match[1]) ? preg_replace('/(\[|\])/', '\\\\$1',$match[1])  : '';
    $data['includeField'] = !empty($req['includeField']) ? $req['includeField'] : [];
    $data['additionalField'] = !empty($req['additionalField']) ? $req['additionalField'] : [];
    
    try{
      $vData    = ['query'=>!empty($req['query']) ? $req['query'] : ''];
      if(isset($req['prop'])){
        $vData['prop'] = $req['prop'];
      }
      
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
    $oData = [];
    $includeField = array_flip($data['includeField']);
    $r = Helper::getElasticResult(Elastic::searchQuery([
      'index'=>T::$propView,
      '_source'=>['prop', 'trust', 'street', 'city', 'state', 'unit.unit', 'unit.street', 'bank.bank', 'bank.name'],
      'size'=>$this->_size,
      'query'=>[
        'should'=>[
          'wildcard'=>[
            'prop.keyword'=>$vData['query'] . '*', 
            'street.keyword'=>$vData['query'] .'*'
          ],
        ]
      ]
    ]));
    
    foreach($r as $val){
      $val = $val['_source'];
      $address = implode(', ', Helper::selectData(['street', 'city', 'state'], $val));
      $unit = $unitDetail = $bank = [];
      if(!empty($val['unit'])){
        foreach($val['unit'] as $v){
          $unit[$v['unit']] = implode(' - ', Helper::selectData(['unit', 'street'], $v));
        }
      }
      if(!empty($val['bank'])){
        foreach($val['bank'] as $v){
          $bank[$v['bank']] = implode(' - ', Helper::selectData(['bank', 'name'], $v));
        }
      }
      
      $oData[] = [
        'value'       => $val['prop'] . ' (Trust: '.$val['trust'].') ' . $address, 
        'data'        => $val['prop'],
        'list_unit'   =>$unit, 
        'list_bank'   =>$bank, 
        'list_ar_bank'=>$bank, 
        'append_prop'  =>$address
      ];
    }
    return $oData;
  }
//------------------------------------------------------------------------------
  private function unit($vData, $data = []){
    $oData = [];
    $includeField = array_flip($data['includeField']);
    $r = M::unit($vData, 
        'CONCAT(u.unit, " - ", u.street) AS value,u.unit AS data, 
        GROUP_CONCAT(DISTINCT t.tenant,"~",CONCAT(t.tenant," - ",t.status) SEPARATOR "|") AS list_tenant');
    
    foreach($r as $i=>$val){
      $listTenant = [];
      $p = explode('|', $val['list_tenant']);
      foreach($p as $v){
        list($key, $value) = explode('~', $v);
        $listTenant[$key] = $value;
      }
      $val['list_tenant'] = $listTenant;
      $oData[] = $val;
    }
    return $oData;
  }
////------------------------------------------------------------------------------
//  private function trust($vData, $data = []){
//    return M::trust(['query'=>$vData['query']], '
//      CONCAT(p.trust, " (Prop: ", p.prop, ") ") AS value,p.trust AS data,
//      GROUP_CONCAT(DISTINCT p.prop,"~",p.prop  SEPARATOR "|") AS list_prop');
//  }
////------------------------------------------------------------------------------
//  private function group1($vData, $data = []){
//    return M::group(['query'=>$vData['query']], 'p.group1 AS value, p.group1 AS data');
//  }
////------------------------------------------------------------------------------
//  private function vendid($vData, $data = []) {
//    return M::vendor(['query'=>$vData['query']], '
//      CONCAT(v.vendid, " (Vendor Name: ", v.name, ") ") AS value,v.vendid AS data, 
//      v.vendor_id AS field_vendor_id,
//      GROUP_CONCAT(v.name SEPARATOR "|") AS append_vendid');
//  }
//------------------------------------------------------------------------------
  private function gl_acct($vData, $data = []) {
    $oData = [];
    $includeField = array_flip($data['includeField']);
    $prop = !empty($data['additionalField']['prop']) ? $data['additionalField']['prop'] : 'Z64';
    $r = Helper::getElasticResult(Elastic::searchQuery([
      'index'=>T::$glChartView,
      '_source'=>['gl_acct', 'title'],
      'size'=>$this->_size,
      'query'=>[
        'must'=>[
          'wildcard'=>[
            'gl_acct.keyword'=>$vData['query'] . '*', 
          ],
          'prop.keyword'=>$prop
        ],
      ]
    ]));
    foreach($r as $v){
      $v = $v['_source'];
      $oData[] = [
        'value'          => $v['gl_acct'] . ' (GL Title: ' . $v['title'] . ')', 
        'data'           => $v['gl_acct'],
        'append_gl_acct' => $v['title'], 
        'field_remark'   => $v['title']
      ];
    }
    return $oData;
  }
////------------------------------------------------------------------------------
  private function service($vData, $data = []){
    $oData = [];
    $includeField = array_flip($data['includeField']);
    $key = !empty($data['arrayIndex']) ? $data['arrayIndex'] : '';
    $prop = !empty($data['additionalField']['prop']) ? $data['additionalField']['prop'] : 'Z64';
    
    $r = Helper::getElasticResult(Elastic::searchQuery([
      'index'=>T::$serviceView,
      '_source'=>['prop', 'service', 'remark'],
      'size'=>$this->_size,
      'query'=>[
        'should'=>[
          'wildcard'=>[
            'service.keyword'=>$vData['query'] . '*', 
            'remark.keyword'=>$vData['query'] . '*', 
          ],
        ],
        'must'=>['prop.keyword'=>$prop]
      ]
    ]));
    dd($r);
    
    foreach($r as $v){
      $v = $v['_source'];
      $oData[] = [
        'value'          => $v['gl_acct'] . ' (GL Title: ' . $v['title'] . ')', 
        'data'           => $v['gl_acct'],
        'append_gl_acct' => $v['title'], 
        'field_remark'   => $v['title']
      ];
    }
    return $oData;
    
    
    
    $key = !empty($data['arrayIndex']) ? $data['arrayIndex'] : '';
    return M::service(['query'=>$vData['query']], '
      CONCAT(service, " - ", remark) AS value,service AS data,
      remark AS field'.$key.'_remark');
  }
//------------------------------------------------------------------------------
//  private function batch($vData, $data = []){
//    return M::service(['query'=>$vData['query']], '
//      CONCAT(service, " - ", remark) AS value,service AS data,
//      remark AS field'.$key.'_remark');
//  }
  
//------------------------------------------------------------------------------
  private function _getIncludeField($includeField, $data){
    $list = [];
    foreach($includeField as $fl){
      if(isset($data[$fl])){
        $list[$fl] = $data[$fl]; 
      }
    }
    return $list[$fl];
  }
}
