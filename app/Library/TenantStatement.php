<?php
namespace App\Library;
use App\Library\{Elastic, TableName AS T, Helper, Format, File};
use PDF;
use Storage;
use App\Http\Controllers\AccountReceivable\CashRec\LedgerCard\LedgerCardController as LedgerCard;

class TenantStatement{
  private static $_mapStar = ['*'=>'', '**'=>'A','***'=>'B','****'=>'C','*****'=>'D','******'=>'E','*******'=>'F','********'=>'G','*********'=>'H'];
  private static $_str = 'ANY MAINTENANCE REQUEST MUST BE IN WRITING. MAKE MONEY ORDERS/CASHIER CHECKS PAY TO PAMA MANAGEMENT';
  private static $_root= 'public/tmp/';  
  public static function getPdf($tenantId,$param=[]){
    $htmlData = [];
    $section  = ['Tenant Copy' , 'Manager Copy', 'Office Copy'];
    $tenantId = !isset($tenantId[0]) ? [$tenantId] : $tenantId;
    $rTenant  = Elastic::searchQuery([
      'index'   =>T::$tenantView,
      '_source' =>['tenant_id','group1', 'return_no', 'prop','unit','tenant', 'group1','tnt_name','street', 'bedrooms','bathrooms', 'move_in_date', 'move_out_date','lease_start_date',
        'status', 'base_rent','sq_feet' ,'phone1', 'dep_held1','city', 'Baldwin Park','state','zip', 'spec_code','mangtgroup'],
      'query'   =>['must'=>['tenant_id'=>$tenantId]]
    ]);
    $isDeleteGroupFolder = isset($param['isDeleteGroupFolder']) ? $param['isDeleteGroupFolder'] : true;
    $rTenant = Helper::getElasticGroupBy(Helper::getElasticResult($rTenant), 'group1');
    $rGroup  = Helper::getElasticResultSource(HelperMysql::getGroup(['prop.keyword'=>key($rTenant)], ['prop', 'fileUpload']), 1);

    if(!empty($rGroup['fileUpload'])) {
      $imgSize = getimagesize($rGroup['fileUpload'][0]['path'] . $rGroup['fileUpload'][0]['uuid'] . '/' .$rGroup['fileUpload'][0]['file']);
      $section = [];
      $section['first'] = 'Tenant Copy';
      if($imgSize[1] > 600) {
        $section['second'] = 'Manager Copy';
      }else {
        $section[] = 'Manager Copy';
      }
      $section[] = 'Office Copy';
    }
    foreach($rTenant as $group=>$tenantData){
      $folderName = self::$_root . 'statement/' . $group . '/';
      if(Helper::isCli()){ ##### RUN FROM COMMAND #####
        $folderName = self::$_root . $group . '/';
        if(Storage::exists($folderName) && $isDeleteGroupFolder){
          Storage::deleteDirectory($folderName);
        } 
        
        if($isDeleteGroupFolder){
          Storage::makeDirectory($folderName); 
        }
        
      } else{ ##### RUN FROM WEB APPLICATION #####
        if(Storage::exists($folderName)){
          Storage::makeDirectory($folderName);
        } 
      }
      foreach($tenantData as $tenant){
        $rTntTrans = Elastic::searchQuery([
          'index'=>T::$tntTransView,
          'query'=>['must'=>['prop.keyword'=>$tenant['prop'], 'unit.keyword'=>$tenant['unit'], 'tenant'=>$tenant['tenant']]]
        ]);
        $fileName           = $folderName . $tenant['prop'].$tenant['unit'].$tenant['tenant'];
        $htmlData[$group][] = $fileName;
        
        $content = '';
        $num = 0;
        foreach($section as $i=>$eachSection){
          if(!is_numeric($i) && $i == 'first') {
            $path = File::getLocation('Group')['showUpload'] . implode('/', [$rGroup['fileUpload'][0]['type'], $rGroup['fileUpload'][0]['uuid'], $rGroup['fileUpload'][0]['file']]);
            $path = parse_url($path);
            $rootPath    = preg_replace('/(.*)storage(.*)/','storage/app/public/$2',storage_path(preg_replace('/\/storage\//','',$path['path'])));
            $path['path']= Helper::isCli() ? $rootPath : $path['path'];
            $content .= $imgSize[1] > 600 ?  Html::div(Html::img(['src'=>$path['path'], 'width'=>1000, 'height'=>908])) : Html::div(Html::img(['src'=>$path['path'], 'width'=>1000, 'height'=>422]));
            $content .= Html::div(' ', ['style'=>'border-top:1px dashed #000;']);
          }else if(is_numeric($i)) {
            $isIncludeLine = $num == 2 ? 0 : 1;
            $content .= self::_getEachStatementHtml($tenant, $rTntTrans, $eachSection, $isIncludeLine);
          }
          $num++;
        }
        Storage::put($fileName, $content);
      }
    }
    
    foreach($htmlData as $group=>$file){
      $content = '';
      foreach($file as $f){
        $content .= Storage::get($f);
      }
      $fileName = $f . '.pdf';
      return self::_getPdf($content,$fileName);
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------  
  public static function _getEachStatementHtml($tenant, $rTntTrans, $eachSection, $isIncludeLine = 1){
    $openItem = LedgerCard::getInstance()->getGridData($rTntTrans, 'listOpenItems');
    $openItem = !empty($openItem) ? $openItem : [];
    $rCompany = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'=>T::$companyView,
      '_source'=>['company_name', 'mailing_street','mailing_city','mailing_state','mailing_zip','phone','company_code']
    ]), 'company_code');
    
