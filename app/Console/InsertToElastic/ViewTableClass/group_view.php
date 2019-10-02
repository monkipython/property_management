<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model; // Include the models class

class group_view{
  private static $_prop = 'prop_id, prop, prop_class, prop_type, cash_accrual, prop_name, line2, street, city, state, zip, phone, name_1099, start_date, tax_date, man_fee_date, last_raise_date, man_flat_amt, man_pct, po_value, sq_feet, number_of_units, county, map_code, fed_id, state_id, ar_bank, ar_contra, ar_sum_acct, ar_sum_contra, ap_bank, ap_contra, ap_sum_acct, ap_sum_contra, usid, sys_date, year_end, first_month, next_year_end, start_year, last_year_end, start_last_year, year_end_acct, group1, group2, cons1, cons2, cons_flg, post_flg, tax_rate, mangt_pct, comm_pct, trans_code_1099, state_ui_pct, dep_int_pct, dep_int_code1, dep_int_code2, dep_int_pay, late_rate_code, late_rate, late_lump_amt, late_max_amt, late_min_amt, late_amt3, late_pc61, late_select_flg, late_int_pct, late_day2, late_day3, vendor_list, no_post_dt, ap_inv_edit, partner_no, man_flg, rock, bank_reserve, raise_pct, raise_pct2, ownergroup, mangtgroup, mangtgroup2'; public  static $maxChunk = 10000;
  private static $_fileUploadViewSelect = [
    'f.fileUpload_id', 'f.foreign_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'
  ];
  private static $_tenantStatementViewSelect = [
    'f2.fileUpload_id','f2.foreign_id','f2.name','f2.file','f2.uuid','f2.path','f2.type','f2.ext',
  ];
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$prop, T::$account,T::$fileUpload];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['firstname'=>'supervisor'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    $account = DB::table(T::$account)->select('firstname', 'lastname', 'ownGroup', 'accessGroup')->where(Model::buildWhere(['active'=>1]))->get();

    foreach($data as $i => $val){
      $fileUpload = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
      $statement  = !empty($val['tenant_statement']) ? explode('|',$val['tenant_statement']) : [];
      unset($data[$i][T::$fileUpload],$data[$i]['tenant_statement']);
      
      $data[$i]['id'] = $val['prop_id'];
      
      $data[$i]['street'] = title_case($val['street']);
      $data[$i]['city'] = title_case($val['city']);
      
      $matchedAcc = self::_findMatchedAccount($val['prop'], $account);
      $data[$i]['supervisor'] = $matchedAcc ? $matchedAcc['firstname'] . ' ' . $matchedAcc['lastname'] : '';
      
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
      
      if(!empty($statement)){
        foreach($statement as $j=>$v){
          $p = explode('~',$v);
          foreach(self::$_tenantStatementViewSelect as $k=>$field){
            $field = preg_replace('/f2\./','',$field);
            $data[$i]['tenant_statement'][$j][$field] = isset($p[$k]) ? $p[$k] : '';
          }
        }
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function getSelectQuery($where = []){
    return 'SELECT ' . Helper::joinQuery('p',self::$_prop). Helper::groupConcatQuery(self::$_fileUploadViewSelect, T::$fileUpload) . Helper::groupConcatQuery(self::$_tenantStatementViewSelect,'tenant_statement',1) . 
      ' FROM ' . T::$prop . ' AS p '.
      'LEFT JOIN ' . T::$fileUpload . ' AS f ON p.prop_id=f.foreign_id AND f.type="group" AND f.active=1 ' .
      'LEFT JOIN ' . T::$fileUpload . ' AS f2 ON p.prop_id=f2.foreign_id AND f2.type="group_tenant_statement" AND f2.active=1 ' . 
      'WHERE p.prop LIKE "#%" '  . Model::getRawWhere($where) . ' GROUP BY p.prop_id';
  }
//------------------------------------------------------------------------------
  private static function _findMatchedAccount($prop, $accounts) {
    foreach($accounts as $key => $value) {
      $accessArr = explode(', ', $accounts[$key]['accessGroup']);
      $ownArr = explode(', ', $accounts[$key]['ownGroup']);
      if(self::_in_array_case_insensitive($prop, $accessArr) && self::_in_array_case_insensitive($prop, $ownArr)) {
        return $value;
      }
    }
  }
//------------------------------------------------------------------------------
  private static function _in_array_case_insensitive($needle, $haystack) {
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
  }
}