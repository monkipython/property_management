<?php 
namespace App\Http\Models;
use App\Library\{TableName AS T,Helper,Elastic};
use Illuminate\Support\Facades\DB;

class CreditCheckModel extends DB{
  public static function getTableData($table,$where,$select='*',$firstRowOnly=1){
    $r = DB::table($table)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
//------------------------------------------------------------------------------
  public static function getTenantInfo($where, $period){
    return DB::table(T::$applicationInfo . ' AS ai')->select(['ai.*','t.status', 'a.unit', 'a.tenant','t.*'])
        ->leftJoin(T::$application . ' AS a', 'ai.application_id', '=', 'a.application_id')
        ->leftJoin(T::$tenant . ' AS t', function($join){
          $join->on('a.prop', '=', 't.prop')
               ->on('a.unit', '=', 't.unit')
               ->on('a.tenant', '=', 't.tenant');
        })
        ->leftJoin(T::$prop . ' AS p', 'p.prop', '=', 't.prop')
        ->where($where)
        ->where([['ai.cdate', '>', $period]])
        ->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTenant($where, $firstRowOnly = 1, $select = '*'){
    $r = DB::table(T::$tenant . ' AS a')->select($select)
        ->where($where)
        ->orderBy('tenant', 'desc');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTenantElastic($must,$source=[],$firstRowOnly=1){
    $queryBody  = ['index'  => T::$tenantView];
    $queryBody += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody += !empty($source) ? ['_source'=>$source] : [];
    $queryBody += $firstRowOnly ? ['size'=>1] : [];
    $r          = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getValue('_source',Helper::getElasticResult($r,1),[]) : $r;
  }
//------------------------------------------------------------------------------
  public static function getTenantOldRent($where, $firstRowOnly = 1){
    $r = DB::table(T::$tntTrans)->select('*')
        ->where($where)
        ->where('amount', '>', 0)
        ->whereIn('service_code', ['602', 'HUD'])
        ->limit(2)
        ->orderBy('date1', 'desc');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getApplication($where, $firstRowOnly){
    $r = DB::table(T::$applicationInfo . ' AS ai')->select('*')
        ->leftJoin(T::$application . ' AS a', 'ai.application_id', '=', 'a.application_id')
        ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getApplicationInfo($id, $list){
    return DB::table(T::$applicationInfo)->whereIn($id, $list)->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getFileUpload($where, $firstRowOnly = 0){
    $r = DB::table(T::$fileUpload . ' AS f')->select('*')
        ->leftJoin(T::$application . ' AS a', 'f.foreign_id', '=', 'a.application_id')
        ->where($where)->orderBy('f.fileUpload_id', 'DESC');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getApplicationUpload($where,$select='*',$firstRowOnly=1){
      $r = DB::table(T::$fileUpload)->select($select)->where($where)->orderBy('fileUpload_id','DESC');
      return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getProp($where = [], $firstRowOnly = 1){
    $r = DB::table(T::$prop)->select('*')->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getUnit($where, $select = '*', $firstRowOnly = 1){
    $r = DB::table(T::$unit)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getUnitElastic($must=[],$source=[],$firstRowOnly=1){
    $queryBody   = ['index'=>T::$unitView];
    $queryBody  += !empty($source) ? ['_source'=>$source] : [];
    $queryBody  += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody  += $firstRowOnly ? ['size'=>1] : [];
    $r           = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : $r;
  }
//------------------------------------------------------------------------------
  public static function getAccount($where = [], $firstRowOnly = 1){
    $r = DB::table(T::$account)->selectRaw('account_id, CONCAT(firstname, " ", lastname) AS name')->where($where)->orderBy('firstname')->orderBy('lastname');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getAccountOwnGroup($select = ['ownGroup','accessGroup'], $where = [], $firstRowOnly = 0){
    $r = DB::table(T::$account)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getRoleEmail($where){
    return DB::table(T::$application . ' AS a')->select('*')
      ->join(T::$account . ' AS ac', 'ac.account_id', '=', 'a.ordered_by')
      ->join(T::$prop . ' AS p', 'a.prop', '=', 'p.prop')
      ->where($where)
      ->first();
  }
//------------------------------------------------------------------------------
  public static function getUnitByProp($propList, $select = '*'){
    return DB::table(T::$unit)->select($select)->whereIn('prop', $propList)->get();
  }
//------------------------------------------------------------------------------
  public static function getTntTrans($where){
    return DB::table(T::$tntTrans)->select('*')->where($where)->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getGlTrans($where){
    return DB::table(T::$glTrans)->select('*')->where($where)->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getAccountInfo($where, $firstRowOnly = 1){
    $r = DB::table(T::$account . ' AS a')
      ->join(T::$accountRole . ' AS ar', 'ar.accountRole_id', '=', 'a.accountRole_id')
      ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  } 
//------------------------------------------------------------------------------
  public static function getBilling($where = []){
    return DB::table(T::$billing)->where($where)->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPropBank($where){
    return DB::table(T::$propBank)->where($where)->first();
  }
}