<?php 
namespace App\Http\Models;
use App\Library\{TableName AS T, Helper, Elastic};
use Illuminate\Support\Facades\DB;

class ServiceModel extends DB{
  public static function getTableData($table, $where, $column = '*') {
    $r = DB::table($table)->select($column)->where($where);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getNumberProps() {
    return  Helper::getElasticResult(Elastic::searchQuery([
                'index'   =>T::$propView,
                '_source' =>['prop'],
                'sort'    =>['prop.keyword'=>'asc'],
                'query'   =>['must'=>['range'=>['prop'=>['gte'=>'0001', 'lte'=>'9999']]]]
            ]));
  }
//------------------------------------------------------------------------------
  public static function getNumberPropsServiceId($service) {
    return  Helper::getElasticResultSource(Elastic::searchQuery([
              'index'   =>T::$serviceView,
              '_source' =>['service_id'],
              'query'   =>['raw'=>['must'=>[['range'=>['prop.keyword'=>['gte'=>'0001', 'lte'=>'9999']]], ['term'=>['service.keyword'=>$service]]]]]
            ]));
  }
//------------------------------------------------------------------------------
  public static function getPropsExeceptNumbers() {
    return  Helper::getElasticAggResult(Elastic::searchQuery([
              'index'   => T::$serviceView,
              '_source' => ['prop'],
              'query'   => ['must_not'=>['range'=>['prop'=>['gte'=>'0001', 'lte'=>'9999']]]],
              'aggs'    => ['unique_prop'=>['terms'=>['field'=>'prop.keyword', 'size'=>10000, 'order'=>['_term'=>'asc']]]]
            ]), 'unique_prop');
  }
//------------------------------------------------------------------------------
  public static function getServices() {
    return  Helper::getElasticAggResult(Elastic::searchQuery([
              'index'   => T::$serviceView,
              '_source' => ['service'],
              'query'   => ['must_not'=>['range'=>['prop'=>['gte'=>'0001', 'lte'=>'9999']]]],
              'aggs'    => ['unique_service'=>['terms'=>['field'=>'service.keyword', 'size'=>10000, 'order'=>['_term'=>'asc']]]]
            ]), 'unique_service');
  }
}