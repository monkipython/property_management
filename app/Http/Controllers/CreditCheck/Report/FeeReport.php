<?php
namespace App\Http\Controllers\CreditCheck\Report;
use App\Library\{Mail, File, Html, Format, TableName AS T, Helper};
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Models\Model; // Include the models class
use PDF;

class FeeReport{
  public static function getReport($r, $req){
    $r        = !empty($r['hits']) ? $r['hits'] : [];
    $maxAllow = 3000;
    $rTotal   = $r['total'];
    $r        = !empty($rTotal) ? $r['hits'] : Helper::echoJsonError(Html::errMsg('No Result. Please check your table again.'));
    $rTotal   = $rTotal <= $maxAllow ? $rTotal : Helper::echoJsonError(Html::errMsg('The report you requested is too big. Please make it smaller.'));
    $rRoleTmp = M::getAccountOwnGroup('*');
    
    $tableData= $rRole = $data = [];
    foreach($rRoleTmp as $i=>$v){
      $ownGroup = explode(',', preg_replace('/\s+/','',$v['ownGroup']));
      foreach($ownGroup as $group){
        $rRole[strtoupper(trim($group))] = $v;
      }
    }
    
    foreach($r as $i=>$val){
      $val = $val['_source'];
      if(!isset($rRole[strtoupper($val['group1'])])){
        Helper::echoJsonError(Html::errMsg('Group: ' . $val['group1'] . ' has not assigned to any supervisor yet. Please go to account and assign this group to one of the supervisor.'));
      }
      $supervisor = $rRole[strtoupper($val['group1'])];
      $supervisor = title_case($supervisor['firstname'] . ' ' . $supervisor['lastname']);
      $data[$supervisor][] = $val;
    }
    
    $txtRight = ['style'=>'text-align:right;'];
    $_hParam = function($width){
      return ['style'=>'font-weight:bold;font-size:8px;text-align:center;', 'width'=>$width];
    };
    
    $grandTotal = 0;
    $num = 0;
    foreach($data as $supervisor=>$value){
      $sumAmount = 0;
      $backgroundColor = 'background-color: #efefef;';
      $bgColor = ($num % 2 == 0) ? ['style'=>$backgroundColor] : ['style'=>$backgroundColor];
      
      $value =  array_reverse(array_sort($value, function ($value) {
        return $value['cdate'];
      }));
      
      
      foreach($value as $val){
        foreach($val['application'] as $v){
          $tableData[] = [
            'date'      =>['val'=>$val['cdate'], 'header'=>['val'=>'Date', 'param'=>$_hParam(50)], 'param'=>$bgColor],
            'prop'      =>['val'=>$val['prop'], 'header'=>['val'=>'Prop#', 'param'=>$_hParam(25)], 'param'=>$bgColor],
            'unit'      =>['val'=>$val['unit'], 'header'=>['val'=>'Unit', 'param'=>$_hParam(25)], 'param'=>$bgColor],
            'address'   =>['val'=>$val['street'], 'header'=>['val'=>'Address', 'param'=>$_hParam(90)], 'param'=>$bgColor],
            'city'      =>['val'=>$val['city'], 'header'=>['val'=>'City', 'param'=>$_hParam(90)], 'param'=>$bgColor],
            'group'     =>['val'=>$val['group1'], 'header'=>['val'=>'Group', 'param'=>$_hParam(30)], 'param'=>$bgColor],
            'supervisor'=>['val'=>$supervisor, 'header'=>['val'=>'Supervisor', 'param'=>$_hParam(75)], 'param'=>$bgColor],
            'orderedby' =>['val'=>title_case($val['ordered_by']), 'header'=>['val'=>'Ordered By', 'param'=>$_hParam(40)], 'param'=>$bgColor],
            'tenant'    =>['val'=>title_case($v['fname'] . ' ' . $v['lname']), 'header'=>['val'=>'Tenant', 'param'=>$_hParam(85)], 'param'=>$bgColor],
            'status'      =>['val'=>$val['status'], 'header'=>['val'=>'Status', 'param'=>$_hParam(35)], 'param'=>$bgColor],
            'paid'      =>['val'=>Format::usMoney($v['app_fee']), 'header'=>['val'=>'Paid', 'param'=>$_hParam(30)], 'param'=>$bgColor],
          ];
          $sumAmount  += 35;
          $grandTotal += 35;
        }
      }
      $tableData[] = ['supervisor'=>[
        'val'=>Html::h3(Html::tag('u', $supervisor . ' Total: ' . Format::usMoney($sumAmount))) . '<hr>',
        'header'=>['val'=>'', 'param'=>[]], 
        'param'=>['colspan'=>11, 'style'=>'text-align:center;' . $backgroundColor]]
      ];
    }
    $tableData[] = ['grandTotal'=>[
      'val'=>Html::h1(Html::tag('u', 'Grand Total: ' . Format::usMoney($grandTotal))),
      'header'=>['val'=>'', 'param'=>[]], 
      'param'=>['colspan'=>11, 'style'=>'text-align:center;']]
    ];
    $msg = 'Your report is ready. Please click the link here to download it';
    return ['msg'=>Html::a($msg, [
      'href'=>'/download/' . self::_getPdf($tableData, $req) . '?type=' . last(explode('\\', __CLASS__)), 
      'target'=>'_blank',
      'class'=>'downloadLink'
    ])];
  }
//------------------------------------------------------------------------------
  private static function _getPdf($tableData, $req){
    try{
      $path = File::mkdirNotExist(File::getLocation('report')['FeeReport']);
      $file = $req['ACCOUNT']['firstname'] . date('-Y-m-d-H-i-s') . '.pdf';
      $font = 'times';
      $size = 8;
    
      PDF::SetTitle('Daily Report');
      PDF::setPageOrientation('P');

      # HEADER SETTING
      PDF::SetHeaderData('', '0', 'Pama Management Co.', ' Report Fee Pending:: Run on ' . date('F j, Y, g:i a'));
      PDF::setHeaderFont([$font, '', $size]);
      PDF::SetHeaderMargin(3);

      # FOOTER SETTING
      PDF::SetFont($font, '', $size);
      PDF::setFooterFont([$font, '', $size]);
      PDF::SetFooterMargin(5);

      PDF::SetMargins(5, 13, 5);
      PDF::SetAutoPageBreak(TRUE, 10);
      PDF::AddPage();
      PDF::writeHTML(Html::buildTable(['data'=>$tableData,'isAlterColor'=>0,'isOrderList'=>0]), true, false, true, false, 'P');

      PDF::Output($path . $file, 'F');
      return $file;
    } catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
}