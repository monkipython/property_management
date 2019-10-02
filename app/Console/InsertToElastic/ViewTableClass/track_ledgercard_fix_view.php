<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use App\Http\Models\Model; // Include the models class
class track_ledgercard_fix_view{
  private static $_trackLedgercardFix = 'track_ledgercard_fix_id, cntl_no, batch_group, cdate, udate, usid';
  public  static $maxChunk   = 100000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$trackLedgerCardFix];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return [];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    foreach($data as $i=>$val){
      $val['id'] = $val['track_ledgercard_fix_id'];
      $data[$i] = $val;
    }
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function getSelectQuery($where = []){
    $isEndSelect = 1;
    $data = 'SELECT ' . Helper::joinQuery('t',self::$_trackLedgercardFix, $isEndSelect) . ' FROM ' . T::$trackLedgerCardFix . ' AS t';
    return $data;
  }
}