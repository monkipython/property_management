<?php
namespace App\Http\Controllers\Report\AccountingReport\AccountingReportDefaultTemplate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Report\AccountingReport\AccountingReportController AS P;
use App\Library\{V, Html, GridData, TableName AS T, Helper, Format};
use App\Http\Models\{ReportModel AS M, Model}; // Include the models class

class AccountingReportDefaultTemplateController extends Controller{
  private $_mapping   = [];
  private $_chunk     = '';
  
  public function __construct(Request $req){
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$prop]);
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
    ]);
    return $this->_getData($valid);
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req) {
    $req->merge(['prop'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$prop.'|prop',
        ]
      ]
    ]);
    $vData  = $valid['data'];
    $vData += Helper::splitDateRate($vData['dateRange'],'date1');
    unset($vData['dateRange']);
    return $this->_getGridData($vData); 
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'report'       => 'required|string',
      'dateRange'    => 'required|string|between:21,23',
      'prop'         => 'nullable|string',
      'group1'       => 'nullable|string',
      'city'         => 'nullable|string',
      'cons1'        => 'nullable|string',
      'trust'        => 'nullable|string',
      'prop_type'    => 'nullable|string|between:0,1',
      'selected'     => 'nullable|string|between:1,4',
    ] + GridData::getRuleReport(); 
  }
