<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, HelperMysql};
use App\Http\Models\Model;
use Illuminate\Support\Facades\DB;
class tenant_view{
  public  static $maxChunk = 12000;
  public static $maxBucketChunk = 250;
  private static $_unit    = 'building, floor, unit_no, street, remark, curr_tenant, future_tenant, owner, past_tenant, rent_rate, market_rent, sec_dep, sq_feet2, count_unit, status2, unit_type, style, bedrooms, bathrooms, cd_enforce_dt1, cd_enforce_dt2, pad_size, mh_owner, must_pay, mh_serial_no';
  //private static $_prop     = 'prop_id, prop_class, prop_type, prop_name, line2, city, state, zip, start_date, po_value, sq_feet, number_of_units, county, ar_bank, ar_contra, ap_bank, group1, group2, cons1, cons2, mangtgroup';
  private static $_tenant = 'tenant_id, prop, unit, tenant, tnt_name, base_rent, dep_held1, dep_held2, dep_held3, ytd_int_paid, dep_held_int_amt, comm_pct, tax_rate, dep_pct, late_rate1, times_late, times_nsf, dep_date, late_rate_code, last_late_date, late_after, late_amount, dep_int_last_date, lease_start_date, lease_exp_date, lease_opt_date, lease_esc_date, move_in_date, move_out_date, phone1, fax, status, spec_code, sales_off, sales_agent, usid, sys_date, tax_code, statement, terms, tenant_class, bank_acct_no, bank_transit, e_mail, web, bill_day, cash_rec_remark, bal_code, last_check_date, last_check, rock, housing_dt1, housing_dt2, return_no, passcode, billed_deposit, appl_inseq, co_signer, isManager';
  private static $_prop     = 'prop_id, prop_class, prop_type, prop_name, line2, city, state, zip, start_date, po_value, sq_feet, number_of_units, county, ar_bank, ar_contra, ap_bank, group1, group2, cons1, cons2, mangtgroup';
  private static $_billing = ['b.billing_id', 'b.active', 'b.bill_seq', 'b.service_code', 'b.seq', 'b.comm', 'b.amount', 'b.remark', 'b.remarks', 'b.start_date', 'b.stop_date', 'b.schedule', 'b.gl_acct', 'b.post_date', 'b.service_type'];
  private static $_alt     = ['alt.alt_code', 'alt.name', 'alt.line2', 'alt.street', 'alt.city', 'alt.state', 'alt.zip','alt.alt_add_id'];
  private static $_member  = ['m.mem_tnt_id','m.member', 'm.last_name', 'm.first_name', 'm.name_key', 'm.phone_bis', 'm.phone_ext', 'm.tax_id', 'm.relation', 'm.charge_card', 'm.driver_lic', 'm.car_lic', 'm.work_place', 'm.work_city', 'm.occupation', 'm.dob_date1'];
  private static $_remark_tnt = ['rt.remark_tnt_id', 'rt.remark_code', 'rt.remark_type', 'rt.amount', 'rt.date1', 'rt.remarks'];
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$tenant, T::$unit, T::$prop,T::$alterAddress, T::$billing, T::$memberTnt,T::$application,T::$fileUpload,T::$remarkTnt];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    $rApplication = Helper::keyFieldName(DB::table(T::$application)->select(['prop','unit','tenant','application_id'])->get(),['prop','unit','tenant'],'application_id');
    $rFileUpload  = Helper::groupBy(DB::table(T::$fileUpload)->select(['foreign_id','fileUpload_id','name','file','uuid','path','type','ext'])->whereIn('type',['application','agreement'])->get(),'foreign_id');
    $rProp        = Helper::keyFieldNameElastic(HelperMysql::getProp([], ['prop','prop_id', 'prop_class', 'prop_type', 'prop_name', 'line2', 'city', 'state', 'zip', 'start_date', 'po_value', 'sq_feet', 'number_of_units', 'county', 'ar_bank', 'ar_contra', 'ap_bank', 'group1', 'group2', 'cons1', 'cons2', 'mangtgroup']), 'prop');
    
    foreach($data as $i=>$val){
      $prop = !empty($data[$i]['prop']) && !empty($rProp[$data[$i]['prop']]) ? $rProp[$data[$i]['prop']] : [];
      if(!empty($prop)){
        $data[$i]['id'] = $val['tenant_id'];
        $bill = !empty($val['billing']) ? explode('|', $val['billing']) : [];
        $member = !empty($val['member']) ? explode('|', $val['member']) : [];
        $alt = !empty($val['alt']) ? explode('|', $val['alt']) : [];
        $remarks = !empty($val[T::$remarkTnt]) ? explode('|',$val[T::$remarkTnt]) : [];
        unset($data[$i]['billing'], $data[$i]['member'], $data[$i]['alt'], $data[$i][T::$remarkTnt]);

        $data[$i]['tnt_name']   = title_case($val['tnt_name']);
        $data[$i]['street']     = title_case($val['street']);

        $data[$i]['tenant']   = (int)$val['tenant'];

        # DEAL WITH PROP DATA      
        foreach($prop as $j => $v){
          if($j !== 'prop'){
            $data[$i][$j] = $j === 'city' ? title_case($v) : $v;
            $data[$i][$j] = $j === 'mangtgroup' ? strtoupper($v) : $v;
          }
        }


        # DEAL WITH BILLING DATA
        if(!empty($bill)){
          foreach($bill as $j=>$v){
            $p = explode('~', $v);
            foreach(self::$_billing as $k=>$field){
              $field = preg_replace('/b\./', '', $field);
              // printf("%s -- %s: %s\n", $data[$i]['id'], $field, $p[$k]);
              $data[$i][T::$billing][$j][$field] = isset($p[$k]) ? $p[$k] : '';
            }
          }
        }
        # DEAL WITH MEMBER DATA
        if(!empty($member)){
          foreach($member as $j=>$v){
            $p = explode('~', $v);
            foreach(self::$_member as $k=>$field){
              $field = preg_replace('/m\./', '', $field);
              $data[$i][T::$memberTnt][$j][$field] = $p[$k];
            }
          }
        }
        # DEAL WITH ALT DATA
        if(!empty($alt)){
          foreach($alt as $j=>$v){
            $p = explode('~', $v);
            foreach(self::$_alt as $k=>$field){
              $field = preg_replace('/alt\./', '', $field);
              $data[$i][T::$alterAddress][$j][$field] = $p[$k];
            }
          }
        }
        # DEAL WITH REMARK DATA
        if(!empty($remarks)){
          foreach($remarks as $j=>$v){
            $p = explode('~',$v);
            foreach(self::$_remark_tnt as $k => $field){
              $field = preg_replace('/rt\./','',$field);
              $data[$i][T::$remarkTnt][$j][$field] = isset($p[$k]) ? $p[$k] : '';
            }
          }
        }


        $id        = $val['prop'] . $val['unit'] . $val['tenant'];
        ## DEAL WITH CREDIT CHECK DATA
        $appId     = isset($rApplication[$id]) ? $rApplication[$id] : 0;
        $data[$i]['application_id'] = $appId;

        ## DEAL WITH FILEUPLOAD DATA
        $files     = isset($rFileUpload[$appId]) ? $rFileUpload[$appId] : [];
        foreach($files as $idx => $r){
          foreach($r as $key => $vl){
            $data[$i][T::$fileUpload][$idx][$key] = $vl;
          }
          unset($data[$i][T::$fileUpload][$idx]['foreign_id']);
        }
      } else {
        unset($data[$i]);
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    return 'SELECT ' . Helper::joinQuery('t',self::$_tenant) . Helper::joinQuery('u',self::$_unit) .  
            Helper::groupConcatQuery(self::$_billing, 'billing') . Helper::groupConcatQuery(self::$_member,'member') .
            Helper::groupConcatQuery(self::$_alt,'alt') .
            Helper::groupConcatQuery(self::$_remark_tnt,T::$remarkTnt,1) . '
            FROM ' . T::$tenant . ' AS t
            INNER JOIN ' . T::$unit . ' AS u ON t.prop=u.prop AND t.unit=u.unit ' . Model::getRawWhere($where) . '
            LEFT JOIN ' . T::$remarkTnt . ' AS rt ON rt.prop=t.prop AND rt.unit=t.unit AND rt.tenant=t.tenant
            LEFT JOIN ' . T::$alterAddress . ' AS alt ON t.prop=alt.prop AND t.unit=alt.unit AND t.tenant=alt.tenant 
            LEFT JOIN ' . T::$billing . ' AS b ON t.prop=b.prop AND t.unit=b.unit AND t.tenant=b.tenant 
            LEFT JOIN ' . T::$memberTnt . ' AS m ON t.prop=m.prop AND t.unit=m.unit AND t.tenant=m.tenant  
            GROUP BY t.prop,t.unit,t.tenant';
  }
}
