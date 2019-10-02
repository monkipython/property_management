<?php
namespace App\Http\Controllers\AccountPayable\Approval\PrintCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, PDFMerge, Html, Helper, HelperMysql, File, Format, PositivePay, TableName AS T, NumberToWord};
use App\Http\Models\{Model, ApprovalModel AS M};
use \App\Http\Controllers\AccountPayable\Approval\ApprovalController AS P;
use PDF;

class PrintCheckController extends Controller {
  private $_printByOption = ['prop'=>'Property', 'transaction'=>'Transaction', 'trust'=>'Trust', 'vendid'=>'Vendor Code', 'invoice'=>'Invoice'];
  private $_maxAmountNoSignature = 5000;
  private $_checkPath = '';
  private static $_instance;
    
  public function __construct(){}
//------------------------------------------------------------------------------
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  } 
//------------------------------------------------------------------------------  
  public function create(Request $req){
    $num = !empty($req['num']) ? $req['num'] : 0;
    $label = ($num) ? $num : 'all';
    $option = [0=>'Print All Checks'];
    $option = $option + (!empty($num) ? [$num=>'Request ' . $label .' Transaction(s) to be Approved.'] : []);
    $printByOption = $this->_printByOption;
    $text = Html::h4('Are you sure you want to print these check(s)?', ['class'=>'text-center']) . Html::br();
    $fields = [
      'numTransaction'=>['id'=>'numTransaction','label'=>'How Many Transactions?','type'=>'option', 'option'=>$option, 'req'=>1],
      'printBy'       =>['id'=>'printBy','label'=>'Print By:','type'=>'option', 'option'=>$printByOption],
      'posted_date'   =>['id'=>'posted_date','label'=>'Print/Post Date', 'class'=>'date', 'type'=>'text', 'value'=>date('m/d/Y')],
    ];
    return ['html'=>$text . Html::tag('form', implode('',Form::generateField($fields)), ['id'=>'showConfirmForm', 'class'=>'form-horizontal'])];
  }
