<?php
namespace App\Http\Controllers\Report\AccountingReport\BalanceSheetReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Report\AccountingReport\AccountingReportController AS P;
use App\Library\{V, Html, GridData, TableName AS T, Helper, Format, HelperMysql};
use App\Http\Models\{ReportModel AS M, Model}; // Include the models class
use Illuminate\Support\Arr;

class BalanceSheetReportController extends Controller{
  private $_mappingProp   = [];
  private $_mappingGlChart= [];
  
  public function __construct(Request $req){
    $this->_mappingProp    = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_mappingGlChart = Helper::getMapping(['tableName'=>T::$glChart]);
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
    $validateDB = [];
    if($id != 'Consolidated') {
      $req->merge(['prop'=>$id]);
      $validateDB = [
        'mustExist'=>[
          T::$prop.'|prop',
        ]
      ];
    }
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
      'validateDatabase' => $validateDB
    ]);
    $vData  = $valid['data'];
    return $this->_getGridData($vData); 
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req) {
    $req->merge(['report_name_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => [T::$reportName],
      'includeCdate'=>0,
      'isPopupMsgError' =>1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$reportName.'|report_name_id'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $perm  = Helper::getPermission($req);
    $sortablePerm = isset($perm['accountingReportmodify']) ? 'nested-sortable' : '';
    $reportGroup   = M::getReportGroup(['report_name_id'=>$vData['report_name_id']]);
    $reportGroupId = array_column($reportGroup, 'report_group_id');
    $reportList    = M::getReportList($reportGroupId);
    $sortable = '';
    foreach($reportGroup as $group) {
      $nestedSortable = '';
      foreach($reportList as $list) {
        if($group['report_group_id'] == $list['report_group_id']) {
          $editList  = Html::a(Html::i('', ['class'=>'fa fa-edit text-aqua pointer tip', 'title'=>'Edit List']), ['class'=>'listEdit', 'data-key'=>$list['report_list_id']]);
          $trashList = Html::a(Html::i('', ['class'=>'fa fa-trash-o text-red pointer tip', 'title'=>'Remove List']), ['class'=>'listRemove', 'data-key'=>$list['report_list_id']]);
          $iconsList = isset($perm['accountingReportmodify']) ? Html::div($editList . ' | ' . $trashList, ['class'=>'listIcon']) : '';
          $title = Html::div($list['name_list'], ['class'=>'listTitle']);
          $nestedSortable .= Html::div($iconsList . $title, ['class'=>'nested-2 list text-hover-list', 'data-order'=>$list['order'], 'data-id'=>$list['report_list_id']]);
        }
      }
      $editGroup  = Html::a(Html::i('', ['class'=>'fa fa-edit text-aqua pointer tip', 'title'=>'Edit Group']), ['class'=>'groupEdit', 'data-key'=>$group['report_group_id']]);
      $trashGroup = Html::a(Html::i('', ['class'=>'fa fa-trash-o text-red pointer tip', 'title'=>'Remove Group']), ['class'=>'groupRemove', 'data-key'=>$group['report_group_id']]);
      $iconsGroup = isset($perm['accountingReportmodify']) ? Html::div($editGroup . ' | ' . $trashGroup, ['class'=>'groupIcon']) : '';
      $sortable .= Html::div(Html::b($group['name_group']) . $iconsGroup . Html::div($nestedSortable, ['class'=>'list-group group '. $sortablePerm]),['class'=>'list-group-item nested-1 text-hover', 'data-order'=>$group['order'], 'data-id'=>$group['report_group_id']]);
    }
    

    return [
      'sortable'     => $sortable,
      'sortablePerm' => $sortablePerm,
      'submitForm'   => P::getReportForm(1)
    ];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'dateRange'    => 'required|string|between:21,23',
      'report'       => 'required|string',
      'prop'         => 'nullable|string',
      'group1'       => 'nullable|string',
      'city'         => 'nullable|string',
      'cons1'        => 'nullable|string',
      'trust'        => 'nullable|string',
      'prop_type'    => 'nullable|string|between:0,1',
      'selected'     => 'nullable|string|between:1,4',
      'groupBy'      => 'required|string',
    ] + GridData::getRuleReport(); 
  }
################################################################################
##########################   GRID FUNCTION   ###################################  
################################################################################  
  private function _getData($valid){
    $vData     = $valid['data'];
    $op        = $valid['op'];
    $vData['origProp'] = $vData['prop'];
    $vData['prop'] = P::getProps($vData);
    $tabData = $vData['groupBy'] == 'consolidate' ? 'Consolidated' : $vData['prop'];
    $column        = $this->_getColumnButtonReportList()['columns'];
    $rReportName   = M::getTableData(T::$reportName, Model::buildWhere(['report_name_id'=>$vData['report']]),'report_name', 1);

    if(!empty($op)){
      switch ($op) {
        case 'tab': return $this->_getTabData($tabData);
        case 'csv': return ReportController::getCsv($this->_getGridData($vData, 1, 1), ['column'=>$column]);
        case 'pdf': return ReportController::getPdf($this->_getPdfData($this->_getGridData($vData, 1), $column), ['title'=>$rReportName['report_name'].' Report','orientation'=>'P','isHeader'=>0, 'titleSpace'=>70, 'chunk'=>67], 1);
      }
    }
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req = []){
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV'];
    $data[] = ['field'=>'description', 'title'=>'Description', 'sortable'=> true,'filterControl'=> 'input', 'width'=>300, 'hWidth'=>300];
    $data[] = ['field'=>'amount', 'title'=>'Total Amount', 'sortable'=> true,'filterControl'=> 'input', 'width'=>200, 'hWidth'=>200];
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($vData, $isExport = 0, $isCsv = 0){
    if($vData['groupBy'] == 'consolidate' && !$isExport) {
      $vData['prop'] = P::getProps($vData);
    }else if ($vData['groupBy'] == 'consolidate' && count($vData['prop']) > 1) {
      $vData['prop'] = [$vData['prop']];
    }
    $vData += Helper::splitDateRate($vData['dateRange'],'date1');
    $reportGroup   = M::getReportGroup(['report_name_id'=>$vData['report']]);
    $reportGroupId = array_column($reportGroup, 'report_group_id');
    $reportList    = Helper::groupBy(M::getReportList($reportGroupId), 'report_group_id');
    $vData['prop'] = $isExport ? $vData['prop'] : [$vData['prop']];
    $rGlChart      = Helper::keyFieldNameElastic(HelperMysql::getGlChart(['prop.keyword'=>'Z64'],['gl_acct','title'], [], 0, 0), 'gl_acct','title');
    $rows = [];

    foreach($vData['prop'] as $prop) {
      $index = is_array($prop) ? 'consolidate' : $prop;
      $rGlChart = !is_array($prop) && preg_match('/[a-zA-Z]/', $prop) ? Helper::keyFieldNameElastic(HelperMysql::getGlChart(['prop.keyword'=>$prop],['gl_acct','title'], [], 0, 0), 'gl_acct','title') : $rGlChart;
      if($isExport) {
        $rows[$index][] = [
          'description'  => '',
          'amount'  => '',
        ];
        $rows[$index][] = [
          'prop' => $prop
        ];
        $rows[$index][] = [
          'description'  => '',
          'amount'  => '',
        ];
        $rows[$index][] = [
          'description'  => 'Description',
          'amount'  => 'Total Amount',
        ];
      }
      
      foreach($reportGroup as $i => $group) {
        $rows[$index][] = [
          'description' => Html::b(strtoupper($group['name_group']) . ':'),
          'amount'  => '',
        ];
        $yearGroupSum  = 0;
        foreach($reportList[$group['report_group_id']] as $k => $list) {
          $yearListSum = 0;
          $accTypeQueryArray = P::getAccTypeTermQuery($list['acct_type_list']);
          $glList = !empty($list['gl_list']) ? $list['gl_list'] : '';
          $glSumYear = P::getGlSum('gl_acct', $prop, $glList, $vData, $accTypeQueryArray);
          if(empty($list['gl_list'])) {
            $glSumYear = P::getGlSumAmount($glSumYear);
            $yearListSum += $glSumYear * $group['display_as'];
          }else {
            $rows[$index][] = [
              'description'  => Html::repeatChar('&nbsp;',3) . Html::b(strtoupper($list['name_list'])),
              'amount'  => '',
            ];
            $tempData = [];
            foreach($glSumYear as $v){
              if(isset($rGlChart[$v['key']])) {
                $tempData[$v['key']]['description'] = $rGlChart[$v['key']];
                $tempData[$v['key']]['amount'] = $v['total_amount']['value'] * $group['display_as'];
              }
            }
            foreach($tempData as $gl => $data) {
              $yearListSum  += $data['amount'];
              $rows[$index][] = [
                'description'  => Html::repeatChar('&nbsp;',6) . $data['description'],
                'amount'  => !empty($data['amount']) ? Format::usMoney($data['amount']) : '-',
              ];
            }     
          }
          $yearGroupSum  += $yearListSum;
          $rows[$index][] = [
            'description' => Html::repeatChar('&nbsp;',3) . Html::b('TOTAL '. strtoupper($list['name_list'])),
            'amount'      => Html::b(Format::usMoney($yearListSum)),
          ];
          $rows[$index][] = [
            'description' => '',
            'amount'      => '',
          ];
        }
        $rows[$index][] = [
          'description' => Html::b('TOTAL '. strtoupper($group['name_group'])),
          'amount'      => Html::b(Format::usMoney($yearGroupSum)),
        ];
        $rows[$index][] = [
          'description'  => '',
          'amount'  => '',
        ];
      }
      array_pop($rows[$index]);
    }

    ## Add prop info to the array if its pdf or csv
    $tempData = $explodedFields = $listOfProps = [];
    if($isExport){ 
      foreach($vData as $field => $value) {
        if(!empty($value) && ($field == 'group1' || $field == 'city' || $field == 'cons1' || $field == 'trust' || $field == 'origProp') ) {
          $explodedFields = array_merge($explodedFields, preg_split( '/(,|-)/', $value));
          $listOfProps = array_merge($listOfProps,  preg_split( '/(,)/', $value));
        }
      }
      if($vData['groupBy'] == 'prop') {
        $rProps = P::getPropInformations($vData['prop']);
      }else {
        $rProp = count($explodedFields) == 1 ? M::getTableData(T::$prop, Model::buildWhere(['prop'=>$explodedFields[0]]), ['prop','prop_name'], 1) : ['prop'=>implode(',',$listOfProps),'prop_name'=>'CONSOLIDATED'];
        $rProp = empty($rProp) ? ['prop'=>implode(',',$listOfProps),'prop_name'=>'CONSOLIDATED'] : $rProp;
      }
      foreach($rows as $k=>$value) {
        foreach($value as $i => $v) {
          if(isset($v['prop'])) {
            $info = ($vData['groupBy'] == 'consolidate') ? $rProp : $rProps[$v['prop']];
            $infoTable = ($vData['groupBy'] == 'consolidate') ? $this->_getConsolidatedInfoTable($info) : $this->_getPropertyInfoTable($info);
            $tempData[$k][] = [
              'description' => $infoTable['left'],
              'amount' => $infoTable['right']
            ];
            continue;
          }
          $tempData[$k][] = $v;
        }
      }
      $rows = $isCsv ? Arr::collapse($tempData) : $tempData;
    }
    return $isExport ? $rows : reset($rows);
  }
//------------------------------------------------------------------------------
  private function _getTabData($props) {
    $propTabs = $columns = [];
    $rProps = P::getPropInformations($props);
    if(empty($rProps)) {
      $propTabs[$props] = Html::table('', ['id'=>$props]);
      $columns[$props]  = $this->_getColumnButtonReportList();
    }else {
      foreach($rProps as $prop=>$info) {
        $infoTable = Html::buildTable([
          'isHeader'=>0,
          'isOrderList'=>0,
          'tableParam'=>['border'=>1,'width'=>'100%', 'class'=>'table table-bordered'],
          'data'=>[
            ['col1' => ['val'=>'Property #','param'=>['width'=>'20%']],'col2' => ['val'=>$info['prop'],'param'=>['width'=>'30%']],'col3' => ['val'=>'Purchase Price','param'=>['width'=>'20%']],'col4' => ['val'=>Format::usMoney($info['po_value']),'param'=>['width'=>'30%']]],
            ['col1' => ['val'=>'Address','param'=>['width'=>'20%']],'col2' => ['val'=>$info['street'].','.$info['city'].','.$info['state'].' '.$info['zip'],'param'=>['width'=>'30%']],'col3' => ['val'=>'Number of Unit','param'=>['width'=>'20%']],'col4' => ['val'=>$info['number_of_units'],'param'=>['width'=>'30%']]],
            ['col1' => ['val'=>'Purchase Date','param'=>['width'=>'20%']],'col2' => ['val'=>Format::usDate($info['start_date']),'param'=>['width'=>'30%']],'col3' => ['val'=>'Prop Class','param'=>['width'=>'20%']],'col4' => ['val'=>$this->_mappingProp['prop_class'][$info['prop_class']],'param'=>['width'=>'30%']]],
          ]
        ]);
        $propTabs[$prop] = Html::div(Html::h5(Html::b('PROPERTY INFORMATION'))) . $infoTable . Html::table('', ['id'=> $prop]);
        $columns[$prop]  = $this->_getColumnButtonReportList();
      }
    }
    $tab = Html::buildTab($propTabs, ['tabClass'=>'']);
    return ['tab' => $tab, 'column'=>$columns];
  }
//------------------------------------------------------------------------------
  private function _getPdfData($props, $column){
    $tableCollection = [];
    foreach($props as $idx => $prop) {
      $tableData = [];
      foreach($prop as $i=>$val){
        foreach($column as $v){
          $colVal  = [
            'val' => isset($val[$v['field']]) ? $val[$v['field']] : '',
          ];

          $colVal += !empty($v['param']) ? ['param'=>$v['param']] : [];

          $tableData[$i][$v['field']] = $colVal;
          
          if(($v['field'] == 'amount' || $v['field'] == 'description') && $val['description'] == 'Description') {
            $tableData[$i][$v['field']]['param'] = ['style'=>'font-weight:bold;font-size:11px;text-decoration:underline;'];
          }else {
            $tableData[$i][$v['field']]['param'] = ['style'=>'font-size:9px;'];
          }
          if(isset($val[$v['field']]) && preg_match('/consolidatedTitle/',$val[$v['field']])) {
            $tableData[$i][$v['field']]['param']['colspan'] =  $v['field'] == 'description' ? 2 : 0;
          }
        }
      }
      $tableCollection[$idx] = $tableData;
    }
    return $tableCollection;
  }
//------------------------------------------------------------------------------
  private function _getPropertyInfoTable($info) {
    $infoTable = [];
    $infoTable['left'] = Html::buildTable([
      'isHeader'=>0,
      'isOrderList'=>0,
      'tableParam'=>['border'=>1,'width'=>'100%', 'cellpadding'=>'1px', 'class'=>'table table-bordered'],
      'data'=>[
        ['col1' => ['val'=>'Property #','param'=>['width'=>'30%']],'col2' => ['val'=>$info['prop'],'param'=>['width'=>'70%']]],
        ['col1' => ['val'=>'Address','param'=>['width'=>'30%']],'col2' => ['val'=>$info['street'].','.$info['city'].','.$info['state'].' '.$info['zip'],'param'=>['width'=>'70%']]],
        ['col1' => ['val'=>'Purchase Date','param'=>['width'=>'30%']],'col2' => ['val'=>Format::usDate($info['start_date']),'param'=>['width'=>'70%']]],
      ]
    ]);
    $infoTable['right'] = Html::buildTable([
      'isHeader'=>0,
      'isOrderList'=>0,
      'tableParam'=>['border'=>1,'width'=>'100%', 'cellpadding'=>'1px', 'class'=>'table table-bordered'],
      'data'=>[
        ['col3' => ['val'=>'Purchase Price','param'=>['width'=>'50%','align'=>'left']],'col4' => ['val'=>Format::usMoney($info['po_value']),'param'=>['width'=>'50%','align'=>'left']]],
        ['col3' => ['val'=>'Number of Unit','param'=>['width'=>'50%','align'=>'left']],'col4' => ['val'=>$info['number_of_units'],'param'=>['width'=>'50%','align'=>'left']]],
        ['col3' => ['val'=>'Prop Class','param'=>['width'=>'50%','align'=>'left']],'col4' => ['val'=>$this->_mappingProp['prop_class'][$info['prop_class']],'param'=>['width'=>'50%','align'=>'left']]],
      ]
    ]);
    return $infoTable;
  }
//------------------------------------------------------------------------------
  private function _getConsolidatedInfoTable($info) {
    $infoTable = [];
    $param = ['width'=>'100%', 'align'=>'center','style'=>'font-weight:bold;font-size:11px;'];
    $infoTable['left'] = Html::buildTable([
      'isHeader'=>0,
      'isOrderList'=>0,
      'tableParam'=>['border'=>0,'width'=>'100%', 'cellpadding'=>'1px', 'class'=>'table consolidatedTitle'],
      'data'=>[
        ['col1' => ['val'=>$info['prop_name']. ' | ' .$info['prop'],'param'=>$param]],
      ]
    ]);
    $infoTable['right'] = '';
    return $infoTable;
  }
}
