<?php
namespace App\Http\Controllers\CreditCheck\Report;
use App\Library\{Mail, File, Html, Format, TableName AS T, Helper, HelperMysql};
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use PDF;

class DailyReport{
  public static function getReport($r, $req=[]){
    $propList = array_values(Helper::keyFieldNameElastic($r, ['prop'], 'prop'));
    $rUnit    = Helper::keyFieldName(M::getUnitByProp($propList, ['prop', 'unit', 'bedrooms']), ['prop','unit'], 'bedrooms');
    $r        = !empty($r['hits']) ? $r['hits'] : [];
    $maxAllow = 3000;
    $rTotal   = $r['total'];
    $rTotal   = $rTotal <= $maxAllow ? $rTotal : Helper::echoJsonError(Html::errMsg('The report you requested is too big. Please make it smaller.'));
    $r        = !empty($rTotal) ? $r['hits'] : Helper::echoJsonError(Html::errMsg('No Result. Please check your table again.'));
    
    $tableData = [];
    $txtRight = ['style'=>'text-align:right;'];
    $_hParam = function($width){
      return ['style'=>'font-weight:bold;font-size:8px;text-align:center;', 'width'=>$width];
    };
    $lastRow = ['newRent'=>0,'oldRent'=>0,'diff'=>0];
    
    foreach($r as $i=>$val){
      $val  = $val['_source'];
      $rTnt = HelperMysql::getTenant(Helper::getPropUnitMustQuery($val, ['status.keyword'=>'P'],0),['base_rent'],['sort'=>['move_out_date'=>'DESC']]);
      $oldRent = !empty($rTnt) ? $rTnt['base_rent'] : HelperMysql::getUnit(['prop.prop.keyword'=>$val['prop'],'unit.keyword'=>$val['unit']],['rent_rate'])['rent_rate'];
      
      foreach($val['application'] as $v){
        $lastRow['newRent'] += $val['new_rent'];
        $lastRow['oldRent'] += $oldRent;
        $lastRow['diff']    += $val['new_rent'] - $oldRent;
        $tableData[] = [
          'date'      =>['val'=>$val['cdate'] . Html::br(), 'header'=>['val'=>'Date', 'param'=>$_hParam(35)]],
          'new_rent'  =>['val'=>Format::usMoney($val['new_rent']), 'header'=>['val'=>'New Rent', 'param'=>$_hParam(40)], 'param'=>$txtRight],
          'old_rent'  =>['val'=>Format::usMoney($oldRent), 'header'=>['val'=>'Old Rent', 'param'=>$_hParam(40)], 'param'=>$txtRight],
          'diff'      =>['val'=>Format::usMoney($val['new_rent'] - $oldRent), 'header'=>['val'=>'Diff', 'param'=>$_hParam(40)], 'param'=>$txtRight],
          'deposit'   =>['val'=>Format::usMoney($val['sec_deposit']), 'header'=>['val'=>'Deposit', 'param'=>$_hParam(35)], 'param'=>$txtRight],
          'adddep'    =>['val'=>Format::usMoney($val['sec_deposit_add']), 'header'=>['val'=>'Add. Dep.', 'param'=>$_hParam(40)],'param'=>$txtRight],
          'beds'      =>['val'=>$rUnit[$val['prop'].$val['unit']], 'header'=>['val'=>'Beds', 'param'=>$_hParam(20)],'param'=>['style'=>'text-align:center;']],
          'prop'      =>['val'=>$val['prop'], 'header'=>['val'=>'Prop#', 'param'=>$_hParam(24)]],
          'unit'      =>['val'=>$val['unit'], 'header'=>['val'=>'Unit', 'param'=>$_hParam(20)]],
          'address'   =>['val'=>title_case($val['street']), 'header'=>['val'=>'Address', 'param'=>$_hParam(60)]],
          'city'      =>['val'=>title_case($val['city']), 'header'=>['val'=>'City', 'param'=>$_hParam(60)]],
          'zip'       =>['val'=>$val['zip'], 'header'=>['val'=>'Zip', 'param'=>$_hParam(22)]],
          'group'     =>['val'=>$val['group1'], 'header'=>['val'=>'Group', 'param'=>$_hParam(25)]],
          'orderedby' =>['val'=>title_case($val['ordered_by']), 'header'=>['val'=>'Ordered By', 'param'=>$_hParam(50)]],
          'ranby'     =>['val'=>title_case($val['ran_by']), 'header'=>['val'=>'Ran By', 'param'=>$_hParam(50)]],
          'ssn'       =>['val'=>'xxx-xx-'.$v['social_security'], 'header'=>['val'=>'SSN', 'param'=>$_hParam(40)]],
          'tenant'    =>['val'=>title_case($v['fname'] . ' ' . $v['lname']), 'header'=>['val'=>'Tenant', 'param'=>$_hParam(50)]],
          'status'    =>['val'=>$val['status'], 'header'=>['val'=>'Status', 'param'=>$_hParam(38)]],
          'paid'      =>['val'=>($v['app_fee_recieved'] ? 'Yes' : 'No'), 'header'=>['val'=>'Paid', 'param'=>$_hParam(20)]],
          'note'      =>['val'=>title_case($val['sec_deposit_note']), 'header'=>['val'=>'Note', 'param'=>$_hParam(102)]],
        ];
      }
    }
    $tableData[] = [
      'date'      =>['val'=>'Total:', 'header'=>['val'=>'Date', 'param'=>$_hParam(35)],'param'=>['style'=>'font-weight:bold;text-align:right;']],
      'new_rent'  =>['val'=>Format::usMoney($lastRow['newRent']), 'header'=>['val'=>'New Rent', 'param'=>$_hParam(40)], 'param'=>['style'=>'font-weight:bold;text-align:right;']],
      'old_rent'  =>['val'=>Format::usMoney($lastRow['oldRent']), 'header'=>['val'=>'Old Rent', 'param'=>$_hParam(40)], 'param'=>['style'=>'font-weight:bold;text-align:right;']],
      'diff'      =>['val'=>Format::usMoney($lastRow['diff']), 'header'=>['val'=>'Diff', 'param'=>$_hParam(40)], 'param'=>['style'=>'font-weight:bold;text-align:right;']],
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
      $path = File::mkdirNotExist(File::getLocation('report')['FeeReport']);
      $file = (isset($req['ACCOUNT']['firstname']) ? $req['ACCOUNT']['firstname'] . '-': '') . date('Y-m-d-H-i-s') . '.pdf';
      $font = 'times';
      $size = 7;

      PDF::SetTitle('Daily Report');
      PDF::setPageOrientation('L');

      # HEADER SETTING
      PDF::SetHeaderData('', '0', 'Pama Management Co.', 'Credit Check Daily Report:: Run on ' . date('F j, Y, g:i a'));
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