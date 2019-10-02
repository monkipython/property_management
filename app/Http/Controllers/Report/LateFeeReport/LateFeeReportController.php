<?php
namespace App\Http\Controllers\Report\LateFeeReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController AS P;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper, RuleField, Format};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\ReportModel AS M; // Include the models class
use Storage;

class LateFeeReportController extends Controller{
  private $_viewTable = '';
  // private $_indexMain = '';
  private $_mapping = [];
  private $_propMapping = [];
  private $_waterGlAccount = 620;            //Water GL account 
  private $_trashGlAccount = 634;            //Trash GL account 
  private $_electricityGlAccount = 630;      //Electricity GL account 
  private $_gasGlAccount = 631;              //Gas GL account 
  private $_rentGlAccount = 602;             //Rent GL account 
  private $_lateGlAccount = 620;             //Late GL account 

  public function __construct(Request $req){
    $this->_mapping       = Helper::getMapping(['tableName'=>T::$tenant]);
    $this->_propMapping   = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_viewTable     = T::$tntTransView;
    // $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
    ]);
    $vData  = $valid['data'];
    $op     = $valid['op'];
    $propList  = Helper::explodeField($vData,['prop','group1','prop_type'])['prop'];
    $vData += Helper::splitDateRate($vData['dateRange'] ,'date1');
    unset($vData['dateRange']);
    $rTenant = $this->getTenantFromElasticSearch(['prop'=>$propList],['match'=>['status'=>'C']],['prop','unit','tenant','move_in_date'],['prop.keyword','unit.keyword','tenant']);
    $aggregateCondition = $this->_getAggregateCondition($rTenant);
    // https://www.elastic.co/guide/en/elasticsearch/reference/6.7/search-aggregations-bucket-composite-aggregation.html
    $rLateFee = $this->getTenantLateFeeReportFromElasticSearch(array_merge(['range'=>['date1'=>['gte'=>$vData['date1'],'lte'=>$vData['todate1']]],'dis_max'=>['queries'=>[['match'=>['tx_code'=>'IN']],['match'=>['tx_code'=>'P']]]]],$aggregateCondition));
    $rTrans = $this->getTenantTransFromElasticSearch(array_merge(['range'=>['date1'=>['lt'=>$vData['date1']]],'dis_max'=>['queries'=>[['match'=>['tx_code'=>'IN']],['match'=>['tx_code'=>'P']]]]],$aggregateCondition));
    unset($aggregateCondition);
    $columnReportList = $this->_getColumnButtonReportList($req);
    $column = $columnReportList['columns'];

    if(!empty($op) ){
      $field = P::getSelectedField($columnReportList, 1);
      $gridData = $this->_getGridData($rTenant,$rTrans,$rLateFee);
      switch ($op) {
        case 'show':  return $gridData; 
        // case 'graph': return $this->_getGraphData($r,$type);
        case 'csv':   return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':   return P::getPdf(P::getPdfData($gridData, $column), ['title'=>'Late Fee Report From '.$vData['date1'].' To '.$vData['todate1']]);
      }
    }
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $fields = [
      'dateRange' => ['id'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange', 'req'=>1],
      'prop'      => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea', 'value'=>'0001-9999', 'req'=>1],
      'group1'    => ['id'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
      'prop_type' => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_propMapping['prop_type']], 
    ];
    return ['html'=>implode('',Form::generateField($fields)), 'column'=>$this->_getColumnButtonReportList($req)];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'dateRange'  => 'required|string|between:21,23',
      'prop'       => 'required|string',
      'group1'     => 'nullable|string',
      'prop_type'  => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport(); 
  }
