<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use App\Http\Models\Model; // Include the models class

class gl_trans_view{
  public static $maxChunk   = 500000; 
  public static $maxResult  = 500000;
  public static $maxBucketChunk = 50000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return  [T::$prop, T::$glTrans]; 
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['batch' => 'gl_acct_num'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? $data : [];
    foreach($data as $i=>$val){
      $data[$i]['id']            = $val['seq'];
      $data[$i]['gl_acct_num']   = $val['gl_acct'];
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    return 'SELECT gl.* FROM ' . T::$glTrans . ' AS gl ' . preg_replace('/ AND /', ' WHERE ', Model::getRawWhere($where));
  }
}