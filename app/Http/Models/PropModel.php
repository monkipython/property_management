<?php 
namespace App\Http\Models;
use App\Library\{TableName AS T, Elastic, Helper};
use Illuminate\Support\Facades\DB;

class PropModel extends DB{

  public static function getPropColumnOptions($select, $where = []){
    $r = DB::table(T::$prop)->select($select)->groupBy($select)->where($where);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTableData($table, $where, $select = '*') {
    $r = DB::table($table)->select($select)->where($where);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getAccount($where = [], $firstRowOnly = 1){
    $r = DB::table(T::$account)->selectRaw('account_id, CONCAT(firstname, " ", lastname) AS name')->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getAssignedAccount($prop) {
    $r = DB::table(T::$account)->select('account_id', 'accessGroup', 'ownGroup')
            ->where([
                ['accessGroup', 'LIKE', '%'. $prop . '%'],
                ['ownGroup', 'LIKE', '%'. $prop . '%'],
            ]);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------\
  public static function getPropsWhereIn($where) {
    $r = DB::table(T::$prop)->whereIn('prop', $where);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------\
  public static function getPropsRange($where) {
    $r = DB::table(T::$prop)->whereBetween('prop', $where);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPropIdTrust($trust) {
    $r = Elastic::searchQuery([
      'index'=> T::$propView,
      '_source' => ['prop_id'],
      'query'  =>[
        'must'  =>['trust.keyword'=>$trust]
      ]
    ]);
    return Helper::keyFieldNameElastic($r, 'prop_id');
  }
//------------------------------------------------------------------------------
  public static function getRentRaiseElastic($must=[],$source=[],$firstRowOnly=0){
    $queryBody  = ['index' => T::$rentRaiseView];
    $queryBody += !empty($source) ? ['_source'=>$source] : [];
    $queryBody += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody += $firstRowOnly ? ['size'=>1] : [];
    $r          = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getElasticResultSource($r,1) : $r;
  }
//------------------------------------------------------------------------------
  public static function getCompanyColumnOptions($select) {
    $r = DB::table(T::$company)->select($select)->groupBy($select);
    return $r->get()->toArray();
  }
}