    $company = isset($rCompany[$tenant['mangtgroup']]) ? $rCompany[$tenant['mangtgroup']] : $rCompany['**PAMA'];
    ##### LEFT TABLE DATA #####
    $tableLeftData = [
      ['row'=>['val'=>Html::bu('Office Information:')]],
      ['row'=>['val'=>$company['company_name']]],
      ['row'=>['val'=>title_case($company['mailing_street'])]],
      ['row'=>['val'=>title_case($company['mailing_city'])]],
      ['row'=>['val'=>$company['mailing_state'] . ' ' . $company['mailing_zip']]],
      ['row'=>['val'=>$company['phone']]],
      
      ['row'=>['val'=>'']],
      ['row'=>['val'=>Html::bu('Tenant Information:')]],
      ['row'=>['val'=>'Account Code: ' . Html::bu($tenant['prop'] . '-' . $tenant['unit'] . '-' . $tenant['tenant'])]],
      ['row'=>['val'=> title_case(preg_split('/,|\-/', $tenant['tnt_name'])[0])]],
      ['row'=>['val'=> title_case($tenant['street'])]],
      ['row'=>['val'=> title_case($tenant['city'] . ', ' . $tenant['state'] . ' ' . $tenant['zip'])]],
      ['row'=>['val'=>'']],
      ['row'=>['val'=>'Amount Paid: ________________']],
    ];
    ##### RIGHT TABLE DATA #####
    $_getRow = function($v = []){
      $borderBottom = 'border-bottom:1px solid #ccc;';
      return [
        'date'  =>['val'=>isset($v['date1']) ? Helper::usDate($v['date1']) : '', 'param'=>['style'=>$borderBottom,'align'=>'left','width'=>'15%']], 
        'desc'  =>['val'=>isset($v['remark']) ? title_case($v['remark']) : '', 'param'=>['style'=>$borderBottom,'align'=>'left','width'=>'65%']], 
        'amount'=>['val'=>isset($v['amount']) ? $v['amount'] : '', 'param'=>['style'=>$borderBottom,'align'=>'right','width'=>'20%']]
      ];
    };
    
