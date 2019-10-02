<?php
namespace App\Library;
use PDF;
use Requests AS curl;

class PDF{
  private static $_host   = 'http://192.168.1.80:9200/';
  private static $_header = ['Content-Type'=>'application/json'];
  
  /**
   * @desc it is used to for gridsearch or if you want to use basic search
   * @param {string|array} $url is a basic query. it can be either string or array
   * @return {array} return the result from elastic search
   */
  public static function gridSearch($url){
    $url = is_array($url) ? http_build_query($url) : $url;
    return self::_finalizeData(curl::get(self::$_host . $url, self::$_header));
  }
  /**
   * @desc Check to see if the given index is exist in the elasticsearch 
   * @param {string} $index name of the index 
   * @return {boolen} 1 if exist and 0 if not exist
   */
  public static function isIndexExist($index){
    return (curl::head(self::$_host . $index)->status_code == '200') ? 1 : 0;
  }
//------------------------------------------------------------------------------
  /**
   * @desc this is for generate search. the different between this function and 
   *  gridSearch is that gridSearch function is used specifically for grid table 
   *  only and it is used basic query
   * @param {array} $params is parameters for elastic search. You can get more 
   *  info from elastic search website elasticsearch
   * @return {array} return the result from elastic search
   */
  public static function search($params){
    return Elasticsearch::search($params);
  }
//------------------------------------------------------------------------------
  /**
   * @desc insert the data into elastic search
   * @param {array} $r is the data getting from the database
   * @param {string} $index is used for both index and type
   * @return {boolen} return true if it is inserted and false if it is not inserted
   */
  public static function insert($r, $index){
    $doc = ['body' => []];
    foreach($r as $v){
      $doc['body'][] = [
        'index' => [
          '_index' => $index,
          '_type' => $index,
          '_id' => $v['id']
        ]
      ];
      $doc['body'][]  = $v;
    }
    return Elasticsearch::bulk($doc);
  }
//------------------------------------------------------------------------------
  /**
   * @desc
   * @param
   * @return 
   */
  public static function delete($index){
    return Elasticsearch::delete($index);
  }
  
//------------------------------------------------------------------------------
  /**
   * @desc
   * @param {string} $inxed the index of the elastic search
   * @return 
   */
  public static function getMapFieldElastic($index){
    $rTmp = Elastic::gridSearch($index . '/_mapping');
    $r    = isset($rTmp[$index]['mappings']) ? $rTmp[$index]['mappings'][$index]['properties'] : [];
    $data = [];
    foreach($r as $view=>$v){
      $data[$view] = $v;
    }
    return $data;
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  /**
   * @desc
   * @param
   * @return 
   */
  private static function _finalizeData($data){
    $r = get_object_vars($data);
    if($r['status_code'] == 200){
      return json_decode($r['body'], true);
    }
    return $r; 
  }
}