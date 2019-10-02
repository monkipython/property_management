<?php
namespace App\Http\Controllers\Report\EvictionReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController AS P;
use App\Library\{V, Form, Elastic, GridData, TableName AS T,Html, Helper, Format};
use Illuminate\Support\Facades\DB;

class EvictionReportController extends Controller{
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping = [];
  public function __construct(Request $req){
    $this->_mapping      = Helper::getMapping(['tableName'=>T::$tenant]);
    $this->_propMapping  = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_viewTable    = T::$tenantView;
    $this->_indexMain    = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=> 0,
    ]);
    $op             = $valid['op'];
    $vData          = $valid['data'];
    $vData['prop']  = Helper::explodeField($vData,['prop','group1','prop_type','trust','city','cons1'])['prop'];
    $columnReportList     = $this->_getColumnButtonReportList($req);
    $column               = $columnReportList['columns'];
    if(!empty($op)){
      $fields = explode(',',P::getSelectedField($columnReportList) . ',remark_tnt.remark_tnt_id,remark_tnt.remark_code');
      $r = Elastic::searchQuery([
        'index'    => $this->_viewTable,
        'sort'     => ['prop.keyword'=>'asc'],
        '_source'  => $fields,
        'query'    => [
          'must' => [
            'prop.keyword'                         => $vData['prop'],
            'spec_code.keyword'                    => 'E',
            'status.keyword'                       => 'C',
            'wildcard' => [
              'mangtgroup.keyword' => '*'. $vData['mangtgroup']
            ]
          ]
        ]
      ]);
      switch ($op) {
        case 'show': return $this->_getGridData($r, $vData); 
        case 'csv':  return P::getCsv($this->_getGridData($r, $vData), ['column'=>$column]);
        case 'pdf':  return P::getPdf(P::getPdfData($this->_getGridData($r, $vData), $column), ['title'=> 'Eviction Report']);
      }
    }
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $mangtGroup = [''=>'All', 'PAMA'=>'PAMA', 'IERH'=>'IERH'];
    $fields = [
      'prop'       => ['id'=>'prop','name'=>'prop','label'=>'Prop','type'=>'textarea','value'=>'0001-9999','req'=>1],
      'prop_type'  => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_propMapping['prop_type'],'req'=>0],
      'group1'     => ['id'=>'group1','name'=>'group1','label'=>'Group','type'=>'textarea','placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'       => ['id'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Los Angeles'],
      'trust'      => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, *ZA67'],  
      'cons1'      => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'****83-**83,Z64'],
      'mangtgroup' => ['id'=>'mangtgroup', 'label'=>'Mgt Group', 'type'=>'option', 'option'=>$mangtGroup, 'value'=>''],
    ];
    return ['html'=>implode('',Form::generateField($fields)), 'column'=>$this->_getColumnButtonReportList($req)];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'prop'       => 'required|nullable|string', 
      'group1'     => 'nullable|string', 
      'mangtgroup' => 'nullable|string', 
      'prop_type'  => 'nullable|string|between:0,1',
      'city'       => 'nullable|string',
      'trust'      => 'nullable|string',
      'cons1'      => 'nullable|string',
    ] + GridData::getRuleReport(); 
  }
//------------------------------------------------------------------------------
   /* COMMENTED UNTIL TNT_TRANS_VIEW IS UP
  private function _getRentBal($vData) {
    $qRentBal = [
      'index' => T::$tntTransView, 
      'type'  => T::$tntTransView,  
      'size'  => 0, 
      'body'  => [
        'query' => [
          'bool' => [
            'must' => [
              'match' => ['spec_code' => 'E']
            ],
            'filter' => [
              'range' => ['prop'=>['gte'=>$vData['prop'], 'lte'=>$vData['toprop']]]
            ]
          ]
        ],
        'aggs'  => [
          'by_prop' => [
            'terms' => ['field' => 'prop.keyword', 'size'=>2147483647],
            'aggs'  => [
              'by_unit'=>[
                'terms' => ['field'=>'unit.keyword'],
                'aggs'  => [
                  'by_tenant' => [
                    'terms' => ['field'=>'tenant'],
                    'aggs'  => [
                      'amount_sum'       => ['sum'=>['field'=>'amount']],
                      'amount_sum_filter'=> ['bucket_selector'=>['buckets_path'=>['amountSum'=>'amount_sum'],'script'=>'params.amountSum > 0']]
                    ]
                  ]
                ]
              ]
            ]
          ]
        ]
      ]
    ];
    $rentBal = [];
    $rRentBal = Elastic::search($qRentBal);
    foreach($rRentBal['aggregations']['by_prop']['buckets'] as $propBuckets){
      foreach($propBuckets['by_unit']['buckets'] as $unitBuckets){
        foreach($unitBuckets['by_tenant']['buckets'] as $tenantBuckets){
          $rentBal[$propBuckets['key'] . $unitBuckets['key'] . $tenantBuckets['key']] = $tenantBuckets['amount_sum']['value'];
        }
      }
    }
    return $rentBal;
  } 
   */
  private function _getRentBal($vData) {
    $select = ['tt.prop', 'tt.unit', 'tt.tenant', DB::raw("SUM(tt.amount) as amount")];
    $where = [
      ['t.status', '=', 'C'],
      ['t.spec_code', '=', 'E']
    ];
    $results = DB::table(T::$tntTrans . ' as tt')
            ->join(T::$tenant . ' as t', function($join){
                $join->on('tt.prop',   '=', 't.prop')
                     ->on('tt.unit',   '=', 't.unit')
                     ->on('tt.tenant', '=', 't.tenant');
              })
            ->select($select)
            ->where($where)
            ->whereIn('tt.prop', $vData['prop'])
            ->groupBy('tt.prop', 'tt.unit', 'tt.tenant')
            ->get()
            ->toArray();
    $rows = Helper::keyFieldName($results, ['prop', 'unit', 'tenant'], 'amount');
    return $rows;
  }
