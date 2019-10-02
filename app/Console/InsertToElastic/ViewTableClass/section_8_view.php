<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model; // Include the models class

class section_8_view {
  private static $_section8 = 'section_8_id, prop, unit, tenant, first_inspection_date, status, second_inspection_date, status2, third_inspection_date, status3, remarks, udate, cdate, usid, active';
  private static $_tenant   = 'tnt_name';
  public static $maxChunk   = 10000;
  
  public static function getTableOfView(){
    return [T::$section8,T::$unit,T::$prop,T::$tenant];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $data  = !empty($data) ? Helper::encodeUtf8($data) : [];
    $rUnit = Helper::keyFieldName(DB::table(T::$unit)->select(['prop','unit','street','unit_type'])->get(),['prop','unit']);
    $rProp = Helper::keyFieldName(DB::table(T::$prop)->select(['prop','city','state','zip','phone','line2','group1','county','prop_name','prop_type','prop_class','mangtgroup','mangtgroup2','trust'])->get(),['prop']);
    foreach($data as $i => $v){
      $val               = $v;
      $val['id']         = $v['section_8_id'];
      $val['tnt_name']   = title_case($v['tnt_name']);
      $unitId            = $v['prop'] . $v['unit'];
      
      $unit              = !empty($rUnit[$unitId]) ? $rUnit[$unitId] : [];
      if(!empty($unit)){
        foreach($unit as $k => $value){
          $val[$k] = $value;
        }
        $val['street'] = title_case($val['street']);
      }
      
      $prop             = !empty($rProp[$val['prop']]) ? $rProp[$val['prop']] : [];
      if(!empty($prop)){
        foreach($prop as $k => $value){
          $val[$k] = $value;
        }
        $val['city']       = title_case($val['city']);
        $val['county']     = title_case($val['county']);
        $val['remarks']    = title_case($val['remarks']);
        $val['line2']      = title_case($val['line2']);
      }
      $data[$i]           = $val;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where=[]){
    $isEndSelect = 1;
    $data = 'SELECT ' . Helper::joinQuery('s',self::$_section8) . Helper::joinQuery('t',self::$_tenant,$isEndSelect) . '
        FROM ' . T::$section8 . ' AS s  
        INNER JOIN ' . T::$tenant . ' AS t ON s.prop=t.prop AND s.unit=t.unit AND s.tenant=t.tenant ' . Model::getRawWhere($where);  
    return $data;
  }
}

