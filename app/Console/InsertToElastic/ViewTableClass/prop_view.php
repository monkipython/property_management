<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Http\Models\Model;
use App\Library\{TableName AS T, Helper};
class prop_view{
  private static $_prop = 'prop_id, prop, prop_class,prop_name, prop_type, cash_accrual, line2, street, city, state, zip, phone, name_1099, start_date, tax_date, man_fee_date, last_raise_date, man_flat_amt, man_pct, po_value, sq_feet, county, map_code, fed_id, state_id, ar_bank, ar_contra, ar_sum_acct, ar_sum_contra, ap_bank, ap_contra, ap_sum_acct, ap_sum_contra, usid, sys_date, year_end, first_month, next_year_end, start_year, last_year_end, start_last_year, year_end_acct, group1, group2, cons1, cons2, cons_flg, post_flg, tax_rate, mangt_pct, comm_pct, trans_code_1099, state_ui_pct, dep_int_pct, dep_int_code1, dep_int_code2, dep_int_pay, late_rate_code, late_rate, late_lump_amt, late_max_amt, late_min_amt, late_amt3, late_pc61, late_select_flg, late_int_pct, late_day2, late_day3, vendor_list, no_post_dt, ap_inv_edit, partner_no, man_flg, rock, bank_reserve, raise_pct, raise_pct2, ownergroup, mangtgroup, mangtgroup2, rent_type, isFreeClear';
  private static $_propBank = ['pb.gl_acct', 'pb.trust'];
  private static $_bank = ['b.prop', 'b.bank', 'b.name', 'b.br_name', 'b.street', 'b.city', 'b.state', 'b.zip', 'b.phone', 'b.last_check_no', 'b.bank_bal', 'b.bank_reg', 'b.transit_cp', 'b.cp_acct', 'b.transit_cr', 'b.cr_acct', 'b.usid','b.print_bk_name', 'b.print_prop_name', 'b.two_sign', 'b.void_after', 'b.dump_group'];
  private static $_unit = ['u.unit_id', 'u.prop', 'u.unit', 'u.building', 'u.floor', 'u.street', 'u.unit_no', 'u.remark', 'u.curr_tenant', 'u.future_tenant', 'u.owner', 'u.past_tenant', 'u.rent_rate', 'u.market_rent', 'u.sec_dep', 'u.sq_feet', 'u.sq_feet2', 'u.count_unit', 'u.move_in_date', 'u.move_out_date', 'u.usid', 'u.sys_date', 'u.status', 'u.status2', 'u.unit_type', 'u.style', 'u.bedrooms', 'u.bathrooms', 'u.cd_enforce_dt1', 'u.cd_enforce_dt2', 'u.pad_size', 'u.mh_owner', 'u.must_pay', 'u.mh_serial_no'];
  public  static $maxChunk = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$prop, T::$propBank, T::$bank, T::$unit];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
          
    foreach($data as $i => $val){
      $data[$i]['id'] = $val['prop_id'];
      $propBanks      = !empty($data[$i][T::$propBank]) ? explode('|',$data[$i][T::$propBank]) : [];
      $banks          = !empty($data[$i][T::$bank]) ? explode('|',$data[$i][T::$bank]) : [];
      $units          = !empty($data[$i][T::$unit]) ? explode('|',$data[$i][T::$unit]) : [];
      unset($data[$i][T::$propBank],$data[$i][T::$bank],$data[$i][T::$unit]);
      
      $data[$i]['street']    = title_case($val['street']);
      $data[$i]['city']      = title_case($val['city']);
      $data[$i]['county']    = title_case($val['county']);
      
      ## DEAL WITH BANK DATA
      if(!empty($banks)){
        foreach($banks as $j => $v){
          $p = explode('~',$v);
          foreach(self::$_bank as $k => $field){
            $field = preg_replace('/b\./','',$field);
            $data[$i][T::$bank][$j][$field] = isset($p[$k])? $p[$k] : '';
          }
        }
        ## BANK_BAL IS USED AS BANK COUNT
        $data[$i]['bank_bal'] = count($data[$i][T::$bank]);
      }else {
        $data[$i]['bank_bal'] = 0;
      }
      
      ## DEAL WITH PROPERTY BANK DATA
      if(!empty($propBanks)){
        foreach($propBanks as $j => $v){
          $p = explode('~',$v);
          foreach(self::$_propBank as $k => $field){
            $field = preg_replace('/pb\./','',$field);
            if($field == 'gl_acct' && !empty($data[$i][T::$bank][$j])) {
              $data[$i][T::$bank][$j][$field] = isset($p[$k])? $p[$k] : '';
            }
            if($field == 'trust' && isset($p[$k])) {	
              $data[$i]['trust'] = $p[$k];	
            }
          }
        }
      }
      
      ## DEAL WITH UNIT DATA
      if(!empty($units)){
        $unitType = ['A', 'B', 'C', 'H', 'I', 'M', 'O', 'R', 'T', 'W'];
        $rentRate = 0;
        $unitCount = 0;
        foreach($units as $j => $v){
          $p = explode('~',$v);
          foreach(self::$_unit as $k => $field){
            $field = preg_replace('/u\./','',$field);
            if($field == 'street') {
              $data[$i][T::$unit][$j][$field] = isset($p[$k])? title_case($p[$k]) : '';
            }else {
              $data[$i][T::$unit][$j][$field] = isset($p[$k])? $p[$k] : ''; 
            }
            $rentRate  += ($field == 'rent_rate' && isset($p[$k]) && is_numeric($p[$k])) ? $p[$k] : 0;
            $unitCount += ($field == 'unit_type' && isset($p[$k]) && in_array($p[$k], $unitType)) ? 1 : 0;
          }
        }
        $data[$i]['number_of_units'] = $unitCount;
        $data[$i]['rent_rate'] = $rentRate;
      }else {
        $data[$i]['number_of_units'] = 0;
        $data[$i]['rent_rate'] = 0;
        
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function getSelectQuery($where = []){
    $_join = function($prefix, $str){
      return $prefix . '.' . implode(',' . $prefix . '.', explode(',', preg_replace('/[\s+]/', '',$str)));
    };
    
    $data = 'SELECT ' . $_join('p',self::$_prop) . ',p2.prop_name AS entity_name, 
      GROUP_CONCAT(DISTINCT ' . implode(',"~",',self::$_propBank) . ' SEPARATOR "|") AS ' . T::$propBank . ', 
      GROUP_CONCAT(DISTINCT ' . implode(',"~",',self::$_bank) . ' SEPARATOR "|") AS ' . T::$bank . ', 
      GROUP_CONCAT(DISTINCT ' . implode(',"~",',self::$_unit) . ' SEPARATOR "|") AS '. T::$unit . ' 
      FROM ' . T::$prop . ' AS p 
      INNER JOIN ' . T::$propBank . ' AS pb ON pb.prop=p.prop AND p.prop NOT LIKE "#%" AND p.prop NOT LIKE "*%" ' . Model::getRawWhere($where) . ' 
      LEFT JOIN ' . T::$bank . ' AS b ON b.prop=pb.trust AND b.bank = pb.bank 
      LEFT JOIN ' . T::$unit . ' AS u ON u.prop=p.prop 
      LEFT JOIN  ' . T::$prop .  ' AS p2 ON p2.prop=pb.trust
      GROUP BY p.prop
      ORDER BY prop_id ASC';
    return $data;
  }
}
