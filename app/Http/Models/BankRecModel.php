<?php
namespace App\Http\Models;
use App\Library\{TableName AS T, Elastic, Helper};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;

class BankRecModel extends DB {
//------------------------------------------------------------------------------
  public static function getBankElastic($query, $source = []){
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
  public static function getBank($where, $select = '*', $firstRowOnly = 1){
    $r = DB::table(T::$prop . ' AS p')->select($select)
        ->join(T::$propBank . ' AS pb', 'pb.prop', '=', 'p.prop')
        ->leftJoin(T::$bank . ' AS b', 'b.prop', '=', 'pb.trust')
        ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPropElastic($must,$source=[],$firstRowOnly=0){
    $queryBody  = ['index' => T::$propView];
    $queryBody += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody += !empty($source) ? ['_source' => $source] : [];
    $queryBody += $firstRowOnly ? ['size' => 1] : [];
    
    $r          = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : $r;
  }
//------------------------------------------------------------------------------
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
//------------------------------------------------------------------------------
  public static function deleteWhereIn($table,$field,$data){
    return DB::table($table)->whereIn($field,$data)->delete();
  }
//------------------------------------------------------------------------------
  public static function getTableDataElastic($index,$query,$param = ['sort'=>['match_id'=>'asc']],$firstRowOnly=0,$isRawQuery=0){
    $mustQuery  = $isRawQuery ? ['query'=>['raw'=>$query]] : ['query'=>['must'=>$query]];
      
    $queryBody  = ['index' => $index];
    $queryBody += !empty($param['sort']) ? ['sort'=>$param['sort']] : [];
    $queryBody += !empty($param['_source']) ? ['_source'=>$param['_source']] : [];
    $queryBody += $firstRowOnly ? ['size'=>1] : ['size'=>50000];
    
    $queryBody += !empty($query) ? $mustQuery : [];
    
    $r          = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : $r;
  }
//------------------------------------------------------------------------------
  public static function getFileUploadIn($whereField,$whereData,$select='*',$firstRowOnly=0,$where=[]){
    $r = DB::table(T::$fileUpload)->select($select)->whereIn($whereField,$whereData);
    $r = !empty($where) ? $r->where($where) : $r;
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
################################################################################
#########################   GET VIEW DATA FUNCTION   ###########################  
################################################################################
  public static function getClearedTransElastic($must=[],$source=[],$firstRowOnly=0){
    $queryBody   = ['index'=>T::$clearedTransView];
    $queryBody  += !empty($source) ? ['_source'=>$source] : [];
    $queryBody  += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody  += $firstRowOnly ? ['size'=>1] : [];
    $r           = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : $r;
  }
//------------------------------------------------------------------------------
  public static function getBankTransElastic($must=[],$source=[],$firstRowOnly=0){
    $queryBody  = ['index' => T::$bankTransView];
    $queryBody += !empty($source) ? ['_source'=>$source] : [];
    $queryBody += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody += $firstRowOnly ? ['size'=>1] : [];
    $r          = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : [];
  }
}
