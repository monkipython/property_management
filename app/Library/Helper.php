<?php
namespace App\Library;
use App\Library\{TableName AS T, Mapping};
use App\Http\Models\Model;
use Illuminate\Support\Facades\DB;
use Storage;
use SimpleCsv;
use ZipArchive;

class Helper{
  public static function getElasticResult($r,$isFirstRowOnly = 0,$includeTotal=0){
    $data = !empty($r['hits']['hits']) ? $r['hits']['hits'] : [];
    $data = ($isFirstRowOnly && !empty($data)) ? $data[0] : $data;
    return $includeTotal ? ['data'=>$data,'total'=>$r['hits']['total']] : $data;
  }
  public static function getElasticResultSource($r,$isFirstRowOnly = 0,$includeTotal=0){
    $data = !empty($r['hits']['hits']) ? $r['hits']['hits'] : [];
    $data = ($isFirstRowOnly && !empty($data)) ? $data[0]['_source'] : array_column($data, '_source');
    return $includeTotal ? ['data'=>$data,'total'=>$r['hits']['total']] : $data;
  }
//------------------------------------------------------------------------------
  public static function getElasticAggResult($r, $group){
    return !empty($r['aggregations'][$group]['buckets']) ? $r['aggregations'][$group]['buckets'] : [];
  }
//------------------------------------------------------------------------------  
  public static function getMapping($data=[]){
    $where = $oData = [];
    foreach($data as $k=>$v){
      $where[$k] = $v;
    }
    $r = Mapping::getMapping($where['tableName']);
    foreach($r as $v){
      ksort($v['mapping']);
      $oData[$v['field']] = $v['mapping'];
    }
    return $oData;
  }
//------------------------------------------------------------------------------
  public static function groupBy($r, $keyField, $valField = ''){
    $data = [];
    foreach($r as $v){
      $id = '';
      if(is_array($keyField)){
        foreach($keyField as $fl){
          $id .= $v[$fl];
        }
      } else{
        $id = $v[$keyField];
      }
      $data[$id][] = ($valField) ? $v[$valField] : $v;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getElasticGroupBy($r, $keyField, $valField = ''){
    $data = [];
    foreach($r as $v){
      $v = $v['_source'];
      $data[$v[$keyField]][] = ($valField) ? $v[$valField] : $v;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getElasticGroupByMultipleKey($r,$keyField,$valField=''){
    $data = [];
    foreach($r as $v){
      $v  = $v['_source'];
      $id = '';
      if(is_array($keyField)){
        foreach($keyField as $fl){
          $id .= $v[$fl];
        }
      } else {
        $id = $v[$keyField];
      }
      $data[$id][] = !empty($valField) ? $v[$valField] : $v;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function strtolowerArray($val, $exclude = []){
    $data = [];
    foreach($val as $k=>$v){
        $data[$k] = (!in_array($k, $exclude)) ? strtolower($v) : $v;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getDateRange($vData, $format = 'm/d/Y'){
    if(!empty($vData['dateRange'])){
      $dateRange = preg_replace('/ /', '', $vData['dateRange']);
      $p = explode('-', $dateRange);
      foreach($p as $i=>$date) {
        $p[$i] = date($format, strtotime($date));
      }
      if(count($p) == 2){
        return implode(' TO ', $p);
      }
    }
    return '';
  }
//------------------------------------------------------------------------------
  public static function isBetweenDateRange($checkDate, $dateRange){
    $checkDate = date('m/d/Y', strtotime(trim($checkDate)));
    $dateRangePiece = preg_split('/\-|to/i', $dateRange);
    if(self::validateDate(trim($dateRangePiece[0])) && self::validateDate(trim($dateRangePiece[1])) && self::validateDate($checkDate)){
      $fromDate  = strtotime($dateRangePiece[0]);
      $toDate    = strtotime($dateRangePiece[1]);
      $checkDate = strtotime($checkDate);
      return ($fromDate <= $checkDate && $checkDate <= $toDate) ? 1 : 0;
    }
    return 0;
  }
//------------------------------------------------------------------------------
  public static function convertGridSelect($data){
    $oData = [];
    foreach($data as $k=>$v){
      $oData[] = ['value'=>$k, 'text'=>$v];
    }
    return $oData;
  }
//------------------------------------------------------------------------------
  public static function keyFieldName($r, $key_field, $val_field = ''){
    /********************** ANONYMOUS FUNCTION ********************************/
    $getId = function($v, $key_field){
      $id = (gettype($key_field) == 'string') ? $v[$key_field] : '';
      if(gettype($key_field) == 'array'){
        foreach($key_field as $fl ){
          $id .= $v[$fl];
        }
      }
      return $id;
    };
    /**********************@ENd ANONYMOUS FUNCTION ****************************/
    $data = [];
    foreach($r as $v){
      $key = $getId($v, $key_field);
      $data[$key] = (empty($val_field)) ? $v : $v[$val_field];
    }
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function keyFieldNameElastic($r, $key_field, $val_field = ''){
    $r = $r['hits']['hits'];
    /********************** ANONYMOUS FUNCTION ********************************/
    $_extractField  = function($v,$key_field){
        $order = explode('.',$key_field);
        $value = $v;
        foreach($order as $i => $val){
            if(!is_array($value[$val])){
                return $value[$val];
            } 
            $value = $value[$val][0];
        }
        return $value;
    };
    /********************** ANONYMOUS FUNCTION ********************************/
    $getId = function($v, $key_field) use(&$_extractField){
      $id = (gettype($key_field) == 'string') ? $_extractField($v,$key_field) : '';
      if(gettype($key_field) == 'array'){
        foreach($key_field as $fl ){
          $id .= $_extractField($v,$fl);
        }
      }
      return $id;
    };
    /**********************@ENd ANONYMOUS FUNCTION ****************************/
    $data = [];
    foreach($r as $v){
      $v = $v['_source'];
      $key = $getId($v, $key_field);
      $data[$key] = (empty($val_field)) ? $v : $v[$val_field];
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function elasticToCollection($r){
    $data = [];
    if(!empty($r['hits']['hits'])){
      $r = $r['hits']['hits'];
      foreach($r as $v){
        $source = $v['_source'];
        $data[] = $source;
      }
      unset($r);
    }
    return collect($data);
    
  }
//------------------------------------------------------------------------------
  /**
   * @desc Used to get the field from given tables 
   *  it is used to determine which field belongs to which table
   * @param {array} $table database tables 
   *  Ex: ['mustExist'=>['unit|prop,unit']]
   * @param {array} $vData the field data from the validate data
   *  Ex:  $vData = $valid['dataNonArr'];
   * @return {array} return the data with key value pair that belongs to given tables
   */
  public static function getDataForTable($table, $vData){
    $data = [];
    $field = RuleField::generateRuleField(['tablez'=>$table])['field'];
    foreach($vData as $k=>$v){
      if(isset($field[$k])){
        $data[$k] = $v;
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  // Date have to be mm/dd/YYYY format
  public static function validateDate($date){
    if(!empty($date)){
      $p = explode('/', $date);
      return (count($p) == 3 && is_numeric($p[0]) && is_numeric($p[1]) && is_numeric($p[2]) && checkdate($p[0], $p[1], $p[2]));
    }
    return 0;
  }
//------------------------------------------------------------------------------
  public static function splitDateRate($dateRange, $datekey = 'date1', $errorKey='dateRange'){
    $error = 'Date format is not correct. The correct format is mm/dd/yyyy - mm/dd/yyyy.';
    $p = explode('-', $dateRange);
    if(!empty($p[0]) && !empty($p[1])){
      $date1 = trim($p[0]);
      $todate1 = trim($p[1]);
      if(self::validateDate($date1) && self::validateDate($todate1)){
        return [$datekey=>Format::mysqlDate($date1), 'to' . $datekey =>Format::mysqlDate($todate1)];
      } else{
        echo json_encode(['error'=>[$errorKey=>$error]]);
        exit;
      }
    }
    echo json_encode(['error'=>[$errorKey=>$error]]);
    exit;
  }
//------------------------------------------------------------------------------
  public static function arrayKeys($obj){
    return array_keys($obj);
  }
//------------------------------------------------------------------------------
  public static function getPermission($req){
    return isset($req['ALLPERM']) ? $req['ALLPERM'] : [];
  }
//------------------------------------------------------------------------------
  public static function isAdmin($req){
    return !empty($req['ISADMIN']) ? 1 : 0;
  }
//------------------------------------------------------------------------------
  public static function getUsid($req){
    return isset($req['ACCOUNT']['email']) ? $req['ACCOUNT']['email'] : '';
  }
  public static function getUsidName($req){
    return title_case($req['ACCOUNT']['firstname'] . ' ' . $req['ACCOUNT']['lastname']);
  }
//------------------------------------------------------------------------------
  public static function arrayIntersetValue($arrayAssoc, $array){
    $data = [];
    foreach($array as $k){
      $data[$k] = 0;
    }
    return array_intersect_key($arrayAssoc, $data);
  }
//------------------------------------------------------------------------------
  public static function checkErrorExit($vData){
    if(!empty($vData['error'])){
      echo json_encode($vData); exit;
    }
  }
//------------------------------------------------------------------------------
  public static function linuxTimeToDate($linxTime, $format = 'Y-m-d H:i:s'){
    return date($format, $linxTime);
  }
//------------------------------------------------------------------------------
  public static function clearAccessCookie(){
    \Cookie::queue(\Cookie::forget('UID'));
    \Cookie::queue(\Cookie::forget('SID'));
  }
//------------------------------------------------------------------------------
  public static function findAllNonEnglishChar($str){
    $match = [];
    preg_match('/[^\x00-\x7F]+/', $str, $match);
    return $match;
  }
//------------------------------------------------------------------------------
  public static function findAllEnglishChar($str){
    $match = [];
    preg_match('/[\x00-\x7F]+/', $str, $match);
    return $match; 
  }
//------------------------------------------------------------------------------
  public static function getCurrentPage($request){
    return explode('/', $request->route()->uri())[0];
  }
//------------------------------------------------------------------------------
  public static function displayErrorMsg($arr){
    $msg = '';
    foreach ($arr['error'] as $k=>$v){
      if(is_array($v)){
        foreach ($v as $i=>$err){
          $msg .= '<br>Field ' . ($i+1) . ': ' . $err;
        }
      } else {
        //When the invalid part of the request is not in the collection of forms
        $msg .= '<br>Field ' . $k . ': ' . $v;
      }
    }
    return Html::errMsg("<b>Upload Warning</b> " . $msg, 'upload-fail-msg upload-aws-msg upload-msg'); // HTML call here
  }
//------------------------------------------------------------------------------
/**
 * @desc Create an error message HTML div based on errors with the source and destination paths of a file 
 * @params {array} $arr original array gathered from the request
 * @return {HMTL Code} Html error message div
 */ 
  public static function displayInvalidLocationMsg($arr){
    $msg = '';
    foreach($arr['error'] as $k=>$v){
      foreach($v as $i => $err){
        //$msg .= '<br>Location ' . $i . ': ' . $err;
        $msg .= '<br>' . $k . ' Location ' . $i . ': ' . $err;
      }
    }
    return Html::errMsg('<b>Upload Warning</b>' . $msg,'upload-fail-msg upload-aws-msg upload-msg');
  }
//------------------------------------------------------------------------------
  public static function constructFieldErrMsgs($arr){
    $msgs = [];
    foreach ($arr as $parentField=>$msgArr){
      if(is_array($msgArr)){
        foreach ($msgArr as $id=>$msg){
          $msgs[] = [
            'msg' => $msg,
            'field' => $parentField,
            'index' => $id
          ];
        }
      } else {
        //When the error encountered in the request does not involve the collection of forms
        $msgs[] = [
          'msg'   => $msgArr,
          'field' => $parentField,
          'index' => 0
        ];
      }
    }
    return $msgs;
  }
//------------------------------------------------------------------------------
/**
 * @desc get the element from the $arr by providing field either string or array
 * @params {array} $arr original array
 * @params {array|string} $fl cannot be array or string
 * @return {array} the data with the element selected
 */  
  public static function getDataFromArr($arr, $fl){
    $data = [];
    if(is_array($fl)){
      foreach($fl as $k){
      $data[$fl] = $arr[$fl];
      }
    }
    else{
      $data = $arr[$fl];
    }
    return $data; 
  }
//------------------------------------------------------------------------------
  public static function arrayPop($arr, $num = 1){
    for($i = 0; $i < $num; $i++){
      array_pop($arr);
    }
    return $arr;
  }
//------------------------------------------------------------------------------
  /**
   * @desc Delete the array by value 
   * @param {array} $fieldValue the value that you want to delete
   * @param {array} $data original array
   * @return retun the data that already delete the given fieldValue
   */
  public static function arrayDelete($fieldValue, $data){
    foreach($fieldValue as $v){
      $key = array_search($v, $data);
      if($key === false){
      } else{
        unset($data[$key]);
      }
    }
    return array_values($data);
  }
//------------------------------------------------------------------------------
/**
 * @desc since the main directory start with either 'fullProduct_', 'archiveProduct_', 'deleteProduct_'
 *	we can remove this the  prefix by sing this function and get the productMap
 * @params {string} $dir any string that start with 'fullProduct_', 'archiveProduct_', 'deleteProduct_'
 * @return {string} a string that will not contain any 'fullProduct_', 'archiveProduct_', 'deleteProduct_'
 * @howToUse: 
 *	  $dir = 'fullProduct_comos_walkinside/full_products/nt/10.2/COMOS_Walkinside_10.2.1.zip';
 *	  Helper::removMainDirPrefix($dir);
 *	  // Output: comos_walkinside
 */
  public static function getProductFromPath($dir){
    return explode('/', self::removMainDirPrefix($dir))[0];
  }
//------------------------------------------------------------------------------
  public static function echoJsonError($str, $errorKey = 'msg'){
    echo json_encode(['error'=>[$errorKey=>$str]]);
    exit;
  }
//------------------------------------------------------------------------------
  public static function isSysTransBalZero($dataSet){
    $dataSet = isset($dataSet[0]) ? [$dataSet] : $dataSet;
    foreach($dataSet as $table=>$val){
      if($table == T::$tntTrans || $table == T::$glTrans){
        $bal = 0;
        foreach($val as $i=>$v){
          $bal += ($v['tx_code'] == 'S') ? $v['amount'] : 0;
        }
        if(Format::floatNumber($bal) != 0){
          Helper::echoJsonError(Html::errMsg('Transaction error. Balance is not correct. Please contact sean.hayes@dataworkers.com for assistant.'), 'popupMsg');
        }
      }
    }
  }
//------------------------------------------------------------------------------
  public static function unknowErrorMsg(){
    return Html::errMsg('Please contact administration to assist with this issue.');
  }
//------------------------------------------------------------------------------
  public static function removeNewLine($string){
    return trim(preg_replace('/\s+/', ' ', $string));
  }
####################################################################################################
#####################################        DATE SECTION      #####################################
####################################################################################################
public static function getDateDifference($fromDate, $toDate, $format = '%a'){
    /**
      % - Literal %
      Y - Year, at least 2 digits with leading zero (e.g 03)
      y - Year (e.g 3)
      M - Month, with leading zero (e.g 06)
      m - Month (e.g 6)
      D - Day, with leading zero (e.g 09)
      d - Day (e.g 9)
      a - Total number of days as a result of date_diff()
      H - Hours, with leading zero (e.g 08, 23)
      h - Hours (e.g 8, 23)
      I - Minutes, with leading zero (e.g 08, 23)
      i - Minutes (e.g 8, 23)
      S - Seconds, with leading zero (e.g 08, 23)
      s - Seconds (e.g 8, 23)
      R - Sign "-" when negative, "+" when positive
      r - Sign "-" when negative, empty when positive
     */
    $interval = date_diff(date_create($fromDate), date_create($toDate));
    return $interval->format($format);
  }
//------------------------------------------------------------------------------
  public static function duration($date1, $date2, $showsecond = false){
    $result = '';

    if (!empty($date1) && !empty($date2) && $date1 != '0000-00-00 00:00:00' && $date2 != '0000-00-00 00:00:00') {
      $date1 = strtotime($date1);
      $date2 = strtotime($date2);

      $diffs = abs($date2 - $date1);

      $days = floor($diffs / (24 * 60 * 60));
      if ($days > 0) {
        $result .= $this->number($days).' '.($days == 1 ? 'day' : 'days').' ';
        $diffs = ($diffs - ($days * 24 * 60 * 60));
      }

      $hours = floor($diffs / (60 * 60));
      if ($hours > 0) {
        $result .= $this->number($hours).' '.($hours == 1 ? 'hour' : 'hours').' ';
        $diffs = ($diffs - ($hours * 60 * 60));
      }

      $minutes = floor($diffs / (60));
      if ($minutes > 0) {
        $result .= $this->number($minutes).' '.($minutes == 1 ? 'minute' : 'minutes').' ';
        $diffs = ($diffs - ($minutes * 60));
      }

      $seconds = $diffs;
      if ($seconds > 0 && (($days == 0 && $hours == 0 && $minutes == 0) || $showsecond == true)) {
        $result .= $this->number($seconds).' '.($seconds == 1 ? 'second' : 'seconds').' ';
      }

      $result = trim($result);
    }

    return $result;
  }
//------------------------------------------------------------------------------
  public static function fullDate(){
    return date('F j, Y, g:i a');
  }
//------------------------------------------------------------------------------
  public static function mysqlDate(){
    return date('Y-m-d H:i:s');
  }
//------------------------------------------------------------------------------
  public static function usDateTime(){
    return date('m/d/Y H:i:s');
  }
//------------------------------------------------------------------------------
  public static function usDate(){
    return date('m/d/Y');
  }
//------------------------------------------------------------------------------ 
  public static function date(){
    return date('Y-m-d');
  }
//------------------------------------------------------------------------------  
  public static function joinQuery($prefix, $str, $isEndSelect = 0){
    return $prefix . '.' . implode(',' . $prefix . '.', explode(',', preg_replace('/[\s+]/', '',$str))) . ($isEndSelect ? ' ': ',');
  }
//------------------------------------------------------------------------------
  public static function groupConcatQuery($columns,$table,$isEndSelect = 0){
    return ' GROUP_CONCAT(DISTINCT ' . implode(',"~",',$columns) . ' SEPARATOR "|" ) AS ' . $table . ($isEndSelect ? ' '  : ',');
  }
//------------------------------------------------------------------------------
  public static function pushArray($originalArray, $arrOrStr){
    if(is_array($arrOrStr)){
      foreach($arrOrStr as $v){
        $originalArray[] = $v;
      }
    } else{
      $originalArray[] = $arrOrStr;      
    }
    return $originalArray;
  }
//------------------------------------------------------------------------------
  public static function dateRange($date1, $date2 = null, $long = true){
    if (!empty($date1) && !empty($date2) && $date1 != '0000-00-00 00:00:00' && $date2 != '0000-00-00 00:00:00') {
      $date1 = strtotime($date1);
      $date2 = strtotime($date2);
      $result = '';
      
      if ($date1 <= $date2) {
        $start = $date1;
        $end = $date2;
      } else {
        $start = $date2;
        $end = $date1;
      }

      $start_year = ($long ? date('Y', $start) : date('y', $start));
      $end_year = ($long ? date('Y', $end) : date('y', $end));

      $start_month = (int) date('m', $start);
      $end_month = (int) date('m', $end);

      $start_date = (int) date('j', $start);
      $end_date = (int) date('j', $end);


      if ($start_year == $end_year) {
        if ($start_month == $end_month) {
          if ($start_date == $end_date) {
            $result = $start_date.' '.($long ? date('F', $start) : date('M', $start));
          } else {
            $result = $start_date.'-'.$end_date.' '.($long ? date('F', $start) : date('M', $start));
          }
        } else {
          $result = $start_date.' '.($long ? date('F', $start) : date('M', $start)).' - '.$end_date.' '.($long ? date('F', $end) : date('M', $end));
        }

        $result .= ' '.$start_year;
      } else {
          $result = $start_date.' '.($long ? date('F', $start) : date('M', $start)).' '.$start_year.' - '.$end_date.' '.($long ? date('F', $end) : date('M', $end)).' '.$end_year;
      }
    } elseif (!empty($date1) && $date1 != '0000-00-00 00:00:00') {
      $timestamp = strtotime($date1);
      $date = (int) date('j', $timestamp);
      $month = (string) ($long ? date('F', $timestamp) : date('M', $timestamp));
      $year = ($long ? date('Y', $timestamp) : date('y', $timestamp));
      $result = $date.' '.$month.' '.$year;
    } elseif (!empty($date2) && $date2 != '0000-00-00 00:00:00') {
      $timestamp = strtotime($date2);
      $date = (int) date('j', $timestamp);
      $month = (string) ($long ? date('F', $timestamp) : date('M', $timestamp));
      $year = ($long ? date('Y', $timestamp) : date('y', $timestamp));
      $result = $date.' '.$month.' '.$year;
    }
    return $result;
  }  
//------------------------------------------------------------------------------
  public static function encodeUtf8($data){
    if(!is_array($data)){
      return utf8_encode($data);
    } else{
      foreach($data as $i => $val){
        if(!is_array($data[$i])){
          $data[$i] = utf8_encode($data[$i]);
        } else{
          foreach($val as $k=>$v){
            $data[$i][$k] = utf8_encode($data[$i][$k]);
          }
        }
      }
      return $data;
    }
  } 
//------------------------------------------------------------------------------
  public static function getValue($key, $vData, $defaultValue = ''){
    return isset($vData[$key]) ? $vData[$key] : $defaultValue;
  }
//------------------------------------------------------------------------------
  public static function exitIfError($key, $vData, $errorMsg, $msgKey = 'popupMsg'){
    if(!isset($vData[$key])){
      self::echoJsonError($errorMsg, $msgKey);
    }
  }
//------------------------------------------------------------------------------
  public static function explodeProp($propStr){
    $propRange = [];
    $propStr   = preg_replace('/\s+/', '', $propStr);
    $prop      = explode(',', $propStr);
    
    foreach($prop as $i=>$v) {
      if(strpos($v, '-') !== false) {
        $propRange[] = explode('-', $v);
        unset($prop[$i]);
      }
    }
    ## Loop through the props and check to see if the user entered valid prop number
    foreach($prop as $i=>$v) {
      $validateData = ['data'=>['prop'=>$v]];
      V::validateionDatabase(['mustExist'=>['prop|prop']], $validateData);
    }
    
    ## Get the props that are split by commas
    $r = DB::table(T::$prop)->select(['prop_id', 'prop'])->whereIn('prop', $prop)->get();
    $rProps = Helper::keyFieldName($r, 'prop_id');
    foreach($propRange as $i=>$v){
      $r = DB::table(T::$prop)->select(['prop_id', 'prop'])->whereBetween('prop', $v)->where('prop_class', '<>', 'X')->get();
      $rProps += Helper::keyFieldName($r, 'prop_id');
    }
    
    return [
      'propId'=>array_values(Helper::keyFieldName($rProps, 'prop_id', 'prop_id')), 
      'prop'=>array_values(Helper::keyFieldName($rProps, 'prop_id', 'prop'))
    ];
  }
//------------------------------------------------------------------------------
  public static function explodeField($vData,$fields=['prop']){
    $fieldRanges = $fieldLists = [];

    //Preprocess to eliminate uneeded spaces
    foreach($fields as $v){
      if(!empty($vData[$v])){
        $vData[$v]      = trim(preg_replace(['/\s*(\,|\-)\s*/','/\s+/'],['$1',' '],$vData[$v]));
        $fieldLists[$v] = explode(',',$vData[$v]);
      }
    }
    
    foreach($fieldLists as $k=>$v){
      foreach($v as $i=>$val){
        if(strpos($val,'-') !== false){
          $fieldRanges[$k][]  = explode('-',$val);
          unset($fieldLists[$k][$i]);
        }
      }
    }
    
    foreach($fieldLists as $k=>$v){
      foreach($v as $i=>$val){
        $validateData = ['data'=>[$k => $val]];
        V::validateionDatabase(['mustExist'=>['prop|' . $k]],$validateData);
      }
    }
    
    $r          = DB::table(T::$prop)->select(['prop_id','prop']);
    foreach($fieldLists as $k=>$v){
      $rangeValues = !empty($fieldRanges[$k]) ? $fieldRanges[$k] : [];
      $r = !empty($v) ? $r->whereIn($k,$v) : $r;
      $r = !empty($rangeValues) ? $r->where(function($query) use(&$rangeValues,&$k){
        foreach($rangeValues as $i => $val){
          $clause = $i === 0 ? 'whereBetween' : 'orWhereBetween';
          $query->$clause($k,$val);
        }
      }) : $r;
    }
    
    $r      = $r->where('prop_class','<>','X')->get();
    $rProps = Helper::keyFieldName($r,'prop_id');
    return [
      'propId'=>array_values(Helper::keyFieldName($rProps, 'prop_id', 'prop_id')), 
      'prop'  =>array_values(Helper::keyFieldName($rProps, 'prop_id', 'prop')),
    ];
  }
//------------------------------------------------------------------------------
  public static function getGlAcctRange($vData){
    $start = !empty($vData['gl_acct']) && is_numeric($vData['gl_acct']) ? $vData['gl_acct'] : 110;
    $stop  = !empty($vData['to_gl_acct']) && is_numeric($vData['to_gl_acct']) ? $vData['to_gl_acct'] : 999;
    
    $highStart  = intval($start) * 10000;
    $highStop   = (intval($stop) * 10000) + 9999;
    
    return [
      'smallGlAcct'      => $start,
      'smallToGlAcct'    => $stop,
      'bigGlAcct'        => $highStart,
      'bigToGlAcct'      => $highStop
    ];
  }
//------------------------------------------------------------------------------
  public static function explodeGlAcct($glAcctStr,$fieldName='gl_acct'){
    $queryItems = $fieldLists = $fieldRanges = [];
    
    $glAcctStr  = trim(preg_replace(['/\s*(\,|\-)\s*/','/\s+/'],['$1',' '],$glAcctStr));
    $termItems  = [];
    $fieldLists = explode(',',$glAcctStr);
    
    $_createSplitRange = function($low,$high,$fieldName='gl_acct'){
      $highStart = (intval($low) * 10000) + 1;
      $highStop  = (intval($high) * 10000) + 9999;
      
      $rangeItems= [
        [
          'range' => [
            $fieldName => [
              'gte'    => $low,
              'lte'    => $high,
            ]
          ]
        ],
        [
          'range' => [
            $fieldName => [
              'gte'    => $highStart,
              'lte'    => $highStop,
            ]
          ]
        ]
      ];
      return $rangeItems;
    };
    
    foreach($fieldLists as $i => $v){
      if(strpos($v,'-') !== false){
        $fieldRanges[] = explode('-',$v);
        unset($fieldLists[$i]);
      }
    }
    
    foreach($fieldLists as $i => $v){
      if(is_numeric($v)){
        $queryItems  = array_merge($queryItems,$_createSplitRange($v,$v,$fieldName)); 
      } else {
        $termItems[] = $v;
      }
      
    }
    
    foreach($fieldRanges as $i => $v){
      $startGl    = Helper::getValue(0,$v);
      $endGl      = Helper::getValue(1,$v);
      
      if(is_numeric($startGl) && is_numeric($endGl)){
        $queryItems = array_merge($queryItems,$_createSplitRange($startGl,$endGl,$fieldName)); 
      } else {
        $termItems[]= implode('-',$v);
      }
    }
    
    if(!empty($termItems)){
      $queryItems[] = [
        'terms' => [
          $fieldName . '.keyword' => $termItems,
        ]
      ];
    }
    return $queryItems;
  }
//------------------------------------------------------------------------------
  public static function explodeFieldTable($vData,$fields,$table,$columns,$idCol=''){
    $result = $fieldRanges = $fieldLists = [];
    $idCol  = !empty($idCol) ? $idCol : $table . '_id';
    foreach($fields as $v){
      if(!empty($vData[$v])){
        $vData[$v]       = trim(preg_replace(['/\s*(\,|\-)\s*/','/\s+/'],['$1',' '],$vData[$v]));
        $fieldLists[$v]  = explode(',',$vData[$v]);
      }
    }
    
    foreach($fieldLists as $k=>$v){
      foreach($v as $i=>$val){
        if(strpos($val,'-') !== false){
          $fieldRanges[$k][]   = explode('-',$val);
          unset($fieldLists[$k][$i]);
        }
      }
    }
    
    foreach($fieldLists as $k=>$v){
      foreach($v as $i => $val){
        $validateData   = ['data'=>[$k => $val]];
        V::validateionDatabase(['mustExist' => [$table . '|' . $k]],$validateData);
      }
    }
    
    $r      = DB::table($table)->select($columns);
    foreach($fieldLists as $k => $v){
      $rangeValues   = self::getValue($k,$fieldRanges,[]);
      $r             = !empty($v) ? $r->whereIn($k,$v) : $r;
      $r             = !empty($v) ? $r->where(function($query) use(&$rangeValues,&$k){
        foreach($rangeValues as $i => $val){
          $clause = $i === 0 ? 'whereBetween' : 'orWhereBetween';
          $query->$clause($k,$val);
        }
      }) : $r;
    }
    
    $r      = $r->get()->toArray();
    $rCols  = Helper::keyFieldName($r,$idCol);
    foreach($columns as $v){
      $result[$v]  = array_values(Helper::keyFieldName($rCols,$idCol,$v));
    }
    
    return $result;
  }
//------------------------------------------------------------------------------
  public static function selectData($selectData, $vData){
    $data = [];
    foreach($selectData as $fl){
      if(isset($vData[$fl])){
        $data[$fl] = $vData[$fl];
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function isCli(){
    return (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) ? 1 : 0;
  }
//------------------------------------------------------------------------------
  public static function getFileAndHref($filename, $path = 'tmp/'){
    $filePath = storage_path('app/public/' . $path . $filename);
    $href     = Storage::disk('public')->url($path . $filename);
    return ['filePath'=>$filePath, 'href'=>$href];
  }
//------------------------------------------------------------------------------
  public static function exportCsv($data){
    $row = [];
    $r        = $data['data'];
    $filename = $data['filename'];
    $msg      = isset($data['msg']) ? $data['msg'] : 'Your file is ready. Please click the link here to download it';
    $fileInfo = self::getFileAndHref($filename); 
    $filePath = $fileInfo['filePath'];
    $href     = $fileInfo['href'];
    foreach($r as $i=>$val){
      $val = isset($val['_source']) ? $val['_source'] : $val;
      foreach($val as $k=>$v){
        $row[$i][$k] = $v;
      }
    }
    
    $exporter = SimpleCsv::export(collect($row));
    $exporter->save($filePath);
    return [
      'file'=>$filePath,
      'popupMsg'=>Html::a($msg, [
        'href'=>$href, 
        'target'=>'_blank',
        'class'=>'downloadLink'
      ])
    ];
  }
//------------------------------------------------------------------------------
  /**
   * @desc it is used to download multiple files as a zip-file
   * @param {array} 
   * "paths"->all files location (Require) key->file name, value->file absolute path
   * "files"->all files name. For remove all temporary files after merge done.(Require)
   * "fileName"->output file name. (Require)
   * "href"->File download url (Require)
   * "msg"->Pop up message. (Optional)
   * @return {array} return pop up information.
  */
  public static function exportZip($data){
    $result       = [];
    $msg          = isset($data['msg']) ? $data['msg'] : 'Your file is ready. Please click the link here to download it';
    $isDeleteFile = isset($data['isDeleteFile']) ? $data['isDeleteFile'] : true;
    if(!empty($data) && isset($data['paths']) && isset($data['files']) && isset($data['fileName']) && isset($data['href'])){
      $href     = $data['href'];
      
      $zipname = $data['fileName'];
      
      $zip = new ZipArchive;
      $zip->open($zipname, ZipArchive::CREATE);
      foreach ($data['paths'] as $name => $loc) {
        $zip->addFile($loc,$name);
      }
      
      $zip->close();
      // Remove all temporary files
      if($isDeleteFile){
        Storage::delete($data['files']);
      }
      
      $result = [
        'popupMsg'=>Html::a($msg, [
          'href'=>$href,
          'target'=>'_blank',
          'class'=>'downloadLink'
        ])
      ];
    }
    return $result;
  }
//------------------------------------------------------------------------------
  public static function extractColumns($data,$columns){
    $r = [];
    foreach($data as $i => $v){
      foreach($columns as $c){
        $r[$i][$c] = $v[$c];
      }
    }
    return $r;
  }
//------------------------------------------------------------------------------
  public static function searchCombinations($table,$params,$select='*'){
    $params = isset($params[0]) ? $params : [$params];
    
    $first  = true;
    $r      = DB::table($table)->select($select);
    foreach($params as $i => $v){
      $r     = $first ? $r->where(Model::buildWhere($v)) : $r->orWhere(Model::buildWhere($v));
      $first = false;
    }
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function formElasticSelectedField($fields){
    $data          = [];
    foreach($fields as $v){
      $data[] = $v . '~' . $v;
    }
    $selectedField = implode('-',$data);
    return $selectedField;
  }
//------------------------------------------------------------------------------
  public static function getRentFromBilling($v){
    $rent = 0;
    $rBilling = !empty($v['billing']) ? $v['billing'] : [];
    if(!empty($rBilling)){
      foreach($rBilling as $v){
        if($v['schedule'] == 'M' && $v['stop_date'] == '9999-12-31' && $v['gl_acct'] == '602'){
          $rent += $v['amount'];
        }
      }
    }
    return $rent;
  }
//------------------------------------------------------------------------------
  public static function getPropUnitTenantMustQuery($vData, $additionQuery = [], $isIncludeMust = 1){
    if(!empty($vData['prop']) && !empty($vData['unit']) && isset($vData['tenant'])){
      $query = ['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant']] + $additionQuery;
      return ($isIncludeMust) ? ['must'=>$query] : $query;
    } else{
      dd($vData);
      self::echoJsonError(Html::errMsg('Property, Unit, and Tenant # cannot be empty.'), 'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  public static function getPropUnitMustQuery($vData, $additionQuery = [], $isIncludeMust = 1){
    if(!empty($vData['prop']) && !empty($vData['unit'])){
      $query = ['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit']] + $additionQuery;
      return ($isIncludeMust) ? ['must'=>$query] : $query;
    } else{
      self::echoJsonError(Html::errMsg('Property, and Unit # cannot be empty.'), 'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  public static function getPropMustQuery($vData, $additionQuery = [], $isIncludeMust = 1){
    if(!empty($vData['prop'])){
      $query = ['prop.keyword'=>$vData['prop']] + $additionQuery;
      return ($isIncludeMust) ? ['must'=>$query] : $query;
    } else{
      self::echoJsonError(Html::errMsg('Property # cannot be empty.'), 'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  public static function getPropBankMustQuery($vData,$additionQuery=[],$isIncludeMust=1){
    if(!empty($vData['prop']) && !empty($vData['bank'])){
      $query = ['prop.keyword'=>$vData['prop'],'bank.keyword'=>$vData['bank']] + $additionQuery;
      return ($isIncludeMust) ? ['must'=>$query] : $query;
    } else {
      self::echoJsonError(Html::errMsg('Property # and Bank # cannot be empty.'),'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  public static function isProductionEnvironment(){
    return (env('APP_ENV') == 'production') ? 1 : 0; 
  }
}