################################################################################
##########################   GRID FUNCTION   #################################  
################################################################################  
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV'];
    
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 30, 'hWidth'=>30];
    $data[] = ['field'=>'unit', 'title'=>'Unit', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 30, 'hWidth'=>30];
    $data[] = ['field'=>'tenant', 'title'=>'Tnt', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>20];
    $data[] = ['field'=>'move_in_date', 'title'=>'Start Date', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 40, 'hWidth'=>45];
    $data[] = ['field'=>'bal_foward', 'title'=>'Bal Foward', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 30, 'hWidth'=>45];
    $data[] = ['field'=>'in_water', 'title'=>'Water(IN)', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>36];
    $data[] = ['field'=>'in_trash', 'title'=>'Trash(IN)', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>35];
    $data[] = ['field'=>'in_ele', 'title'=>'Ele(IN)', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>35];
    $data[] = ['field'=>'in_gas', 'title'=>'Gas(IN)', 'sortable'=> true,'filterControl'=> 'input', 'width'=>25, 'hWidth'=>35];
    $data[] = ['field'=>'in_rent', 'title'=>'Rent(IN)', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 30, 'hWidth'=>40];
    $data[] = ['field'=>'in_late', 'title'=>'Late(IN)', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>35];
    $data[] = ['field'=>'in_other', 'title'=>'Other(IN)', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>38];
    $data[] = ['field'=>'in_total', 'title'=>'Total(IN)','sortable'=> true,'filterControl'=> 'input','width'=> 30, 'hWidth'=>45];
    $data[] = ['field'=>'p_water', 'title'=>'Water(P)','sortable'=> true,'filterControl'=> 'input','width'=> 25, 'hWidth'=>36];
    $data[] = ['field'=>'p_trash', 'title'=>'Trash(P)','sortable'=> true,'filterControl'=> 'input','width'=> 25, 'hWidth'=>35];
    $data[] = ['field'=>'p_ele', 'title'=>'Ele(P)','sortable'=> true,'filterControl'=> 'input','width'=> 25, 'hWidth'=>35];
    $data[] = ['field'=>'p_gas', 'title'=>'Gas(P)','sortable'=> true,'filterControl'=> 'input','width'=> 25, 'hWidth'=>35];
    $data[] = ['field'=>'p_rent', 'title'=>'Rent(P)','sortable'=> true,'filterControl'=> 'input','width'=> 30, 'hWidth'=>40];
    $data[] = ['field'=>'p_late', 'title'=>'Late(P)','sortable'=> true,'filterControl'=> 'input','width'=> 25, 'hWidth'=>35];
    $data[] = ['field'=>'p_other', 'title'=>'Other(P)','sortable'=> true,'filterControl'=> 'input','width'=> 25, 'hWidth'=>38];
    $data[] = ['field'=>'p_total', 'title'=>'Total(P)','sortable'=> true,'filterControl'=> 'input','width'=> 30, 'hWidth'=>45];
    $data[] = ['field'=>'end_bal', 'title'=>'End Bal','sortable'=> true,'filterControl'=> 'input','width'=> 30, 'hWidth'=>45];
    
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($rTenant,$rTrans,$rLateFee){
    $rows = [];
    $lastRow = [];
    $invoiceCode = 'IN';
    $payCode = 'P';
    foreach($rTenant as $source){
      if(isset($rLateFee[$source['prop'].$source['unit'].$source['tenant']])){
        $lateFee = $rLateFee[$source['prop'].$source['unit'].$source['tenant']];
        $balFoward = (isset($rTrans[$source['prop'].$source['unit'].$source['tenant']])) ? $rTrans[$source['prop'].$source['unit'].$source['tenant']] : 0;
        $invoiceTotal = (isset($lateFee[$invoiceCode]['total'])) ? $lateFee[$invoiceCode]['total'] : 0;
        $payTotal = (isset($lateFee[$payCode]['total'])) ? $lateFee[$payCode]['total'] : 0;
        $total = $balFoward + $invoiceTotal + $payTotal;
        $source['bal_foward'] = (Format::usMoney($balFoward)!='$0.00' && Format::usMoney($balFoward)!='$(0.00)') ? Format::usMoney($balFoward) : '-';
        $source['in_water'] = (isset($lateFee[$invoiceCode][$this->_waterGlAccount])) ? Format::usMoney($lateFee[$invoiceCode][$this->_waterGlAccount]) : '-';
        $source['in_trash'] = (isset($lateFee[$invoiceCode][$this->_trashGlAccount])) ? Format::usMoney($lateFee[$invoiceCode][$this->_trashGlAccount]) : '-';
        $source['in_ele'] = (isset($lateFee[$invoiceCode][$this->_electricityGlAccount])) ? Format::usMoney($lateFee[$invoiceCode][$this->_electricityGlAccount]) : '-';
        $source['in_gas'] = (isset($lateFee[$invoiceCode][$this->_gasGlAccount])) ? Format::usMoney($lateFee[$invoiceCode][$this->_gasGlAccount]) : '-';
        $source['in_rent'] = (isset($lateFee[$invoiceCode][$this->_rentGlAccount])) ? Format::usMoney($lateFee[$invoiceCode][$this->_rentGlAccount]) : '-';
        $source['in_late'] = (isset($lateFee[$invoiceCode][$this->_lateGlAccount])) ? Format::usMoney($lateFee[$invoiceCode][$this->_lateGlAccount]) : '-';
        $source['in_other'] = (isset($lateFee[$invoiceCode]['other'])) ? Format::usMoney($lateFee[$invoiceCode]['other']) : '-';
        $source['in_total'] = ($invoiceTotal!=0) ? Format::usMoney($invoiceTotal) : '-';
        $source['p_water'] = (isset($lateFee[$payCode][$this->_waterGlAccount])) ? Format::usMoney($lateFee[$payCode][$this->_waterGlAccount]) : '-';
        $source['p_trash'] = (isset($lateFee[$payCode][$this->_trashGlAccount])) ? Format::usMoney($lateFee[$payCode][$this->_trashGlAccount]) : '-';
        $source['p_ele'] = (isset($lateFee[$payCode][$this->_electricityGlAccount])) ? Format::usMoney($lateFee[$payCode][$this->_electricityGlAccount]) : '-';
        $source['p_gas'] = (isset($lateFee[$payCode][$this->_gasGlAccount])) ? Format::usMoney($lateFee[$payCode][$this->_gasGlAccount]) : '-';
        $source['p_rent'] = (isset($lateFee[$payCode][$this->_rentGlAccount])) ? Format::usMoney($lateFee[$payCode][$this->_rentGlAccount]) : '-';
        $source['p_late'] = (isset($lateFee[$payCode][$this->_lateGlAccount])) ? Format::usMoney($lateFee[$payCode][$this->_lateGlAccount]) : '-';
        $source['p_other'] = (isset($lateFee[$payCode]['other'])) ? Format::usMoney($lateFee[$payCode]['other']) : '-';
        $source['p_total'] = ($payTotal!=0) ? Format::usMoney($payTotal) : '-';
        $source['end_bal'] = (Format::usMoney($total)!='$0.00' && Format::usMoney($total)!='$(0.00)') ? Format::usMoney($total) : '-';
        $source['move_in_date'] = Format::usDate($source['move_in_date']);
        $rows[] = $source;
      }
    }
    return P::getRow($rows, $lastRow);
  }
