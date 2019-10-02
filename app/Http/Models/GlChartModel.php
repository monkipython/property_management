<?php 
namespace App\Http\Models;
use App\Library\{TableName AS T, Helper, Elastic};
use Illuminate\Support\Facades\DB;

class GlChartModel extends DB{
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
  public static function getNumberPropsGlChartId($glAcct) {
    return  Helper::getElasticResultSource(Elastic::searchQuery([
              'index'   =>T::$glChartView,
              '_source' =>['gl_chart_id'],
              'query'   =>['raw'=>['must'=>[['range'=>['prop.keyword'=>['gte'=>'0001', 'lte'=>'9999']]], ['term'=>['gl_acct.keyword'=>$glAcct]]]]]
            ]));
  }
//------------------------------------------------------------------------------
  public static function getPropsExeceptNumbers() {
    return  Helper::getElasticAggResult(Elastic::searchQuery([
              'index'   => T::$glChartView,
              '_source' => ['prop'],
              'query'   => ['must_not'=>['range'=>['prop'=>['gte'=>'0001', 'lte'=>'9999']]]],
              'aggs'    => ['unique_prop'=>['terms'=>['field'=>'prop.keyword', 'size'=>10000, 'order'=>['_term'=>'asc']]]]
            ]), 'unique_prop');
  }
//------------------------------------------------------------------------------
  public static function getGlAccts() {
    return  Helper::getElasticAggResult(Elastic::searchQuery([
              'index'   => T::$glChartView,
              '_source' => ['gl_acct'],
              'query'   => ['must_not'=>['range'=>['prop'=>['gte'=>'0001', 'lte'=>'9999']]]],
              'aggs'    => ['unique_gl_acct'=>['terms'=>['field'=>'gl_acct.keyword', 'size'=>10000, 'order'=>['_term'=>'asc']]]]
            ]), 'unique_gl_acct');
  }
}