//------------------------------------------------/.,;',;-----------------------------
  public function store(Request $req){
    $batch = HelperMysql::getBatchNumber();
    $maxRowPerCheck = 33;
    $data = $printData = $updateData = $updateDataTmp = $vendorPaymentId = $pdfCheck = $pdfCheckCopy = $whereSeqQuery = [];
    $insertData = [T::$glTrans=>[], T::$batchRaw=>[], T::$clearedCheck=>[]];
    
    $this->_checkPath = File::getLocation('PrintCheck')['tmpCheck'] . $req['ACCOUNT']['firstname'] . $req['ACCOUNT']['lastname'] . '/';
    ##### EMPTY DIRECTOY BEFORE PUT PDF FILE IN THE TMP DIRECTORY #####
    \File::deleteDirectory($this->_checkPath);

    $approvalStatus  = P::getInstance()->approvalStatus;
    $id = 'vendor_payment_id';
    $req->merge([$id=>$req['id']]);
    $valid = V::startValidate([
      'rawReq'         => $req->all(),
      'tablez'         => [T::$vendorPayment],
      'orderField'     => [$id,'printBy', 'posted_date'],
      'includeCdate'   => 0, 
      'isExistIfError' => 0,
      'includeUsid'    => 1, 
      'setting'        => $this->_getSetting('store', $req), 
    ]);
    
    $vData = $valid['data'];
    $usid  = $vData['usid'];
    
    $rSource = P::getInstance()->getApprovedResult($valid);
    $rCompany = Helper::keyFieldNameElastic(HelperMysql::getCompany([], 0, 0), 'company_code');
    $rProp    = HelperMysql::getProp(array_column($rSource, 'prop'), ['prop', 'entity_name', 'mangtgroup', 'trust', 'bank']);
//    $this->_validateTrustBank($rProp);
    $rProp    = Helper::keyFieldNameElastic($rProp, 'trust');
    $rVendor  = Helper::keyFieldNameElastic(HelperMysql::getVendor(['vendor_id'=>array_column($rSource, 'vendor_id')], [], [], 0, 0), 'vendor_id');
    $allowGrouping = $this->_printByOption;
    $printBy  = $vData['printBy'];
    
    /**
     * IMPORTANT: 
     *  + Print by print_trans
     *    - If it is print by trans, we don't group anything, we let it print the way it is
     *  + Print by prop, trust
     *    - There are 2 levels of group,
     *      - 1st level is always group by VENDID and BANK
     *      - 2nd level is depended on what they select either by prop or trust
     */
    
    foreach($rSource as $i=>$v){
      if(!isset($allowGrouping[$printBy])){
        Helper::echoJsonError('something wrong');
      }
      
      $mainGroup = $printBy == 'transaction' ? $i : $v['trust'] . '-' . $v['bank'] . '-' . $v['vendor_id'];
      $userGroup = $printBy == 'transaction' ? $i : $v[$printBy];
      
      $printData[$mainGroup][$userGroup]['data'][] = $v;
      $printData[$mainGroup][$userGroup]['num'] = isset($printData[$mainGroup][$userGroup]['num']) ? $printData[$mainGroup][$userGroup]['num'] + 1 : 1;
    }
    ##### SPLIT THE DATA INTO MULTIPLE CHECK #####
    foreach($printData as $mainGroup=>$val){
      foreach($val as $userGroup=>$val){
        $totalRow  = $val['num'];
        $numSplit  = floor($totalRow / $maxRowPerCheck);
        $remaining = $totalRow - ($numSplit * $maxRowPerCheck);
        
        for($i = 0; $i <= $numSplit; $i++){
          $offset = ($i * $maxRowPerCheck);
          $length = ($i == $numSplit) ? $remaining : $maxRowPerCheck;
          if($length > 0){
            $data[$mainGroup][$userGroup]['split' . $i] = array_slice($val['data'], $offset, $length);
          }
        }
      }
    }
    
    ##### START TO RESERVE THE CHECK NUMBER #####
    $allCheck = $checkReserveNum = $checkDetail = $positivePayData = [];
    $num = 1;
    $lastVendorPaymentId = 0;
    foreach($data as $mainGroup=>$value){
      foreach($value as $userGroup=>$val){
        foreach($val as $checkSplit=>$v){
          $allCheck[$userGroup . '-'. $mainGroup]['check' . $num++] = $v;
          $lastVendorPaymentId = last($v)['vendor_payment_id'];
        }
      }
    }
    ksort($allCheck);
    
    ##### UPDATE AND RESERVER THE BANK CHECK NUMBER #####
    $checkNoData = $this->_updateBankCheckNumber($allCheck);
    
    ##### START TO BUILD EACH CHECK #####
    foreach($allCheck as $val){
      foreach($val as $eachCheck=>$trans){
        $currentTrans = current($trans);
        $checkNo        = Format::checkNumber(++$checkNoData[$currentTrans['trust'] . '-' . $currentTrans['bank']]);
        $tmpCheck      = $this->_getCheck($trans, $rCompany, $rProp, $rVendor, $checkNo, $vData, $eachCheck, $req);
        $checkDetail[] = $tmpCheck; 
        
        ##### SAVE EACH PEF INTO FOR MERGING INTO ONE FILE #####
        $pdfCheck[] = $tmpCheck['check']['path'] . $tmpCheck['check']['file'];
      }
    }
    
    ##### START TO BUILD POSITIVE PAY FILE#####
    $positivePayData = Helper::groupBy($checkDetail, 'bankName');
    $positivePayCSV = [];
    foreach($positivePayData as $bankName=>$v){
      $positivePayCSV[$bankName] = PositivePay::getFormatedData($bankName, $v);
    }

    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])),'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>'Z64'])),'service');
    foreach($checkDetail as $i => $v){
      if(!isset($positivePayCSV[$v['bankName']])){
        Helper::echoJsonError($this->_getErrorMsg('noPositivePay',['bank'=>$v['bankName']]),'popupMsg');
      }
      $glChart = !preg_match('/[a-zA-Z]+/', $v['prop']) ? $glChart : Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$v['prop']])),'gl_acct');
      $service = !preg_match('/[a-zA-Z]+/', $v['prop']) ? $service : Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$v['prop']])),'service');
      
      $v       = P::getInstance()->getGlTransData($v, $vData, $batch, $glChart);
      $dataset = [T::$glTrans=>[$v], T::$batchRaw=>[$v], 'summaryAcctPayable'=>[]];
      
      $tmp =  HelperMysql::getDataSet($dataset, $usid, $glChart, $service);
      $insertData[T::$glTrans] = array_merge($insertData[T::$glTrans], $tmp[T::$glTrans]);
      $insertData[T::$batchRaw] = array_merge($insertData[T::$batchRaw], $tmp[T::$batchRaw]);
      $insertData[T::$clearedCheck] = array_merge($insertData[T::$clearedCheck], $tmp[T::$clearedCheck]);
      
      $id = $this->getCheckUniqueId($v);
      $updateDataTmp[$id] = [
        'print'             => 1,
        'print_type'        => 'check',
        'vendor_payment_id' => $v['vendor_payment_id'],
        'print_by'          => $usid,
        'posted_date'       => $vData['posted_date'],
        'check_no'          => $v['check_no'],
        'check_pdf'         => $v['checkCopy']['file'],
        'positivePayFile'   => PositivePay::getBankFileName($v['bankName']),
      ];
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = $cntlNoData = $vendorPaymentId = [];
    try{
      $success += Model::insert($insertData);
      $rGlTrans = M::getTableDataWhereIn(T::$glTrans, 'seq', $success['insert:' . T::$glTrans]);
      $elastic[T::$glTransView] = ['seq'=>$success['insert:' . T::$glTrans]];
      
      ##### GET BUILD UPDATE DATA FOR THE SEQ #####
      foreach($rGlTrans as $v){
        $id = $this->getCheckUniqueId($v);
        if(isset($updateDataTmp[$id]) && $v['amount'] > 0){
          $updateDataTmp[$id]['seq'] = $v['seq'];
          $tmpVendorPaymentId = $updateDataTmp[$id]['vendor_payment_id'];
          $vendorPaymentId[] = $tmpVendorPaymentId; 
          unset($updateDataTmp[$id]['vendor_payment_id']);
    
          $updateData[T::$vendorPayment][] = [
            'whereData'   => ['vendor_payment_id'=>$tmpVendorPaymentId],
            'updateData'  => $updateDataTmp[$id],
          ];
        }
      }
      
      $success += Model::update($updateData);
      $elastic[T::$vendorPaymentView] = ['vp.vendor_payment_id'=>$vendorPaymentId];
      
      if(!empty($positivePayCSV)){
        foreach($positivePayCSV as $bankName=>$csv){
          PositivePay::putDataSftp($bankName, $csv);
        }
      }
      
      ##### START TO MERGE PEF INTO ONE FILE #####
      $filename = explode('@', $usid)[0] . $lastVendorPaymentId . '.pdf';
      $response = PDFMerge::mergeFiles([
        'href'     => 'storage/check/' . $filename,
        'fileName' => 'public/check/'. $filename,
        'files'    => $pdfCheck,
        'paths'    => $pdfCheck,
      ]);
      
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic],
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//  private function _sendEmail($req, $vData, $batchGroup){
//    $name = Helper::getUsidName($req);
//    $usid = Helper::getUsid($req);
//    $msg  = 'Hi Mike:' . Html::br();
//    $msg  .= '"'.$name.'" has requested to approve the checks.' . Html::br();
//    $msg  .= Html::a('Please click here to approve the checks', ['href'=>P::getInstance()->getBatchGroupLink($batchGroup) ]);
//    
////    if(!empty($vData['remark'])){
//      $msg  .= Html::br(2) . Html::h3($name . '\'s Note:' . Html::br() . (!empty($vData['remark']) ? $vData['remark'] : 'None') , ['style'=>'color:red;']);
////    }
//    
//    if(!Mail::send([
//      'to'      =>'mike@pamamgt.com',
//      'cc'      =>$usid,
//      'bcc'     =>'sean@pamamgt.com',
//      'from'    =>'admin@pamamgt.com',
//      'subject' =>'Request For Approval From ' . $name,
//      'msg'     =>$msg
//    ])){
//      Helper::echoJsonError($this->_getErrorMsg('email'), 'popupMsg');
//    }
//  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      '_getCompanyAddress'   => Html::errMsg('There is no company address for  property.'),
      'storeNoApprovedCheck' => Html::errMsg('There is no approved check. Please double check it again.'),
      'email'                => Html::errMsg('There some issues with requesting for approval. Please report this to sean.hayes@dataworker.com.'),
      'noBank'               => Html::errMsg('There is no bank '. Helper::getValue('bank', $vData) .' for trust ' . Helper::getValue('trust', $vData)),
      'noPositivePay'        => Html::errMsg('There is no positive pay for bank ' . Helper::getValue('bank',$vData)),
    ];
    return $data[$name];
  }  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' =>Html::sucMsg('Successfully Send The Request For Approval To Upper Management.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    $data = [
      'store'=>[
        'field'=>[
        ],
        'rule'=>[
          'vendor_payment_id'=>'required|integer',
          'printBy'=>'required|string',
        ]
      ],
    ];
    return $data[$fn];
  }  
