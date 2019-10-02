<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use App\Http\Models\Model;

class service_view{
  public static $maxChunk = 100000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$service];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    foreach($data as $i=>$val){
      $data[$i]['id'] = $val['service_id'];
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    return 'SELECT * FROM ' . T::$service . ' ' . preg_replace('/ AND /', ' WHERE ', Model::getRawWhere($where));
  }
}