################################################################################
##########################   GRID FUNCTION   ###################################  
################################################################################  
  private function _getData($valid){
    $vData     = $valid['data'];
    $op        = $valid['op'];
    $vData += Helper::splitDateRate($vData['dateRange'],'date1');
    unset($vData['dateRange']);

    $propList      = Helper::explodeField($vData,['prop','trust', 'group1', 'city','prop_type'])['prop'];
    $results       = M::getPropsWithTenant($propList);
    $vData['prop'] = array_column($results, 'key');
    sort($vData['prop']);
    $column        = $this->_getColumnButtonReportList()['columns'];
    $rReportName   = M::getTableData(T::$reportName, Model::buildWhere(['report_name_id'=>$vData['report']]),'report_name', 1);

    if(!empty($op)){
      switch ($op) {
        case 'tab': return $this->_getTabData($vData['prop']);
        case 'csv': return ReportController::getCsv($this->_getGridData($vData, 1), ['column'=>$column]);
        case 'pdf': return ReportController::getPdf($this->_getPdfData($this->_getGridData($vData, 1), $column), ['title'=>$rReportName['report_name'].' Report','orientation'=>'P', 'chunk'=>$this->_chunk,'isHeader'=>0, 'titleSpace'=>83]);
      }
    }
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req = []){
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV'];
    $data[] = ['field'=>'description', 'title'=>'Description', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 300, 'hWidth'=>300];
    $data[] = ['field'=>'amount', 'title'=>'Amount', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 250, 'hWidth'=>250];
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($vData, $isExport = 0){
    $reportGroup   = M::getReportGroup(['report_name_id'=>$vData['report']]);
    $reportGroupId = array_column($reportGroup, 'report_group_id');
    $reportList    = Helper::groupBy(M::getReportList($reportGroupId), 'report_group_id');
    $vData['prop'] = $isExport ? $vData['prop'] : [$vData['prop']];
    $rows = [];

    foreach($vData['prop'] as $prop) {
      if($isExport) {
        $rows[] = [
          'description' => 'PROPERTY INFORMATION',
          'amount'      => ''
        ];
        $rows[] = [
          'prop' => $prop
        ];
        $rows[] = [
          'description' => 'REPORT',
          'amount'      => ''
        ];
        $rows[] = [
          'description' => 'Description',
          'amount'      => 'Amount'
        ];
      }
      foreach($reportGroup as $i => $group) {
        $rows[] = [
          'description' => Html::b($group['name_group']),
          'amount'     => ''
        ];
        $totalSum = 0;
        foreach($reportList[$group['report_group_id']] as $k => $list) {
          $sum = P::getGlSum('prop', $prop, $list['gl_list'], $vData);
          $totalSum += P::getGlSumAmount($sum);
          $rows[] = [
            'description' => $list['name_list'],
            'amount'      => Format::usMoney($sum)
          ];
        }
        $rows[] = [
          'description' => Html::b('TOTAL '. strtoupper($group['name_group'])),
          'amount'      => Html::b(Format::usMoney($totalSum))
        ];
        $rows[] = [
          'description' => '',
          'amount'      => ''
        ];
      }
      if(empty($this->_chunk)) {
        $this->_chunk = count($rows);
      }
    }

    ## Add prop info to the array if its pdf or csv
    $tempData = [];
    if($isExport){
      $rProps = P::getPropInformations($vData['prop']);
      foreach($rows as $k=>$v) {
        if(isset($v['prop'])) {
          $info = $rProps[$v['prop']];
          $infoTable = Html::buildTable([
            'isHeader'=>0,
            'isOrderList'=>0,
            'tableParam'=>['border'=>1,'width'=>'100%', 'cellpadding'=>'1px', 'class'=>'table table-bordered'],
            'data'=>[
              ['col1' => ['val'=>'Property #','param'=>['width'=>'40%']],'col2' => ['val'=>$info['prop'],'param'=>['width'=>'60%']]],
              ['col1' => ['val'=>'Address','param'=>['width'=>'40%']],'col2' => ['val'=>$info['street'].','.$info['city'].','.$info['state'].' '.$info['zip'],'param'=>['width'=>'60%']]],
              ['col1' => ['val'=>'Purchase Date','param'=>['width'=>'40%']],'col2' => ['val'=>Format::usDate($info['start_date']),'param'=>['width'=>'60%']]],
            ]
          ]);
          $infoTable2 = Html::buildTable([
            'isHeader'=>0,
            'isOrderList'=>0,
            'tableParam'=>['border'=>1,'width'=>'100%', 'cellpadding'=>'1px', 'class'=>'table table-bordered'],
            'data'=>[
              ['col3' => ['val'=>'Purchase Price','param'=>['width'=>'40%']],'col4' => ['val'=>Format::usMoney($info['po_value']),'param'=>['width'=>'60%']]],
              ['col3' => ['val'=>'Number of Unit','param'=>['width'=>'40%']],'col4' => ['val'=>$info['number_of_units'],'param'=>['width'=>'60%']]],
              ['col3' => ['val'=>'Prop Class','param'=>['width'=>'40%']],'col4' => ['val'=>$this->_mapping['prop_class'][$info['prop_class']],'param'=>['width'=>'60%']]],
            ]
          ]);
          $tempData[] = [
            'description' => $infoTable,
            'amount'      => $infoTable2
          ];
          continue;
        }
        $tempData[] = $v;
      }
      $rows = $tempData;
    }
    return $rows;
  }
//------------------------------------------------------------------------------
  private function _getTabData($props) {
    $propTabs = $columns = [];
    $rProps = P::getPropInformations($props);
    foreach($rProps as $prop=>$info) {
      $infoTable = Html::buildTable([
        'isHeader'=>0,
        'isOrderList'=>0,
        'tableParam'=>['border'=>1,'width'=>'100%', 'class'=>'table table-bordered'],
        'data'=>[
          ['col1' => ['val'=>'Property #','param'=>['width'=>'20%']],'col2' => ['val'=>$info['prop'],'param'=>['width'=>'30%']],'col3' => ['val'=>'Purchase Price','param'=>['width'=>'20%']],'col4' => ['val'=>Format::usMoney($info['po_value']),'param'=>['width'=>'30%']]],
          ['col1' => ['val'=>'Address','param'=>['width'=>'20%']],'col2' => ['val'=>$info['street'].','.$info['city'].','.$info['state'].' '.$info['zip'],'param'=>['width'=>'30%']],'col3' => ['val'=>'Number of Unit','param'=>['width'=>'20%']],'col4' => ['val'=>$info['number_of_units'],'param'=>['width'=>'30%']]],
          ['col1' => ['val'=>'Purchase Date','param'=>['width'=>'20%']],'col2' => ['val'=>Format::usDate($info['start_date']),'param'=>['width'=>'30%']],'col3' => ['val'=>'Prop Class','param'=>['width'=>'20%']],'col4' => ['val'=>$this->_mapping['prop_class'][$info['prop_class']],'param'=>['width'=>'30%']]],
        ]
      ]);
      $propTabs[$prop] = Html::div(Html::h5(Html::b('PROPERTY INFORMATION'))) . $infoTable . Html::table('', ['id'=> $prop]);
      $columns[$prop]  = $this->_getColumnButtonReportList();
    }
    $tab = Html::buildTab($propTabs, ['tabClass'=>'']);
    return ['tab' => $tab, 'column'=>$columns];
  }
//------------------------------------------------------------------------------
  private function _getPdfData($r, $column){
    $tableData = [];
    foreach($r as $i=>$val){
      foreach($column as $v){
        $colVal  = [
          'val' => isset($val[$v['field']]) ? $val[$v['field']] : '',
        ];

        $colVal += !empty($v['param']) ? ['param'=>$v['param']] : [];
  
        $tableData[$i][$v['field']] = $colVal;
        ## Add colspan to the prop info table row
        if(($v['field'] == 'amount' || $v['field'] == 'description') && ($val['amount'] == 'Amount' || $val['description'] == 'Description')) {
          $tableData[$i][$v['field']]['param'] = ['style'=>'font-weight:bold;font-size:9px;text-decoration:underline;'];
        }else if($v['field'] == 'description' && $val['description'] == 'PROPERTY INFORMATION') {
          $tableData[$i][$v['field']]['param'] = ['style'=>'font-weight:bold;font-size:9px;'];
        }else if($v['field'] == 'description' && $val['description'] == 'REPORT') {
          $tableData[$i][$v['field']]['param'] = ['style'=>'font-weight:bold;font-size:9px;'];
        }
      }
    }
    return $tableData;
  }
}
