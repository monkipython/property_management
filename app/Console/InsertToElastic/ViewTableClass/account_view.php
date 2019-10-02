<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Format, Helper};
use App\Http\Models\Model; // Include the models class

class account_view{
  public static $maxChunk = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$account, T::$accountRole];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? $data : [];
    foreach($data as $i=>$val){
      $val = Helper::strtolowerArray($val, ['ownGroup']);
      $val['id'] = $val['account_id'];
      $val['cellphone'] = Format::phone($val['cellphone']);
      $val['phone'] = Format::phone($val['phone']);
      $val['role'] = empty($val['role']) ? 'Customize' : $val['role'];
      $data[$i] = $val;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    return '
      SELECT a.*, ar.role, ar.rolePermission FROM ' . T::$account . ' AS a
      LEFT JOIN ' . T::$accountRole . ' AS ar ON a.accountRole_id=ar.accountRole_id ' .preg_replace('/ AND /', ' WHERE ', Model::getRawWhere($where));
  }
}