    ##### HEADER LISTING ######
    $tableRightData = [
      [ 'date'  =>['val'=>'', 'param'=>['width'=>'15%']], 
        'desc'  =>['val'=>Html::h1(Html::bu('Statement - ' . $eachSection)), 'param'=>['width'=>'65%', 'align'=>'center']], 
        'amount'=>['val'=>'', 'param'=>['width'=>'20%']]
      ],
      [ 'date'  =>['val'=>'DATE',        'param'=>['style'=>'background-color:#ccc;', 'align'=>'left', 'width'=>'15%']], 
        'desc'  =>['val'=>'DESCRIPTION', 'param'=>['style'=>'background-color:#ccc;', 'align'=>'left', 'width'=>'65%']], 
        'amount'=>['val'=>'AMOUNT',      'param'=>['style'=>'background-color:#ccc;', 'align'=>'right', 'width'=>'20%']]
      ],
    ]; 
    ##### OPEN ITEM LISTING ######
    $balance = '';
    foreach($openItem as $v){
      if(!empty($v['date1'])){
        $tableRightData[] = $_getRow($v);
      } else{
        $balance = $v['balance'];
      }
    }
    ##### FILLING LISTING ######
    for($i = count($tableRightData); $i <= 10; $i++){
      $tableRightData[] = $_getRow();
    }
    ##### BALANCE LISTING ######
    $tableRightData[] = ['amount'  =>['val'=>$balance, 'param'=>['colspan'=>3, 'style'=>'border-top:1px solid #000;', 'align'=>'right']]];
    $tableRightData[] = ['amount'  =>['val'=>Html::b('Due Date: ' . date('m/01/Y', strtotime('+7 day'))), 'param'=>['colspan'=>3, 'align'=>'right']]];
    
    $table = Html::buildTable([
      'data'=>[[
        'tableLeft' =>['val'=>Html::buildTable(['data'=>$tableLeftData,'isHeader'=>0,'isOrderList'=>0]),  'param'=>['width'=>'30%']], 
        'tableRight'=>['val'=>Html::buildTable(['data'=>$tableRightData,'isHeader'=>0,'isOrderList'=>0]), 'param'=>['width'=>'70%']]
      ]],'isHeader'=>0, 'isOrderList'=>0
    ]);
    
    $line = ($isIncludeLine) ? Html::div(' ', ['style'=>'border-top:1px dashed #000;']) : '';
    return $table  . Html::br() . Html::div(self::$_str, ['style'=>'font-size:9px;line-height:11.5px;']) . self::_getRpsNum($tenant, $openItem) . Html::br() . $line;  
  }
//------------------------------------------------------------------------------
  private static function _getRpsNum($tenant, $openItem){
    $starMatch = [];
    $balanceData = end($openItem);
    $rBank = Elastic::searchQuery([
      'index'=>T::$bankView,
      '_source'=>['trust'],
      'query'=>['must'=>['prop'=>$tenant['prop']]]
    ]);
    if(isset(Helper::getElasticResult($rBank, 1)['_source'])){
      $trust = Helper::getElasticResult($rBank, 1)['_source']['trust'];
      preg_match('/\*+/', $trust, $starMatch); // Capture all the stars
      $trust      = preg_replace('/[^A-Za-z0-9\-]/', '', $trust); 
      $trust      = !empty($starMatch[0]) ? self::$_mapStar[$starMatch[0]] . $trust : $trust;

      return Html::div(implode('&nbsp;', [
        'trust'     =>self::_insertSpace(5, $trust),
        'returnNo'  =>self::_insertSpace(8, $tenant['return_no']),
        'checkDigit'=>Html::space(),
        'balance'   =>self::_insertSpace(12, $balanceData['balanceRaw']),
        'prop'      =>self::_insertSpace(4, $tenant['prop']),
        'unit'      =>self::_insertSpace(4, $tenant['unit']),
        'tenant'    =>self::_insertSpace(3, $tenant['tenant'])
      ]), ['style'=>'font-size:11px;font-family:OCRA;text-align:center;']);
    }
  }
//------------------------------------------------------------------------------
  private static function _insertSpace($length, $str){
    $data = $str;
    $strlen = strlen($str);
    $space = '';
    for($i = $strlen; $i < $length; $i++){
      $space .= '&nbsp;';
    }
    return $space . $data;
  }
//------------------------------------------------------------------------------
  private static function _getPdf($content, $file){
    $title       = 'Statement';
    $orientation = 'P';
    $font        = 'times';
    $size        = '10';
    $msg         = 'Your file is ready. Please click the link here to download it';
    $filePath = storage_path('app/' . $file);
    $href     = Storage::disk('public')->url(preg_replace('|public\/|', '', preg_replace('/\#/', '%23', $file)));

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
      return [
        'popupMsg'=>Html::a($msg, [
          'href'=>$href, 
          'target'=>'_blank',
          'class'=>'downloadLink'
        ]),
        'link'    =>$href,
        'filePath'=>$filePath,
      ];
    } catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
}
