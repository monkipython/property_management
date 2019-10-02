<?php 
namespace App\Http\Models;
use App\Library\{TableName AS T, Elastic, Helper};
use Illuminate\Support\Facades\DB;

class CashRecModel extends DB{
  public static function getTableData($table, $where = [], $select = '*', $isFirstRow = 0) {
    $r = DB::table($table)->select($select)->where($where);
    return $isFirstRow ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTableDataWhereIn($table, $whereField, $whereData, $select = '*') {
    return DB::table($table)->select($select)->whereIn($whereField,$whereData)->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getZeroAppyto($where, $cntlNo, $select = '*', $isFirstRow = 1){
    $r = DB::table(T::$tntTrans)->select($select)->where($where)->where('appyto', 0)->whereIn('cntl_no',$cntlNo);
    return $isFirstRow ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTntSecurityDeposit($where, $prop, $select = '*'){
    $r = DB::table(T::$tntSecurityDeposit)->select($select)->where($where)->whereIn('prop',$prop);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTenantId($vData){
    return Elastic::searchQuery([
      'index'=>T::$tenantView,
      '_source'=>['includes'=>['tenant_id']],
      'query'=>[
        'must'=>Helper::selectData(['prop', 'tenant', 'unit'], $vData)
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public static function getGlTrans($vData, $field = '*', $firstRowOnly = 0){
    $r = DB::table(T::$glTrans)->select($field)
        ->whereIn('prop', $vData['prop'])
        ->where(Model::buildWhere(['batch'=>$vData['batch']]))
        ->whereBetween('date1', [$vData['date1'], $vData['todate1']])
        ->orderBy('date1', 'DESC')
        ->orderBy('prop', 'ASC')
        ->orderBy('gl_acct', 'DESC')
        ;
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getClearedCheck($vData, $field = '*', $firstRowOnly = 0){
    $r = DB::table(T::$clearedCheck . ' AS c')->select($field)
        ->leftJoin(T::$clearedCheckExtend . ' AS cx', 'cx.cleared_check_id', '=', 'c.cleared_check_id')
        ->where(Model::buildWhere($vData));
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getClearedCheckExt($vData, $field = '*', $firstRowOnly = 0){
    $r = DB::table(T::$clearedCheckExtend)->select($field)
        ->where(Model::buildWhere($vData));
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
  //------------------------------------------------------------------------------
  public static function getTenant($must, $source = [], $param = [], $isFirstRow = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Helper::getElasticResult(Elastic::searchQuery([
      'index'   =>T::$tenantView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]), $isFirstRow );
    if($isFirstRow){
      return !empty($r) ? $r['_source'] : [];
    } else{
      return $r;
    }
  }
//------------------------------------------------------------------------------
  public static function getTntSum($must){
    $r = Elastic::searchQuery([
      'index'=>T::$tntTransView,
      'query'=>['must'=>$must],
      'aggs'=>[
        'group_appyto'=> [
          'terms'=> ['field'=>'appyto', 'size'=>150000],
          'aggs'=>[
            'sum_amount'=>['sum'=>['field'=>'amount']],
            'by_amount_filter'=>[
              'bucket_selector'=>[
                'buckets_path'=>[
                  'sumamount'=>'sum_amount'
                ],
                'script'=>'params.sumamount != 0'
              ]
            ]
          ]
        ]
      ]
    ]);
  }
//------------------------------------------------------------------------------
//  public static function getTenantTransById($vData, $sort, $isFirstRowOnly = 0){
//    $r = Elastic::searchQuery([
//      'index'=>T::$tntTransView,
//      'sort'=>$sort,
//      '_source'=>['includes'=>[
//        'date1','date2','invoice','prop','unit','tenant','amount','appyto','tx_code','batch','job','gl_acct','service_code','check_no','remark','journal','sys_date', 'group1', 'cntl_no']
//      ],
//      'query'=>[
//        'must'=>Helper::selectData(['prop', 'tenant', 'unit'], $vData),
//      ]
//    ]);
//    return Helper::getElasticResult($r, $isFirstRowOnly);
//  }
//------------------------------------------------------------------------------
  public static function getBank($query, $source = []){
    return Elastic::searchQuery([
      'index'=>T::$bankView,
      '_source'=>['includes'=>$source],
      'sort'=>[['bank.keyword'=>'asc']],
      'size'=>100,
      'query'=>[
        'must'=>$query
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public static function getDataFromTable($index, $query, $param = []){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    return Elastic::searchQuery([
      'index'=>$index,
      'sort'    =>$sort,
      '_source'=>['includes'=>isset($param['source']) ? $param['source'] : []],
      'query'=>[
        'must'=>$query
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
}