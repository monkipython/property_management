<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use App\Http\Models\Model;

class gl_chart_view{
  public static $maxChunk = 100000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$glChart];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    $mapping = Helper::getMapping(['tableName'=>T::$glChart]);
    foreach($data as $i=>$val){
      $data[$i]['id'] = $val['gl_chart_id'];
      $data[$i]['acct_type'] = isset($mapping['acct_type'][$val['acct_type']]) ? $mapping['acct_type'][$val['acct_type']] : $val['acct_type'];
      $data[$i]['type1099']  = isset($mapping['type1099'][$val['type1099']]) ? $mapping['type1099'][$val['type1099']] : $val['type1099'];
      $data[$i]['no_post']  = isset($mapping['no_post'][$val['no_post']]) ? $mapping['no_post'][$val['no_post']] : $val['no_post'];
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    return '
      SELECT * FROM ' . T::$glChart . ' ' . preg_replace('/ AND /', ' WHERE ', Model::getRawWhere($where));
  }
}