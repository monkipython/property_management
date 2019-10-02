<?php
namespace App\Library;
use \Elasticsearch;
use Requests AS curl;

class Elastic{
  private static $_header = ['Content-Type'=>'application/json'];
  private static $_options = ['timeout'=>'100000'];
  /**
   * @desc it is used to for gridsearch or if you want to use basic search
   * @param {string|array} $url is a basic query. it can be either string or array
   * @return {array} return the result from elastic search
   */
  public static function gridSearch($url){
    $url = is_array($url) ? http_build_query($url) : $url;
    return self::_finalizeData(curl::get(self::_getHost() . $url, self::$_header, self::$_options));
  }
//------------------------------------------------------------------------------
  /**
   * Example: 
   *  $r = Elastic::searchQuery([
        'index'  =>$this->_viewTable,
        '_source'=>['unit', 'prop.city', 'prop.group1', 'prop.prop_type'],
        'sort'   =>[[$sortKey=>'asc', 'prop.prop_type.keyword'=>'asc', 'prop.prop.keyword'=>'asc', 'unit.keyword'=>'asc']],
        'query'  =>[
          'must'  =>[
            'status.keyword'=>'V', 
            'range' => [
              'move_out_date' => [
                'gte'    => '9999-12-31',
                'lte'    => '9999-12-31',
                'format' => 'yyyy-MM-dd',
              ]
            ]
          ],
         'should'  =>[
            'status.keyword'=>'V', 
            'range' => [
              'move_out_date' => [
                'gte'    => '9999-12-31',
                'lte'    => '9999-12-31',
                'format' => 'yyyy-MM-dd',
              ]
            ]
          ],
         'must_not'  =>[
            'status.keyword'=>'V', 
            'range' => [
              'move_out_date' => [
                'gte'    => '9999-12-31',
                'lte'    => '9999-12-31',
                'format' => 'yyyy-MM-dd',
              ]
            ]
          ],
          'filter'=>['prop.prop'=>$prop], 
        ]
     ]);
   */
  public static function searchQuery($data){
//    $query     = [];
//    $dataQuery = !empty($data['query']) ? $data['query'] : [];
//    foreach($dataQuery as $type=>$val){
//      foreach($val as $k=>$v){
//        switch($k){
//          case 'regexp':
//          case 'range': 
//          case 'match':
//          case 'dis_max': 
//          case 'wildcard':
//            $query[$type][][$k] = $v;
//            break;
//          default:
//            $term = is_array($v) ? 'terms' : 'term';
//            $query[$type][][$term][$k] = $v; 
//            break;
//        }
//      }
//    }
//    
//    $params = [
//      'index'=> $data['index'],
//      'type' => $data['index'],
//      'size' => isset($data['size']) ? $data['size'] : 50000,
//      'from' => isset($data['from']) ? $data['from'] : 0
//    ];
//
//    if(!empty($query)){
//      $params['body']['query'] = ['bool'=>$query];
//    }
//    if(!empty($data['aggs'])){
//      $params['body']['aggs'] = $data['aggs'];
//    }
//    if(!empty($data['sort'])){
//      $params['body']['sort'] = $data['sort'];
//    }
//    if(!empty($data['_source'])){
//      $params['body']['_source'] = $data['_source'];
//    }
    return Elasticsearch::search(self::_buildElasticQuery($data));
  }

