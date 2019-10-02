<?php 
namespace App\Http\Models;
use App\Library\{TableName AS T, Helper, Elastic, Format};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;

class ReportModel extends DB{

  public static function getTableData($table, $where = [], $select = '*', $firstRowOnly = 0) {
    $r = DB::table($table)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getOldRent($where, $firstRowOnly = 0) {
    $r = DB::table(T::$billing)->selectRaw('prop,unit,tenant,SUM(amount) AS amount')
        ->whereRaw('('.$where.') AND schedule="M" AND (service_code="602" OR service_code="HUD") AND stop_date="9999-12-31"')
        ->groupBy('prop', 'unit', 'tenant')
        ->orderBy('billing_id', 'ASC');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getDelinquencyBalForward($whereCondition,$data) {
    $r = DB::table(T::$tntTrans)->selectRaw('prop,unit,tenant,SUM(amount) AS amount')
        ->where('gl_acct','<>', '610')
        ->whereRaw($whereCondition,$data)
        ->groupBy('prop', 'unit', 'tenant')
        ->having('amount', '<>', 0)
        ->orderBy('prop', 'ASC')
        ->orderBy('unit', 'ASC')
        ->orderBy('tenant', 'ASC');
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getDelinquencyRentAmount($fromDate, $toDate, $props) {
    $r = DB::table(T::$tntTrans . ' AS tt')->selectRaw('tt.prop,tt.unit,tt.tenant,SUM(tt.amount) AS amount')
        ->join(T::$tenant . ' AS t', function($join){
          $join->on('tt.prop', '=' , 't.prop');
          $join->on('tt.unit', '=' , 't.unit');
          $join->on('tt.tenant', '=' , 't.tenant');
        })
        ->whereIn('tt.gl_acct',['602', '607', '375'])
        ->where('t.status','C')
        ->where('t.spec_code','<>', 'E')
        ->whereBetween('tt.date1', [$fromDate, $toDate])
        ->whereIn('tt.prop', $props)
        ->groupBy('tt.prop', 'tt.unit', 'tt.tenant')
        ->orderBy('tt.prop', 'ASC')
        ->orderBy('tt.unit', 'ASC')
        ->orderBy('tt.tenant', 'ASC');
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getDelinquencyRentAmount_old($fromDate, $toDate, $props, $status='C') {
    $r = DB::table(T::$tntTrans . ' AS tt')->selectRaw('tt.prop,tt.unit,tt.tenant,SUM(tt.amount) AS amount')
        ->join(T::$tenant . ' AS t', function($join){
          $join->on('tt.prop', '=' , 't.prop');
          $join->on('tt.unit', '=' , 't.unit');
          $join->on('tt.tenant', '=' , 't.tenant');
        })
        ->where('t.spec_code', '<>', 'E')    
        ->where('tt.code', '<>', 'S')    
        ->where('tt.gl_acct','602')
        ->where('t.status',$status)
        ->whereBetween('tt.date1', [$fromDate, $toDate])
        ->whereIn('tt.prop', $props)
        ->groupBy('tt.prop', 'tt.unit', 'tt.tenant')
        ->orderBy('tt.prop', 'ASC')
        ->orderBy('tt.unit', 'ASC')
        ->orderBy('tt.tenant', 'ASC');
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getOpenItems($props, $status='C'){
     $r = DB::table(T::$tntTrans . ' AS tt')->selectRaw('sum(tt.amount) as amount,tt.prop,tt.unit,tt.tenant,tt.date1,tt.gl_acct')
        ->join(T::$tenant . ' AS t', function($join){
          $join->on('tt.prop', '=' , 't.prop');
          $join->on('tt.unit', '=' , 't.unit');
          $join->on('tt.tenant', '=' , 't.tenant');
        })
        ->where('t.spec_code', '<>', 'E')
        ->where('tt.tx_code', '<>', 'S')  
        ->where('t.status',$status)
        ->whereIn('tt.prop', $props)
        ->groupBy('tt.prop', 'tt.unit', 'tt.tenant', 'appyto')
        ->orderBy('tt.date1', 'ASC')
        ->having('amount', '>', 0);

    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getTenantAmountOwed($whereInField,$whereInData,$firstRowOnly=0){
    $r = DB::table(T::$tntSecurityDeposit)->selectRaw('prop,unit,tenant,SUM(amount) AS amount_owed')
      ->whereIn($whereInField,$whereInData)
      ->groupBy(['prop','unit','tenant'])
      ->having('amount_owed','<',0)
      ->orderBy('prop','ASC')
      ->orderBy('unit','ASC')
      ->orderBy('tenant','ASC');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPropsWithTenant($props) {
    $r = Elastic::searchQuery([
      'index'=>T::$tenantView,
      'size' =>0,
      'query'=>[
        'filter'=>['prop.keyword' => $props]
      ],
      'aggs' => [
        'group_by_prop' => [
          'terms' => [
            'field' => 'prop.keyword',
            'size'  => 10000
          ]
        ]
      ]
    ]);
    return Helper::getElasticAggResult($r, 'group_by_prop');
  }
//------------------------------------------------------------------------------
  public static function getVacantUnit($prop){
    $r = Elastic::searchQuery([
      'index'  => T::$unitView,
      '_source'=> ['prop.prop', 'unit', 'rent_rate', 'bathrooms', 'status2', 'unit_type', 'bedrooms', 'street','pad_size','unit_size','mh_owner','mh_serial_no','sec_dep'],
      'sort'   => ['prop.prop.keyword'=>'asc', 'unit.keyword'=>'asc'],
      'query'  => [
        'filter'=>['prop.prop'=>$prop]
      ] 
    ]);
    return Helper::getElasticResult($r);
  }
//------------------------------------------------------------------------------
  public static function getTenantsWithProp($vData) {
    $r = Elastic::searchQuery([
      'index'   => T::$tenantView,
      'sort'    => ['prop.keyword'=>'asc', 'group1.keyword'=>'asc', 'unit.keyword'=>'asc', 'tenant'=>'desc'],
      '_source' => ['prop', 'unit', 'tenant', 'group1', 'lease_opt_date', 'tnt_name', 'street', 'city', 'bathrooms', 'bedrooms', 'move_in_date', 'phone'],
      'size'    => 100000,
      'query'=>[
        'must'=>[
          'range' => [
            'lease_opt_date' => [
              'gte' => Format::mysqlDate($vData['date'])
            ]
          ]
        ],
        'filter'=>['prop'=>$vData['prop']]
      ]
    ]);
    return Helper::getElasticResult($r);
  }
//------------------------------------------------------------------------------
  public static function getBankList($select = '*') {
    $r = DB::table(T::$bank)->select($select);
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getRentRollTenant($vData, $fields) {
    $term = is_array($vData['prop']) ? 'terms' : 'term';
    return Helper::getElasticResult(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','move_in_date'=>'desc'],
      '_source'  => $fields,
      'query'    => [
        'raw' => [
          'must'  => [
            [
              $term => [
                'prop.keyword'  => $vData['prop'],
              ]
            ],
            [
              'bool' => [
                'must' => [
                  [
                    'range' => [
                      'move_in_date' => [
                        'lte'    => $vData['tomove_in_date'],
                      ]
                    ]
                  ],
                  [
                    'range' => [
                      'move_out_date' => [
                        'gte'    => $vData['move_out_date'],
                      ]
                    ]
                  ]
                ]
              ]
            ]
          ]
        ]
      ]
    ]));
  }
//------------------------------------------------------------------------------
  public static function getFutureCurrentTenant() {
    $must['raw']['must'] = [
      [
        'range' => ['move_in_date'=>['gt'=>Helper::date()]],
      ],
      [
        'bool' => [
          'should' => [
            [
              'term'  => ['status.keyword'=>'F']
            ],
            [
              'term' => ['status.keyword'=>'C']
            ]
          ]
        ]
      ]
    ];
    return  Helper::keyFieldNameElastic(Elastic::searchQuery([
              'index'  => T::$tenantView,
              '_source'=> [],
              'query'  => $must 
              ]
            ), ['prop', 'unit']);
  }
//------------------------------------------------------------------------------
  public static function getReportName($firstRowOnly = 0) {
    $r = DB::table(T::$reportName)->select(['report_name_id', 'report_name']);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getReportGroup($where, $firstRowOnly = 0) {
    $r = DB::table(T::$reportGroup)->select('*')->where($where)->orderBy('order', 'asc');
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getReportList($reportGroupId) {
    $r = DB::table(T::$reportList)->select('*')->whereIn('report_group_id', $reportGroupId)->orderBy('order', 'asc')->orderBy('cdate', 'asc');
    return $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
//------------------------------------------------------------------------------
  public static function deleteWhereInTableData($table,$column,$columnValue){
    return DB::table($table)->whereIn($column, $columnValue)->delete();
  }
}