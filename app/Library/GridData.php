<?php
namespace App\Library;
use App\Library\{Elastic, Util};

class GridData{
  private static $_indexProp = 'prop_view/prop_view/_search?';
  /**
   * @desc is used to build the query for the grid table 
   *  it is an important function because it will build the query for 
   *  SORT, QUERY, FILTER, LIMIT, OFFSET 
   * @param {array} 
   *  [
   *   "search" => "e"
   *   "sort" => "applicants.tenant" | optional
   *   "order" => "asc" | optional
   *   "offset" => "0"
   *   "limit" => "100"
   *   "filter" => "{"applicants.social_security":"428","ordered_by":"e"}" | optional
   * ]
   * @return 
   * [
   *    "query" => "q=(prop:"0010")&size=100&from=0&sort="
   *    "rProp" => array:1 [
   *      "0010" => array:50 [
   *        "man_fee_date" => "1776-01-01"
   *        "entity_name" => "GROUP X ROSEMEAD PROPERTIES, LP"
   *        "county" => "LOS ANGELES"
   *    ]
   *  ]
   * selectedField format: action~Action-prop~Prop-unit~Unit-tenant~Tnt-new_rent~Rent-sec_deposit~Dep
   */
  public static function getQuery($vData, $index){
    $q = $sort = '';
    unset($vData['ACCOUNT'], $vData['PERMISSION'], $vData['NAV'], $vData['PERM'], $vData['ALLPERM'], $vData['ISADMIN']);
    $defaultSort = !empty($vData['defaultSort']) ? $vData['defaultSort'] : [];
    $filter      = !empty($vData['filter']) ? json_decode($vData['filter'],true) : [];
    //$filter        = !empty($vData['filter']) ? json_decode(strtolower($vData['filter']), true) : [];
    $defaultFilter = !empty($vData['defaultFilter']) ? $vData['defaultFilter'] : [];
    $defaultFilter = is_array($defaultFilter) ? $defaultFilter : json_decode($defaultFilter, true);
    $selectedField = !empty($vData['selectedField']) ? self::_getSelectedFieldKey($vData['selectedField']) : [];
    $field     = Elastic::getMapFieldElastic($index);
//    dd($filter, $field['tnt_name']);
    ###################### FILTER SECTION #######################
    if(!empty($filter)){
      foreach($filter as $k=>$v){
        if(preg_match('/\./', $k)){
          list($table, $key) = explode('.', $k);
          $dataType = $field[$table]['properties'][$key]['type'];
        } else{
          $dataType = $field[$k]['type'];
        }
        $queryVal = ltrim($v, '^<=>');
//        $not    = preg_match('/^\!/', $v) ? ' NOT ': '';
        $not    = '';
        switch ($dataType){
          case 'long':
          case 'float':
          case 'date':
          case 'datetime':
            if(preg_match('/to/i', $v)){
              $queryVal = '[' . preg_replace('/to/i', ' TO ', $queryVal) . ']';
            } else if(preg_match('/^\<\=/', $v)){
              $queryVal = '[* TO ' . $queryVal . ']';
            } else if(preg_match('/^\>\=/', $v)){
              $queryVal = '[' . $queryVal . ' TO *]';
            } else if(preg_match('/^\</', $v)){
              $queryVal = '[* TO ' . $queryVal . ']';
            } else if(preg_match('/^\>/', $v)){
              $queryVal = '[' . ($queryVal) . ' TO *]'; 
            }
            break;
          case 'text':
            if(preg_match('/^\^/', $v)){
              $queryVal = $queryVal . '*';
            } else if(preg_match('/AND|OR|NOT/', $v)){
              $queryVal = preg_replace('/([a-z]+)/', ' *$1* ', $queryVal);
              $queryVal = preg_replace('/\*\s+\*|\s+/', ' ', $queryVal);
//              $queryVal = preg_replace('/([a-z]+\s+[a-z]+)/', '($1)', $queryVal);
            } else{
              $queryVal = '*' . $queryVal . '*';
//              $queryVal = $queryVal;
            }
            break;
        }
        $q .= $not . $k . ':' . $queryVal . ' AND ';
//        switch ($k){
//          case 'application.tenant': $q .= self::_getApplicationtenantQuery($k, $queryVal, $not); break;
//          default: $q .= $not . $k . ':' . $queryVal . ' AND ';
//        }
      }
    }
    foreach($defaultFilter as $k=>$v){
      $q .= $k . ':' . $v . ' AND ';        
    }
//    dd(preg_replace('/(\@)/', '\\\\$1', $q));
    ###################### SORT SECTION #########################
    if(!empty($vData['sort']) && !empty($vData['order'])){
      if(preg_match('/\./', $vData['sort'])){
        list($first, $second) = explode('.', $vData['sort']);
        $type = !empty($field[$first]['properties'][$second]['type']) ? $field[$first]['properties'][$second]['type'] : '';
      } else{
        $type = !empty($field[$vData['sort']]['type']) ? $field[$vData['sort']]['type'] : '';
      }
      if(!empty($type)){
        switch ($vData['sort']){
          case 'application.tenant':
            $sort = 'application.fname.keyword:' . $vData['order'];break;
          default:
            if($type == 'long' || $type == 'float' || $type == 'date'){
              $sort = $vData['sort'] . ':' . $vData['order'];
            } else{
              $sort = $vData['sort'] . '.keyword:' . $vData['order'];
            }
        }
      }
    }
    ################ MAIN QUERY SECTION #########################
    $param = [
      'q'    => !empty($q) ? rtrim($q, ' AND ') : '*',
      'size' => ($vData['limit'] == -1) ? 500000 : $vData['limit'],
      'from' => isset($vData['offset']) ? $vData['offset'] : 0,
      'default_operator'=>'AND'
    ];
    
    if(!empty($selectedField)){
      $param['_source_include'] = implode(',', array_keys($selectedField));
    }
  
    if(!empty($sort)){
      $param['sort'] = $sort . ',' . implode(',', $defaultSort);
    }
    return ['query'=>http_build_query($param), 'selectedFieldMap'=>$selectedField];
  }
//------------------------------------------------------------------------------
   public static function getReportQuery($vData, $index){
    $q = $sort = '';
    $defaultSort   = !empty($vData['defaultSort']) ? preg_replace('/\s+/', '', $vData['defaultSort']) : [];
    $filter        = !empty($vData['filter']) ? $vData['filter'] : [];
    $selectedField = !empty($vData['selectedField']) ? $vData['selectedField'] : [];
    $field         = Elastic::getMapFieldElastic($index);
    $qData  = [];

    # GROUP FROM AND TO DATA
    foreach($filter as $k=>$v){
      if(preg_match('/^to/', $k)){
        list($tmp, $key) = explode('to', $k);
        $val   = $filter[$key]; 
        $toval = $filter[$k]; 
        $qData[$key] = ['val'=>$val, 'toval'=>$toval];
        unset($filter[$key], $filter[$k]);
      }
    }
    
    if(!empty($filter)){
      foreach($filter as $k=>$v){
        $qData[$k] = $v;
      }
    }
    
    foreach($qData as $k=>$v){
//      $dataType = self::_getDataType($field, $k);
      if(is_array($v)){
        if(!empty($v['val']) && !empty($v['toval'])){
          $q .= $k  . ':[' . $v['val'] . ' TO ' . $v['toval'] .  '] AND ';
        }else {
          ## WhereIn for elasticSearch
          $tmpQ = '';
          foreach($v as $key=>$value) {
            $tmpQ .= $value . ' OR ';
          }
          $q .= $k . ':(' . preg_replace('/ OR $/', '', $tmpQ) . ')';
        }
      } else{
        $q .= $k . ':"' . $v . '" AND ';
      }
    }
    ###################### SORT SECTION #########################
    if(!empty($vData['sort']) && !empty($vData['order'])){
      if(preg_match('/\./', $vData['sort'])){
        list($first, $second) = explode('.', $vData['sort']);
        $type = !empty($field[$first]['properties'][$second]['type']) ? $field[$first]['properties'][$second]['type'] : '';
      } else{
        $type = !empty($field[$vData['sort']]['type']) ? $field[$vData['sort']]['type'] : '';
      }
      if(!empty($type)){
        switch ($vData['sort']){
          case 'application.tenant':
            $sort = 'application.fname.keyword:' . $vData['order'];break;
          default:
            if($type == 'long' || $type == 'float' || $type == 'date' || $type == 'keyword'){
              $sort = $vData['sort'] . ':' . $vData['order'];
            } else{
              $sort = $vData['sort'] . '.keyword:' . $vData['order'];
            }
        }
      }
    }
    ################ MAIN QUERY SECTION #########################
    $param = [
      'q'    => !empty($q) ? rtrim($q, ' AND ') : '*',
      'size' => 5000,
//      'from' => isset($vData['offset']) ? $vData['offset'] : 0 
    ];
    if(!empty($selectedField)){
      $param['_source_include'] = $selectedField;
    }
    if(!empty($sort)){
      $param['sort'] = $sort . ',' . implode(',', $defaultSort);
    }

    return ['query'=>http_build_query($param), 'selectedFieldMap'=>$selectedField];
   }
//------------------------------------------------------------------------------
  public static function getRule(){
    return [
      'search' =>'nullable|string|between:1,255',
      'sort'   =>'nullable|string|between:2,255',
      'order'  =>'required|string|between:3,4',
      'offset' =>'required|integer|between:0,1000000',
      'limit'  =>'required|integer|between:-1,500',
      'filter' =>'nullable|string|between:1,5000',
      'defaultFilter' =>'nullable|string|between:1,5000',
//      'reportType' =>'nullable|string|between:1,50',
      'selectedField' => 'nullable|string',
    ];
  }
//------------------------------------------------------------------------------
  public static function getRuleReport(){
    return [
      'sort'   =>'nullable|string|between:2,255',
      'order'  =>'nullable|string|between:3,4',
    ];
  }
//  public static function getDefaultFilterFromUrl($vData){
//    if(!empty($vData['defaultFilter'])){
//      $defaultProp = json_decode($vData['defaultFilter'], true);
//      if(!empty($defaultProp['prop'])){
//        $vData['defaultFilter'] = ['prop.prop'=>$defaultProp['prop']];
//      }
//    }
//  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//  private static function _getApplicationtenantQuery($k,$queryVal, $not){
//    return $not . '(applicants.fname:' . $queryVal . ' OR applicants.mname:'. $queryVal . ' OR applicants.lname:'.$queryVal.') AND ';
//  }
//------------------------------------------------------------------------------
//  private static function _getFilterQuery(){
//  }
//------------------------------------------------------------------------------
  private static function _getSelectedFieldKey($selectedField){
    $data = [];
    $keyVal = explode('-', trim($selectedField, '-'));
    foreach($keyVal as $v){
      list($fl, $v) = explode('~', $v);
      $data[$fl] = $v;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private static function _getDataType($field, $k){
    if(preg_match('/\./', $k)){
      list($table, $key) = explode('.', $k);
      return $field[$table]['properties'][$key]['type'];
    } else{
      return $field[$k]['type'];
    }
  }
}