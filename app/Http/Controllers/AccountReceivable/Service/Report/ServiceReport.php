<?php
namespace App\Http\Controllers\AccountReceivable\Service\Report;
use App\Library\{File, Html, GridData,  Elastic, TableName AS T, Helper};
use PDF;

class ServiceReport{
  public static function getReport($vData, $req=[]){
    $qData    = GridData::getQuery($vData, T::$serviceView);
    $r        = Elastic::gridSearch(T::$serviceView.'/'.T::$serviceView.'/_search?' . $qData['query']);
    $r        = !empty($r['hits']) ? $r['hits'] : [];
    $maxAllow = 3000;
    $rTotal   = $r['total'];
    $rTotal   = $rTotal <= $maxAllow ? $rTotal : Helper::echoJsonError(Html::errMsg('The report you requested is too big. Please make it smaller.'));
    $r        = !empty($rTotal) ? $r['hits'] : Helper::echoJsonError(Html::errMsg('No Result. Please check your table again.'));
    
    $service = array_column($r, '_source');
    usort($service, function($a, $b){
      return strcasecmp($a['service'],$b['service']);
    });
    $groupedService = Helper::groupBy($service, 'prop');
    $tableData = [];
    $_hParam = function($width){
      return ['style'=>'font-weight:bold;font-size:12px;text-align:left;', 'width'=>$width];
    };
    foreach($groupedService as $key => $val) {
      $tableData[] = [
        'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
        'service' => ['val'=>'', 'header'=>['val'=>'Service', 'param'=>$_hParam(100)]],
        'title'   => ['val'=>'', 'header'=>['val'=>'Title', 'param'=>$_hParam(400)]],
      ];
      $tableData[] = [
        'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
        'service' => ['val'=>'Prop-', 'header'=>['val'=>'Service', 'param'=>$_hParam(100)], 'param'=>['style'=>'font-weight:bold;font-size:12px;text-align:right;']],
        'title'   => ['val'=>$key, 'header'=>['val'=>'Title', 'param'=>$_hParam(400)], 'param'=>['style'=>'font-weight:bold;font-size:12px;']],
      ];
      $tableData[] = [
        'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
        'service' => ['val'=>'', 'header'=>['val'=>'Service', 'param'=>$_hParam(100)]],
        'title'   => ['val'=>'', 'header'=>['val'=>'Title', 'param'=>$_hParam(400)]],
      ];
      foreach($val as $v){
        $tableData[] = [
          'empty'   => ['val'=>'', 'header'=>['val'=>'', 'param'=>$_hParam(150)]],
          'service' => ['val'=>$v['service'], 'header'=>['val'=>'Service', 'param'=>$_hParam(100)], 'param'=>['style'=>'font-size:12px;']],
          'title'   => ['val'=>$v['remark'], 'header'=>['val'=>'Title', 'param'=>$_hParam(400)], 'param'=>['style'=>'font-size:12px;']],
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
      $path = File::mkdirNotExist(File::getLocation('report')['ServiceReport']);
      $file = (isset($req['ACCOUNT']['firstname']) ? $req['ACCOUNT']['firstname'] . '-': '') . date('Y-m-d-H-i-s') . '.pdf';
      $font = 'times';
      $size = 7;

      PDF::SetTitle('Service Report');
      PDF::setPageOrientation('P');

      # HEADER SETTING
      PDF::SetHeaderData('', '0', 'Pama Management Co.', 'Service Report:: Run on ' . date('F j, Y, g:i a'));
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