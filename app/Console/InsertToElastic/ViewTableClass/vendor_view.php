<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};

class vendor_view{
  private static $_vendor = 'vendor_id, vendid, name, line2, street, city, state, zip, phone, fax, e_mail, web, gl_acct, name_key, rate, auto_age, terms, discount, flg_1099, fed_id, vendor_type, tin_type, tin_date, ins_carrier, ins_date, contr_no, remarks, one_inv, pay_code, bk_transit, ach_flg, bank_lock, bank_acct_no, acct_with_vend, name_1099, inv_edit, usid, sys_date, rock, passcode';
  private static $_fileUploadViewSelect = [
    'f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'
  ];
  public static $maxChunk = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendor, T::$fileUpload];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    foreach($data as $i=>$val){
      $data[$i]['id'] = $val['vendor_id'];
      $fileUpload = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
      unset($data[$i][T::$fileUpload]);
      
      # DEAL WITH FILEUPLOAD
      if(!empty($fileUpload)){
        foreach($fileUpload as $j=>$v){
          $p = explode('~', $v);
          foreach(self::$_fileUploadViewSelect as $k=>$field){
            $field = preg_replace('/f\./', '', $field);
            $data[$i][T::$fileUpload][$j][$field] = isset($p[$k]) ? $p[$k] : '';
          }
        }
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    $whereStr = '';
    if(!empty($where)){
      foreach($where as $field=>$val){
        $id = [];
        foreach($val as $idNum){
          if(is_numeric($idNum)){
            $id[] = $idNum;
          }
        }
        $whereStr .= ' AND '.$field.' IN (' . implode(',', $id) . ')';
      }
    }
    $whereStr = empty($whereStr) ? '' : preg_replace('/^ AND /', ' WHERE ', $whereStr);
    
    $_join = function($prefix, $str){
      return $prefix . '.' . implode(',' . $prefix . '.', explode(',', preg_replace('/[\s+]/', '',$str)));
    };
    
    return 'SELECT '. $_join('v',self::$_vendor) . ',' .
      ' GROUP_CONCAT(DISTINCT ' . implode(',"~",', self::$_fileUploadViewSelect) . ' SEPARATOR "|") AS ' . T::$fileUpload . 
      ' FROM ' . T::$vendor . ' AS v ' . 
      ' LEFT JOIN ' . T::$fileUpload . ' AS f ON v.vendor_id=f.foreign_id AND f.type="vendors" AND f.active=1 ' .$whereStr . ' ' .  
      ' GROUP BY v.vendor_id'; 
  }
}