  /**
   * @desc Check to see if the given index is exist in the elasticsearch 
   * @param {string} $index name of the index 
   * @return {boolen} 1 if exist and 0 if not exist
   */
  public static function isIndexExist($index){
    return (curl::head(self::_getHost() . $index)->status_code == '200') ? 1 : 0;
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
  public static function searchMatch($index, $query, $size = 1){
    return self::search([
      'index'=>$index, 
      'type'=>$index, 
      'size'=>$size, 
      'body'=>[
        'query' =>$query
      ]]);
  }
//------------------------------------------------------------------------------
  /**
   * @desc insert the data into elastic search
   * @param {array} $r is the data getting from the database
   * @param {string} $index is used for both index and type
   * @param {string} $field is used to determine if what is the type for dynamic mapping in the elastic search
   * @return {boolen} return true if it is inserted and false if it is not inserted
   */
  public static function insert($r, $index, $field = []){
    $doc = ['body' => []];
    foreach($r as $i => $val){
      $doc['body'][] = [
        'index' => [
          '_index' => $index,
          '_type' => $index,
          '_id' => $val['id']
        ]
      ];
      $doc['body'][]  = self::_castValue($field, $val);
    }
    return Elasticsearch::bulk($doc);
  }
//------------------------------------------------------------------------------
  public static function update($updateData, $index, $id){
    $doc = [
      'index' => $index,
      'type'  => $index,
      'id' => $id,
      'body' => [
        'doc' => $updateData
      ]
    ];
    return Elasticsearch::update($doc);
  }
//------------------------------------------------------------------------------
  /**
   * @desc delete a document from an elastic search index
   * @param {string} name of the index in Elastic search that is having a document removed from it
   * @param {string|integer} Document id of the document being removed
   * @return {array} Response from Elastic search cluster
   */
  public static function delete($index, $id){
    $doc = [
      'index' => $index,
      'type'  => $index,
      'id'    => $id
    ]; 
    return Elasticsearch::delete($doc);
  }
//------------------------------------------------------------------------------
  public static function deleteByQuery($data){
//    $query     = [];
//    $dataQuery = !empty($data['query']) ? $data['query'] : [];
//    foreach($dataQuery as $type=>$val){
//      foreach($val as $k=>$v){
//        switch($k){
//          case 'regexp':
//          case 'range': 
//          case 'match':
//          case 'dis_max': 
//          case 'wildcard':
//            $query[$type][][$k] = $v;
//            break;
//          default:
//            $term = is_array($v) ? 'terms' : 'term';
//            $query[$type][][$term][$k] = $v; 
//            break;
//        }
//      }
//    }
//    
//    $params = [
//      'index'=> $data['index'],
//      'type' => $data['index'],
//    ];
//
//    if(!empty($query)){
//      $params['body']['query'] = ['bool'=>$query];
//    }
//    if(!empty($data['aggs'])){
//      $params['body']['aggs'] = $data['aggs'];
//    }
//    if(!empty($data['sort'])){
//      $params['body']['sort'] = $data['sort'];
//    }
//    if(!empty($data['_source'])){
//      $params['body']['_source'] = $data['_source'];
//    }
    $params = self::_buildElasticQuery($data);
    unset($params['from']);
    return Elasticsearch::deleteByQuery($params);
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
//------------------------------------------------------------------------------
  public static function getElasticQueryBody($dataStr,$field,$key='should'){
    $dataStr    = preg_replace('/\s+/','',$dataStr);
    $values     = explode(',',$dataStr);
    $ranges     = [];
    
    foreach($values as $i=>$v) {
      if(strpos($v, '-') !== false) {
        $ranges[] = explode('-', $v);
        unset($values[$i]);
      }
    }
    
    $queryBody  = [
      $key         => [
        ['terms'   => [$field=>array_values($values)]],
      ]
    ];
    
    foreach($ranges as $i => $v){
      $rangeBody                   = ['range'=>[$field=>[]]];
      $rangeBody['range'][$field] += !empty($v[0]) ? ['gte'=>$v[0]] : [];
      $rangeBody['range'][$field] += !empty($v[1]) ? ['lte'=>$v[1]] : [];
      if(!empty($rangeBody['range'][$field])){
        $queryBody[$key][]         = $rangeBody;
      }
    }
    return $queryBody;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private static function _getHost(){
    return env('ELASTICSEARCH_SCHEME') . '://' . env('ELASTICSEARCH_HOST') . ':' . env('ELASTICSEARCH_PORT') . '/';
  }
//------------------------------------------------------------------------------
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
//------------------------------------------------------------------------------
  private static function _castValue($field, $data){
    $error = 'Error: Check _getTableOfView function in InsertToElastic class to see if you have all join table in there';
    
    if(isset($field)){
      $_startCastVal = function($type, $val){
        switch ($type){
          case 'decimal': return floatval($val);
          case 'integer': return intval($val);
          case 'date':    return date('Y-m-d', strtotime($val));
          case 'datetime': return date('Y-m-d', strtotime($val));
          default: return $val;
        }
      };
      
      foreach($data as $key=>$value){
        if($key != 'id'){
          if(is_array($value)){
            foreach($value as $i=>$val){
              foreach($val as $k=>$v){
                if(isset($field[$k])){
//                  echo $k . '=' . $field[$k]['class'] . "\n";
                  $data[$key][$i][$k] = $_startCastVal($field[$k]['class'], $v);
                } else{
                  dd($key . ' OR ' . $k . ': ' . $error);
                }
              }
            }
          } else{
            if(isset($field[$key])){
//              if($key == 'cdate'){
//                dd($field[$key]['class'], $_startCastVal($field[$key]['class'], $value));
//              }
              $data[$key] = $_startCastVal($field[$key]['class'], $value);
            } else{
              dd($key . ': ' . $error);
            }
          }
        }
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private static function _buildElasticQuery($data){
    $query     = [];
    $dataQuery = !empty($data['query']) ? $data['query'] : [];
    if(isset($dataQuery['raw'])){
      $query = $dataQuery['raw']; 
    } else{
      foreach($dataQuery as $type=>$val){
        foreach($val as $k=>$v){
          switch($k){
            case 'regexp':
            case 'range': 
            case 'match':
            case 'dis_max':
              $query[$type][][$k] = $v;
              break;
            case 'wildcard':
              foreach($v as $field=>$value){
                $query[$type][][$k][$field] = $value;
              }
              break;
            default:
              $term = is_array($v) ? 'terms' : 'term';
              $query[$type][][$term][$k] = $v; 
              break;
          }
        }
      }
    }
    
    $params = [
      'index'=> $data['index'],
      'type' => $data['index'],
      'size' => isset($data['size']) ? $data['size'] : 10000,
      'from' => isset($data['from']) ? $data['from'] : 0
    ];

    if(!empty($query)){
      $params['body']['query'] = ['bool'=>$query];
    }
    if(!empty($data['aggs'])){
      $params['body']['aggs'] = $data['aggs'];
    }
    if(!empty($data['sort'])){
      $params['body']['sort'] = $data['sort'];
    }
    if(!empty($data['_source'])){
      $params['body']['_source'] = $data['_source'];
    }
//    dd($params);
    return $params;
  }
}