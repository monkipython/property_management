<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
class trust_view{
  private static $_prop = 'prop_id, prop, prop_class, prop_type, cash_accrual, prop_name, line2, street, city, state, zip, phone, name_1099, start_date, tax_date, man_fee_date, last_raise_date, man_flat_amt, man_pct, po_value, sq_feet, number_of_units, county, map_code, fed_id, state_id, ar_bank, ar_contra, ar_sum_acct, ar_sum_contra, ap_bank, ap_contra, ap_sum_acct, ap_sum_contra, usid, sys_date, year_end, first_month, next_year_end, start_year, last_year_end, start_last_year, year_end_acct, group1, group2, cons1, cons2, cons_flg, post_flg, tax_rate, mangt_pct, comm_pct, trans_code_1099, state_ui_pct, dep_int_pct, dep_int_code1, dep_int_code2, dep_int_pay, late_rate_code, late_rate, late_lump_amt, late_max_amt, late_min_amt, late_amt3, late_pc61, late_select_flg, late_int_pct, late_day2, late_day3, vendor_list, no_post_dt, ap_inv_edit, partner_no, man_flg, rock, bank_reserve, raise_pct, raise_pct2, ownergroup, mangtgroup, mangtgroup2';
  //private static $_propBank = ['pb.prop', 'pb.bank', 'pb.gl_acct', 'pb.trust', 'pb.recon_prop', 'pb.usid', 'pb.sys_date', 'pb.rock'];
  //private static $_bank = ['b.prop', 'b.bank', 'b.name', 'b.br_name', 'b.street', 'b.city', 'b.state', 'b.zip', 'b.phone', 'b.last_check_no', 'b.bank_bal', 'b.bank_reg', 'b.transit_cp', 'b.cp_acct', 'b.transit_cr', 'b.cr_acct', 'b.usid', 'b.sys_date', 'b.print_bk_name', 'b.print_prop_name', 'b.two_sign', 'b.void_after', 'b.rock', 'b.dump_group'];
  //private static $_unit = ['u.unit_id', 'u.prop', 'u.unit', 'u.building', 'u.floor', 'u.street', 'u.unit_no', 'u.remark', 'u.curr_tenant', 'u.future_tenant', 'u.owner', 'u.past_tenant', 'u.rent_rate', 'u.market_rent', 'u.sec_dep', 'u.sq_feet', 'u.sq_feet2', 'u.count_unit', 'u.move_in_date', 'u.move_out_date', 'u.usid', 'u.sys_date', 'u.status', 'u.status2', 'u.unit_type', 'u.style', 'u.bedrooms', 'u.bathrooms', 'u.rock', 'u.cd_enforce_dt1', 'u.cd_enforce_dt2', 'u.pad_size', 'u.mh_owner', 'u.must_pay', 'u.mh_serial_no'];
  public  static $maxChunk = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$prop];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
  
    foreach($data as $i => $val){
      $data[$i]['id'] = $val['prop_id'];
      
      $data[$i]['street'] = title_case($val['street']);
      $data[$i]['city'] = title_case($val['city']);
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
        $whereStr .= ' AND ' . $field .' IN (' . implode(',', $id) . ')';
      }
    }
    
    $_join = function($prefix, $str){
      return $prefix . '.' . implode(',' . $prefix . '.', explode(',', preg_replace('/[\s+]/', '',$str)));
    };
    
    return 'SELECT ' . $_join('p',self::$_prop) . 
      ' FROM ' . T::$prop . ' AS p ' .
      'WHERE p.prop LIKE "*%" ' . Model::getRawWhere($where);
  }
}