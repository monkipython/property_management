<?php
namespace App\Http\Controllers\CreditCheck\Report;
use App\Library\{Mail, File, Html, Format, TableName AS T, Helper};
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use PDF;

class MoveinReport{
  public static function getReport($r, $req=[]){
    $propList    = array_values(Helper::keyFieldNameElastic($r, ['prop'], 'prop'));
    $rUnits      = M::getUnitByProp($propList, ['prop', 'unit', 'bedrooms', 'bathrooms']);
    $rUnitBed    = Helper::keyFieldName($rUnits, ['prop','unit'], 'bedrooms');
    $rUnitBath   = Helper::keyFieldName($rUnits, ['prop','unit'], 'bathrooms');
    $r           = !empty($r['hits']) ? $r : [];
    $maxAllow    = 3000;
    $rTotal      = $r['hits']['total'];
    $rTotal      = $rTotal <= $maxAllow ? $rTotal : Helper::echoJsonError(Html::errMsg('The report you requested is too big. Please make it smaller.'));
    $r           = !empty($rTotal) ? Helper::keyFieldNameElastic($r, ['prop', 'unit', 'tenant']) : Helper::echoJsonError(Html::errMsg('No Result. Please check your table again.'));

    $tableData = [];
    $txtRight = ['style'=>'text-align:right;'];
    $_hParam = function($width){
      return ['style'=>'font-weight:bold;font-size:8px;text-align:center;', 'width'=>$width];
    };
    
    $tntCount = 0;
    $oldRentSum = 0;
    $baseRentSum = 0;
    $depositSum = 0;
    foreach($r as $i=>$val){
      $tableData[] = [
        'move_in_date' => ['val'=>$val['move_in_date'], 'header'=>['val'=>'Move in Date', 'param'=>$_hParam(75)],'param'=>['style'=>'text-align:center;']],
        'housing_dt2'  =>['val'=>$val['housing_dt2'] . Html::br(), 'header'=>['val'=>'Enter Date', 'param'=>$_hParam(75)],'param'=>['style'=>'text-align:center;']],
        'beds'         =>['val'=>$rUnitBed[$val['prop'].$val['unit']], 'header'=>['val'=>'Beds', 'param'=>$_hParam(25)],'param'=>['style'=>'text-align:center;']],
        'bath'         =>['val'=>$rUnitBath[$val['prop'].$val['unit']], 'header'=>['val'=>'Bath', 'param'=>$_hParam(25)],'param'=>['style'=>'text-align:center;']],
        'prop'         =>['val'=>$val['prop'], 'header'=>['val'=>'Prop#', 'param'=>$_hParam(40)],'param'=>['style'=>'text-align:center;']],
        'unit'         =>['val'=>$val['unit'], 'header'=>['val'=>'Unit', 'param'=>$_hParam(40)],'param'=>['style'=>'text-align:center;']],
        'tenant'       =>['val'=>$val['tenant'], 'header'=>['val'=>'Tenant', 'param'=>$_hParam(35)],'param'=>['style'=>'text-align:center;']],
        'tnt_name'     =>['val'=>title_case($val['application'][0]['fname'] . ' ' . $val['application'][0]['lname']), 'header'=>['val'=>'Tenant Name', 'param'=>$_hParam(75)], 'param'=>['style'=>'text-align:center;']],
        'old_rent'     =>['val'=>Format::usMoney($val['old_rent']), 'header'=>['val'=>'Old Rent', 'param'=>$_hParam(50)], 'param'=>$txtRight],
        'base_rent'    =>['val'=>Format::usMoney($val['new_rent']), 'header'=>['val'=>'New Rent', 'param'=>$_hParam(50)], 'param'=>$txtRight],
        'deposit'      =>['val'=>Format::usMoney($val['sec_deposit']), 'header'=>['val'=>'Deposit', 'param'=>$_hParam(50)], 'param'=>$txtRight],
        'group'        =>['val'=>$val['group1'], 'header'=>['val'=>'Group', 'param'=>$_hParam(40)],'param'=>['style'=>'text-align:center;']],
        'address'      =>['val'=>title_case($val['street']), 'header'=>['val'=>'Address', 'param'=>$_hParam(100)], 'param'=>['style'=>'text-align:center;']],
        'city'         =>['val'=>title_case($val['city']), 'header'=>['val'=>'City', 'param'=>$_hParam(75)], 'param'=>['style'=>'text-align:center;']],
        'ran_by'       =>['val'=>(strpos($val['ran_by'], '@') !== false) ? strstr($val['ran_by'], '@', true) : $val['ran_by'], 'header'=>['val'=>'Ran By', 'param'=>$_hParam(50)], 'param'=>['style'=>'text-align:center;']],
      ];
      $tntCount++;
      $oldRentSum += !empty($val['old_rent']) ? $val['old_rent'] : 0;
      $baseRentSum += !empty($val['new_rent']) ? $val['new_rent'] : 0;
      $depositSum += !empty($val['sec_deposit']) ? $val['sec_deposit'] : 0;
    }
    $tableData[] = [
      'tnt_name'  =>['val'=>'# Tenant: ' . $tntCount, 'header'=>['val'=>'Tenant Name', 'param'=>$_hParam(75)],'param'=>['style'=>'text-align:right;', 'colspan'=>'8']],
      'old_rent'  =>['val'=>Format::usMoney($oldRentSum), 'header'=>['val'=>'Old Rent', 'param'=>$_hParam(40)], 'param'=>['style'=>'text-align:right;', 'colspan'=>'0']],
      'base_rent' =>['val'=>Format::usMoney($baseRentSum), 'header'=>['val'=>'Base Rent', 'param'=>$_hParam(40)], 'param'=>['style'=>'text-align:right;', 'colspan'=>'0']],
      'deposit'   =>['val'=>Format::usMoney($depositSum), 'header'=>['val'=>'Deposit', 'param'=>$_hParam(35)], 'param'=>['style'=>'text-align:right;', 'colspan'=>'0']],
      ''          =>['val'=>'', 'header'=>['val'=>' ', 'param'=>$_hParam(35)], 'param'=>['colspan'=>'4']],
    ];
    
    $msg = 'Your report is ready. Please click the link here to download it';
    $file = self::_getPdf(array_chunk($tableData,28), $req);
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
      $path = File::mkdirNotExist(File::getLocation('report')['MoveinReport']);
      $file = (isset($req['ACCOUNT']['firstname']) ? $req['ACCOUNT']['firstname'] . '-': '') . date('Y-m-d-H-i-s') . '.pdf';
      $font = 'times';
      $size = 7;

      PDF::SetTitle('Move In Report');
      PDF::setPageOrientation('L');

      # HEADER SETTING
      PDF::SetHeaderData('', '0', 'Pama Management Co.', 'Move In Report:: Run on ' . date('F j, Y, g:i a'));
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