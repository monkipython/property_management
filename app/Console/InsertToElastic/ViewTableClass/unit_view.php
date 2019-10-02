<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Http\Models\Model;
use App\Library\{TableName AS T,Helper};

/*
ALTER TABLE `ppm`.`unit` 
CHANGE COLUMN `mh_owner` `mh_owner` VARCHAR(255) NOT NULL DEFAULT '' ;
ADD COLUMN `unit_size` VARCHAR(255) DEFAULT '' AFTER `late_charge`;
 */
class unit_view {
  private static $_unit         = 'unit_id, unit, building, floor, street, curr_tenant, future_tenant, unit_no, owner, past_tenant, rent_rate,market_rent, late_charge, remark, sec_dep, sq_feet, sq_feet2, count_unit, move_in_date, move_out_date, status, status2, unit_type, style, bedrooms, bathrooms, rock, cd_enforce_dt1, cd_enforce_dt2, pad_size, mh_owner, must_pay, mh_serial_no, unit_size, usid';
  private static $_unitDate     = ['ud.date_code','ud.last_date', 'ud.next_date', 'ud.amount', 'ud.remark', 'ud.vendor'];
  private static $_unitHist     = ['uh.building', 'uh.floor', 'uh.street', 'uh.curr_tenant', 'uh.future_tenant', 'uh.unit_no', 'uh.owner', 'uh.past_tenant', 'uh.rent_rate','uh.market_rent', 'uh.remark', 'uh.sec_dep', 'uh.sq_feet', 'uh.sq_feet2', 'uh.count_unit','uh.move_in_date', 'uh.move_out_date', 'uh.status', 'uh.status2', 'uh.unit_type', 'uh.style', 'uh.bedrooms', 'uh.bathrooms', 'uh.rock', 'uh.cd_enforce_dt1', 'uh.cd_enforce_dt2', 'uh.pad_size', 'uh.mh_owner', 'uh.must_pay', 'uh.mh_serial_no','uh.late_charge','uh.usid'];
  private static $_unitFeatures = ['uf.persons', 'uf.furnished','uf.pets', 'uf.stove', 'uf.refrigerator', 'uf.parking_spaces', 'uf.desirability', 'uf.other', 'uf.total_rooms', 'uf.microwave', 'uf.dishwasher' ,'uf.garbage_disposal', 'uf.fireplace', 'uf.den_study', 'uf.security', 'uf.carpet_color','uf.mini_blinds', 'uf.bay_window', 'uf.carport_garage', 'uf.wet_bar', 'uf.ceiling_fan', 'uf.ice_maker', 'uf.walk_in_closet', 'uf.enclosed_patio'];
  private static $_prop         = ['p.prop', 'p.prop_class', 'p.trust','p.prop_type', 'p.cash_accrual', 'p.prop_name', 'p.line2', 'p.street', 'p.city', 'p.state', 'p.zip', 'p.phone', 'p.name_1099', 'p.start_date', 'p.tax_date', 'p.man_fee_date', 'p.last_raise_date', 'p.man_flat_amt', 'p.man_pct', 'p.po_value', 'p.sq_feet', 'p.number_of_units', 'p.county', 'p.map_code', 'p.fed_id', 'p.state_id', 'p.ar_bank', 'p.ar_contra', 'p.ar_sum_acct', 'p.ar_sum_contra', 'p.ap_bank', 'p.ap_contra', 'p.ap_sum_acct', 'p.ap_sum_contra', 'p.year_end', 'p.first_month', 'p.next_year_end', 'p.start_year', 'p.last_year_end', 'p.start_last_year', 'p.group1', 'p.group2', 'p.cons1', 'p.cons2', 'p.post_flg', 'p.tax_rate','p.mangtgroup', 'p.mangt_pct', 'p.comm_pct', 'p.trans_code_1099', 'p.state_ui_pct', 'p.dep_int_pct', 'p.dep_int_code1', 'p.dep_int_code2', 'p.dep_int_pay', 'p.late_rate_code', 'p.late_lump_amt', 'p.late_max_amt', 'p.late_min_amt', 'p.late_amt3', 'p.rent_type','p.late_pc61'];
  