//------------------------------------------------------------------------------
  private function _updateBankCheckNumber($allCheck){
    $updateData  = $bankId = $checkReserveNum = $checkNoData = [];
    
     // Start counting the check
    foreach($allCheck as $userGroup=>$val){
      $currentVal = current($val);
      $id = $currentVal[0]['trust'] . '-' . $currentVal[0]['bank'];
      $num = count($val);
      $checkReserveNum[$id] = isset($checkReserveNum[$id]) ? $checkReserveNum[$id] + $num : $num;
    }
    
    foreach($checkReserveNum as $trustBank=>$totalCheck){
      list($trust, $bank) = explode('-', $trustBank);
      $r = Helper::keyFieldNameElastic(M::getBank(['trust.keyword'=>$trust, 'bank'=>$bank], ['bank_id', 'bank', 'last_check_no'], ''), 'bank');
      if(empty($r)){
        Helper::echoJsonError($this->_getErrorMsg('noBank', ['trust'=>$trust, 'bank'=>$bank]), 'popupMsg');
      }
      
      $checkNoData[$trustBank] = $r[$bank]['last_check_no'];
      $updateData[T::$bank][] = [
        'whereData'=>['prop'=>$trust, 'bank'=>$bank], 
        'updateData'=>['last_check_no'=>($r[$bank]['last_check_no'] + $totalCheck)]
      ];
      $bankId[] = $r[$bank]['bank_id'];
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    try{
      $success = Model::update($updateData);
      $elastic = [T::$bankView =>['b.bank_id'=>$bankId]];
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic],
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $checkNoData;
  }
//------------------------------------------------------------------------------
    private function _getCheck($trans, $rCompany, $rProp, $rVendor, $checkNo, $vData, $eachCheck, $req){
      $name    = Helper::getUsidName($req);
      $detail  = ['balance'=>0, 'vendid'=>'', 'vendor_name'=>''];
      ##### TAKE CARE OF THE TOP PART WHICH IS TABLE #####
      $styleHeader = ['style'=>'text-decoration:underline;border-right: 1px solid #000;'];
      $borderRight = ['style'=>'border-right: 1px solid #000;', 'valign'=>'top'];
//        'test'    =>['val'=>'<img src="/pub/img/clear.png" width="1" height="530">',    'param'=>$styleHeader], 
      $colData = ['date'=>'', 'invoice'=>'', 'remark'=>'', 'prop'=>'', 'gl_acct'=>'', 'amount'=>''];
      
      foreach($trans as $v){
        $colData['date']    .= Format::usDate($v['invoice_date']) . Html::br();
        $colData['invoice'] .= $v['invoice'] . Html::br(); 
        $colData['remark']  .= title_case($v['remark'])  . Html::br();
        $colData['prop']    .= $v['prop']  . Html::br(); 
        $colData['gl_acct'] .= $v['gl_acct']  . Html::br(); 
        $colData['amount']  .= Format::usMoney($v['amount']) . Html::br(); 
              
        $detail['is_with_signature']  = $v['is_with_signature'];
        $detail['vendor_payment_id']  = $v['vendor_payment_id'];
        $detail['balance']           += $v['amount'];
        $detail                      += Helper::selectData(['prop','unit','tenant','gl_acct','invoice'],$v);
        $detail['vendid']             = $v['vendid'];
        $detail['vendor_id']          = $v['vendor_id'];
        $detail['trust']              = $v['trust'];
        $detail['bank']               = !empty($v['bank']) ? $v['bank'] : 0;
        $detail['remark']             = $v['remark'];
        //$detail['noLimit']   = $v['noLimit'];
        $detail['noLimit']            = !empty($v['noLimit']) ? $v['noLimit'] : 0;
      }
      
      
//              $colData['date']    .=> Format::usDate($v['invoice_date']) ., 'param'=>$borderRight], 
//        $colData['invoice'] .=> $v['invoice'], 'param'=>$borderRight], 
//        $colData['remark']  .=> title_case($v['remark']), 'param'=>$borderRight], 
//        $colData['prop']    .=> $v['prop'], 'param'=>$borderRight], 
//        $colData['gl_acct'] .=> $v['gl_acct'], 'param'=>$borderRight], 
//        $colData['amount']  .=> Format::usMoney($v['amount']), 'param'=>['align'=>'right']], 
      
      $tableData = [[
        'date'    =>['val'=>'Date',    'param'=>$styleHeader + ['width'=>'50']], 
        'invoice' =>['val'=>'Invoice', 'param'=>$styleHeader + ['width'=>'95']], 
        'remark'  =>['val'=>'Remark',  'param'=>$styleHeader + ['width'=>'260']], 
        'prop'    =>['val'=>'Prop',    'param'=>$styleHeader + ['width'=>'42']], 
        'gl_acct' =>['val'=>'Gl Acct', 'param'=>$styleHeader + ['width'=>'45']], 
        'amount'  =>['val'=>'Amount',  'param'=>$styleHeader + ['width'=>'70', 'align'=>'right']], 
      ],[
        'date'    =>['val'=>rtrim($colData['date'], Html::br()), 'param'=>$borderRight + ['height'=>'430']], 
        'invoice' =>['val'=>rtrim($colData['invoice'], Html::br()), 'param'=>$borderRight], 
        'remark'  =>['val'=>rtrim($colData['remark'], Html::br()), 'param'=>$borderRight], 
        'prop'    =>['val'=>rtrim($colData['prop'], Html::br()), 'param'=>$borderRight], 
        'gl_acct' =>['val'=>rtrim($colData['gl_acct'], Html::br()), 'param'=>$borderRight], 
        'amount'  =>['val'=>rtrim($colData['amount'], Html::br()), 'param'=>['align'=>'right']], 
      ],[
        'date'    =>['val'=>'', 'param'=>$borderRight], 
        'invoice' =>['val'=>'', 'param'=>$borderRight], 
        'remark'  =>['val'=>'Vendor: ' . $detail['vendid'] . ' - '. title_case($rVendor[$detail['vendor_id']]['name']), 'param'=>$borderRight], 
        'prop'    =>['val'=>'', 'param'=>$borderRight], 
        'gl_acct' =>['val'=>'Total:', 'param'=>$borderRight], 
        'amount'  =>['val'=>Format::usMoney($detail['balance']), 'param'=>['align'=>'right']], 
      ]];
      
      $html = Html::div($detail['trust'] . ' - ' . $rProp[$detail['trust']]['entity_name'] . ' | Check Date: '. Format::usDate($vData['posted_date']) . ' | Print By: ' . $name . ' | Check No: ' . $checkNo);
      $html .= Html::buildTable(['data'=>$tableData, 'isHeader'=>0,'isOrderList'=>0, 'tableParam'=>['border'=>'0', 'width'=>'100%', 'cellpadding'=>'4', 'style'=>'border: 1px solid black;']]);
      $html .= Html::br(5); 
      
      ##### TAKE CARE OF THE CHECK TEMPLATE #####
      $rBank  = Helper::keyFieldName($rProp[$detail['trust']]['bank'],'bank');
      $detail['bankDetail'] = $rBank[$detail['bank']];
      $detail['bankName']   = $rBank[$detail['bank']]['name'];
      $detail['check_no']    = $checkNo;
      $detail['isIssue']    = 1;
      $detail['posted_date']= $vData['posted_date'];
      $detail['vendor_name']= $rVendor[$detail['vendor_id']]['name'];
      
      $tableData = [
        [
          'col1' =>['val'=>$this->_getCompanyAddress($rCompany, $rProp, $detail), 'param'=>['valign'=>'top', 'width'=>180]], 
          'col2' =>['val'=>$this->_getBankAddress($detail), 'param'=>['valign'=>'top', 'width'=>160]], 
          'col3' =>['val'=>'Check No: ' . $checkNo,  'param'=>['valign'=>'top', 'align'=>'right', 'width'=>200]], 
        ],
        [ 
          'col1' =>['val'=>'Memo: ' . Html::u($detail['remark']) . Html::br(), 'param'=>['colspan'=>3]], 
        ], 
        [ 
          'col1' =>['val'=>NumberToWord::convert($detail['balance']), 'param'=>['colspan'=>2]], 
          'col3' =>['val'=>Html::repeatChar('&nbsp;', 27) . $this->_getBoxDateAmount($detail, $vData)], 
        ], 
        [ 
          'col1' =>['val'=>$this->_getToTheOrder($detail, $rVendor), 'param'=>['colspan'=>2, 'valign'=>'top']], 
          'col3' =>['val'=>$this->_getSignature($detail),  'param'=>['align'=>'right', 'valign'=>'bottom']], 
        ], 
      ];
      $html .= Html::buildTable(['data'=>$tableData, 'isHeader'=>0,'isOrderList'=>0, 'tableParam'=>['border'=>'0', 'width'=>'100%']]);
      $html .= $this->_getRoutingNum($detail, $checkNo);
      $detail['check']     = $this->_getPdf($html, $eachCheck, $req);
      $detail['checkCopy'] = $this->_getPdf($html, $eachCheck, $req, $this->_getWatermark());
      return $detail;
  }
//------------------------------------------------------------------------------
  private function _getCompanyAddress($rCompany, $rProp, $detail){
    if(!isset($rCompany[$rProp[$detail['trust']]['mangtgroup']])){
      Helper::echoJsonError($this->_getErrorMsg('_getCompanyAddress', $rProp[$detail['trust']]), 'popupMsg');
    }
    $company = $rCompany[$rProp[$detail['trust']]['mangtgroup']];
    return 
      $company['company_name'] . Html::br() . 
      $company['mailing_street'] . Html::br() . 
      $company['mailing_city'] . ', '. $company['mailing_state'] . ' ' . $company['mailing_zip'] . Html::br() . 
      $company['phone'];
  
  }
//------------------------------------------------------------------------------
  private function _getBankAddress($detail){
    $bankDetail = $detail['bankDetail'];
    return 
      $bankDetail['name'] . Html::br() . 
      $bankDetail['br_name'] . Html::br() . 
      $bankDetail['street'] . Html::br() . 
      $bankDetail['city'] . ', ' . $bankDetail['state'] . ' ' . $bankDetail['zip'];
  }
//------------------------------------------------------------------------------
  private function _getToTheOrder($detail, $rVendor){
    $vendor = $rVendor[$detail['vendor_id']];
    $name   = !empty($vendor['name']) ? $vendor['name'] : $vendor['vendid'];
    return 
      'To the ' . Html::repeatChar('&nbsp;', 10) . $name . Html::br() .
      'order of: '. Html::repeatChar('&nbsp;', 6) . $vendor['line2'] . Html::br() . 
      Html::repeatChar('&nbsp;', 21) . $name . Html::br() . 
      Html::repeatChar('&nbsp;', 21) . $vendor['street'] . Html::br() .
      Html::repeatChar('&nbsp;', 21) . $vendor['city'] . ', ' . $vendor['state'] . ' ' . $vendor['zip'] . Html::br() .
      'VOID UNLESS PRESENTED WITHIN 180 DAYS FROM DATE OF ISSUE.' . Html::br();
  }
//------------------------------------------------------------------------------
  private function _getBoxDateAmount($detail, $vData){
    $alignLeft = ['align'=>'left'];
    $alignRight = ['align'=>'right'];
    return Html::buildTable(['data'=>[
        [ 
          'col1' =>['val'=>'Date', 'param'=>['width'=>55] + $alignLeft], 
          'col2' =>['val'=>'Amount', 'param'=>['width'=>80] + $alignRight], 
        ], 
        [ 
          'col1' =>['val'=>Format::usDate($vData['posted_date']), 'param'=>$alignLeft], 
          'col2' =>['val'=>Format::usMoney($detail['balance']), 'param'=>$alignRight], 
        ], 
    ], 'isHeader'=>0, 'isOrderList'=>0, 'tableParam'=>['border'=>'1', 'cellpadding'=>'4']
    ]);
  }
//------------------------------------------------------------------------------
  private function _getSignature($detail){
    $this->_maxAmountNoSignature = ($detail['noLimit'] == 'yes') ? 50000000 : $this->_maxAmountNoSignature;
    $signature = ($detail['balance'] < $this->_maxAmountNoSignature && $detail['is_with_signature']) ? 'signature.jpg' : 'signature_line_only.jpg';
    return Html::br(2) . Html::img(['src'=>'../storage/app/private/signatures/' . $signature, 'width'=>'200', 'height'=>'56']);
  }
//------------------------------------------------------------------------------
  private function _getWatermark(){
    return ['x'=>20,'y'=>190, 'imageFile'=>'../storage/app/private/signatures/copy.png'];
  }
//------------------------------------------------------------------------------
  private function _getRoutingNum($detail, $checkNo){
    $num  = 'c' . $checkNo . 'c ';
    $num .= 'a' . $detail['bankDetail']['transit_cp'] . 'a';
    $num .=  $detail['bankDetail']['cp_acct'];
    return Html::div($num, ['style'=>'font-family:MICR Encoding;font-size:18px;text-align:center;line-height:40px;']);
  }
//------------------------------------------------------------------------------
//  private function _validateTrustBank($rProp){
//    dd($rProp);
//    foreach($r as $v){
//      
//    }
//  }
//------------------------------------------------------------------------------
  public function getCheckUniqueId($v){ // USED IN VoidCheckController
    return $v['check_no'] . $v['prop'] . $v['bank'] . (isset($v['vendid']) ? $v['vendid'] : $v['vendor']);
  }
//------------------------------------------------------------------------------
  private function _getPdf($html, $eachCheck, $req, $watermark = []){
    if(!empty($watermark)){
      $data = [
        'path'=>File::getLocation('PrintCheck')['checkCopy'], 
        'file'=>$req['ACCOUNT']['firstname'] . $req['ACCOUNT']['lastname'] . date('Y-m-d-H-i-s') . $eachCheck . '.pdf'
      ];
    } else{
      $data = [
        'path'=>File::mkdirNotExist($this->_checkPath),
        'file'=>$eachCheck . '.pdf'
      ];
    }
    
    $font        = 'times';
    $size        = '9';
    $title       = 'Check Print';
    $orientation = 'P';
    
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
      PDF::writeHTML($html,true,false,true,false,$orientation);
      if(!empty($watermark)){
        PDF::SetAlpha(0.5);
        PDF::Image($watermark['imageFile'], $watermark['x'], $watermark['y'], 0, 0, '', '', '', true, 72);
        PDF::SetAlpha(1);
      } 
      PDF::Output($data['path'].$data['file'], 'F');
      return $data;
    } catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
}