//------------------------------------------------------------------------------
  private function _getAggregateCondition($r){
    $propList = [];
    $unitList = [];
    $tenantList = [];
    foreach($r as $item){
      array_push($propList,$item['prop']);
      array_push($unitList,$item['unit']);
      array_push($tenantList,$item['tenant']);
    }
    return Helper::selectData(['prop','unit','tenant'], ['prop'=>array_values(array_unique($propList)),'unit'=>array_values(array_unique($unitList)),'tenant'=>array_values(array_unique($tenantList))]);
  }

################################################################################
##########################   Elastic Search FUNCTION   #########################  
################################################################################
//------------------------------------------------------------------------------
  private function getTenantFromElasticSearch($filters=[],$queryConditions=[],$source=[],$sort=[],$fetchAllFlag=true,$from=0,$size=1000) {
    $r = [];
    do {  
      $search = Elastic::searchQuery([
        'index'=>T::$tenantView,
        'query'=>[
          'must'=>$queryConditions,
          'filter'=>$filters,
        ],
        '_source'=>$source,
        'sort'=>$sort,
        'from'=>$from,
        'size'=>$size
      ]);
      $from += $size;
      $total = $search['hits']['total'];
      if(!empty($search['hits']['hits'])){
        $data = Helper::getElasticResult($search);
        foreach($data as $i=>$val){
          array_push($r,$val['_source']);
        }
      }
    } while ($fetchAllFlag && ($from<$total));
    return $r; 
  }

