<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model; // Include the models class

class bank_view{
  private static $_bank = 'bank_id, name, br_name, street, city, state, zip, phone, last_check_no, bank_bal, bank_reg, transit_cp, cp_acct, transit_cr, cr_acct, usid, sys_date, print_bk_name, print_prop_name, two_sign, void_after, rock, dump_group, remark';
  private static $_prop = 'prop_id, prop_class, prop_type, cash_accrual, line2, name_1099, start_date, tax_date, man_fee_date, last_raise_date, man_flat_amt, man_pct, po_value, sq_feet, number_of_units, county, map_code, fed_id, state_id, ar_bank, ar_contra, ar_sum_acct, ar_sum_contra, ap_bank, ap_contra, ap_sum_acct, ap_sum_contra, year_end, first_month, next_year_end, start_year, last_year_end, start_last_year, year_end_acct, group1, group2, cons1, cons_flg, post_flg, tax_rate, mangt_pct, comm_pct, trans_code_1099, state_ui_pct, dep_int_pct, dep_int_code1, dep_int_code2, dep_int_pay, late_rate_code, late_rate, late_lump_amt, late_max_amt, late_min_amt, late_amt3, late_pc61, late_select_flg, late_int_pct, late_day2, late_day3, vendor_list, no_post_dt, ap_inv_edit, partner_no, man_flg, bank_reserve, raise_pct, raise_pct2, ownergroup, mangtgroup, mangtgroup2';
  private static $_propBank = 'prop_bank_id, prop, bank, trust,gl_acct';
  public static $maxChunk = 10000;
  
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$bank, T::$propBank, T::$prop];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------ 
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    foreach($data as $i => $val){
      $data[$i]['id'] = $val['prop_bank_id'];
      
      $data[$i]['street'] = title_case($val['street']);
      $data[$i]['city'] = title_case($val['city']);
    }
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function getSelectQuery($where = []){
    return 'SELECT ' . Helper::joinQuery('b',self::$_bank) . ' p2.prop_name AS entity_name'  . ',' . Helper::joinQuery('p',self::$_prop) . Helper::joinQuery('pb',self::$_propBank, 1)
      . ' FROM ' . T::$bank . ' AS b ' .
      'INNER JOIN ' . T::$propBank . ' AS pb ON b.bank = pb.bank AND b.prop = pb.trust ' . Model::getRawWhere($where) . ' ' .
      'INNER JOIN ' . T::$prop . ' AS p ON pb.prop = p.prop ' .
      'INNER JOIN ' . T::$prop . ' AS p2 ON pb.trust = p2.prop ' .
      'WHERE p.prop NOT LIKE "#%" '. 
      'GROUP BY pb.prop_bank_id';
  }
}