<?php
namespace App\Http\Controllers\PropertyManagement\TenantEvictionProcess\Report;
use App\Library\{Form, Html, Format, TableName AS T, Helper, TenantTrans};
use PDF;
use Storage;

class EvictionReport{
  
  public static function create() {
    $page = 'app/PropertyManagement/TenantEvictionProcess/report/create';
    $mappingEvictionEvent   = Helper::getMapping(['tableName'=>T::$tntEvictionEvent]);
    $mappingEvictionProcess = Helper::getMapping(['tableName'=>T::$tntEvictionProcess]);
    unset($mappingEvictionProcess['process_status']['']);
    $mappingEvictionEvent['status'][''] = 'All';
    foreach($mappingEvictionProcess['process_status'] as $i=>$v) {
      $processStatusOption[$v] = $v;
    }
    $fields = [
      'type'           => ['id'=>'type','label'=>'Group By','type'=>'option', 'option'=>['group1'=>'Group','city'=>'City'], 'req'=>1],
      'process_status' => ['id'=>'process_status','label'=>'Process Status', 'type'=>'option', 'option'=>$processStatusOption, 'req'=>1],
      'dateRange'      => ['id'=>'dateRange','label'=>'Date Range','type'=>'text','class'=>'daterange', 'req'=>1],
      'status'         => ['id'=>'status','label'=>'Status', 'type'=>'option', 'option'=>$mappingEvictionEvent['status']],
    ];
    $formFields = implode('',Form::generateField($fields));
    $formFields .= Html::button('Generate', ['type'=>'submit', 'class'=>'col-sm-12 btn btn-info']);
    return view($page, [
      'data'=>[
        'formEviction' => $formFields
      ]
    ]);
  }
  public static function getReport($r, $req=[], $vData=[]){
    $r           = !empty($r['hits']) ? $r : [];
    $maxAllow    = 3000;
    $rTotal      = $r['hits']['total'];
    $rTotal      = $rTotal <= $maxAllow ? $rTotal : Helper::echoJsonError(Html::errMsg('The report you requested is too big. Please make it smaller.'));

    $r = !empty($rTotal) ? Helper::getElasticGroupBy($r['hits']['hits'], $vData['type']) : [];
    $txtBoldUnder = ['style'=>'text-align:right;text-decoration:underline;font-weight:bold;'];
    $txtRightBold = ['style'=>'text-align:right;font-weight:bold;'];
    $_hParam = function($width){
      return ['style'=>'font-weight:bold;font-size:8px;text-decoration:underline;', 'width'=>$width];
    };
    $_dateCompare = function ($a, $b){
      $t1 = strtotime($a['date']);
      $t2 = strtotime($b['date']);
      $compare = $t2 - $t1; 
      if($compare == 0) {
        return $b['tnt_eviction_event_id'] - $a['tnt_eviction_event_id'];
      }else {
        return $compare;
      }
    };  
    ## If no result display NO RESULT
    $tableData  = empty($r) ? [['result'=>['val' => 'NO RESULT','header' => ['val' => '',],'param' => ['style' => 'font-size:12px;font-weight:bold;text-align:center;']]]] : [];
    $totalUnitCount = $totalUnitRentSum = $totalUnitDepositSum = $totalVacantCount = $totalVacantRentSum = $totalVacantDepositSum = $firstCheck = 0;
    foreach($r as $type => $rows) {
      $unitCount = $unitRentSum = $unitDepositSum = $vacantCount = $vacantRentSum = $vacantDepositSum =0;
      $headerStyle = 'font-weight:bold;';
      $headerStyle .= ($firstCheck != 0) ? 'border-top:1px solid black;' : '';
      $firstCheck++;
      $tableData[] = [
        'prop' => ['val'=>$type,'header'=>['val'=>''], 'param'=>['style'=>$headerStyle, 'colspan'=>11]],
      ];
      $tableData[] = [
        'prop'           =>['val'=>'Prop-Unit-Tnt','param'=>$_hParam(55)],
        'tnt_name'       =>['val'=>'Tnt Name','param'=>$_hParam(100)],
        'unitTypeBedBath'=>['val'=>'Type/Bd/Bth','param'=>$_hParam(50)],
        'last_raise_date'=>['val'=>'Last Raise','param'=>$_hParam(40)],
        'base_rent'      =>['val'=>'Rent','param'=>$_hParam(45)],
        'rent_bal'       =>['val'=>'Rent Bal','param'=>$_hParam(50)],
        'dep_held1'      =>['val'=>'Deposit','param'=>$_hParam(45)],
        'attorney'       =>['val'=>'Attorney','param'=>$_hParam(60)],
        'address'        =>['val'=>'Address','param'=>$_hParam(150)],
        'move_in_date'   =>['val'=>'Move In','param'=>$_hParam(40)],
        'event'          =>['val'=>'Event','param'=>$_hParam(250)],
      ];
      foreach($rows as $i=>$val){
        $tntBalanceData = [
          'prop'   => $val['prop'],
          'unit'   => $val['unit'],
          'tenant' => $val['tenant']
        ];
        if(!empty($val['tnt_eviction_event'])) {
          usort($val['tnt_eviction_event'], $_dateCompare);
          $eventRow =[];
          foreach($val['tnt_eviction_event'] as $j => $v) {
            $eventRow[] = [
              'date'            => ['val'=>date('m/d/y', strtotime($v['date'])), 'header'=>['val'=>'Date', 'param'=>['style'=>'text-decoration:underline;font-size:8px;', 'width'=>40]]],
              'remark'          => ['val'=>$v['remark'], 'header'=>['val'=>'Remark', 'param'=>['style'=>'text-decoration:underline;font-size:8px;', 'width'=>110]]],
              'tenant_attorney' => ['val'=>$v['tenant_attorney'], 'header'=>['val'=>'Tnt Attorney', 'param'=>['style'=>'text-decoration:underline;font-size:8px;', 'width'=>55]]]
            ];
          }
          $eventTable = Html::buildTable(['data'=>$eventRow, 'isOrderList'=>0]);
        }
        $balance = TenantTrans::getApplyToSumResult($tntBalanceData);
        $tableData[] = [
          'prop'           =>['val'=>$val['prop']. '-' . $val['unit'] . '-' . $val['tenant']],
          'tnt_name'       =>['val'=>title_case($val['tnt_name'])],
          'unitTypeBedBath'=>['val'=>$val['unit_type'] . ' <b>/</b> '. $val['bedrooms'] .' <b>/</b> '. $val['bathrooms']],
          'last_raise_date'=>['val'=>!empty($val['last_raise_date']) ? date('m/y', strtotime($val['last_raise_date'])) : 'N/A'],
          'base_rent'      =>['val'=>Format::usMoney($val['base_rent'])],
          'rent_bal'       =>['val'=>Format::usMoney($balance['balance'])],
          'dep_held1'      =>['val'=>Format::usMoney($val['dep_held1'])],
          'attorney'       =>['val'=>$val['attorney']],
          'address'        =>['val'=>$val['street']],
          'move_in_date'   =>['val'=>date('m/d/y', strtotime($val['move_in_date']))],
          'event'          =>['val'=>$eventTable]
        ];
        
        if($val['status'] == 'C') {
          $unitCount++;
          $unitRentSum    += !empty($val['base_rent']) ? $val['base_rent'] : 0;
          $unitDepositSum += !empty($val['dep_held1']) ? $val['dep_held1'] : 0;
          $totalUnitCount++; 
          $totalUnitRentSum    += !empty($val['base_rent']) ? $val['base_rent'] : 0; 
          $totalUnitDepositSum += !empty($val['dep_held1']) ? $val['dep_held1'] : 0;
        }else {
          $vacantCount++;
          $vacantRentSum    += !empty($val['base_rent']) ? $val['base_rent'] : 0;
          $vacantDepositSum += !empty($val['dep_held1']) ? $val['dep_held1'] : 0;
          $totalVacantCount++;
          $totalVacantRentSum    += !empty($val['base_rent']) ? $val['base_rent'] : 0;
          $totalVacantDepositSum += !empty($val['dep_held1']) ? $val['dep_held1'] : 0;
        }
      }
      $tableData[] = [
        'prop'            =>['val'=>'', 'param'=>['colspan'=>2]],
        'last_raise_date' =>['val'=>'Units: ','param'=>$txtRightBold],
        'process_status'  =>['val'=>$unitCount,'param'=>['style'=>'text-align:center;font-weight:bold;']],
        'base_rent'       =>['val'=>Format::usMoney($unitRentSum), 'param'=>$txtRightBold],
        'rent_bal'        =>['val'=>''],
        'dep_held1'       =>['val'=>Format::usMoney($unitDepositSum), 'param'=>$txtRightBold],
        'attorney'        =>['val'=>'', 'param'=>['colspan'=>4]],
      ];
      $tableData[] = [
        'prop'            =>['val'=>'', 'param'=>['colspan'=>2]],
        'last_raise_date' =>['val'=>'Vacant: ','param'=>$txtRightBold],
        'process_status'  =>['val'=>$vacantCount,'param'=>['style'=>'text-align:center;font-weight:bold;']],
        'base_rent'       =>['val'=>Format::usMoney($vacantRentSum), 'param'=>$txtRightBold],
        'rent_bal'        =>['val'=>''],
        'dep_held1'       =>['val'=>Format::usMoney($vacantDepositSum), 'param'=>$txtRightBold],
        'attorney'        =>['val'=>'', 'param'=>['colspan'=>4]],
      ];
      $tableData[] = [
        '' => ['val'=>' ', 'param'=>['colspan'=>11]]
      ];
    }
    if(!empty($r)) {
      $tableData[] = [
        '' => ['val'=>' ', 'param'=>['colspan'=>11, 'style'=>'border-top:1px solid black;']]
      ];
      $tableData[] = [
        'prop'            =>['val'=>''],
        'last_raise_date' =>['val'=>'Total Units: ','param'=>$txtBoldUnder + ['colspan'=>2]],
        'process_status'  =>['val'=>$totalUnitCount,'param'=>['style'=>'text-align:center;text-decoration:underline;font-weight:bold;']],
        'base_rent'       =>['val'=>Format::usMoney($totalUnitRentSum), 'param'=>$txtBoldUnder],
        'rent_bal'        =>['val'=>''],
        'dep_held1'       =>['val'=>Format::usMoney($totalUnitDepositSum), 'param'=>$txtBoldUnder],
        'attorney'        =>['val'=>'', 'param'=>['colspan'=>4]],
      ];
      $tableData[] = [
        'prop'            =>['val'=>''],
        'last_raise_date' =>['val'=>'Total Vacant: ','param'=>$txtBoldUnder + ['colspan'=>2]],
        'process_status'  =>['val'=>$totalVacantCount,'param'=>['style'=>'text-align:center;text-decoration:underline;font-weight:bold;']],
        'base_rent'       =>['val'=>Format::usMoney($totalVacantRentSum), 'param'=>$txtBoldUnder],
        'rent_bal'        =>['val'=>''],
        'dep_held1'       =>['val'=>Format::usMoney($totalVacantDepositSum), 'param'=>$txtBoldUnder],
        'attorney'        =>['val'=>'', 'param'=>['colspan'=>4]],
      ];
    }
    return self::_getPdf([$tableData], $vData);
  }
  //------------------------------------------------------------------------------
  private static function _getPdf($contentData, $param){
    $evictionType= ['city'=>'City', 'group1'=>'Group'];
    $fileInfo    = self::_getFileAndHref('pdf');
    $file        = $fileInfo['filePath'];
    $href        = $fileInfo['href'];
    $title       = 'Eviction Report by ' . $evictionType[$param['type']];
    $orientation = 'L';
    
    $font = isset($param['font']) ? $param['font'] : 'times';
    $size = isset($param['size']) ? $param['size'] : '9';
    $msg  = isset($param['downloadMsg']) ? $param['downloadMsg'] : 'Your report is ready. Please click the link here to download it';
    
    try{
      PDF::reset();
      PDF::SetTitle($title);
      PDF::setPageOrientation($orientation);

      # HEADER SETTING
      PDF::SetHeaderData('', '0', Html::repeatChar(' ', 100) . $title, 'Run on ' . date('F j, Y, g:i a'));
      PDF::setHeaderFont([$font, '', ($size + 3)]);
      PDF::SetHeaderMargin(3);

      # FOOTER SETTING
      PDF::setPrintFooter(false);
      PDF::SetFont($font, '', $size);
      PDF::setFooterFont([$font, '', $size]);
      PDF::SetFooterMargin(5);

      PDF::SetMargins(0, 13, 0);
      PDF::SetAutoPageBreak(TRUE, 10);
      $contentData = isset($contentData[0]) ? $contentData : [$contentData];
      
      foreach($contentData as $content){
        PDF::AddPage();
        PDF::writeHTML(Html::buildTable(['data'=>$content, 'isOrderList'=>0,'tableParam'=>['cellpadding'=>2]]),true,false,true,false,$orientation);
      }
      
      PDF::Output($file, 'F');
      return [
        'file'=>(php_sapi_name() == "cli") ? $fileInfo['filePath'] : '',
        'downloadMsg'=>Html::a($msg, [
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
  private static function _getFileAndHref($extension){
    $file     = 'eviction_report' . \Request::ip() . date('_YmdHis') . '.' . $extension;
    $filePath = storage_path('app/public/tmp/' . $file);
    $href     = Storage::disk('public')->url('tmp/'. $file);
    return ['filePath'=>$filePath, 'href'=>$href];
  }
}