<?php
namespace App\Http\Controllers\AccountReceivable\GlChart\Report;
use App\Library\{GridData, File, Html, Elastic, TableName AS T, Helper};
use PDF;

class GlChartReport{
  
  public static function getReport($vData, $req=[]){
    $qData    = GridData::getQuery($vData, T::$glChartView);
    $r        = Elastic::gridSearch(T::$glChartView.'/'.T::$glChartView.'/_search?' . $qData['query']);
    $r        = !empty($r['hits']) ? $r['hits'] : [];
    $maxAllow = 3000;
    $rTotal   = $r['total'];
    $rTotal   = $rTotal <= $maxAllow ? $rTotal : Helper::echoJsonError(Html::errMsg('The report you requested is too big. Please make it smaller.'));
    $r        = !empty($rTotal) ? $r['hits'] : Helper::echoJsonError(Html::errMsg('No Result. Please check your table again.'));
    
    $glChart = array_column($r, '_source');
    usort($glChart, function($a, $b){
      return strcasecmp($a['gl_acct'],$b['gl_acct']);
    });
    $groupedGlChart = Helper::groupBy($glChart, 'prop');
    $tableData = [];
    $_hParam = function($width){
      return ['style'=>'font-weight:bold;font-size:12px;text-align:left;', 'width'=>$width];
    };
    $mappingGlChart = Helper::getMapping(['tableName'=>T::$glChart]);
    $prevAcctType = '';
    foreach($groupedGlChart as $key => $val) {
      $tableData[] = [
        'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
        'gl_acct' => ['val'=>'', 'header'=>['val'=>'GL Accoount', 'param'=>$_hParam(100)]],
        'title'   => ['val'=>'', 'header'=>['val'=>'Title', 'param'=>$_hParam(400)]],
      ];
      $tableData[] = [
        'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
        'gl_acct' => ['val'=>'Prop-', 'header'=>['val'=>'GL Accoount', 'param'=>$_hParam(100)], 'param'=>['style'=>'font-weight:bold;font-size:12px;text-align:right;']],
        'title'   => ['val'=>$key, 'header'=>['val'=>'Title', 'param'=>$_hParam(400)], 'param'=>['style'=>'font-weight:bold;font-size:12px;']],
      ];
      foreach($val as $v){
        if($v['acct_type'] != $prevAcctType) {      
          $prevAcctType = $v['acct_type'];
          $tableData[] = [
            'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
            'gl_acct' => ['val'=>'', 'header'=>['val'=>'GL Accoount', 'param'=>$_hParam(100)]],
            'title'   => ['val'=>'', 'header'=>['val'=>'Title', 'param'=>$_hParam(400)]],
          ];
          $tableData[] = [
            'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
            'gl_acct' => ['val'=>'', 'header'=>['val'=>'GL Accoount', 'param'=>$_hParam(100)]],
            'title'   => ['val'=>isset($mappingGlChart['acct_type'][$v['acct_type']]) ? $mappingGlChart['acct_type'][$v['acct_type']] : $v['acct_type'], 'header'=>['val'=>'Title', 'param'=>$_hParam(400)], 'param'=>['style'=>'font-weight:bold;font-size:12px;']],
          ];
        }
        $tableData[] = [
          'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
          'gl_acct' => ['val'=>$v['gl_acct'], 'header'=>['val'=>'GL Accoount', 'param'=>$_hParam(100)], 'param'=>['style'=>'font-size:12px;']],
          'title'   => ['val'=>$v['title'], 'header'=>['val'=>'Title', 'param'=>$_hParam(400)], 'param'=>['style'=>'font-size:12px;']],
        ];
      }
    }
    
    $msg = 'Your report is ready. Please click the link here to download it';
    $file = self::_getPdf(array_chunk($tableData,50), $req);
    return [
      'msg'=>Html::a($msg, [
        'href'=>'/download/' . $file . '?type=' . last(explode('\\', __CLASS__)), 
        'target'=>'_blank',
        'class'=>'downloadLink'
      ]),
      'file'=>$file
    ];
  }
//------------------------------------------------------------------------------
  private static function _getPdf($tableData, $req){
    try{
      $path = File::mkdirNotExist(File::getLocation('report')['GlChartReport']);
      $file = (isset($req['ACCOUNT']['firstname']) ? $req['ACCOUNT']['firstname'] . '-': '') . date('Y-m-d-H-i-s') . '.pdf';
      $font = 'times';
      $size = 7;

      PDF::SetTitle('GL Chart Report');
      PDF::setPageOrientation('P');

      # HEADER SETTING
      PDF::SetHeaderData('', '0', 'Pama Management Co.', 'GL Account Report:: Run on ' . date('F j, Y, g:i a'));
      PDF::setHeaderFont([$font, '', $size]);
      PDF::SetHeaderMargin(3);

      # FOOTER SETTING
      PDF::SetFont($font, '', $size);
      PDF::setFooterFont([$font, '', $size]);
      PDF::SetFooterMargin(5);

      PDF::SetMargins(5, 13, 5);
      PDF::SetAutoPageBreak(TRUE, 10);
      foreach($tableData as $v){
        PDF::AddPage();
        PDF::writeHTML(Html::buildTable(['data'=>$v,'isAlterColor'=>1,'isOrderList'=>0]), true, false, true, false, 'P');
      }

      PDF::Output($path . $file, 'F');
      return $file;
    } catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
}