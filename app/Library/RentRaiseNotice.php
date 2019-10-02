<?php
namespace App\Library;
use App\Library\{Elastic, Html, TableName AS T, Helper, PDFMerge, Format,GlName AS G,ServiceName AS S};
use PDF;
use Storage;

class RentRaiseNotice {
  private static $_root         = 'public/tmp/';  
  private static $_landlordName = 'Nevin Iwatsuru';
  
  public static function getPdf($vData,$includePopup=false,$isSubmitted=1,$rentRaiseId=0,$isPastNotice=false){
    $ids        = [];
    $rRentRaise = Helper::keyFieldName($vData,['prop','unit','tenant']);
    $props      = array_column($rRentRaise,'prop');

    $rTenant    = !empty($props) ? Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['tenant_id','prop','unit','tenant','tnt_name','city','state','zip','county','base_rent',T::$billing],
      'query'    => [
        'must'   => [
          'prop.keyword'   => $props,
          'status.keyword' => 'C',
        ]
      ]
    ]),['prop','unit','tenant']) : [];
    $rUnit      = !empty($props) ? Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$unitView,
      '_source'  => ['prop.prop','unit','street'],
      'query'    => [
        'must'   => [
          'prop.prop.keyword' => $props,
        ]
      ]
    ]),['prop.prop','unit']) : [];
    $pdfArr     = [];
    foreach($rRentRaise as $i => $v){
      $ids[]            = $v['rent_raise_id'];
      $tenantSearchKey  = $v['prop'] . $v['unit'] . $v['tenant'];
      $unitSearchKey    = $v['prop'] . $v['unit'];
      $tenant           = !empty($rTenant[$tenantSearchKey]) ? $rTenant[$tenantSearchKey] : [];
      $unit             = !empty($rUnit[$unitSearchKey]) ? $rUnit[$unitSearchKey] : [];
      $pages            = self::_buildNotice([
        'rentRaise'   => $v,
        'tenant'      => $tenant,
        'unit'        => $unit,
      ],$isSubmitted,$rentRaiseId,$isPastNotice);
      $pdfArr[]         = $pages;
    }
    $fileName = 'rent_raise_notice.pdf';
    
    $folderName = self::$_root . 'rent_raise_notice' . DIRECTORY_SEPARATOR . uniqid() . DIRECTORY_SEPARATOR;
    if(Helper::isCli()){
      $folderName = self::$_root . uniqid() . DIRECTORY_SEPARATOR;
      if(Storage::exists($folderName)){
        Storage::deleteDirectory($folderName);
      }
      Storage::makeDirectory($folderName);
    } else {
      if(!Storage::exists($folderName)){
        Storage::makeDirectory($folderName);
      } 
    }

    return !empty($ids) ? self::_getPdf($pdfArr,$folderName . $fileName,$folderName,$includePopup) : self::_getErrorMsg();
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private static function _buildNotice($data,$isSubmitted=1,$rentRaiseId=0,$isPastNotice=false){  
    $rRentRaise    = Helper::getValue('rentRaise',$data,[]);
    $rentRaiseData = !empty($rRentRaise['rent_raise']) ? self::_fetchRentRaiseWithId($rRentRaise['rent_raise'],$rentRaiseId) : [];
    $rTenant       = Helper::getValue('tenant',$data,[]);
    $rUnit         = Helper::getValue('unit',$data,[]);

    $accountNum  = !empty($rRentRaise) ? implode('-',Helper::selectData(['prop','unit','tenant'],$rRentRaise)) : '';
    $street      = Helper::getValue('street',$rUnit);
    $city        = Helper::getValue('city',$rTenant);
    $state       = Helper::getValue('state',$rTenant);
    $zip         = Helper::getValue('zip',$rTenant);
    $tntName     = Helper::getValue('tnt_name',$rRentRaise);
    $suffix      = preg_match('/\([A-Z]+\)/i',$tntName) ? preg_replace('/(.*)\(([a-z]+)\)(.*)/i','($2)',$tntName) : '';
    $namePieces  = explode(',',$tntName);
    $tntName     = Helper::getValue(0,$namePieces);
    $tntName    .= preg_match('/\([A-Z]+\)/i',$tntName) ? '' : $suffix;
    
    
    $tntBaseRent    = Helper::getValue('base_rent',$rTenant,0);
    $replacePattern = Helper::isCli() ? 'storage$2' : '../storage$2';
    $sigImg         = preg_replace('/(.*)storage(.*)/',$replacePattern,storage_path('app/private/signatures/signature1.png'));

    $draftDay       = date('d');
    $notice         = Helper::getValue('notice',$rentRaiseData,'N/A');
    $lastRaise      = Helper::getValue('last_raise_date',$rentRaiseData,0);
    
    $tempRaiseDate  = date('Y-m-d',strtotime($lastRaise . ' -' . $notice . ' days +30 days'));
    
    $raiseAmt       = Helper::getValue('raise',$rentRaiseData,0);
    $tenantBilling  = Helper::getValue(T::$billing,$rTenant,[]);
    $rentBilling    = $isSubmitted || empty($rentRaiseData['billing_id']) ? self::_getRecentRentForSubmit($tenantBilling) : self::_getRecentRentFromBilling($tenantBilling);
    
    $pastOldBilling = $isPastNotice ? self::_getPastRentFromBilling($tenantBilling, $rentRaiseData['billing_id'], $lastRaise) : [];
    $oldRent        = $isPastNotice ? Helper::getValue('amount',$pastOldBilling,$tntBaseRent) : Helper::getValue('amount',$rentBilling,$tntBaseRent);
    
    $effectDate     = $isPastNotice ? $lastRaise : date('m/01/Y',strtotime(date('m/d/Y') . ' +1 month ' . $notice . ' days'));
    $yearlyPct      = Helper::getValue('yearly_pct',$rRentRaise,10);
    $pastPct        = $isPastNotice ? self::_calculatePastYearlyPct($tenantBilling,$raiseAmt,$tempRaiseDate) : 0;
    $percent        = $isPastNotice ? $pastPct : $yearlyPct;
 
    $overTenPct  = $percent > 10.0;
    $pages       = [];
    
    $pamaAddr       = '4900 SANTA ANITA AVE, SUITE 2C' . Html::br() . 'EL MONTE, CA, 91731' . Html::br() . 'Phone: (626) 575-3070' . Html::br() . 'FAX: (626) 575-7817' . Html::br() . 'FAX: (626) 575 3084';
    $content        = Html::buildTable([
      'isHeader'    => 0,
      'isOrderList' => 0,
      'data'        => [
        ['title'=>['val'=>Html::span(Html::b('NOTICE OF CHANGE IN TERMS OF TENANCY',['style'=>'text-align:center;font-size:14px;'])) . Html::br() . Html::repeatChar('&nbsp;',50) . Html::span(Html::b('(Rent Increase)'),['style'=>'font-size:14px;']) ,'param'=>['width'=>420]],
          'value'=>['val'=>Html::span($pamaAddr,['style'=>'text-align:right;font-size:8px;display:inline-block;']),'param'=>['width'=>130]]],
      ]
    ]);
    $content       .= Html::br(5) . Html::buildTable([
      'isHeader'    => 0,
      'isOrderList' => 0,
      'data'        => [
        ['title' => ['val'=>'Resident(s):','param'=>['width'=>60]],'value' => ['val'=>Html::repeatChar('&nbsp;',1) . strtoupper($tntName) . ' and all others in possession of:','param'=>['width'=>420]]],
        ['title' => ['val'=>'Premises:','param'=>['width'=>60]],'value'=>['val'=>Html::repeatChar('&nbsp;',1) . strtoupper($street),'param'=>['width'=>400]]],
        ['title' => ['val'=>'','param'=>['width'=>60]],'value'=>['val'=>Html::repeatChar('&nbsp;',1) . title_case($city) . ' ' . strtoupper($state) . ' ' . $zip,'param'=>['width'=>400]]]
      ]
    ]);

    $content    .= Html::h4(Html::b('TO RESIDENT(S): '));
    $noticeP     = Html::span(Html::b('PLEASE TAKE NOTICE'),['style'=>'font-size:14px;']) . ' that the terms of your of tenancy of the above-described ';
    $noticeP    .= 'premises are changed in the following respects, as indicated by the Check mark on the line(s) before the applicable paragraph(s)';
    $content    .= Html::div(Html::p($noticeP));
    $underTenDate = !($overTenPct) ? date('F j, Y',strtotime($effectDate)) : '';
    $underTenAmt  = !($overTenPct) ? Format::usMoney($raiseAmt) : '';
    $content    .= Html::buildTable([
      'isHeader'     => 0,
      'isOrderList'  => 0,
      'data'         => [
        ['checked' => ['val'=>!($overTenPct) ? Html::b(Html::u('   X   ')) : Html::b(Html::u('         ')),'param'=>['width'=>30]],'caption'=>['val'=>Html::b('Rent Increase of 10% or less -'),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'Account #: ' . (!($overTenPct) ? $accountNum : ''),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'Old Rental Amount: ' . (!($overTenPct) ? Format::usMoney($oldRent) : ''),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'New Rental Amount: ' . $underTenAmt,'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'','param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'Rent Due Date: ' . Html::u('1st') . ' day of each calendar month' . Html::repeatChar('&nbsp;',8)  . 'Effective Date: ' . (!($overTenPct) ? Html::u($underTenDate) : ''),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>self::_formCivilCode(30),'param'=>['width'=>450]]],
      ]
    ]);
    
    $overTenDate  = $overTenPct ? date('F j, Y',strtotime($effectDate)) : '';
    $overTenAmt   = $overTenPct ? Format::usMoney($raiseAmt) : '($)';
    
    $content    .= Html::br(2);
    
    $content    .= Html::buildTable([
      'isHeader'     => 0,
      'isOrderList'  => 0,
      'data'         => [
        ['checked' => ['val'=>$overTenPct ? Html::b(Html::u('   X   ')) : Html::b(Html::u('         ')),'param'=>['width'=>30]],'caption'=>['val'=>Html::b('Rent Increase over 10% or more -'),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'Account #: '  . ($overTenPct ? $accountNum : ''),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'Old Rental Amount: ' . ($overTenPct ? Format::usMoney($oldRent) : ''),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'New Rental Amount: ' . ($overTenPct ? $overTenAmt : ''),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'','param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>'Rent Due Date: ' . Html::u('1st') . ' day of each calendar month' . Html::repeatChar('&nbsp;',8) . 'Effective Date: ' . Html::u($overTenDate),'param'=>['width'=>450]]],
        ['checked' => ['val'=>'','param'=>['width'=>30]],'caption'=>['val'=>self::_formCivilCode(60,0),'param'=>['width'=>450]]],
      ]
    ]);
    
    $content    .= Html::p('Except as herein provided, all other terms of your tenancy shall remain in full force and effect.' . Html::br());
    $lawStmt     = '"As required by law, you are hereby notified that a negative credit report reflecting on your credit ';
    $lawStmt    .= 'report reflecting on your credit record may be submitted to a credit reporting agency if you fail to fulfill ';
    $lawStmt    .= 'the terms of your credit obligations." CC1785(2).';
    $content    .= Html::p(strtoupper($lawStmt) . Html::repeatChar(Html::br(),1),['style'=>'font-size:11px;']);
    
    $content    .= Html::buildTable([
      'isHeader'     => 0,
      'isOrderList'  => 0,
      'data'         => [
        ['date' => ['val' => Html::span(Html::br() .  'Date: ' . Html::u(date('M j, Y')),['style'=>'font-size:13px;']),'param'=>['valign'=>'bottom','width'=>340]],'signature'=>['val'=>Html::repeatChar('&nbsp;',7) . Html::img(['src'=>$sigImg,'width'=>'170','height'=>'70'])]],
        ['date' => ['val'=>'','param'=>['width'=>310]],'signature'=>['val'=>Html::span('Landlord Signature',['style'=>'text-align:center;'])]]
      ]
    ]);
    
    $pages[]    = $content;
    
    $content    = '';
    $content   .= Html::span(Html::h1(strtoupper('Declaration of service of notice to residents')),['style'=>'text-align:center;']);
    $content   .= Html::span(Html::h3('(California ' . Html::u('Civil Code') . ' Section 827)'),['style'=>'text-align:center;']);
    $content   .= Html::br(2);
     
    $agreeStmt  = 'I, the undersigned, declare that at the time of service of the papers herein refereed to, I was at least ';
    $agreeStmt .= 'least eighteen (18) years of age, and that I served the following checked notice:' . Html::br(2);
    $agreeStmt .= Html::span(Html::u('Notice of Change in Terms of Tenancy (Rent Increase)'),['style'=>'text-align:center;']) . Html::br(2);
    $agreeStmt .= 'On the ' . $draftDay . ' day of ' . date('F, Y') . ' in one of the manners checked and set forth below: ';
    $content   .= Html::p($agreeStmt) . Html::br();
    
    $content   .= Html::span(Html::p('(1) PERSONAL SERVICE'),['style'=>'text-align:center;']) . Html::br();
    $content   .= Html::p('______ By DELIVERING a copy of the Notice PERSONALLY to: ') . Html::br();
    $content   .= Html::p('_________________________________________________________________________________________' . Html::br(4));
    
    $content   .= Html::span(Html::p('(2) SERVICE BY MAIL'),['style'=>'text-align:center;']) . Html::br();
    $content   .= Html::p('(To be used only in the event that Personal service cannot be completed and adds an additional five (5) days to the effective date of the rent increase.)',['style'=>'font-size:9px;text-align:center;']);
    $content   .= Html::br(2);
    $content   .= Html::p(Html::u('    X     ') . ' By ' . Html::u('MAILING') . ' by first class mail a copy to each said resident (s) by depositing said copy in the United States Mail in a sealed envelope with postage fully prepaid to: ');
    $content   .= Html::p('Account #: ' . $accountNum);
    $content   .= Html::p(Html::u(strtoupper($tntName)));
    
    $content   .= Html::p('At their place of residence: ' . strtoupper($street) . ', ' . title_case($city) . ', ' . $state . ', ' . $zip) . Html::br(3);
    
    $content   .= Html::p('I declare under the penalty of perjury that the foregoing is true and correct to the best of my knowledge and if called as a witness to testify thereto, I could do so competently.') . Html::br();
    $content   .= Html::p('Executed the ' . Html::u($draftDay) . ' day of ' . Html::u(date('F, Y')) . ' at ' . Html::u('El Monte, California')  );
    

    $content   .= Html::buildTable([
      'isHeader'     => 0,
      'isOrderList'  => 0,
      'data'         => [
        ['date' => ['val' => Html::span(Html::br() . Html::u('    ' . self::$_landlordName . '    '),['style'=>'font-size:13px;']),'param'=>['valign'=>'bottom','width'=>340]],'signature'=>['val'=> Html::repeatChar('&nbsp;',7) . Html::img(['src'=>$sigImg,'width'=>'170','height'=>'70']),'valign'=>'bottom']],
        ['date' => ['val' => Html::repeatChar('&nbsp;',3) . 'Print Name','param'=>['width'=>310]],'signature'=>['val'=>Html::span('Landlord Signature',['style'=>'text-align:center;'])]]
      ]
    ]);
    
    $pages[]    = $content;
    return $pages;
  }
//------------------------------------------------------------------------------
  private static function _formCivilCode($days,$includeNot=1){
    $additionalDays    = $days + 5;
    $notToken          = $includeNot ? ' not ' : '';
    $civilCodeContent  = '(Pursuant to ' . Html::u('California Civil Code 827:') . ' If this rent increase plus all rent ';
    $civilCodeContent .= 'increases during the prior 12 months does ' . $notToken .  ' increase rent by a cumulative amount over 10%, this rent ';
    $civilCodeContent .= 'increase notice will be effective in ' . $days .  ' days if personally served upon you or ' . $additionalDays .  ' days if served by mail in ';
    $civilCodeContent .= 'accordance with ' . Html::u('Code of Civil Procedure 1013)');
    $civilCodeDiv      = Html::span($civilCodeContent,['style'=>'font-size:9px;']);
    return $civilCodeDiv;
  }
//------------------------------------------------------------------------------
  private static function _getRecentRentFromBilling($billing){
    $data = [];
    if(!empty($billing)){
      foreach($billing as $i => $v){
        if($v['schedule'] == 'M' && $v['gl_acct'] == G::$rent){
          $data[] = $v;
        }
      }
    }
    return !empty($data[count($data) - 2]) ? $data[count($data) - 2] : [];
  }
//------------------------------------------------------------------------------
  private static function _getPastRentFromBilling($billing,$billingId,$startDate){
    $data           = [];
    $billing602  = [];
    
    $_sortFn  = function($a,$b){
      $aTime       = strtotime(Helper::getValue('start_date',$a,0));
      $bTime       = strtotime(Helper::getValue('start_date',$b,0));
      $aStopTime   = strtotime(Helper::getValue('stop_date',$a,0));
      $bStopTime   = strtotime(Helper::getValue('stop_date',$b,0));
      return $aTime == $bTime ? ($aStopTime == $bStopTime ? 0 : ($aStopTime < $bStopTime ? -1 : 1)) : ($aTime < $bTime ? -1 : 1);
    };
   
    $billingIndex  = -1;
    foreach($billing as $i => $v){
      if($v['schedule'] == 'M' && $v['gl_acct'] == G::$rent && $v['service_code'] == S::$rent){
        $billing602[] = $v;
      }
    }

    usort($billing602,$_sortFn);
    foreach($billing602 as $i => $v){
      if($v['billing_id'] == $billingId){
        $billingIndex = $i;
      }
    }
    return Helper::getValue($billingIndex - 1,$billing602,[]);
  }
//------------------------------------------------------------------------------
  private static function _getRecentRentForSubmit($billing){
    $data = [];
    if(!empty($billing)){
      foreach($billing as $i => $v){
        if($v['schedule'] == 'M' && $v['gl_acct'] == G::$rent){
          $data[] = $v;
        }
      }
    }
    return !empty($data[count($data) - 1]) ? $data[count($data) - 1] : [];
  }
//------------------------------------------------------------------------------
  private static function _calculatePastYearlyPct($billing,$raise,$lastRaiseDate){
    $lastYear    = strtotime($lastRaiseDate . ' -12 months +1 day');
    $lastYearRent= $firstRent = 0;
    $billing602  = [];
    
    $_sortFn  = function($a,$b){
      $aTime       = strtotime(Helper::getValue('start_date',$a,0));
      $bTime       = strtotime(Helper::getValue('start_date',$b,0));
      $aStopTime   = strtotime(Helper::getValue('stop_date',$a,0));
      $bStopTime   = strtotime(Helper::getValue('stop_date',$b,0));
      return $aTime == $bTime ? ($aStopTime == $bStopTime ? 0 : ($aStopTime < $bStopTime ? -1 : 1)) : ($aTime < $bTime ? -1 : 1);
    };
   
    foreach($billing as $i => $v){
      if($v['schedule'] == 'M' && $v['gl_acct'] == G::$rent && $v['service_code'] == S::$rent){
        $billing602[] = $v;
      }
    }
    usort($billing602,$_sortFn);
    
    foreach($billing602 as $i => $v){
      if(strtotime($v['start_date']) <= $lastYear){
        $lastYearRent = $v['amount'];
      } else {
        break;
      }
    }
    
    $firstRent     = !empty($billing602[0]['amount']) ? $billing602[0]['amount'] : 0;
    $lastYearRent  = !empty($lastYearRent) ? $lastYearRent : $firstRent;
    $difference    = Format::roundDownToNearestHundredthDecimal($raise - $lastYearRent);
    $divisor       = !empty($lastYearRent) ? Format::roundDownToNearestHundredthDecimal($lastYearRent) : 1;
    
    $percentChange = (floatval($difference) / $divisor) * 100.0;
    return $percentChange;
  }
//------------------------------------------------------------------------------
  private static function _fetchRentRaiseWithId($rentRaiseData,$id){
    foreach($rentRaiseData as $i => $v){
      if($id != 0 && $id == $v['rent_raise_id']){
        return $v;
      }
    }
    return !empty($rentRaiseData) ? last($rentRaiseData) : [];
  }
//------------------------------------------------------------------------------
  private static function _getPdf($contentArr, $file,$folder,$includePopup=false){
    $title       = 'PAMA MANAGEMENT INC.';
    $orientation = 'P';
    $font        = 'times';
    $size        = '12';
    $msg         = 'Your Rent Raise Notice(s) are ready. Please click the here to download it';
    $dirPath     = storage_path('app/' . $folder);
    $href        = Storage::disk('public')->url(preg_replace('|public\/|', '', preg_replace('/\#/', '%23', $file)));
    $paths       = $files = [];
    
    foreach($contentArr as $i => $v){
      try {
        $tmpFile   = 'notice_' . $i . '.pdf';
        PDF::reset();
        PDF::SetTitle($title);
        PDF::setPageOrientation($orientation);
        PDF::SetFont($font, '', $size);
        PDF::SetMargins(10, 13, 10);
        PDF::SetAutoPageBreak(TRUE, 10);

        # HEADER SETTING
        PDF::SetHeaderData('', '0',$title);
        PDF::setHeaderFont([$font, '', ($size + 6)]);
        PDF::SetHeaderMargin(3);
        
        $paths[]  = $dirPath  . $tmpFile;
        $files[]  = $folder . $tmpFile;
        foreach($v as $page){
          PDF::AddPage();
          PDF::writeHTML($page,true,false,true,false,$orientation);
        }
        PDF::Output($dirPath . DIRECTORY_SEPARATOR . $tmpFile, 'F');
      } catch(Exception $e){
        Helper::echoJsonError(Helper::unknowErrorMsg());
      } 
    }

    $mergeR = PDFMerge::mergeFiles([
      'msg'          => $msg,
      'href'         => $href,
      'fileName'     => $file,
      'files'        => $files,
      'paths'        => $paths,
    ]);
    
    $linkParam = [
      'popupMsg'  => Html::a($msg,[
        'href'    => $href,
        'target'  => '_blank',
        'class'   => 'downloadLink',
      ])
    ];
    return $includePopup ? $linkParam : ['link'=>$href,'file'=>$file,'path'=>storage_path('app/' . $file)];
  }
//------------------------------------------------------------------------------
  public static function mergePdfNotices($mergeData){
    if(empty($mergeData['paths']) || empty($mergeData['files'])){
      return self::_getErrorMsg();
    }
    
    $msg         = 'Your Rent Raise Notice(s) are ready. Please click the here to download it';
    
    $folderName  = self::$_root . 'rent_raise_notice' . DIRECTORY_SEPARATOR . uniqid() . DIRECTORY_SEPARATOR;
    $fileName    = 'rent_raise_notice.pdf';
    
    $paths       = $mergeData['paths'];
    $files       = $mergeData['files'];
    $filePath    = $folderName . $fileName;
    
    $href        = Storage::disk('public')->url(preg_replace('|public\/|', '', preg_replace('/\#/', '%23', $filePath)));
    
    $merge       = PDFMerge::mergeFiles([
      'msg'         => $msg,
      'href'        => $href,
      'fileName'    => $filePath,
      'files'       => $files,
      'paths'       => $paths,
      'isDeleteFile'=> false,
    ]);
    
    $linkParam   = [
      'popupMsg'  => Html::a($msg,[
        'href'    => $href,
        'target'  => '_blank',
        'class'   => 'downloadLink',
      ]),
    ];
    return $linkParam;
  }
//------------------------------------------------------------------------------
  private static function _getErrorMsg(){
    return ['error'=>['msg'=>Html::errMsg('It appears there are no Rent Raise Notice(s) Ready to Print')]];
  }
}