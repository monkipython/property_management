<?php
namespace App\Http\Controllers\Report\TrailBalanceReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController AS P;
use App\Library\{V, Form, Elastic, Html, GridData, TableName AS T, Helper, Format, PDFMerge, HelperMysql};
use App\Http\Models\{Model,ReportModel AS M}; // Include the models class
use Storage;
use PDF;
use SimpleCsv;

class TrailBalanceReportController extends Controller{
  private $_viewTable = '';
  private $_indexMain = '';
  private $_folderName = 'public/tmp/trailbalance/';
  private $_reportType = ['P'=>'Prop','T'=>'Trust','O'=>'Owner'];
  private $_reportList = ['A'=>'ASSETS','L'=>'LIABILITIES','C'=>'CAPITAL','I'=>'INCOME','E'=>'EXPENSES'];

  public function __construct(Request $req){
    $this->_viewTable = T::$glTransView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }

  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule($req->all()),
      'includeCdate'=>0,
    ]);
    return $this->getData($valid);
  }

//------------------------------------------------------------------------------
  public function create(Request $req){
    $fields = [
      'reportType'=>['id'=>'reportType','label'=>'Group By','type'=>'option','option'=>$this->_reportType,'req'=>1],
      'dateRange'=> ['id'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange', 'req'=>1],
      'prop'     => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea','placeHolder'=>'Ex. 0001-9999, 0028', 'value'=>'0001-9999', 'req'=>1],
      'trust'    => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. *ZA67'],
      // 'group1'   => ['id'=>'group1','label'=>'Group','type'=>'option', 'option'=>$propGroup],
      'cons1'    => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'Ex. Z64'],
    ];
    return [
      'html'=>implode('',Form::generateField($fields)), 
      'tab'=>[],
    ];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData     = $valid['data'];
    $op        = $valid['op'];
    $vData += Helper::splitDateRate($vData['dateRange'] ,'date1');
  
    $propList = Helper::explodeField($vData,['prop','trust', 'group1', 'cons1'])['prop'];

    $vData['prop'] = (isset($vData['selected']) && $op == 'show' && $vData['reportType'] == 'P') ? $vData['selected'] : $propList;
    
    if(!empty($op)){
      switch ($op) {
        case 'tab': return $this->_getTabData($vData);
        case 'show':
          return $this->_getGridData($vData);
        case 'csv': return ($vData['reportType'] == 'P') ? $this->_exportCSV($this->_getGridData($vData),$vData) : P::getCsv($this->_wrapCsvData($this->_getGridData($vData),$vData), ['column'=>$this->_getColumnButtonReportList()['columns']]);
        case 'pdf': return ($vData['reportType'] == 'P') ? $this->_exportPropPDF($this->_getGridData($vData),$vData) : $this->_exportPDF($this->_getGridData($vData),$vData);
      }
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule($req){
    return [
      'prop'     => 'required|string', 
      'trust'    => $req['reportType'] == 'T' ? 'required|string' : 'nullable|string',
      'cons1'    => $req['reportType'] == 'O' ? 'required|string' : 'nullable|string',
      'dateRange'=> 'required|string|between:21,23',
      'selected' => 'nullable|string',
      'reportType'=> 'required|string'
    ] + GridData::getRuleReport(); 
  }
//------------------------------------------------------------------------------
  private function _getGlChartByProps($vData) {
    $glCharts = [];
    $flag = false;
    $keys = ['gl_acct','acct_type'];
    if(is_array($vData['prop'])){
      foreach($vData['prop'] as $prop){
        if(preg_match('/^[0-9]+$/', $prop)){
          if(!$flag){
            $glCharts = array_merge($glCharts,Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])), $keys, 'title'));
            $flag = true;
          }
        }else{
          $glCharts = array_merge($glCharts,Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$prop])), $keys, 'title'));
        }
      }
    }else{
      $glCharts = array_merge($glCharts,Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$vData['prop']])), $keys, 'title'));
    }
    return $glCharts;
  }

