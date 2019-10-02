<?php
namespace App\Http\Controllers\AccountPayable\VoidCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Account, TableName AS T, Helper, HelperMysql, Format, Mail, PositivePay};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\VoidCheckModel AS M; // Include the models class
use \App\Http\Controllers\AccountPayable\Approval\PrintCheck\PrintCheckController AS PrintCheck;

class VoidCheckController extends Controller{
  private $_viewPath  = 'app/AccountPayable/VoidCheck/';
  private $_usr       = '';

  public function index(Request $req){
    $page = $this->_viewPath . 'index';
    $today = date("m/d/Y");
    $fields = [
      'check_no'   => ['id'=>'check_no','label'=>'From Check No', 'type'=>'text', 'value'=>'000001'],
      'tocheck_no' => ['id'=>'tocheck_no','class'=>'copyTo', 'label'=>'To Check No', 'type'=>'text', 'value'=>'999999'],
      'batch'      => ['id'=>'batch','label'=>'From Batch', 'type'=>'text'],
      'tobatch'    => ['id'=>'tobatch','class'=>'copyTo', 'label'=>'To Batch', 'type'=>'text'],
      'vendid'     => ['id'=>'vendid','label'=>'From Vendor', 'type'=>'text', 'class'=>'autocomplete', 'autocomplete'=>'false', 'hint'=>'You can type vendor name or number for autocomplete'],
      'tovendid'   => ['id'=>'tovendid','class'=>'copyTo autocomplete', 'label'=>'To Vendor', 'type'=>'text', 'autocomplete'=>'false', 'hint'=>'You can type vendor name or number for autocomplete'],
      'prop'       => ['id'=>'prop','label'=>'Prop','type'=>'textarea','placeHolder'=>'0001-9999'],
      'trust'      => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, *ZA67'],
      'date1'      => ['id'=>'date1','label'=>'Check Date', 'class'=>'date','type'=>'text', 'value'=>$today, 'req'=>1],
      'void_date'  => ['id'=>'void_date','label'=>'Void Check Date', 'class'=>'date','type'=>'text', 'value'=>$today, 'req'=>1],
      'remark'     => ['id'=>'remark','label'=>'Void Check Remark','type'=>'textarea','value'=>'Void Check* Dated ', 'req'=>1],  
      '900date'    => ['id'=>'900date', 'label'=>'Post to 900 if check date is before', 'class'=>'date', 'type'=>'text', 'value'=>date("1/1/Y"), 'req'=>1]
    ];
    return view($page, [
      'data'=>[
        'nav'    => $req['NAV'],
        'account'=> Account::getHtmlInfo($req['ACCOUNT']),
        'form'   => implode('',Form::generateField($fields)),
      ]
    ]); 
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
    ]);
    $vData  = $valid['data'];
    $vData['trust'] = Helper::explodeField($vData,['prop','trust'])['prop'];
    $initData = $this->_getColumnButtonReportList($req);
    $rAllTrans = $this->_getAllTransaction($vData);
    return [
      'rows'    =>$this->_getGridData($rAllTrans), 
      'total'   =>count($rAllTrans),
      'gridInfo'=>$initData,
      'toolbar' =>!empty($rAllTrans) ? Html::repeatChar('&nbsp', 5) . Html::button(Html::i('',['class'=>'fa fa-fw fa-remove']) . ' Void Check',['id'=>'delete','class'=>'btn btn-danger']) : ''
    ];
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
      'includeUsid' =>1,
    ]);
    $vData          = $valid['data'];
    $batch          = HelperMysql::getBatchNumber();

    $this->_usr     = $vData['usid'];
    $total          = $vData['total'];
    $vData['trust'] = Helper::explodeField($vData,['trust'])['prop'];
    $rAllTrans      = $this->_getAllTransaction($vData);
    $positivePayData= $insertDataTrackVoidCheck = [];
    
    if(count($rAllTrans) == $total){
      $updateData = $seq = [];
      foreach($rAllTrans as $i=>$v){
        $seq[]   = $v['seq'];
        $v['amount'] = $v['amount'] * -1;
        $v['remark'] = $v['remark'];
        $v['batch']  = $batch;
        $v['date1']  = Format::mysqlDate($vData['void_date']);
        
        $insertDataTrackVoidCheck[T::$trackVoidCheck][] = ['seq' => $v['seq']];
        $dataset[T::$glTrans][] = $v;
        
        $id = PrintCheck::getInstance()->getCheckUniqueId($v);
        $rBank = HelperMysql::getBank(['prop.keyword'=>$v['prop'], 'bank.keyword'=>$v['bank']]);
        $rVendor = HelperMysql::getVendor(['vendid.keyword'=>$v['vendor']]);
        
        if(empty($rBank)){
          Helper::echoJsonError($this->_getErrorMsg('destroyNoBank', $v), 'popupMsg');
        } else if(empty($rVendor)){
          Helper::echoJsonError($str, 'popupMsg');
        }
        
        $v['bankDetail']  = $rBank;
        $v['balance']     = $v['amount'];
        $v['posted_date'] = $v['date1'];
        $v['vendor_name'] = $rVendor['name'];
        $v['isIssue']     = 1;
        $positivePayData[$id . '-' . $rBank['name']][] = $v;
      }
      $dataset[T::$batchRaw] = $dataset[T::$glTrans];
      $dataset['summaryAcctPayable'] = [];
      
      $updateData[T::$vendorPayment] = [
        'whereInData'=>['field'=>'seq', 'data'=>$seq], 
        'updateData'=>['void' => 1]
      ];
      $insertData = HelperMysql::getDataset($dataset, $this->_usr);
      
      ##### DEAL WITH POSITIVE PAY #####
      foreach($positivePayData as $idBankName=>$val){
        list($id, $bankName) = explode('-', $idBankName);
        $positivePayCSV[$bankName] = PositivePay::getFormatedData($bankName, $val);
      }
      
      ############### DATABASE SECTION ######################
      DB::beginTransaction();
      $success = $response = $elastic = [];
      try{
        $success += Model::insert($insertData);
        $success += Model::update($updateData);
        
        
        foreach($success['insert:' . T::$glTrans] as $v){
          $insertDataTrackVoidCheck[T::$trackVoidCheck][] = ['seq' => $v];
        }
        $success += Model::insert($insertDataTrackVoidCheck);
        
        $elastic = [
          'insert'=>[
            T::$vendorPaymentView => ['vp.seq' => $seq],  
            T::$glTransView       => ['seq' => $success['insert:'. T::$glTrans]],
          ]
        ];
        
        if(!empty($positivePayCSV)){
          foreach($positivePayCSV as $bankName=>$csv){
            PositivePay::putDataSftp($bankName, $csv);
          }
        }
        dd('done');
        
        Model::commit([
          'success' =>$success,
          'elastic' =>$elastic,
        ]);
        $this->_getEmail($vData, $rAllTrans);
        $response = [
          'sideMsg'=>$this->_getSuccessMsg(__FUNCTION__),
          'success'=>1
        ];
      } catch(\Exception $e){
        $response['error']['mainMsg'] = Model::rollback($e);
      }
      return $response;
    } else{
      return ['sideMsg'=>$this->_getErrorMsg(__FUNCTION__)];
    }
  }
