<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use App\Http\Models\Model; // Include the models class
class track_fund_transfer_view{
  private static $_trackFundTransfer = 'track_fund_transfer_id, prop, to_prop, bank, to_bank, bank_acct, to_bank_acct, gl_acct, to_gl_acct, amount, batch, post_date, cdate, usid';
  public  static $maxChunk   = 100000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$trackFundTransfer];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    foreach($data as $i=>$val){
      $val['id'] = $val['track_fund_transfer_id'];
      $data[$i] = $val;
    }
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function getSelectQuery($where = []){
    $isEndSelect = 1;
    $data = 'SELECT ' . Helper::joinQuery('t',self::$_trackFundTransfer, $isEndSelect) . 
            ' FROM ' . T::$trackFundTransfer . ' AS t ' . preg_replace('/ AND /', ' WHERE ', Model::getRawWhere($where));
    return $data;
  }
}