################################################################################
##########################   GRID FUNCTION   ###################################  
################################################################################ 
//------------------------------------------------------------------------------
  private function _getTabData($vData) {
    $tabs = $columns = [];
    $propInfos = $this->_getPropInformations($vData['prop']);
    switch ($vData['reportType']) {
      case 'T':
      case 'O':
        $headerInfo = ($vData['reportType']=='T') ? $this->_getSummaryInformations($vData['trust'],$vData['reportType']) : $this->_getSummaryInformations($vData['cons1'],$vData['reportType']);
        $propCount = 0;
        $unitCount = 0;
        foreach($propInfos as $prop=>$info){
          $unitCount += $info['number_of_units'];
          $propCount++;
        }
        $infoTable = Html::buildTable([
          'isHeader'=>0,
          'isOrderList'=>0,
          // 'tableParam'=>['class'=>'table table-striped'],
          'tableParam'=>['border'=>1,'width'=>'100%'],
          'data'=>[
            ['col1' => ['val'=>($vData['reportType']=='T') ? 'Trust #' : 'Owner #','param'=>['width'=>'20%']],'col2' => ['val'=>$headerInfo['prop_name']]],
            ['col1' => ['val'=>'Address'],'col2' => ['val'=>$headerInfo['street'].','.$headerInfo['city'].','.$headerInfo['state'].' '.$headerInfo['zip']]],
            ['col1' => ['val'=>'Prop Count'],'col2' => ['val'=>$propCount]],
            ['col1' => ['val'=>'Unit Count'],'col2' => ['val'=>$unitCount]]
          ]
        ]);
        $tabs[$headerInfo['prop']] = Html::div(Html::h5(Html::b('TRUST INFORMATION'))) . $infoTable . Html::table('', ['id'=> str_replace('*','',$headerInfo['prop'])]);
        $columns[$headerInfo['prop']]  = $this->_getColumnButtonReportList();
        break;
      default:
        foreach($propInfos as $prop=>$info) {
          $infoTable = Html::buildTable([
            'isHeader'=>0,
            'isOrderList'=>0,
            // 'tableParam'=>['class'=>'table table-striped'],
            'tableParam'=>['border'=>1,'width'=>'100%'],
            'data'=>[
              ['col1' => ['val'=>'Property #','param'=>['width'=>'20%']],'col2' => ['val'=>$info['prop']]],
              ['col1' => ['val'=>'Address'],'col2' => ['val'=>$info['street'].','.$info['city'].','.$info['state'].' '.$info['zip']]],
              ['col1' => ['val'=>'Purchase Date'],'col2' => ['val'=>$info['start_date']]],
              ['col1' => ['val'=>'Purchase Price'],'col2' => ['val'=>Format::usMoney($info['po_value'])]],
              ['col1' => ['val'=>'Number of Unit'],'col2' => ['val'=>$info['number_of_units']]],
              ['col1' => ['val'=>'Prop Class'],'col2' => ['val'=>$info['prop_class']]]
            ]
          ]);
          $tabs[$prop] = Html::div(Html::h5(Html::b('PROPERTY INFORMATION'))) . $infoTable . Html::table('', ['id'=> $prop]);
          $columns[$prop]  = $this->_getColumnButtonReportList();
        }
        break;
    }
    $tab = Html::buildTab($tabs, ['tabClass'=>'']);
    return ['tab' => $tab, 'column'=>$columns];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req = []){
    $perm = Helper::getPermission($req);
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV'];
    $data[] = ['field'=>'gl_acct', 'title'=>'GL Acct', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'desciption', 'title'=>'Desc', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 100, 'hWidth'=>100];
    $data[] = ['field'=>'mtd_amount', 'title'=>'Mtd Amount', 'sortable'=> true,'filterControl'=> 'input', 'width'=>50, 'hWidth'=>50];
    $data[] = ['field'=>'ytd_amount', 'title'=>'Balance','sortable'=> true, 'filterControl'=> 'input', 'width'=>50,'hWidth'=>50];
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($vData){
    
    $rGlTrans = $this->_getReportDataFromElasticSearch($this->_viewTable,[
      'prop'=>$vData['prop'],
      'range'=>['date1'=>['gte'=>$vData['date1'],'lte'=>$vData['todate1']]],
      'regexp'=>['gl_acct'=>['value'=>'[0-9]+']]
    ],['prop','gl_acct','amount','acct_type','date1'],['prop.keyword','gl_acct.keyword','acct_type.keyword']);

    $glCharts = $this->_getGlChartByProps($vData);
    $result = [];
    if($vData['reportType'] == 'P') {
      $result = $this->_getPropGridData($this->_getReportRow($rGlTrans, $vData), $glCharts, $vData);
    }else{
      $result = $this->_getSummaryGridData($this->_getSumReportRow($rGlTrans, $vData),$glCharts);
    }
    return $result;
  }
//------------------------------------------------------------------------------
  private function _getReportRow($result, $vData) {
    $mtdData = $ytdData = [];
    $checkDate = date('Y-m-01', strtotime($vData['todate1']));

    foreach($result as $v){
      if(Helper::isBetweenDateRange($checkDate,$vData['dateRange'])){
        if(!isset($mtdData[$v['prop']][$v['acct_type']][$v['gl_acct']])){
          $mtdData[$v['prop']][$v['acct_type']][$v['gl_acct']] = 0;
        }
        $mtdData[$v['prop']][$v['acct_type']][$v['gl_acct']] += $v['amount'];
      }
      if(!isset($ytdData[$v['prop']][$v['acct_type']][$v['gl_acct']])){
        $ytdData[$v['prop']][$v['acct_type']][$v['gl_acct']] = 0;
      }
      $ytdData[$v['prop']][$v['acct_type']][$v['gl_acct']] += $v['amount'];
    }
    return ['mtd'=>$mtdData,'ytd'=>$ytdData];
  }
//------------------------------------------------------------------------------
  private function _getSumReportRow($result, $vData) {
    $mtdData = $ytdData = [];
    $checkDate = date('Y-m-01', strtotime($vData['todate1']));

    foreach($result as $v){
      if(Helper::isBetweenDateRange($checkDate,$vData['dateRange'])){
        if(!isset($mtdData[$v['acct_type']][$v['gl_acct']])){
          $mtdData[$v['acct_type']][$v['gl_acct']] = 0;
        }
        $mtdData[$v['acct_type']][$v['gl_acct']] += $v['amount'];
      }
      if(!isset($ytdData[$v['acct_type']][$v['gl_acct']])){
        $ytdData[$v['acct_type']][$v['gl_acct']] = 0;
      }
      $ytdData[$v['acct_type']][$v['gl_acct']] += $v['amount'];
    }
    return ['mtd'=>$mtdData,'ytd'=>$ytdData];
  }
//------------------------------------------------------------------------------
  private function _getPropGridData($reportRow,$chartKeys,$vData){
    $rows = $allListByProp = [];
    $totalMtd = 0;
    $totalYtd = 0;
    $netBalance =  0;
    foreach($reportRow['mtd'] as $prop=>$v1){
      foreach($v1 as $type =>$v2){
        $head = ['gl_acct'=>'','desciption'=>Html::b($this->_reportList[$type]),'mtd_amount'=>'','ytd_amount'=>''];
        $rows[] = $head;
        foreach($v2 as $gl =>$v3){
          $row = ['gl_acct'=>$gl,'desciption'=>$chartKeys[$gl.$type],'mtd_amount'=>Format::usMoney($v3),'ytd_amount'=>Format::usMoney($reportRow['ytd'][$prop][$type][$gl])];
          $rows[] = $row;
          $totalMtd += $v3;
          $totalYtd += $reportRow['ytd'][$prop][$type][$gl];
          $netBalance = $totalYtd;
        }
        $totalRow = ['gl_acct'=>'','desciption'=>Html::b('TOTAL '.$this->_reportList[$type]),'mtd_amount'=>Html::tag('span',Format::usMoney($totalMtd),['style'=>'border-top:2px solid #13648d;display:block;']),'ytd_amount'=>Html::tag('span',Format::usMoney($totalYtd),['style'=>'border-top:2px solid #13648d;display:block;'])];
        $rows[] = $totalRow;
        $totalMtd = 0;
        $totalYtd = 0;
      }
      $lastRow = ['gl_acct'=>'','desciption'=>Html::b('NET'),'mtd_amount'=>'','ytd_amount'=>Format::usMoney($totalYtd)];
      $rows[] = $lastRow;
      $allListByProp[$prop] = $rows;
      unset($rows);
    }

    if(is_array($vData['prop'])){
      return $allListByProp;
    }else{
      return isset($allListByProp[$vData['prop']]) ? $allListByProp[$vData['prop']] : [];
    }
  }
//------------------------------------------------------------------------------
  private function _getSummaryGridData($reportRow,$chartKeys){
    $rows = [];
    $totalMtd = 0;
    $totalYtd = 0;
    // // Calculate sum for each Gl account
    foreach($reportRow['mtd'] as $type=>$v2){
      $rows[] = ['gl_acct'=>'','desciption'=>Html::b($this->_reportList[$type]),'mtd_amount'=>'','ytd_amount'=>''];
      foreach($v2 as $gl =>$v3){
        $row = ['gl_acct'=>$gl,'desciption'=>$chartKeys[$gl.$type],'mtd_amount'=>Format::usMoney($v3),'ytd_amount'=>Format::usMoney($reportRow['ytd'][$type][$gl])];
        $rows[] = $row;
        $totalMtd += $v3;
        $totalYtd += $reportRow['ytd'][$type][$gl];
        $netBalance = $totalYtd;
      }
      $totalRow = ['gl_acct'=>'','desciption'=>Html::b('TOTAL '.$this->_reportList[$type]),'mtd_amount'=>Html::tag('span',Format::usMoney($totalMtd),['style'=>'border-top:2px solid #13648d;display:block;']),'ytd_amount'=>Html::tag('span',Format::usMoney($totalYtd),['style'=>'border-top:2px solid #13648d;display:block;'])];
      $rows[] = $totalRow;
    }
    $lastRow = ['gl_acct'=>'','desciption'=>Html::b('NET'),'mtd_amount'=>'','ytd_amount'=>Format::usMoney($totalYtd)];
    $rows[] = $lastRow;
    return $rows;
  }
################################################################################
##########################   Elastic Search FUNCTION   #########################  
################################################################################
//------------------------------------------------------------------------------
  private function _getPropInformations($propList) {
    $r = [];
    $search = Elastic::searchQuery([
      'index'=>T::$propView,
      'query'=>[
        'must'=>['prop'=>$propList],
      ],
      '_source'=>['prop','prop_class','street','city','state','zip','po_value','start_date','number_of_units'],
      "sort"=>['prop.keyword']
    ]);
    if(!empty($search['hits']['hits'])){
      $data = Helper::getElasticResult($search);
      foreach($data as $i=>$val){
        $r[$val['_source']['prop']] = $val['_source']; 
      }
    }
    return $r;
  }

//------------------------------------------------------------------------------
  private function _getSummaryInformations($prop,$reportType) {
    $r = [];
    $view = ($reportType=='T') ? T::$trustView : T::$propView;
    $search = Elastic::searchQuery([
      'index'=>$view,
      'query'=>[
        'must'=>['match'=>['prop'=>$prop]],
      ],
      '_source'=>['prop','prop_name','street','city','state','zip']
    ]);
    if(!empty($search['hits']['hits'])){
      $data = Helper::getElasticResult($search);
      $r = $data[0]['_source'];
    }
    return $r;
  }

//------------------------------------------------------------------------------
  private function _getReportDataFromElasticSearch($viewName,$filters=[],$source=[],$sort=[],$from=0,$size=1000) {
    $r = [];
    do {  
      $search = Elastic::searchQuery([
        'index'=>$viewName,
        'query'=>[
          'must'=>$filters,
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
    } while ($from<$total);
    return $r; 
  }

################################################################################
##########################   PDF FUNCTION   #################################  
################################################################################
  private function _exportPropPDF($gridData,$vData){
    $paths = [];
    $files = [];
    $fromDate = $vData['date1'];
    $toDate = $vData['todate1'];
    $propInfos = $this->_getPropInformations($vData['prop']);
    
    $tableData = [];
    foreach($gridData as $prop=>$data){
      if(!empty($data)){
        foreach($data as $v){
          $tableData[] = [
            'gl_acct' => ['val'=>$v['gl_acct'], 'header'=>['val'=>Html::b('GL Acct'), 'param'=>['width'=>'50']]],
            'desciption' => ['val'=>$v['desciption'], 'header'=>['val'=>Html::b('Desciption'), 'param'=>['width'=>'270']]],
            'mtd_amount' => ['val'=>$v['mtd_amount'], 'header'=>['val'=>Html::b('Mtd Amount'),'param'=>['width'=>'90','style'=>'text-align:right;']], 'param'=>(strpos($v['mtd_amount'],'border-top')>0) ? ['style'=>'border-top:1px solid black;text-align:right;'] : ['style'=>'text-align:right;']],
            'ytd_amount' => ['val'=>$v['ytd_amount'], 'header'=>['val'=>Html::b('Balance'),'param'=>['style'=>'text-align:right;']], 'param'=>(strpos($v['ytd_amount'],'border-top')>0) ? ['style'=>'border-top:1px solid black;text-align:right;'] : ['style'=>'text-align:right;']]
          ];
        }
        $table = Html::buildTable(['data'=>$tableData,'isOrderList'=>0]);
        $propTitle = '#'.Html::b($prop.' '.$propInfos[$prop]['street'].','.$propInfos[$prop]['city'].','.$propInfos[$prop]['state'].' '.$propInfos[$prop]['zip'].', Units:'.$propInfos[$prop]['number_of_units']).Html::br();
        $dateRangeTitle = $fromDate.' TO '.$toDate;
        $pageContent = Html::div(Html::b('TRAIL BALANCE').Html::br().$propTitle.$dateRangeTitle,['style'=>'text-align:center;']);
        $fileName = $this->_folderName . $prop . $fromDate . $toDate . '.pdf';
        $filePath = storage_path('app/' . $fileName);
        $this->_generatePdf($pageContent.$table,$filePath);
        array_push($paths,$filePath);
        array_push($files, $fileName);
        unset($tableData);
      }
    }
    // Call library to merge all PDF files into one PDF file.
    $outputFileName = $this->_folderName . 'Trail-Balance-'.$fromDate.'-to-'.$toDate.'.pdf';
    $href = Storage::disk('public')->url(preg_replace('|public\/|', '', preg_replace('/\#/', '%23', $outputFileName)));
    return PDFMerge::mergeFiles(['paths'=>$paths,'files'=>$files,'fileName'=>$outputFileName,'href'=>$href]);
  }
//------------------------------------------------------------------------------
  private function _generatePdf($content,$filePath){
    $title       = 'TRAIL BALANCE';
    $orientation = 'P';
    $font        = 'times';
    $size        = '13';
    try{
      PDF::reset();
      PDF::SetTitle($title);
      PDF::setPageOrientation($orientation);
      PDF::setPrintHeader(false);
      PDF::SetPrintFooter(false);
      PDF::SetFont($font, '', $size);
      PDF::SetMargins(10, 13, 10);
      PDF::SetAutoPageBreak(TRUE, 10);
      PDF::AddPage();
      PDF::writeHTML($content,true,false,true,false,$orientation);
      PDF::Output($filePath, 'F');
    } catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
//------------------------------------------------------------------------------
  private function _exportPDF($gridData,$vData){
    $paths = [];
    $files = [];
    $headInfo = [];
    $fromDate = $vData['date1'];
    $toDate = $vData['todate1'];
    $tableData = [];
    $propCount = 0;
    $unitCount = 0;
    $propInfos = $this->_getPropInformations($vData['prop']);
    $headInfo = ($vData['reportType']=='T') ? $this->_getSummaryInformations($vData['trust'],$vData['reportType']) : $this->_getSummaryInformations($vData['cons1'],$vData['reportType']);
    foreach($propInfos as $prop=>$info){
      $unitCount += $info['number_of_units'];
      $propCount++;
    }
    foreach($gridData as $type=>$v){
      $tableData[] = [
        'gl_acct' => ['val'=>$v['gl_acct'], 'header'=>['val'=>Html::b('GL Acct'), 'param'=>['width'=>'50']]],
        'desciption' => ['val'=>$v['desciption'], 'header'=>['val'=>Html::b('Desciption'), 'param'=>['width'=>'270']]],
        'mtd_amount' => ['val'=>$v['mtd_amount'], 'header'=>['val'=>Html::b('Mtd Amount'),'param'=>['width'=>'90','style'=>'text-align:right;']], 'param'=>(strpos($v['mtd_amount'],'border-top')>0) ? ['style'=>'border-top:1px solid black;text-align:right;'] : ['style'=>'text-align:right;']],
        'ytd_amount' => ['val'=>$v['ytd_amount'], 'header'=>['val'=>Html::b('Balance'),'param'=>['style'=>'text-align:right;']], 'param'=>(strpos($v['ytd_amount'],'border-top')>0) ? ['style'=>'border-top:1px solid black;text-align:right;'] : ['style'=>'text-align:right;']]
      ];
    }
    $table = Html::buildTable(['data'=>$tableData,'isOrderList'=>0]);
    $title = Html::b($headInfo['prop'].' '.$headInfo['prop_name']).Html::br();
    $title .= Html::b($headInfo['street'].','.$headInfo['city'].','.$headInfo['state'].' '.$headInfo['zip'].', Prop Count:'.$propCount.', Unit Count:'.$unitCount).Html::br();
    $dateRangeTitle = $fromDate.' TO '.$toDate;
    $pageContent = Html::div(Html::b('TRAIL BALANCE').Html::br().$title.$dateRangeTitle,['style'=>'text-align:center;']);
    $fileName = $this->_folderName . $headInfo['prop'] . $fromDate . $toDate . '.pdf';
    $filePath = storage_path('app/' . $fileName);
    $this->_generatePdf($pageContent.$table,$filePath);
    // Call library to merge all PDF files into one PDF file.
    $outputFileName = $this->_folderName . 'Trail-Balance-'.$fromDate.'-to-'.$toDate.'.pdf';
    $href = Storage::disk('public')->url(preg_replace('|public\/|', '', preg_replace('/\#/', '%23', $outputFileName)));
    return PDFMerge::mergeFiles(['paths'=>[$filePath],'files'=>[$fileName],'fileName'=>$outputFileName,'href'=>$href]);
  }
################################################################################
##########################   CSV FUNCTION   #################################  
################################################################################
  private function _exportCSV($gridData,$vData) {
    $propInfos = $this->_getPropInformations($vData['prop']);
    $csvData = [];
    $fromDate = $vData['date1'];
    $toDate = $vData['todate1'];
    $paths = [];
    $files = [];
    foreach($gridData as $prop=>$data){
      if(!empty($data)){
        $title = '#'.$prop.' '.$propInfos[$prop]['street'].','.$propInfos[$prop]['city'].','.$propInfos[$prop]['state'].' '.$propInfos[$prop]['zip'].', Units:'.$propInfos[$prop]['number_of_units'];
        // Title Header
        $csvData[] = [
          'GL Acct' => '',
          'Desc' => $title,
          'Mtd Amount' => '',
          'Balance' => ''
        ];
        foreach($data as $v){
          $csvData[] = [
            'GL Acct' => $v['gl_acct'],
            'Desc' => preg_replace('/<[^>]*>|&nbsp;/', '', $v['desciption']),
            'Mtd Amount' => preg_replace('/<[^>]*>|&nbsp;/', '', $v['mtd_amount']),
            'Balance' => preg_replace('/<[^>]*>|&nbsp;/', '', $v['ytd_amount'])
          ];
        }
        $fileName = $prop . '-' . $fromDate . '-' . $toDate . '.csv';
        $filePath = storage_path('app/' . $this->_folderName . $fileName);
        $paths[$fileName] = $filePath;
        array_push($files, $this->_folderName . $fileName);
        $this->_generateCSV($csvData,$filePath);
        unset($csvData);
      }
    }
    // Call library to merge all PDF files into one PDF file.
    $outputFileName = storage_path('app/' . $this->_folderName . 'Trail-Balance-'.$fromDate.'-to-'.$toDate.'.zip');
    $href = Storage::disk('public')->url(preg_replace('|public\/|', '', preg_replace('/\#/', '%23', $outputFileName)));
    return Helper::exportZip(['paths'=>$paths,'files'=>$files,'fileName'=>$outputFileName,'href'=>$href]);
  }

//------------------------------------------------------------------------------
  private function _generateCSV($row,$filePath) {
    $exporter = SimpleCsv::export(collect($row));
    $exporter->save($filePath);
  }
//------------------------------------------------------------------------------
  private function _wrapCsvData($gridData,$vData) {
    $csvData = [];
    $propCount = 0;
    $unitCount = 0;
    $headInfo = [];
    $propInfos = $this->_getPropInformations($vData['prop']);
    $headInfo = ($vData['reportType']=='T') ? $this->_getSummaryInformations($vData['trust'],$vData['reportType']) : $this->_getSummaryInformations($vData['cons1'],$vData['reportType']);
    foreach($propInfos as $prop=>$info){
      $unitCount += $info['number_of_units'];
      $propCount++;
    }
    $firstHeader = $headInfo['prop'].' '.$headInfo['prop_name'];
    $secondHeader = $headInfo['street'].','.$headInfo['city'].','.$headInfo['state'].' '.$headInfo['zip'].', Prop Count:'.$propCount.', Unit Count:'.$unitCount;
    $csvData[] = ['gl_acct' => '','desciption' => $firstHeader,'mtd_amount' => '','ytd_amount' => ''];
    $csvData[] = ['gl_acct' => '','desciption' => $secondHeader,'mtd_amount' => '','ytd_amount' => ''];
    foreach($gridData as $type=>$v){
      $csvData[] = ['gl_acct' => $v['gl_acct'],'desciption' => $v['desciption'],'mtd_amount' => $v['mtd_amount'],'ytd_amount' => $v['ytd_amount']];
    }
    return $csvData;
  }
}