//------------------------------------------------------------------------------

################################################################################
##########################   GRID FUNCTION   ###################################  
################################################################################ 
  private function _getGridData($rAllTrans){
    $rows = [];
    foreach($rAllTrans as $i=>$v){
      $v['num']      = $i + 1;
      $v['amount']   = Format::usMoney($v['amount']);
      $v['date1']    = Format::usDate($v['date1']);
      $v['sys_date'] = Format::usDate($v['sys_date']);
      $rows[] = $v;
    }
    return $rows;
  }
//------------------------------------------------------------------------------  
  private function _getColumnButtonReportList($req = []){
    $columns = [
      ['field'=>'num','title'=>'#','width'=>25],
//      ['field'=>'type','title'=>'Transaction Type','sortable'=>true,'width'=>150],
      ['field'=>'date1','title'=>'Date','sortable'=>true,'width'=>100],
      ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>50],
      ['field'=>'batch','title'=>'Batch','sortable'=>true,'width'=>50],
      ['field'=>'unit','title'=>'Unit','sortable'=>true,'width'=>50],
      ['field'=>'bank','title'=>'Bank','sortable'=> true, 'width'=>50],
      ['field'=>'tx_code','title'=>'Desc','sortable'=> true, 'width'=>100],
      ['field'=>'gl_acct','title'=>'Gl Acct','sortable'=> true, 'width'=>50],
      ['field'=>'check_no','title'=>'Check No.','sortable'=>true,'width'=>75],
      ['field'=>'remark','title'=>'Remark','sortable'=> true, 'width'=>250],
      ['field'=>'amount','title'=>'Amount','sortable'=> true,'width'=>100],
      ['field'=>'usid','title'=>'Usid','sortable'=> true, 'width'=>100],
      ['field'=>'sys_date','title'=>'System Date','sortable'=> true],
    ];
    return ['columns'=>$columns];
  }
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'check_no'   => 'nullable|string|between:6,6',
      'tocheck_no' => 'nullable|string|between:6,6',
      'batch'      => 'nullable|string',
      'tobatch'    => 'nullable|string',
      'vendid'     => 'nullable|string',
      'tovendid'   => 'nullable|string',
      'prop'       => 'nullable|string',
      'trust'      => 'nullable|string', 
      'date1'      => 'required|string|between:10,10',
      'void_date'  => 'required|string|between:10,10',
      'remark'     => 'required|string', 
      '900date'    => 'required|string|between:10,10',
      'total'      => 'required|integer|between:1,20548', 
    ]; 
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData){
    $data = [
      'destroy' => Html::errMsg('There is some issues while trying to void the check.'),
      'destroyNoBank' => Html::errMsg('There is no bank for this property.'),
      'destroyNoVendor' => Html::errMsg('There is some issues while trying to void the check.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------  
  private function _getSuccessMsg($name){
    $data = [
      'destroy' => Html::sucMsg('Successfully voided the check'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getAllTransaction($vData){
    $allTrans = ['allTrans'=>[], 'seq'=>[]];
    $field = ['date1', 'batch', 'prop', 'unit', 'tenant', 'bank', 'tx_code', 'gl_acct', 'check_no', 'vendor', 'remark', 'amount', 'usid', 'sys_date', 'seq', 'service_code'];
    $must = [];
    $must['raw']['must'][]['term']['date1'] = Format::mysqlDate($vData['date1']);
    
    if(!empty($vData['trust']) || !empty($vData['prop'])) {
      $must['raw']['must'][]['terms']['prop.keyword'] = $vData['trust'];
    }
    if(!empty($vData['check_no']) && !empty($vData['tocheck_no'])) {
      $must['raw']['must'][]['range']['check_no'] = [
        'gte' => $vData['check_no'],
        'lte' => $vData['tocheck_no']
      ];
    }
    if(!empty($vData['batch']) && !empty($vData['tobatch'])) {
      $must['raw']['must'][]['range']['batch'] = [
        'gte' => $vData['batch'],
        'lte' => $vData['tobatch']
      ];
    }
    if(!empty($vData['vendid']) && !empty($vData['tovendid'])) {
      $must['raw']['must'][]['range']['vendor'] = [
        'gte' => strtolower($vData['vendid']),
        'lte' => strtolower($vData['tovendid'])
      ];
    }
    $must['raw']['must_not'][]['terms']['remark.keyword'] = ['Summary'];
    
    
    $rGlTrans = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'   => T::$glTransView,
      '_source' => $field,
      'sort'    => ['date1'=>'DESC', 'prop.keyword'=>'ASC', 'gl_acct.keyword'=>'DESC'],
      'query'   => $must
    ]));  

    foreach($rGlTrans as $v){
      $v['type'] = Html::span('Gl Transaction',['class'=>'text-warning']);
      $v['usid'] = isset($vData['usid']) ? $vData['usid'] : $v['usid'];
      $allTrans['allTrans'][] = $v; 
      $allTrans['seq'][] = $v['seq'];
    }
    ## Retrieve all the gl_trans that were voided and remove it from the allTrans
    $rTrackVoidCheck = M::getTrackVoidCheck($allTrans['seq'], ['seq']);
    $removeVoidedTrans = function($a, $b){
      return $a['seq'] - $b['seq'];
    }; 
    $rGlTrans = count($rGlTrans) > 1 ? array_udiff($allTrans['allTrans'], $rTrackVoidCheck, $removeVoidedTrans) : $rGlTrans;
    $rGlTrans = array_values($rGlTrans);
    return $rGlTrans;
  }
//------------------------------------------------------------------------------
  private function _getEmail($vData, $rAllTrans){
    $tableData = [];
    $rProp = Helper::keyFieldNameElastic(HelperMysql::getProp(array_column($rAllTrans, 'prop'), ['prop', 'trust']), 'prop');
    foreach($rAllTrans as $v){
      $tableData[] = [
        'date'    =>['val' => $v['date1'], 'header'=>['val'=>'Date']], 
        'trust'   =>['val' => $rProp[$v['prop']]['trust'], 'header'=>['val'=>'Date']], 
        'prop'    =>['val' => $v['prop'], 'header'=>['val'=>'Prop']], 
        'check_no'=>['val' => $v['check_no'], 'header'=>['val'=>'Check No']], 
        'usid'    =>['val' => $v['usid'], 'header'=>['val'=>'Usid']]
      ];
    }
    $msg = Html::buildTable(['data'=>$tableData, 'isAlterColor'=>1, 'tableParam'=>['border'=>1, 'cellpadding'=>'10']]);
    Mail::send([
      'to'      =>'ryan@pamamgt.com,sean@pamamgt.com,cindy@pamamgt.com,kary@pamamgt.com',
      'from'    =>'admin@pamamgt.com',
      'subject' =>$vData['total'] . ' Checks Voided by ' . $this->_usr,
      'msg'     =>$msg
    ]);
  } 
}