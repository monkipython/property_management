<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Http\Models\Model;
use App\Library\{TableName AS T};

class accountrole_view{
  public static $maxChunk = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$accountRole];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? $data : [];
    foreach($data as $i=>$v){
      $data[$i]['id'] = $v['accountRole_id'];
    }
    return $data;
  }
//------------------------------------------------------------------------------
   public static function getSelectQuery($where = []){
    return 'SELECT * FROM ' . T::$accountRole . ' AS a ' . preg_replace('/ AND /',' WHERE ',Model::getRawWhere($where));
   
  }
}