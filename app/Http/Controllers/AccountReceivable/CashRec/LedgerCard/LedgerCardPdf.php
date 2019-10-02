<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\LedgerCard;
use App\Library\{Elastic, Html, TableName AS T, Helper, TenantTrans,Format};
use App\Http\Models\CashRecModel AS M; // Include the models class
use PDF;
use Storage;
use App\Http\Controllers\AccountReceivable\CashRec\LedgerCard\LedgerCardController as LedgerCard;

class LedgerCardPdf{
  private static $_headerCss = 'border-bottom:1px solid #000;border-top:1px solid #000;font-weight:bold;';
//------------------------------------------------------------------------------  
  public static function getPdf($vData, $ledgerCardType = 'ledgerCard'){
    $must = Helper::selectData(['prop', 'unit', 'tenant'], $vData);
    $dateRange = Helper::getDateRange($vData);
    $tenant  = Elastic::searchQuery([
      'index'   =>T::$tenantView,
      '_source' =>['tenant_id','group1', 'prop','unit','tenant', 'group1','tnt_name','street', 'bedrooms','bathrooms', 'move_in_date', 'move_out_date','lease_start_date',
        'status', 'base_rent','sq_feet' ,'phone1', 'dep_held1','city', 'Baldwin Park','state','zip', 'spec_code'],
      'query'   =>['must'=>$must]
    ]);
    $rTntTrans = Elastic::searchQuery([
      'index'=>T::$tntTransView,
      'sort'=>[['date1'=>'asc'], ['tx_code.keyword'=>'asc'], ['cntl_no'=>'asc']],
      'query'=>['must'=>$must]
    ]);
    
    $rDeposit = M::getTableData(T::$tntSecurityDeposit, Helper::selectData(['prop', 'unit', 'tenant'], $vData), ['tenant', 'amount', 'date1', 'gl_acct', 'usid', 'tx_code', 'remark']);
    
    $tenant    = Helper::getElasticResult($tenant, 1)['_source'];
    $content   = self::_tenantInfomation($tenant);
    $content .= self::_getTenantTrans($rTntTrans, $dateRange, $ledgerCardType);
    $content .= self::_getOpenItem($rTntTrans);
    $content .= self::_getSecurityDepositItem($rDeposit);
   
    return self::_getPdf([$content], []);
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private static function _tenantInfomation($tenant){
    return Html::buildTable([
      'data'=>[
        [ 'desc1'=>['val'=>Html::bu('Tenant Information'), 'param'=>['width'=>'250px', 'colspan'=>2]] ,
          'desc2'=>['val'=>Html::bu('Other Information'), 'param'=>['width'=>'180px', 'colspan'=>2]],
          'desc3'=>['val'=>Html::bu('Financial Information')],'val3'=>['val'=>''],
        ],
        [ 'desc1'=>['val'=>'Account Number:', 'param'=>['width'=>'70px']],  'val1'=>['val'=>$tenant['prop'].'-'.$tenant['unit'].'-'.$tenant['tenant'], 'param'=>['width'=>'180px']],
          'desc2'=>['val'=>'Move In Date:', 'param'=>['width'=>'110px']],    'val2'=>['val'=>Format::usDate($tenant['move_in_date']), 'param'=>['width'=>'70px']],
          'desc3'=>['val'=>'Security Deposit:'],'val3'=>['val'=>Format::usMoney($tenant['dep_held1'])],
        ],
        [ 'desc1'=>['val'=>'Tenant Name(s):'],  'val1'=>['val'=>title_case($tenant['tnt_name'])],
          'desc2'=>['val'=>'Move out Date:'],   'val2'=>['val'=>($tenant['move_out_date'] == '9999-12-31' ? 'Occupied' : Format::usDate($tenant['move_out_date']))],
          'desc3'=>['val'=>'Base Rent:'],       'val3'=>['val'=>Format::usMoney($tenant['base_rent'])],
        ],
        [ 'desc1'=>['val'=>'Unit Address:'],               'val1'=>['val'=>title_case($tenant['street'])],
          'desc2'=>['val'=>'Lease Start/Rent Inc. Date:'], 'val2'=>['val'=>Format::usDate($tenant['lease_start_date']), ['param'=>['colspan'=>3]]],
        ],
        [ 'desc1'=>['val'=>'City, State, Zip:'],  'val1'=>['val'=>title_case($tenant['city']).','.$tenant['state'].','.$tenant['zip']],
          'desc2'=>['val'=>'Lease Stop Date:'],    'val2'=>['val'=>'Month-to-Month',['param'=>['colspan'=>3]]],
        ],
        [ 'desc1'=>['val'=>'Account Status:'],  'val1'=>['val'=>$tenant['status']],
          'desc2'=>['val'=>'Special Code:'],    'val2'=>['val'=>$tenant['spec_code'],['param'=>['colspan'=>3]]],
        ],
        [ 'desc1'=>['val'=>'Property Group:'],  'val1'=>['val'=>$tenant['group1']],
          'desc2'=>['val'=>'Bedroom:'],    'val2'=>['val'=>$tenant['bedrooms'],['param'=>['colspan'=>3]]],
        ],
        [ 'desc1'=>['val'=>'Run On:'],  'val1'=>['val'=>date('m/d/Y g:i a')],
          'desc2'=>['val'=>'Bathroom:'],    'val2'=>['val'=>$tenant['bathrooms'],['param'=>['colspan'=>3]]],
        ],
        [ 'desc1'=>['val'=>'Phone:'],  'val1'=>['val'=>$tenant['phone1']],
          'desc2'=>['val'=>'Square Feet:'],    'val2'=>['val'=>Format::number($tenant['sq_feet']),['param'=>['colspan'=>3]]],
        ],
        [ 'val2'=>['val'=>'',['param'=>['colspan'=>6]]],
        ],
      ], 'isHeader'=>0, 'isOrderList'=>0
    ]);
  }
//------------------------------------------------------------------------------
  private static function _getTenantTrans($rTntTrans, $dateRange, $ledgerCardType){
    $tableData   = [];
    $ledgerCard  = LedgerCard::getInstance()->getGridData($rTntTrans, $ledgerCardType, $dateRange);
    $mapping     = Helper::getMapping(['tableName'=>T::$tntTrans]);
    $headerStyle = self::$_headerCss;
    $alignRight  = 'text-align:right;';
    foreach($ledgerCard as $v){
      if(isset($v['date1'])){
        $date1  = Format::usDate($v['date1']);
        $txCode = isset($mapping['tx_code'][$v['tx_code']]) ? $mapping['tx_code'][$v['tx_code']] : $v['tx_code'];
        $tableData[] = [
          'date'    =>['val'=>$date1,        'param'=>[], 'header'=>['val'=>'Date', 'param'=>['style'=>$headerStyle.self::_getWidth(65)]] ],
          'type'    =>['val'=>$txCode, 'param'=>[], 'header'=>['val'=>'Type', 'param'=>['style'=>$headerStyle.self::_getWidth(65)]] ],
          'remark'  =>['val'=>title_case($v['remark']),  'param'=>[], 'header'=>['val'=>'Remark', 'param'=>['style'=>$headerStyle.self::_getWidth(200)]] ],
          'charge'  =>['val'=>$v['inAmount'],'param'=>['style'=>$alignRight], 'header'=>['val'=>'Charge', 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ],
          'payment' =>['val'=>$v['pAmount'], 'param'=>['style'=>$alignRight], 'header'=>['val'=>'Payment', 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ],
          'balance' =>['val'=>$v['balance'], 'param'=>['style'=>$alignRight], 'header'=>['val'=>'Balance', 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ],
        ];
      }else{ // Last Row: Total
        $tableData[] = ['balance' =>['val'=>$v['balancePdf'], 'param'=>['colspan'=>6, 'style'=>'border-top:1px dashed #000;font-weight:bold;'.$alignRight]]];
      }
    }
    if(!isset($tableData[0]['date'])){
      $emptyData = [
        'date'    =>['val'=>'',  'param'=>[], 'header'=>['val'=>'Date', 'param'=>['style'=>$headerStyle.self::_getWidth(65)]] ],
        'type'    =>['val'=>'', 'param'=>[], 'header'=>['val'=>'Type', 'param'=>['style'=>$headerStyle.self::_getWidth(65)]] ],
        'remark'  =>['val'=>'No Result',  'param'=>[], 'header'=>['val'=>'Remark', 'param'=>['style'=>$headerStyle.self::_getWidth(200)]] ],
        'charge'  =>['val'=>'','param'=>['style'=>$alignRight], 'header'=>['val'=>'Charge', 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ],
        'payment' =>['val'=>'', 'param'=>['style'=>$alignRight], 'header'=>['val'=>'Payment', 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ],
        'balance' =>['val'=>'', 'param'=>['style'=>$alignRight], 'header'=>['val'=>'Balance', 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ],
      ];
     $tableData =  array_prepend($tableData, $emptyData);
    }
    return Html::buildTable(['data'=>$tableData, 'isOrderList'=>0, 'isAlterColor'=>1]);
  }
//------------------------------------------------------------------------------
  private static function _getOpenItem($rTntTrans){
    $alignRight  = 'text-align:right;';
    $headerStyle = self::$_headerCss;
    $ledgerCard  = LedgerCard::getInstance()->getGridData($rTntTrans, 'listOpenItems');
    $ledgerCard  = !empty($ledgerCard) ? $ledgerCard : [];
    
    $tableData   = [
      ['header'=>['val'=>Html::br() . Html::h2('Open Item Detail'), 'param'=>['style'=>'text-align:center', 'colspan'=>5]]],
      [      
        'date'    =>['val'=>Html::b('Date'), 'param'=>['style'=>$headerStyle.self::_getWidth(65)] ],
        'invoice' =>['val'=>Html::b('Invoice'), 'param'=>['style'=>$headerStyle.self::_getWidth(65)] ],
        'remark'  =>['val'=>Html::b('Open Item'), 'param'=>['style'=>$headerStyle.self::_getWidth(280)] ],
        'charge'  =>['val'=>Html::b('Charge'), 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)] ],
        'balance' =>['val'=>Html::b('Balance'), 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)] ],
      ]
    ];
    
    foreach($ledgerCard as $v){
      if(isset($v['date1'])){
        $date1 = Format::usDate($v['date1']);
        $tableData[] = [
          'date'    =>['val'=>$date1,        'param'=>[], 'header'=>['val'=>Html::b('Date'), 'param'=>['style'=>$headerStyle.self::_getWidth(65)]] ],
          'invoice' =>['val'=>$v['appyto'], 'param'=>[], 'header'=>['val'=>Html::b('Invoice'), 'param'=>['style'=>$headerStyle.self::_getWidth(65)]] ],
          'remark'  =>['val'=>title_case($v['remark']),  'param'=>[], 'header'=>['val'=>Html::b('Open Item'), 'param'=>['style'=>$headerStyle.self::_getWidth(280)]] ],
          'charge'  =>['val'=>$v['inAmount'],'param'=>['style'=>$alignRight], 'header'=>['val'=>Html::b('Charge'), 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ],
          'balance' =>['val'=>$v['balance'], 'param'=>['style'=>$alignRight], 'header'=>['val'=>Html::b('Balance'), 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ],
        ];
      }
    }
    return Html::buildTable(['data'=>$tableData, 'isHeader'=>0,'isOrderList'=>0, 'isAlterColor'=>1]);
  }
//------------------------------------------------------------------------------
  private static function _getSecurityDepositItem($rDeposit){
    $alignRight  = 'text-align:right;';
    $headerStyle = self::$_headerCss;
    $css         = 'border-top:1px dashed #000;font-weight:bold;';
    $rDeposit  = !empty($rDeposit) ? $rDeposit : [];
    
    $tableData   = [
      ['header'=>['val'=>Html::br() . Html::h2('Security Deposit Detail'), 'param'=>['style'=>'text-align:center', 'colspan'=>4]]],
      [      
        'date'    =>['val'=>Html::b('Date'), 'param'=>['style'=>$headerStyle.self::_getWidth(65)]] ,
        'remark'  =>['val'=>Html::b('Remark'), 'param'=>['style'=>$headerStyle.self::_getWidth(345)]] ,
        'charge'  =>['val'=>Html::b('Amount'), 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ,
        'balance' =>['val'=>Html::b('Balance'), 'param'=>['style'=>$headerStyle.$alignRight.self::_getWidth(80)]] ,
      ]
    ];
    $balance   = 0;
    foreach($rDeposit as $v){
      if(isset($v['date1'])){
        $balance += $v['amount'];
        $date1 = Format::usDate($v['date1']);
        $tableData[] = [
          'date'    =>['val'=>$date1, 'param'=>[] ],
          'remark'  =>['val'=>title_case($v['remark']),  'param'=>[]],
          'charge'  =>['val'=>Format::usMoneyMinus($v['amount']),'param'=>['style'=>$alignRight]],
          'balance' =>['val'=>Format::usMoneyMinus($balance), 'param'=>['style'=>$alignRight]],
        ];
      }
    }
        
    $tableData[] = [
      'remark'=>['val'=>($balance > 0 ? 'Security Deposit Held as of ' . Helper::usDate() : 'Move-Out Charges: '), 'param'=>['style'=>$css.$alignRight, 'colspan'=>3]], 
      'balance'=>['val'=>Format::usMoneyMinus($balance), 'param'=>['style'=>$css.$alignRight]]
    ];
    return Html::buildTable(['data'=>$tableData, 'isHeader'=>0, 'isOrderList'=>0, 'isAlterColor'=>1]);
  }
//------------------------------------------------------------------------------
  private static function _getPdf($contentData, $param){
    $fileInfo    = self::_getFileAndHref('pdf');
    $file        = isset($param['filePath']) ? $param['filePath'] : $fileInfo['filePath'];
    $href        = isset($param['href']) ? $param['href'] : $fileInfo['href'];
    $title       = 'Ledger Card';
    $orientation = 'P';
    
    $font = isset($param['font']) ? $param['font'] : 'times';
    $size = isset($param['size']) ? $param['size'] : '9';
    $msg  = isset($param['popupMsg']) ? $param['popupMsg'] : 'Your export file is ready. Please click the link here to download it';
    try{
      PDF::reset();
      PDF::SetTitle($title);
      PDF::setPageOrientation($orientation);

      # HEADER SETTING
      PDF::SetHeaderData('', '0', Html::repeatChar(' ', 75) .  $title);
      PDF::setHeaderFont([$font, '', ($size + 3)]);
      PDF::SetHeaderMargin(3);

      # FOOTER SETTING
      PDF::SetFont($font, '', $size);
      PDF::setFooterFont([$font, '', $size]);
      PDF::SetFooterMargin(5);

      PDF::SetMargins(5, 13, 5);
      PDF::SetAutoPageBreak(TRUE, 10);

      $contentData = isset($contentData[0]) ? $contentData : [$contentData];
      
      foreach($contentData as $content){
        PDF::AddPage();
        PDF::writeHTML($content,true,false,true,false,$orientation);
      }
      
      PDF::Output($file, 'F');
      return [
        'file'=>(php_sapi_name() == "cli") ? $fileInfo['filePath'] : '',
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
  private static function _getFileAndHref($extension){
    $file     = 'ledger_card' . \Request::ip() . date('_YmdHis') . '.' . $extension;
    $file     = 'ledger_card.' . $extension;
    $filePath = storage_path('app/public/tmp/' . $file);
    $href     = Storage::disk('public')->url('tmp/'. $file);
    return ['filePath'=>$filePath, 'href'=>$href];
  }
//------------------------------------------------------------------------------
  private static function _getWidth($int){
    return 'width:'.$int.'px;';
  }
}