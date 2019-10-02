<?php
namespace App\Http\Models;
use App\Library\{TableName AS T};
use Illuminate\Support\Facades\DB;

class UnitModel extends DB{
  public static function getTableData($table,$where,$select='*',$firstRowOnly=1){
    $r = DB::table($table)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------  
  public static function getUnit($where,$select = '*',$firstRowOnly=1){
    $r = DB::table(T::$unit)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------  
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
//------------------------------------------------------------------------------
  public static function getAccount($where = [], $firstRowOnly = 1){
    $r = DB::table(T::$account)->selectRaw('account_id, CONCAT(firstname, " ", lastname) AS name')->where($where)->orderBy('firstname')->orderBy('lastname');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function hasRecord($table,$where=[],$select='*'){
    $r = DB::table($table)->select($select)->where($where)->first();
    return !empty($r) ? 1 : 0;
  }
//------------------------------------------------------------------------------
  public static function getPropId($where){
    $r = DB::table(T::$prop)->select('prop_id')->where($where);
    return $r->pluck('prop_id')->first();
  }
//------------------------------------------------------------------------------
  public static function getLastUnit($where){
    return DB::table(T::$unit)->where($where)->orderBy(T::$unit . '_id', 'DESC')->first();
  }
//------------------------------------------------------------------------------
  public static function getLastBilling($where){
    return DB::table(T::$billing)->where($where)->orderBy(T::$billing . '_id', 'DESC')->first();
  }
 
}
