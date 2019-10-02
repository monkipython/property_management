<?php 
namespace App\Http\Models;
use App\Library\{TableName AS T, Helper, Elastic};
use Illuminate\Support\Facades\DB;

class BankModel extends DB{
  public static function getTableData($table, $where, $column = '*') {
    $r = DB::table($table)->select($column)->where($where);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getBankData($where, $select = '*') {
    $r = DB::table(T::$bank . ' AS b')->select($select)
            ->join(T::$propBank . ' AS pb', function($join){
              $join->on('pb.bank', '=', 'b.bank')
                   ->on('pb.trust', '=', 'b.prop');
            })
            ->where($where);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPropData($where, $select = '*', $firstRowOnly = 1) {
    $r = DB::table(T::$prop . ' AS p')->select($select)
            ->join(T::$propBank . ' AS pb', 'pb.prop', '=', 'p.prop')
            ->where([[$where], ['pb.prop', 'NOT LIKE', '*%']]);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }  
//------------------------------------------------------------------------------  
  public static function getEntityName($where) {
    $r = DB::table(T::$prop)->select('prop', 'prop_name')->where($where)->orderBy('prop_name');
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPropElastic($must, $source = []) {
    $r = Helper::getElasticResult(Elastic::searchQuery([
      'index'   => T::$propView,
      '_source' => $source,
      'query'   => ['must'=>$must]
    ]));
    return array_column($r, '_source');
  }
}