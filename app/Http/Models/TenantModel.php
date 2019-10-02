<?php 
namespace App\Http\Models;
use App\Library\{Helper, Elastic, TableName AS T, HelperMysql};
use Illuminate\Support\Facades\DB;

class TenantModel extends DB{
//------------------------------------------------------------------------------
  public static function getTenant($where, $select = '*' , $firstRowOnly = 1){
    $r = DB::table(T::$tenant . ' AS t')->select($select)
        ->where($where)
        ->orderBy('tenant', 'desc');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getBilling($where, $select = 'b.*', $firstRowOnly = 0){
    $r = DB::table(T::$billing . ' AS b')->select($select)
        ->join(T::$tenant . ' AS t', function($join){
          $join->on('b.prop', '=' , 't.prop');
          $join->on('b.unit', '=' , 't.unit');
          $join->on('b.tenant', '=' , 't.tenant');
        })->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getAltAddress($where, $select = 'a.*', $firstRowOnly = 1){
    $r = DB::table(T::$alterAddress . ' AS a')->select($select)
        ->join(T::$tenant . ' AS t', function($join){
          $join->on('a.prop', '=' , 't.prop');
          $join->on('a.unit', '=' , 't.unit');
          $join->on('a.tenant', '=' , 't.tenant');
        })->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTenantMember($where, $select = 'm.*', $firstRowOnly = 0){
    $r = DB::table(T::$memberTnt . ' AS m')->select($select)
        ->join(T::$tenant . ' AS t', function($join){
          $join->on('m.prop', '=' , 't.prop');
          $join->on('m.unit', '=' , 't.unit');
          $join->on('m.tenant', '=' , 't.tenant');
        })->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTenantUtility($where, $select = 'u.*', $firstRowOnly = 1){
    $r = DB::table(T::$tenantUtility . ' AS u')->select($select)
        ->join(T::$tenant . ' AS t', function($join){
          $join->on('u.prop', '=' , 't.prop');
          $join->on('u.unit', '=' , 't.unit');
          $join->on('u.tenant', '=' , 't.tenant');
        })->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getRentRaise($where,$select='*',$firstRowOnly=0){
    $r = DB::table(T::$rentRaise)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getMassiveBilling($props){
    $r = Elastic::searchQuery([
      'index'=>T::$tenantView,
      'size' =>500000,
      'query'=>[
        'must'  =>['status.keyword'=>'C'],
        'filter'=>['prop.keyword' => $props]
      ],
      '_source'=>['tenant_id', 'prop', 'unit', 'tenant','tnt_name','isManager', 'move_in_date', 'manager', 'move_out_date', 'base_rent', 'billing.billing_id','billing.start_date','billing.stop_date','billing.active', 'billing.post_date','billing.schedule', 'billing.amount', 'billing.remark', 'billing.gl_acct', 'billing.service_code']
    ]);
    return Helper::getElasticResult($r);
  }

  //------------------------------------------------------------------------------
  public static function getProp($where=[], $firstRowOnly = 0){
    $r = DB::table(T::$prop)->select('*')->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getUnit($where, $select = '*', $firstRowOnly = 1){
    $r = DB::table(T::$unit)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getRemark($where,$select='*',$firstRowOnly=1,$orderBy=''){
    $r = DB::table(T::$remarkTnt)->select($select)->where($where);
    $r = !empty($orderBy) ? $r->orderBy($orderBy) : $r;
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------  
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
//------------------------------------------------------------------------------  
  public static function getTenantFromElasticSearchById($vData, $source=[]){
    $r = Elastic::searchQuery([
      'index'=>T::$tenantView,
      'query'=>[
        'must'=>Helper::selectData(['tenant_id'], $vData)
      ],
      'size'=>1,
      '_source'=>$source
    ]);
    return Helper::getElasticResult($r,1)['_source'];
  }
//------------------------------------------------------------------------------
  public static function getTenantDeposit($where) {
    $r = DB::table(T::$tntSecurityDeposit)->selectRaw('SUM(amount) as deposit_credit')->where($where)->whereIn('gl_acct', ['607', '755']);
    return $r->first();
  }
//------------------------------------------------------------------------------
  public static function getTenantDepositBalance($where) {
    $r = DB::table(T::$tntSecurityDeposit)->selectRaw('SUM(amount) as deposit_balance')->where($where);
    return $r->first();
  }
//------------------------------------------------------------------------------
  public static function getTableData($table, $where, $select = '*', $firstRowOnly = 0) {
    $r = DB::table($table)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTntSecurityDeposits($where, $select = '*', $firstRowOnly = 0) {
    $r = DB::table(T::$tntSecurityDeposit)->select($select)->where($where)->orderBy('date1', 'asc');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTntEvictionProcess($where, $select = '*', $firstRowOnly = 1) {
    $r = DB::table(T::$tntEvictionEvent)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getCityList() {
    return Helper::keyFieldName(Helper::getElasticAggResult(Elastic::searchQuery([
      'index' => T::$tenantView,
      'size'  => 0,
      'query' => [
        'must' => [
          'status.keyword' => 'C'
        ],
        'must_not' => [
          'city.keyword' => ''
        ]
      ],
      'aggs' => [
        'by_city'=>['terms'=>['field'=>'city.keyword', 'size'=>1000, 'order'=>['_term'=>'asc']]],
        
      ]
    ]),'by_city'), 'key', 'key');
  }
//------------------------------------------------------------------------------
  public static function getCntlNo($data) {
    $rTntTrans = HelperMysql::getTntTrans(Helper::getPropUnitTenantMustQuery($data, [], 0), ['cntl_no'], [], 0);
    return array_column(array_column($rTntTrans, '_source'), 'cntl_no');
  }
}
