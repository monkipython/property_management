<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use App\Http\Models\Model;
use Illuminate\Support\Facades\DB;

class creditcheck_view{
  public static $maxChunk  = 10000;
  public static $maxResult = 500000;
  private static $_application = 'application_id, prop, unit, tenant, new_rent, sec_deposit, sec_deposit_add, sec_deposit_note, ordered_by, ran_by, status, application_status, moved_in_status, signature_status, section8, is_upload_agreement, usid, active, cdate, udate, old_rent'; 
  private static $_viewSelect  = [
    'ai.application_info_id', 'ai.application_id', 'ai.app_fee', 'ai.app_fee_recieved', 'ai.application_num', 'ai.fname', 'ai.mname', 'ai.lname','ai.email',
    'ai.mname','ai.lname','ai.suffix','ai.email','ai.dob','ai.cell','ai.social_security','ai.street_num','ai.street_name','ai.evicted','ai.run_credit','ai.active',
    'ai.app_fee_recieved_date', 'ai.tnt_unit', 'ai.city', 'ai.state', 'ai.zipcode', 'ai.driverlicense', 'ai.old_rent', 'ai.new_rent', 'ai.driverlicensestate'
  ];
  private static $_fileUploadViewSelect = [
    'f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type','f.cdate', 'f.ext'
  ];
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return  [T::$application, T::$applicationInfo, T::$prop, T::$fileUpload, T::$tenant];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? $data : [];
    $r = Helper::keyFieldName(DB::table(T::$account)->get()->toArray(), 'account_id');
    
    foreach($data as $i=>$val){
      $applicantz = explode('|', $val[T::$application]);
      $fileUpload = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
      unset($data[$i][T::$application], $data[$i][T::$fileUpload]);
      foreach($val as $k=>$v){
        if(is_numeric($v)){
          $val[$k] = intval($v);
        }
      }
      
      $data[$i]['id']            = $data[$i]['application_id'];
      $data[$i]['ran_by']        = isset($r[$val['ran_by']]) ? $r[$val['ran_by']]['firstname'] : $val['ran_by'];
      $data[$i]['ordered_by']    = isset($r[$val['ordered_by']]) ? $r[$val['ordered_by']]['firstname'] : $val['ordered_by'];
      $data[$i]['move_in_date']  = !empty($val['move_in_date']) ? $val['move_in_date'] : '1000-01-01';
      $data[$i]['housing_dt2']   = !empty($val['housing_dt2']) ? $val['housing_dt2'] : '1000-01-01';
      $data[$i]['street']        = title_case($val['street']);
      $data[$i]['city']          = title_case($val['city']);
      # DEAL WITH APPLICATION_INFO
      if(!empty($applicantz[0])){
        foreach($applicantz as $j=>$v){
          $p = explode('~', $v);
          foreach(self::$_viewSelect as $k=>$field){
            $field = preg_replace('/ai\./', '', $field);
            if(!isset($p[$k])){
              dd($applicantz);
            }
            $data[$i][T::$application][$j][$field] = ($field == 'social_security') ? substr($p[$k], -4) : $p[$k];
          }
          $data[$i][T::$application][$j]['tnt_name'] = $data[$i][T::$application][$j]['fname'] . ' ' . $data[$i][T::$application][$j]['lname'];
        }
      }
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
    $query = '
      SELECT  ' .
        Helper::groupConcatQuery(self::$_viewSelect,T::$application) . Helper::groupConcatQuery(self::$_fileUploadViewSelect,T::$fileUpload) . 
        Helper::joinQuery('a',self::$_application) . ' IF(raw_agreement IS NOT NULL AND raw_agreement <> "",1,0) AS raw_agreement, 
        prop_class, prop_name, line2, street, p.city, p.state, p.zip, p.phone, name_1099, start_date, tax_date, man_fee_date, last_raise_date, 
        man_flat_amt, man_pct, po_value, sq_feet, number_of_units, county, map_code, fed_id, state_id, ar_bank, ar_contra, ar_sum_acct, ar_sum_contra, ap_bank, ap_contra, 
        ap_sum_acct, ap_sum_contra, year_end, first_month, next_year_end, start_year, last_year_end, start_last_year, year_end_acct, group1, group2, 
        cons1, cons2, cons_flg, post_flg, p.tax_rate, t.sys_date, t.housing_dt2, t.move_in_date
      FROM ' . T::$application . ' AS a
      INNER JOIN ' . T::$prop . ' AS p ON p.prop=a.prop ' . Model::getRawWhere($where) . ' 
      INNER JOIN ' . T::$fileUpload . ' AS f ON a.application_id=f.foreign_id AND (f.type="application" OR f.type="agreement") AND f.active=1
      INNER JOIN ' . T::$applicationInfo .  ' AS ai ON a.application_id=ai.application_id 
      LEFT  JOIN ' . T::$tenant .  ' AS t ON a.prop=t.prop AND a.unit=t.unit AND a.tenant=t.tenant
      GROUP BY a.application_id 
      ORDER BY a.application_id';
    return $query;
  }
}