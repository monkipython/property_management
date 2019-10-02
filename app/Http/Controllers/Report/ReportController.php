<?php
namespace App\Http\Controllers\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, PDFMerge, Account, TableName AS T, Helper};
use App\Http\Models\Model; // Include the models class
use SimpleCsv;
use PDF;
use Storage;
class ReportController extends Controller{
  private $_viewPath  = 'app/Report/report/';
  private $_mapping   = [];
  private $nestedReportList = [
    'Accounts Payable' => [
      'checkRegisterReport'=>'Check Register',
      'fundTransferReport' =>'Fund Transfer', 
      'trailBalanceReport' =>'Trail Balance',
    ],
    'Accounts Receivable' => [
      'cashRecReport'      =>'Cash Receipt',
      'generalLedgerReport'=>'General Ledger',
      'operatingStatementReport' => 'Operating Statement',
    ],
    'Property Management' => [
      'vacancyReport'           =>'Vacancy',
      'violationReport'         =>'Violation',
      'evictionReport'          =>'Eviction',
      'lateFeeReport'           =>'Late Fee',
      'lateChargeReport'        =>'Late Charge',
      'managerMoveinReport'     =>'Manager List',
      'moveOutReport'           =>'Move Out',
      'readyMoveInReport'       =>'Ready Move In',
      'rentRaiseSummaryReport'  =>'Rent Raise Summary',
      'rentRollReport'          =>'Rent Roll',
      'section8Report'          =>'Section 8',
      'supervisorReport'        =>'Supervisor Meeting',
      'tenantAmountOwedReport'  =>'Tenant Amount Owed',
      'tenantBalanceReport'     =>'Tenant Balance',
      'delinquencyReport'       =>'Tenant Rent Delinquency',
      'tenantStatusReport'      =>'Tenant Status',
    ]
  ];
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $page   = $this->_viewPath . 'index';
    $perm   = Helper::getPermission($req);
    $fields = [
      'report'=>['id'=>'report','label'=>'Report','type'=>'option','option'=>[''=>''], 'req'=>1],
    ];
    $nestedList = $this->_mapListToSelect2($this->nestedReportList, $perm);
    return view($page, ['data'=>[
      'reportHeader' => 'SELECT REPORT', 
      'reportForm'   => Form::getField($fields['report']),
      'nav'          => $req['NAV'],
      'account'      => Account::getHtmlInfo($req['ACCOUNT']),
      'dropdownData' => json_encode($nestedList)
    ]]);
  }

################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  public static function getPdf($tableData, $param, $isMultipleTable=0){
    $fileInfo = self::_getFileAndHref('pdf');
    $file = isset($param['filePath']) ? $param['filePath'] : $fileInfo['filePath'];
    $href = isset($param['href']) ? $param['href'] : $fileInfo['href'];
    $title = $param['title'];
    $titleSpace = isset($param['titleSpace']) ? $param['titleSpace'] : 115;
    $chunk = isset($param['chunk']) ? $param['chunk'] : 47;
    $orientation = isset($param['orientation']) ? $param['orientation'] : 'L';
    $isHeader    = isset($param['isHeader']) ? $param['isHeader'] : 1;
    $ipAddr      = \Request::ip();
    
    $font = isset($param['font']) ? $param['font'] : 'times';
    $size = isset($param['size']) ? $param['size'] : '8';
    $msg  = isset($param['popupMsg']) ? $param['popupMsg'] : 'Your export file is ready. Please click the link here to download it';
    $_space = function($num){
      $space = '';
      for($i=0; $i <= $num; $i++){
        $space .= ' ';
      }
      return $space;
    };
    try{
      $_getPageTotal = function($tableData,$chunk=50){
        $total = 0;
        foreach($tableData as $i => $table){
            $tableDatum = $chunk ? array_chunk($table,$chunk) : [$table];
            $total     += count($tableDatum);
        }
        return $total;
    };
    
      
      $tableData = $isMultipleTable ? $tableData : [$tableData];
      $pageCount = $_getPageTotal($tableData,$chunk);
      $pageNum   = 0;
      $paths     = $files = [];
      $tempDir   = 'public/tmp/' . $ipAddr;
      Storage::makeDirectory($tempDir);
      foreach($tableData as $i=>$table){
        $tableDatum = $chunk ? array_chunk($table,$chunk) : [$table];
        foreach($tableDatum as $idx=>$v){
       	  PDF::reset();
          PDF::SetTitle($title);
          PDF::setPageOrientation($orientation);

          # HEADER SETTING
          PDF::SetHeaderData('', '0', $_space($titleSpace) . $title, 'Run on ' . date('F j, Y, g:i a'));
          PDF::setHeaderFont([$font, '', ($size + 3)]);
          PDF::SetHeaderMargin(3);

          # FOOTER SETTING
          PDF::setPrintFooter(false);
          PDF::SetFont($font, '', $size);
          PDF::setFooterFont([$font, '', $size]);
          PDF::SetFooterMargin(5);

          PDF::SetMargins(5, 13, 5); 
          PDF::SetAutoPageBreak(TRUE, 10);

          $tmpFilename  = 'tmp_chunk_' . $i . '_' . $idx . '.pdf';
          $dirPath      = storage_path('app/public/tmp/' . $ipAddr . '/');
          $paths[]      = $dirPath . $tmpFilename;
          $files[]      = $tempDir . '/' . $tmpFilename;
          PDF::AddPage();
          PDF::writeHTML(Html::buildTable(['data'=>$v,'isAlterColor'=>1,'isOrderList'=>0,'isHeader'=>$isHeader]),true,false,true,false,$orientation);
          PDF::SetY(-15);
          PDF::writeHtmlCell(0,0,'','','<hr>Page ' . (++$pageNum) . ' / ' . $pageCount,0,0,false,true,'L',true);
          PDF::Output($dirPath . $tmpFilename,'F');
          unset($tableDatum[$idx]);
        }
        unset($tableDatum);
        //PDF::Output($file, 'F');
      }
      unset($tableData);
      
      $mergeR = PDFMerge::mergeFiles([
         'msg'          => $msg,
         'href'         => $href,
         'fileName'     => $file,
         'files'        => $files,
         'paths'       => $paths,
      ]);
      
      
      Storage::deleteDirectory($tempDir);
      
      return [
        'file'=>(php_sapi_name() == "cli") ? storage_path('app/' . $fileInfo['filePath']) : '',
        'popupMsg'=>Html::a($msg, [
          'href'=>$href, 
          'target'=>'_blank',
          'class'=>'downloadLink'
        ])
      ];
    } catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
