<?php
namespace App\Http\Models;
use App\Library\{TableName as T,Helper,Elastic};
use Illuminate\Support\Facades\DB;
class RentRaiseModel extends DB {
  public static function getRentRaise($where,$select='*',$firstRowOnly=1){
    $r = DB::table(T::$rentRaise)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getRentRaiseIn($field,$values,$select='*',$firstRowOnly=0){
    $r = DB::table(T::$rentRaise)->select($select)->whereIn($field,$values);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTenantElastic($must,$source=[],$firstRowOnly=0){
    $queryBody  = ['index' => T::$tenantView];
    $queryBody += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody += !empty($source) ? ['_source'=>$source] : [];
    $queryBody += $firstRowOnly  ? ['size'=>1] : [];
    $r          = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : $r;
  }
//------------------------------------------------------------------------------
  public static function getRentRaiseElastic($must=[],$source=[],$firstRowOnly=1){
    $queryBody   = ['index'=>T::$rentRaiseView];
    $queryBody  += !empty($source) ? ['_source'=>$source] : [];
    $queryBody  += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody  += $firstRowOnly ? ['size'=>1] : [];
    $r           = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : $r;
  }
//------------------------------------------------------------------------------
  public static function getRentRaiseInElastic($field,$values,$source=[]){
    $queryBody   = ['index'=>T::$rentRaiseView];
    $queryBody  += !empty($source) ? ['_source'=>$source] : [];
    $queryBody  += ['query'=>['must'=>[$field=>$values]]];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getBillings($billingIds,$select='*',$firstRowOnly=0){
    $r = DB::table(T::$billing)->select($select)->whereIn('billing_id',$billingIds)->where([['stop_date','=','9999-12-31']]);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getServiceIn($whereInCol,$whereInData,$select='*',$firstRowOnly=0){
    $r = DB::table(T::$service)->select($select)->whereIn($whereInCol,$whereInData);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getServiceElastic($must=[],$source=[]){
    $queryBody   = ['index'=>T::$serviceView];
    $queryBody  += !empty($source) ? ['_source'=>$source] : [];
    $queryBody  += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getUnitElastic($must=[],$source=[]){
    $queryBody  = ['index'=>T::$unitView];
    $queryBody += !empty($source) ? ['_source'=>$source] : [];
    $queryBody += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getTenantBillingElastic($must,$source=['prop','unit','tenant','base_rent','billing'],$firstRowOnly=1){
    $queryBody  = ['index'=>T::$tenantView];
    $queryBody += !empty($source) ? ['_source'=>$source] : [];
    $queryBody += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Helper::getElasticResult(Elastic::searchQuery($queryBody),$firstRowOnly);
  }
//------------------------------------------------------------------------------
  public static function getRecentBilling($where,$select='*',$firstRowOnly=1){
    $r = DB::table(T::$billing)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTenant($where,$select='*',$firstRowOnly=1){
    $r = DB::table(T::$tenant)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getProp($where,$select='*',$firstRowOnly=1){
    $r = DB::table(T::$prop)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getUnit($where,$select='*',$firstRowOnly=1){
    $r = DB::table(T::$unit)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
}