################################################################################
##########################   GRID FUNCTION   ###################################  
################################################################################  
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $reportList = ['pdf' => 'Download PDF', 'csv' => 'Download CSV'];
    
    $data[] = ['field'=>'mangtgroup', 'title'=>'Mgt Grp','sortable'=> true,'filterControl'=> 'input','width'=> 30, 'hWidth'=>40];
    $data[] = ['field'=>'group1', 'title'=>'Grp','sortable'=> true,'filterControl'=> 'input','width'=> 30, 'hWidth'=>30];
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 30, 'hWidth'=>30];
    $data[] = ['field'=>'unit', 'title'=>'Unit', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 30, 'hWidth'=>30];
    $data[] = ['field'=>'tenant', 'title'=>'Tnt', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 30, 'hWidth'=>20];
    $data[] = ['field'=>'tnt_name', 'title'=>'Tenant Name', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 200, 'hWidth'=>70];
    $data[] = ['field'=>'street', 'title'=>'Address','sortable'=> true,'filterControl'=> 'input','width'=> 200, 'hWidth'=>80];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>60];
    $data[] = ['field'=>'status', 'title'=>'Status', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>40];
    $data[] = ['field'=>'base_rent', 'title'=>'Base Rent', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'rent_bal', 'title'=>'Rent Bal', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'dep_held1', 'title'=>'Deposit', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'move_in_date', 'title'=>'Move In', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'remark_tnt.date1', 'title'=>'Filed', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 90, 'hWidth'=>50];
    $data[] = ['field'=>'remark_tnt.remarks', 'title'=>'Remarks', 'sortable'=> true,'filterControl'=> 'input', 'hWidth'=>165];
    
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($r, $vData){
    $result = Helper::getElasticResult($r);
    $rows = [];
    $lastRow = ['tnt_name'=>0, 'rent_bal'=>0, 'base_rent'=>0,'dep_held1'=>0, 'pama_count'=>0, 'ierh_count'=>0];
    $rentBal = $this->_getRentBal($vData);
    foreach($result as $i=>$v){
      $source = $v['_source']; 
      $id = $source['prop'] . $source['unit'] . $source['tenant'];
      
      $source['status']    = $this->_mapping['status'][$source['status']];
      $source['rent_bal']  = isset($rentBal[$id]) ? $rentBal[$id] : 0;
    
      if (stripos($source['mangtgroup'], 'PAMA') !== false){
        $lastRow['pama_count']++;
      }else if(stripos($source['mangtgroup'], 'IERH') !== false){
        $lastRow['ierh_count']++;
      }
      $lastRow['tnt_name']++;
      $lastRow['rent_bal']  += $source['rent_bal'];
      $lastRow['dep_held1'] += $source['dep_held1'];
      $lastRow['base_rent'] += $source['base_rent'];
      $source['rent_bal']     = Format::usMoney($source['rent_bal']);
      $source['dep_held1']    = Format::usMoney($source['dep_held1']);
      $source['base_rent']    = Format::usMoney($source['base_rent']);
      $source['move_in_date'] = Format::usDate($source['move_in_date']);
      
      $remarks = '';
      if(!empty($source[T::$remarkTnt])){
        $tenantRemarks = [];
        foreach($source[T::$remarkTnt] as $idx => $val){
          if($val['remark_code'] === 'EVI'){
            $tenantRemarks[strtotime($val['date1']) . $val['remark_tnt_id']] = $val;
          }
        }
        krsort($tenantRemarks);
        $isFirstRemark  = true;
        foreach($tenantRemarks as $val){
          $remarks      .= $val['remarks'] . Html::br() . ($isFirstRemark ? Html::br() : '');
          $source[T::$remarkTnt . '.date1'] = $isFirstRemark ? Format::usDate($val['date1']) : Format::usDate($source[T::$remarkTnt . '.date1']);
          $isFirstRemark = false;
        }
      }
      $source[T::$remarkTnt . '.remarks'] = ucfirst($remarks);
      unset($source[T::$remarkTnt]);
      $rows[] = $source;
    }
    $lastRow['tnt_name']  = '# Tenant: ' . $lastRow['tnt_name'];
    $lastRow['street']    = '# PAMA: ' . $lastRow['pama_count'];
    $lastRow['city']      = '# IERH: ' . $lastRow['ierh_count'];
    $lastRow['rent_bal']  = Format::usMoney($lastRow['rent_bal']);
    $lastRow['dep_held1'] = Format::usMoney($lastRow['dep_held1']);
    $lastRow['base_rent'] = Format::usMoney($lastRow['base_rent']);
    
    return P::getRow($rows, $lastRow);
  }
}