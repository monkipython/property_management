<?php
namespace App\Http\Controllers\Report\AccountingReport\OperatingStatementReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Report\AccountingReport\AccountingReportController AS P;
use App\Library\{V, Html, GridData, TableName AS T, Helper, Format, HelperMysql};
use App\Http\Models\{ReportModel AS M, Model}; // Include the models class
use Illuminate\Support\Arr;

class OperatingStatementReportController extends Controller{
  private $_mapping   = [];
  
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
    $data[] = ['field'=>'description', 'title'=>'Description', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 180, 'hWidth'=>180];
    $data[] = ['field'=>'monthAmount', 'title'=>'Current Month', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 80, 'hWidth'=>80];
    $data[] = ['field'=>'monthPercent', 'title'=>'%', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 80, 'hWidth'=>80];
    $data[] = ['field'=>'yearAmount', 'title'=>'Year to Date', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 80, 'hWidth'=>80];
    $data[] = ['field'=>'yearPercent', 'title'=>'%', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 80, 'hWidth'=>80];
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($vData, $isExport = 0, $isCsv = 0){
    if($vData['groupBy'] == 'consolidate' && !$isExport) {
      $vData['prop'] = P::getProps($vData);
    }else if ($vData['groupBy'] == 'consolidate' && count($vData['prop']) > 1) {
      $vData['prop'] = [$vData['prop']];
    }
    $vData  += Helper::splitDateRate($vData['dateRange'],'date1');
    $reportGroup   = M::getReportGroup(['report_name_id'=>$vData['report']]);
    $reportGroupId = array_column($reportGroup, 'report_group_id');
    $reportList    = Helper::groupBy(M::getReportList($reportGroupId), 'report_group_id');
    $vData['prop'] = $isExport ? $vData['prop'] : [$vData['prop']];
    $rGlChart      = Helper::keyFieldNameElastic(HelperMysql::getGlChart(['prop.keyword'=>'Z64'],['gl_acct','title'], [], 0, 0), 'gl_acct','title');
    $rows = [];
    $dateYear['date1']    = $vData['date1'];
    $dateMonth['date1']   = date('Y-m-01', strtotime($vData['todate1']));
    $dateMonth['todate1'] = $dateYear['todate1'] = $vData['todate1'];
   
    foreach($vData['prop'] as $prop) {
      $index = is_array($prop) ? 'consolidate' : $prop;
      $rGlChart = !is_array($prop) && preg_match('/[a-zA-Z]/', $prop) ? Helper::keyFieldNameElastic(HelperMysql::getGlChart(['prop.keyword'=>$prop],['gl_acct','title'], [], 0, 0), 'gl_acct','title') : $rGlChart;
      $dotRentSumYear  = P::getGlSum('prop', $prop, '.RENT', $dateYear);
      $dotRentSumMonth = P::getGlSum('prop', $prop, '.RENT', $dateMonth);
      $dotRentSumYear  = P::getGlSumAmount($dotRentSumYear);
      $dotRentSumMonth = P::getGlSumAmount($dotRentSumMonth);
      $dotRentSumYear  = $dotRentSumYear ? $dotRentSumYear * -1 : 0;
      $dotRentSumMonth = $dotRentSumMonth ? $dotRentSumMonth * -1 : 0;
      if($isExport) {
        $rows[$index][] = [
          'description'  => '',
          'monthAmount'  => '',
          'monthPercent' => '',
          'yearAmount'   => '',
        ];
        $rows[$index][] = [
          'prop' => $prop,
          'description' => '',
          'monthAmount'  => '',
          'monthPercent' => '',
          'yearAmount'   => '',
          'yearPercent'  => ''
        ];
        $rows[$index][] = [
          'description'  => '',
          'monthAmount'  => '',
          'monthPercent' => '',
          'yearAmount'   => '',
          'yearPercent'  => ''
        ];
        $rows[$index][] = [
          'description'  => 'Description',
          'monthAmount'  => 'Current Month',
          'monthPercent' => '%',
          'yearAmount'   => 'Year to Date',
          'yearPercent'  => '%'
        ];
      }
      $rows[$index][] = [
        'description'  => Html::b('GROSS RENT POTENTIAL'),
        'monthAmount'  => Html::b(Format::usMoney($dotRentSumMonth)),
        'monthPercent' => Html::b('100.00%'),
        'yearAmount'   => Html::b(Format::usMoney($dotRentSumYear)),
        'yearPercent'  => Html::b('100.00%')
      ];
      $rows[$index][] = [
        'description'  => Html::b('VACANCY'),
        'monthAmount'  => '',
        'monthPercent' => '',
        'yearAmount'   => '',
        'yearPercent'  => ''
      ];
      foreach($reportGroup as $i => $group) {
        $rows[$index][] = [
          'description'  => Html::b(strtoupper($group['name_group']) . ':'),
          'monthAmount'  => '',
          'monthPercent' => '',
          'yearAmount'   => '',
          'yearPercent'  => ''
        ];
        $yearGroupSum = $monthGroupSum = 0;
        foreach($reportList[$group['report_group_id']] as $k => $list) {
          $rows[$index][] = [
            'description'  => Html::repeatChar('&nbsp;',3) . Html::b(strtoupper($list['name_list'])),
            'monthAmount'  => '',
            'monthPercent' => '',
            'yearAmount'   => '',
            'yearPercent'  => ''
          ];
          $yearListSum = $monthListSum = 0;
          $accTypeQueryArray = P::getAccTypeTermQuery($list['acct_type_list']);
          $glSumMonth = P::getGlSum('gl_acct', $prop, $list['gl_list'], $dateMonth,$accTypeQueryArray);
          $glSumYear  = P::getGlSum('gl_acct', $prop, $list['gl_list'], $dateYear, $accTypeQueryArray);
          $tempData = [];
          foreach($glSumYear as $v){
            if(isset($rGlChart[$v['key']])) {
              if(!isset($tempData[$v['key']]['yearAmount'])) {
                $tempData[$v['key']]['yearAmount'] = 0;
              }
              $tempData[$v['key']]['description'] = $rGlChart[$v['key']];
              $tempData[$v['key']]['yearAmount']  += $v['total_amount']['value'];
              $tempData[$v['key']]['monthAmount'] = 0;
            }
          }
          foreach($glSumMonth as $v){
            if(isset($rGlChart[$v['key']])) {
              $tempData[$v['key']]['description'] = $rGlChart[$v['key']];
              $tempData[$v['key']]['yearAmount']  = !empty($tempData[$v['key']]['yearAmount']) ? $tempData[$v['key']]['yearAmount'] : 0;
              $tempData[$v['key']]['monthAmount'] += $v['total_amount']['value'];
            }
          }
          foreach($tempData as $gl => $data) {
            $data['yearAmount']  = $data['yearAmount'] * $group['display_as'];
            $data['monthAmount'] = $data['monthAmount'] * $group['display_as'];
            $yearListSum  += $data['yearAmount'];
            $monthListSum += $data['monthAmount'];
            $rows[$index][] = [
              'description'  => Html::repeatChar('&nbsp;',6) . $data['description'],
              'monthAmount'  => !empty($data['monthAmount']) ? Format::usMoney($data['monthAmount']) : '-',
              'monthPercent' => !empty($data['monthAmount']) ?  Format::percent($data['monthAmount'] / ($dotRentSumMonth ? $dotRentSumMonth : 1) * 100) : '-',
              'yearAmount'   => !empty($data['yearAmount']) ? Format::usMoney($data['yearAmount']) : '-',
              'yearPercent'  => !empty($data['yearAmount']) ? Format::percent($data['yearAmount'] / ($dotRentSumYear ? $dotRentSumYear : 1) * 100) : '-',
            ];
            if($data['description'] == 'APARTMENTS RENT') {
              foreach($rows[$index] as $i => $r) {
                if($r['description'] == '<b>VACANCY</b>') {
                  $vacMonthAmount = $data['monthAmount'] - $dotRentSumMonth;
                  $vacYearAmount = $data['yearAmount'] - $dotRentSumYear;
                  $rows[$index][$i] = [
                    'description'  => Html::b('VACANCY'),
                    'monthAmount'  => Html::b(Format::usMoney($vacMonthAmount)),
                    'monthPercent' => Html::b(Format::percent($vacMonthAmount / ($dotRentSumMonth ? $dotRentSumMonth : 1) * -100)),
                    'yearAmount'   => Html::b(Format::usMoney($vacYearAmount)),
                    'yearPercent'  => Html::b(Format::percent($vacYearAmount / ($dotRentSumYear ? $dotRentSumYear : 1) * -100)),
                  ];
                }
              }
            }
          }
          $yearGroupSum  += $yearListSum;
          $monthGroupSum += $monthListSum;
          $rows[$index][] = [
            'description'  => Html::repeatChar('&nbsp;',3) . Html::b('TOTAL '. strtoupper($list['name_list'])),
            'monthAmount'  => Html::b(Format::usMoney($monthListSum)),
            'monthPercent' => Html::b(Format::percent($monthListSum / ($dotRentSumMonth ? $dotRentSumMonth : 1) * 100)),
            'yearAmount'   => Html::b(Format::usMoney($yearListSum)),
            'yearPercent'  => Html::b(Format::percent($yearListSum / ($dotRentSumYear ? $dotRentSumYear : 1) * 100))
          ];
          $rows[$index][] = [
            'description'  => '',
            'monthAmount'  => '',
            'monthPercent' => '',
            'yearAmount'   => '',
            'yearPercent'  => ''
          ];
        }
        $rows[$index][] = [
          'description'  => Html::b('TOTAL '. strtoupper($group['name_group'])),
          'monthAmount'  => Html::b(Format::usMoney($monthGroupSum)),
          'monthPercent' => Html::b(Format::percent($monthGroupSum / ($dotRentSumMonth ? $dotRentSumMonth : 1) * 100)),
          'yearAmount'   => Html::b(Format::usMoney($yearGroupSum)),
          'yearPercent'  => Html::b(Format::percent($yearGroupSum / ($dotRentSumYear ? $dotRentSumYear : 1) * 100))
        ];
        $rows[$index][] = [
          'description'  => '',
          'monthAmount'  => '',
          'monthPercent' => '',
          'yearAmount'   => '',
          'yearPercent'  => ''
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
              'monthAmount' => $infoTable['right']
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
            ['col1' => ['val'=>'Purchase Date','param'=>['width'=>'20%']],'col2' => ['val'=>Format::usDate($info['start_date']),'param'=>['width'=>'30%']],'col3' => ['val'=>'Prop Class','param'=>['width'=>'20%']],'col4' => ['val'=>$this->_mapping['prop_class'][$info['prop_class']],'param'=>['width'=>'30%']]],
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
          
          if(($v['field'] == 'monthAmount' || $v['field'] == 'description' || $v['field'] == 'monthPercent' || $v['field'] =='yearAmount' || $v['field'] == 'yearPercent') && $val['description'] == 'Description') {
            $tableData[$i][$v['field']]['param'] = ['style'=>'font-weight:bold;font-size:11px;text-decoration:underline;'];
          }else {
            $tableData[$i][$v['field']]['param'] = ['style'=>'font-size:9px;'];
          }
          ## Add colspan to the prop info table row
          if(isset($val[$v['field']]) && preg_match('/consolidatedTitle/',$val[$v['field']])) {
            $tableData[$i][$v['field']]['param']['colspan'] =  $v['field'] == 'description' ? 5 : 0;
          }else if(isset($val[$v['field']]) && preg_match('/<table/',$val[$v['field']])) {
            $tableData[$i][$v['field']]['param']['colspan'] =  $v['field'] == 'description' ? 3 : 2;
          }else if($v['field'] == 'description') {
            $tableData[$i][$v['field']]['param']['width'] = '250px';
          }else {
            $tableData[$i][$v['field']]['param']['width'] = '80px';
            $tableData[$i][$v['field']]['param']['align'] = 'right';
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
    $leftParam = ['width'=>'40%'];
    $rightParam = ['width'=>'60%'];
    $infoTable['left'] = Html::buildTable([
      'isHeader'=>0,
      'isOrderList'=>0,
      'tableParam'=>['border'=>1,'width'=>'100%', 'cellpadding'=>'1px', 'class'=>'table table-bordered'],
      'data'=>[
        ['col1' => ['val'=>'Property #','param'=>$leftParam],'col2' => ['val'=>$info['prop'],'param'=>$rightParam]],
        ['col1' => ['val'=>'Address','param'=>$leftParam],'col2' => ['val'=>$info['street'].','.$info['city'].','.$info['state'].' '.$info['zip'],'param'=>$rightParam]],
        ['col1' => ['val'=>'Purchase Date','param'=>$leftParam],'col2' => ['val'=>Format::usDate($info['start_date']),'param'=>$rightParam]],
      ]
    ]);
    $infoTable['right'] = Html::buildTable([
      'isHeader'=>0,
      'isOrderList'=>0,
      'tableParam'=>['border'=>1,'width'=>'100%', 'cellpadding'=>'1px', 'class'=>'table table-bordered'],
      'data'=>[
        ['col3' => ['val'=>'Purchase Price','param'=>$leftParam],'col4' => ['val'=>Format::usMoney($info['po_value']),'param'=>$rightParam]],
        ['col3' => ['val'=>'Number of Unit','param'=>$leftParam],'col4' => ['val'=>$info['number_of_units'],'param'=>$rightParam]],
        ['col3' => ['val'=>'Prop Class','param'=>$leftParam],'col4' => ['val'=>$this->_mapping['prop_class'][$info['prop_class']],'param'=>$rightParam]],
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
