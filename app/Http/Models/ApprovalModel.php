<?php
namespace App\Http\Models;
use App\Library\{TableName AS T,Helper,Elastic};
use Illuminate\Support\Facades\DB;

class ApprovalModel extends DB {
  public static function getTableData($table, $where = [], $select = '*', $isFirstRow = 0) {
    $r = DB::table($table)->select($select)->where($where);
    return $isFirstRow ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTableDataWhereIn($table, $whereField, $whereData, $select = '*') {
    return DB::table($table)->select($select)->whereIn($whereField,$whereData)->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getFileUpload($where, $firstRowOnly = 0,$select='*'){
    $r = DB::table(T::$fileUpload . ' AS f')->select($select)
        ->leftJoin(T::$vendorPayment . ' AS v', 'f.foreign_id', '=', 'v.vendor_payment_id')
        ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getVendorApprovalElastic($query,$source=[]){
    $queryBody    = ['index' => T::$vendorPaymentView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($query['query']) ? ['query'=>$query['query']] : ['query'=>['must'=>$query]];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getVendorElastic($must,$source=[]){
    $queryBody    = ['index'=>T::$vendorView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getBank($query, $source = []){
    return Elastic::searchQuery([
      'index'   =>T::$bankView,
      'sort'    =>[['bank.keyword'=>'asc'],['name.keyword'=>'asc']],
      '_source' =>['includes'=>$source],
      'size'    =>100,
      'query'   =>[
        'must'  =>$query
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
//------------------------------------------------------------------------------
  public static function getGlTransElastic($must=[],$source=[],$firstRowOnly=1){
    $queryBody   = ['index' => T::$glTransView];
    $queryBody  += !empty($source) ? ['_source'=>$source]: [];
    $queryBody  += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody  += $firstRowOnly ? ['size'=>1] : [];
    $r           = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : $r;
  }
}

