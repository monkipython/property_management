<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;
use Illuminate\Support\Facades\DB;
/*
ALTER TABLE `ppm`.`bank_trans` 
ADD COLUMN `bank_id` INT(10) NOT NULL DEFAULT '0' AFTER `bank_trans_id`,
ADD COLUMN `usid` VARCHAR(255) NOT NULL DEFAULT '' AFTER `updated_by`;

ALTER TABLE `ppm`.`bank_trans` 
ADD INDEX `bank_rec_bank_id` (`bank_id` ASC);
*/
class bank_trans_view {
  private static $_bank_trans = 'bank_trans_id, bank_id, trust, bank, date, journal, batch, check_no, amount, cleared_date, remark, source, match_id, cdate, udate, usid';
  public static $maxChunk = 50000;
 
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$bankTrans,T::$prop,T::$bank,T::$propBank];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $data    = !empty($data) ? Helper::encodeUtf8($data) : [];
    $bankIds = array_column($data,'bank');
    //$bankIds = array_column($data,'bank_id');
    $rBank   = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$bankView,
      '_source'  => ['bank_id','bank','ap_bank','ar_bank','last_check_no','cr_acct','cp_acct','name','br_name','entity_name','po_value'],
      'query'    => [
        'must'   => [
          'bank.keyword' => $bankIds,
          //'bank_id' => $bankIds,
        ]
      ]
    ]),'bank');
    
    foreach($data as $i => $v){
      $data[$i]['id']    = $v['bank_trans_id'];
      
      //$bank                      = Helper::getValue($v['bank_id',$rBank,[]);
      $bank                      = Helper::getValue($v['bank'],$rBank,[]);
      $data[$i]['ap_bank']       = Helper::getValue('ap_bank',$bank);
      $data[$i]['ar_bank']       = Helper::getValue('ar_bank',$bank);
      $data[$i]['last_check_no'] = Helper::getValue('last_check_no',$bank);
      $data[$i]['cr_acct']       = Helper::getValue('cr_acct',$bank);
      $data[$i]['cp_acct']       = Helper::getValue('cp_acct',$bank);
      $data[$i]['name']          = Helper::getValue('name',$bank);
      $data[$i]['br_name']       = Helper::getValue('br_name',$bank);
      $data[$i]['entity_name']   = Helper::getValue('entity_name',$bank);
      $data[$i]['po_value']      = Helper::getValue('po_value',$bank,0);
    }
     
    return $data;
  }
//------------------------------------------------------------------------------    
  public static function getSelectQuery($where=[]){
    return 'SELECT ' . Helper::joinQuery('bt',self::$_bank_trans,1) . ' FROM ' . T::$bankTrans . ' AS bt ' . preg_replace('/ AND /',' WHERE ',Model::getRawWhere($where));
  }
}