  public static $maxChunk  = 10000;

//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$unit,T::$prop,T::$unitFeatures,T::$unitHist,T::$unitDate];
  }
//------------------------------------------------------------------------------    
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    foreach($data as $i=>$val){
      $data[$i]['id'] = $val['unit_id'];
      $props     = !empty($data[$i][T::$prop]) ? explode('|',$data[$i][T::$prop]) : [];
      $features  = !empty($data[$i][T::$unitFeatures]) ? explode('|',$data[$i][T::$unitFeatures]) : [];
      $dates     = !empty($data[$i][T::$unitDate]) ? explode('|',$data[$i][T::$unitDate]) : [];
      $histories = !empty($data[$i][T::$unitHist]) ? explode('|',$data[$i][T::$unitHist]) : [];
      unset($data[$i][T::$prop],$data[$i][T::$unitFeatures],$data[$i][T::$unitDate],$data[$i][T::$unitHist]);
      
      $data[$i]['street'] = title_case($val['street']);
      
      ## DEAL WITH PROPERTY DATA
      if(!empty($props)){
        foreach($props as $j=>$v){
          $p = explode('~',$v);
          foreach(self::$_prop as $k=>$field){
            $field = preg_replace('/p\./','',$field);
            $data[$i][T::$prop][$j][$field] = $p[$k];
            
            if($field == 'city'){
              $data[$i][T::$prop][$j][$field] = title_case($data[$i][T::$prop][$j][$field]);
            }
            
          }
        }
      }

      ## DEAL WITH UNIT FEATURE DATA
      if(!empty($features)){
        foreach($features as $j=>$v){
          $p = explode('~',$v);
          foreach(self::$_unitFeatures as $k=>$field){
            $field = preg_replace('/uf\./','',$field);
            $data[$i][T::$unitFeatures][$j][$field] = $p[$k];
          }
        } 
      }

      ## DEAL WITH UNIT DATE DATA
      if(!empty($dates)){
        foreach($dates as $j=>$v){
          $p = explode('~',$v);
          foreach(self::$_unitDate as $k=>$field){
            $field = preg_replace('/ud\./','',$field);
            $data[$i][T::$unitDate][$j][$field] = $p[$k];
          }
        }
      }

      ## DEAL WITH UNIT HISTORY DATA
      if(!empty($histories)){
        foreach($histories as $j=>$v){
          $p = explode('~',$v);
          foreach(self::$_unitHist as $k=>$field){
            $field = preg_replace('/uh\./','',$field);
            $data[$i][T::$unitHist][$j][$field] = isset($p[$k]) ? $p[$k] : '';
          }
        }
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------    
  public static function getSelectQuery($where = []){
    return 'SELECT ' . 
      Helper::joinQuery('u',self::$_unit) . 
      Helper::groupConcatQuery(self::$_prop,T::$prop) .
      Helper::groupConcatQuery(self::$_unitDate,T::$unitDate) .
      Helper::groupConcatQuery(self::$_unitHist,T::$unitHist) . 
      Helper::groupConcatQuery(self::$_unitFeatures,T::$unitFeatures,1) . ' 
      FROM ' . T::$unit . ' AS u 
      INNER JOIN ' . T::$prop . ' as p ON u.prop=p.prop ' . Model::getRawWhere($where) . ' 
      LEFT JOIN ' . T::$unitHist . ' as uh ON u.prop=uh.prop AND u.unit=uh.unit 
      LEFT JOIN ' . T::$unitDate . ' as ud ON u.prop=ud.prop AND u.unit=ud.unit 
      LEFT JOIN ' . T::$unitFeatures . ' as uf ON u.prop=uf.prop AND u.unit=uf.unit 
      GROUP BY u.unit, u.prop';
  }
}