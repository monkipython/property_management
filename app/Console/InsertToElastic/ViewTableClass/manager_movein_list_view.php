<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model; // Include the models class

class manager_movein_list_view{
  private static $_managerMoveinList = 'manager_movein_list_id';
  private static $_prop   = 'trust, prop_class, prop_type, prop_name, line2, street, city, state, zip, number_of_units, county,  group1';
  private static $_tenant = 'tenant_id, prop, unit, tenant, tnt_name, base_rent, dep_held1, dep_held2, dep_held3, lease_exp_date, lease_opt_date, lease_esc_date, move_in_date, move_out_date, phone1, fax, status, spec_code, tax_code, housing_dt1, housing_dt2, return_no';
  public  static $maxChunk = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$managerMoveinList, T::$tenant, T::$prop, T::$unit, T::$application];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return [];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data    = !empty($data) ? $data : [];
    $rTenant = Helper::keyFieldName(DB::table(T::$tenant)->select(['prop', 'unit', 'tenant', 'base_rent', 'dep_held1'])->get(), ['prop', 'unit', 'tenant']);
    $rUnit   = Helper::keyFieldName(DB::table(T::$unit)->select(['prop', 'unit', 'past_tenant', 'bedrooms', 'bathrooms'])->get(), ['prop', 'unit']);
    $rApp    = Helper::keyFieldName(DB::table(T::$application)->select(['prop', 'unit', 'tenant', 'ran_by'])->get(), ['prop', 'unit', 'tenant']);
    $rAcct   = Helper::keyFieldName(DB::table(T::$account)->select(['account_id', 'firstname', 'lastname'])->get(), 'account_id');
    
    foreach($data as $i=>$val){
      $val['id'] = $val['manager_movein_list_id'];
      $val['tnt_name'] = title_case($val['tnt_name']);
      $val['street'] = title_case($val['street']);
      $val['city'] = title_case($val['city']);
      $id = $val['prop']. $val['unit'] . $val['tenant'];
      $oldTenant = isset($rUnit[$val['prop'] . $val['unit']]) ? $rUnit[$val['prop'] . $val['unit']]['past_tenant'] : 255;
      
      $val['bathrooms'] = isset($rUnit[$val['prop'] . $val['unit']]) ? $rUnit[$val['prop'] . $val['unit']]['bathrooms'] : 0;
      $val['bedrooms']  = isset($rUnit[$val['prop'] . $val['unit']]) ? $rUnit[$val['prop'] . $val['unit']]['bedrooms'] : 0;
      $val['old_rent']  = isset($rTenant[$val['prop']. $val['unit'] . $oldTenant]['base_rent']) ? $rTenant[$val['prop']. $val['unit'] . $oldTenant]['base_rent'] : $rTenant[$id]['base_rent'];
      $val['new_rent']  = $rTenant[$id]['base_rent'];
      
      if(isset($rApp[$id]['ran_by'])){
        $val['ran_by']  = isset($rAcct[$rApp[$id]['ran_by']]) ? $rAcct[$rApp[$id]['ran_by']]['firstname'] : $rApp[$id]['ran_by'];
      } else{
        $val['ran_by']  = 'Unknown';
      }
      $data[$i] = $val;
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
    $data = 'SELECT ' . $_join('m',self::$_managerMoveinList) . ',' . $_join('t',self::$_tenant) . ',' . $_join('p',self::$_prop) . ' 
      FROM ' . T::$managerMoveinList . ' AS m
      INNER JOIN ' . T::$tenant . ' AS t ON m.prop=t.prop AND m.unit=t.unit AND m.tenant=t.tenant ' . Model::getRawWhere($where) . ' 
      LEFT JOIN ' . T::$prop . ' AS p ON m.prop=p.prop';
    return $data;
  }
}