//------------------------------------------------------------------------------
  public static function getPdfData($r, $column){
    $tableData = [];
    foreach($r as $i=>$val){
      foreach($column as $v){
        $colVal  = [
          'val'     => isset($val[$v['field']]) ? $val[$v['field']] : '',
        ];

        $colVal      += !empty($v['param']) ? ['param'=>$v['param']] : [];
        $headerParam  = [];
        $headerParam += isset($v['hWidth']) ? ['width'=>$v['hWidth']] : [];
        $headerParam += isset($v['headerParam']) ? $v['headerParam'] : [];
        $colVal['header'] = ['val'=>Html::b($v['title']),'param'=>$headerParam];

        $tableData[$i][$v['field']] = $colVal;
        // $tableData[$i][$v['field']] = [
        //   'val'=>isset($val[$v['field']]) ? $val[$v['field']] : '', 
        //   //'param' => ['align'=>'center'],
        //   'header'=>[
        //     'val'=>Html::b($v['title']), 
        //     'param'=>isset($v['hWidth']) ? ['width'=>$v['hWidth']] : []
        //   ]
        // ];
      }
    }
    return $tableData;
  }
//------------------------------------------------------------------------------
  public static function getCsv($r, $param){
    $fileInfo = self::_getFileAndHref('csv');
    $filePath = isset($param['filePath']) ? $param['filePath'] : $fileInfo['filePath'];
    $href     = isset($param['href']) ? $param['href'] : $fileInfo['href'];
    $column   = $param['column'];
    $msg      = isset($param['popupMsg']) ? $param['popupMsg'] : 'Your export file is ready. Please click the link here to download it';
    $row      = [];
    foreach($r as $i=>$val){
      foreach($column as $j=>$v){
        $row[$i][$v['title']] = isset($val[$v['field']]) ? preg_replace('/<[^>]*>|&nbsp;/', '', $val[$v['field']]) : '';
      }
    }
    $exporter = SimpleCsv::export(collect($row));
    $exporter->save(storage_path('app/' . $filePath));
    return [
      'file'=>(php_sapi_name() == "cli") ? $fileInfo['filePath'] : '',
      'popupMsg'=>Html::a($msg, [
        'href'=>$href, 
        'target'=>'_blank',
        'class'=>'downloadLink'
      ])
    ];
  }
//------------------------------------------------------------------------------
  public static function getFilter($vData){
    unset($vData['sort'], $vData['order'], $vData['defaultSort']);
    return $vData;
  }
//------------------------------------------------------------------------------
  public static function getSelectedField($column, $isArrayOutput = 0){
    $data = '';
    foreach($column['columns'] as $v){
      $data .= $v['field'] . ',';
    }
    return $isArrayOutput ? explode(',', preg_replace('/,$/', '', $data)) : preg_replace('/,$/', '', $data);
  }
//------------------------------------------------------------------------------
  public static function getFileName($req, $op){
    return preg_replace('/\s+/', '_', Helper::getUsidName($req)) . date('_YmdHis.') . $op;
  }
//------------------------------------------------------------------------------
  public static function getRow($rows, $lastRow){
    if(isset($rows[0])){
      $tmpRow = [];
      foreach($rows[0] as $k=>$v){
        $tmpRow[$k] = isset($lastRow[$k]) ? $lastRow[$k] : '';
      }
      $rows[] = $tmpRow;
      return $rows;
    }
    return [];
  }
//------------------------------------------------------------------------------
  private static function _getFileAndHref($extension){
    $file     = \Request::ip() . date('_YmdHis') . '.' . $extension;
    $filePath = 'public/tmp/' . $file;
    //$filePath = storage_path('app/public/tmp/' . $file);
    $href     = Storage::disk('public')->url('tmp/'. $file);
    return ['filePath'=>$filePath, 'href'=>$href];
  }
//------------------------------------------------------------------------------
  private function _mapListToSelect2($list, $perm) {
    $selectFormat = [];
    $num = 0;
    foreach($list as $title => $children) {
      $selectFormat[$num] = [
        'text'     => $title,
        'children' => []
      ];
      foreach($children as $field => $val) {
        if(isset($perm[$field])) {
          $selectFormat[$num]['children'][] = [
            'id'   => $field,
            'text' => $val
          ];
        }
      }
      if(empty($selectFormat[$num]['children'])) {
        unset($selectFormat[$num]);
      }
      $num++;
    }
    return array_values($selectFormat);
  }
}