//------------------------------------------------------------------------------
  private function getTenantLateFeeReportFromElasticSearch($queryConditions) {
    $r = [];
    $after = []; 
    $group = 'group_by_prop_unit_tenant_gl';
    $composite = ['size'=>10000,'sources'=>[
      ['by_prop'=>['terms'=>['field'=>'prop.keyword']]],
      ['by_unit'=>['terms'=>['field'=>'unit.keyword']]],
      ['by_tenant'=>['terms'=>['field'=>'tenant']]],
      ['by_gl'=>['terms'=>['field'=>'gl_acct.keyword']]],
      ['by_code'=>['terms'=>['field'=>'tx_code.keyword']]]]];
    do {
      if(!empty($after)){
        $composite['after'] = $after; //Get next page data by key
      }
      $search = Elastic::searchQuery([
        'index'=>T::$tntTransView,
        'size'=>0,
        'query'=>[
          'must'=>$queryConditions
        ],
        'aggs'=>[
          $group=> [
            'composite'=>$composite,
            'aggregations'=>['amount_sum'=>['sum'=>['field'=>'amount']]]
          ]
        ]
      ]);
      $data = Helper::getElasticAggResult($search,$group);
      if(!empty($data)){
        $after = $search['aggregations'][$group]['after_key']; //Get after search key
      }
      // Package the tenant trans data form({prop+unit+tenant:{'IN':{XXX:XXX...},'P':{XXX:XXX....}}})
      // The key format is "prop+unit+tenant"
      foreach($data as $item) {
        $key = $item['key']['by_prop'].$item['key']['by_unit'].$item['key']['by_tenant'];
        $glAcct = $item['key']['by_gl'];
        $code = $item['key']['by_code'];
        $amount = $item['amount_sum']['value'];
        $codeData = [];
        $keyData = [];
        if(isset($r[$key])){
          $keyData = $r[$key];
        }
        if(isset($keyData[$code])){
          $codeData = $keyData[$code];
        }
        switch($glAcct){
          case $this->_rentGlAccount:
          case $this->_lateGlAccount:
          case $this->_waterGlAccount:                
          case $this->_trashGlAccount:               
          case $this->_electricityGlAccount:          
          case $this->_gasGlAccount:                   
            $codeData[$glAcct] = $amount;
            break;
          default:         // Other
            $codeData['other'] = isset($codeData['other']) ? $codeData['other']+$amount : $amount;
            break;
        }
        // Sum the total
        $codeData['total'] = isset($codeData['total']) ? $codeData['total']+$amount : $amount;
        $keyData[$code] = $codeData;
        $r[$key] = $keyData;
      }
    } while (!empty($data));
    return $r;
  }

//------------------------------------------------------------------------------
  private function getTenantTransFromElasticSearch($queryConditions){
    $r = [];
    $after = []; 
    $group = 'group_by_prop_unit_tenant';
    $composite = ['size'=>10000,'sources'=>[
      ['by_prop'=>['terms'=>['field'=>'prop.keyword']]],
      ['by_unit'=>['terms'=>['field'=>'unit.keyword']]],
      ['by_tenant'=>['terms'=>['field'=>'tenant']]]]];
    do {
      if(!empty($after)){
        $composite['after'] = $after; //Get next page data by key
      }
      $search = Elastic::searchQuery([
        'index'=>T::$tntTransView,
        'size'=>0,
        'query'=>[
          'must'=>$queryConditions
        ],
        'aggs'=>[
          $group=> [
            'composite'=>$composite,
            'aggregations'=>['amount_sum'=>['sum'=>['field'=>'amount']],'amount_sum_filter'=>['bucket_selector'=>['buckets_path'=>['amountSum'=>'amount_sum'],'script'=>'params.amountSum!=0']]]
          ]
        ]
      ]);
      $data = Helper::getElasticAggResult($search,$group);
      if(!empty($data)){
        $after = $search['aggregations'][$group]['after_key']; //Get after search key
      }
      // Package the tenant trans data form({prop+unit+tenant:amount})
      // The key format is "prop+unit+tenant"
      foreach($data as $item) {
        $r[$item['key']['by_prop'].$item['key']['by_unit'].$item['key']['by_tenant']] = $item['amount_sum']['value'];
      }
    } while (!empty($data));
    return $r;
  }

################################################################################
##########################   PDF FUNCTION   #################################  
################################################################################  
  private function _getPdfData($r, $req){
    $column = $this->_getColumnButtonReportList($req)['columns'];
    $tableData = [];
    foreach($r as $i=>$val){
      foreach($column as $v){
        $tableData[$i][$v['field']] = [
          'val'=>$val[$v['field']], 
          'header'=>[
            'val'=>Html::b($v['title']), 
            'param'=>isset($v['hWidth']) ? ['width'=>$v['hWidth']] : []
          ]
        ];
      }
    }
    return $tableData;
  }
}
