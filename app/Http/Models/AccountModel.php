<?php 
namespace App\Http\Models;
use App\Library\{TableName AS T};
use Illuminate\Support\Facades\DB;

class AccountModel extends DB{
  public static function getAccount($where = [], $firstRowOnly = 0){
    $r = DB::table(T::$account . ' AS a')
      ->selectRaw('a.*, ar.*, GROUP_CONCAT(ap.accountPermission_id,"~",ap.account_id,"~",ap.accountProgram_id,"~",ap.permission SEPARATOR "|") AS accountPerm')
      ->leftJoin(T::$accountRole . ' AS ar', 'a.accountRole_id', '=', 'ar.accountRole_id')
      ->leftJoin(T::$accountPermission . ' as ap', 'a.account_id', '=', 'ap.account_id')
      ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPermission($where = [], $firstRowOnly = 0){
    $r = DB::table(T::$accountPermission . ' as ap')
      ->select('*')
      ->join(T::$accountProgram . ' as apr', 'ap.accountProgram_id', '=', 'apr.accountProgram_id')
      ->where($where)
      ->orderBy('category', 'asc');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getAccountPermission($where = [], $firstRowOnly = 0){
    $r = DB::table(T::$accountPermission . ' as ap')
      ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getProgram($where = [], $firstRowOnly = 0){
    $r = DB::table(T::$accountProgram)->select('*')->where($where)->orderBy('category', 'asc');;
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getAccountRole($where = [], $firstRowOnly = 0){
    $r = DB::table(T::$accountRole)->select('*')->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPasswordReset($where = [], $firstRowOnly = 0){
    $r = DB::table(T::$accountPasswordReset)->select('*')->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPermissionByAccountId($where = [], $firstRowOnly = 0){
    $r = DB::table(T::$accountPermission . ' AS ap')
      ->join(T::$account . ' AS a', 'ap.account_id', '=', 'a.account_id')  
      ->select('*')->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
}