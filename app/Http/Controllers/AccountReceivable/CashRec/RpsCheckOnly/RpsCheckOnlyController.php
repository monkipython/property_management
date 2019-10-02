<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\RpsCheckOnly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Upload, TableName AS T, Helper, HelperMysql, TenantTrans, Format};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use App\Http\Models\Model; // Include the models class
use Storage;

class RpsCheckOnlyController extends Controller{
  private $_rProp;
//------------------------------------------------------------------------------
  public function create(Request $req){
    $text = 'To Record Scanned Check<br> Please Click the Upload File Button or Drag & Drop the RPS .TXT File on the Left' . Html::br(2);
    $text .= 'The RPS Upload Check Only function allows users to upload RPS .TXT files containing scanned money. Property and GL numbers are entered via RPS. Remarks will be entered by the end users.'  . Html::br(3);
    $text .= Html::span(Html::icon('fa fa-fw fa-file-text-o') . 'All the Files located at T:\PpmNet\RPS_PPM', ['class'=>'alert alert-info alert-dismissible']);
    return ['html'=>P::getUploadForm(), 'isUpload'=>true, 'text'=>Html::h2(Html::br() . $text, ['class'=>'text-center text-muted'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    return (isset($req['route']) && $req['route'] == 'store') ? $this->_store($req) : $this->_show($req);
  }
//------------------------------------------------------------------------------
  public function destroy($id){
    // ALWAYS SET THIS TO 1 BECAUSE WE DON'T WANT TO DO ANYTHING, BUT JUST ALLOW FRONT END TO CLEAR WHEN THEY CLICK DELETE
    return ['success'=>1];
  }
################################################################################
##########################   ROUTE  SECTION    #################################  
################################################################################  
  private function _show($req){
    $fileData   = P::getFileUploadContent($req);
    $textList   = $fileData['data'];
    $file       = $fileData['fileInfo']['uploadData']['name'];
    unset($textList[0]); // The first Element is just the header
    $row = $prop = [];
    
    foreach($textList as $i=>$v){
      if(!empty($v)){
        $entry  = $this->_validateAndGetEachEntry($v, $i, $file);
        $row[]  = $entry; 
        $prop[] = $entry['prop'];
      }
    }
    $this->_rProp = Helper::keyFieldNameElastic(HelperMysql::getProp($prop, ['prop', 'ar_bank'], 0), 'prop');
    return !empty($row) ? ['success'=>1, 'html'=>$this->_getTableHtml($row, $fileData['fileInfo'])] : Helper::echoJsonError($this->_getErrorMsg('_validateAndGetEachEntry'), 'popupMsg');;
  }
//------------------------------------------------------------------------------
  private function _store($req){
    $dataset = [T::$glTrans=>[], T::$batchRaw=>[], 'summaryAcctReceivable'=>[]];
    $usid = Helper::getUsid($req);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'setting'         => $this->_getSetting('store'),
      'orderField'      => ['prop','check_no','bk_acct','batch','gl_acct','amount','trust','job', 'remark', 'bank', 'file'], 
      'includeCdate'    => 0,
      'validateDatabase'=> [
        'mustExist' => [
          T::$glChart . '|prop,gl_acct',
        ]
      ]
    ]);
    $vData = $valid['dataArr'];
    $file = $valid['data']['file'];
    foreach($vData as $v){
      $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$v['prop']])), 'gl_acct');
      $service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$v['prop']])), 'service');
      
      $v['unit']    = $v['tenant'] = '';
      $v['tx_code'] = 'P';
      $v['amount']  = $v['amount'] * -1;
      $v['inv_remark']   = $v['remarks'] = $v['remark'];
      $v['service_code'] = isset($glChart[$v['gl_acct']]) ? $glChart[$v['gl_acct']]['service'] : '';
      $dataset[T::$glTrans][] = $v;
    }
    
    $dataset[T::$batchRaw] = $dataset[T::$glTrans];
    $insertData = HelperMysql::getDataSet($dataset, $usid, $glChart, $service);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert($insertData);
      $elastic = [T::$glTransView=>['seq'=>$success['insert:' . T::$glTrans]]];
      $response['file']    = preg_replace('/\.TXT/i', '.TXT', $file);
      $response['sideMsg'] = $this->_getSuccessMsg('store');
      $response['file']    = P::renameRPSFileToDone($file);
      
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic]
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _getTable($fn){
    return [T::$glTrans, T::$propBank];
  }  
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      '_validateAndGetEachEntryLine' =>Html::errMsg('Your file format is not correct. Please double check at line number '.Helper::getValue('line', $vData).' and try it again.'),
      '_validateAndGetEachEntry' =>Html::errMsg('Your file format is not correct. This is Upload for RPS Upload Check Only. Please double check it and try it again.'),
      'destroy' =>Html::errMsg('There is some issues while trying to delete the batch.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------  
  private function _getSuccessMsg($name, $vData = []){
    $data = [
      'store'  =>Html::sucMsg('Successfully Post the Transacitons.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSetting($name){
    $data = [
      'store'=>[
        'field'=>[],
        'rule'=>[
          'file' =>'required|string|between:5,255',
        ]
      ],
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _validateAndGetEachEntry($v, $line, $file){
    $entry = explode(',', trim($v));
    if(count($entry ) == 6 && preg_match('/^CKOnly/', $file)){
      $data = [
        'check_no'  =>sprintf("%06s", $entry[1]), 
        'gl_acct'   =>$entry[3], 
        'amount'    =>$entry[4] / 100, 
        'batchInfo' =>$entry[5]
      ];
      $p = explode('-', $entry[2]);
      if(count($p) < 3){
        Helper::echoJsonError($this->_getErrorMsg('_validateAndGetEachEntryLine', ['line'=>$line + 1]), 'popupMsg');
      }
      list($data['trust'], $data['bk_acct'], $data['prop']) = $p;
      $data['date1'] = substr($data['batchInfo'], 4, 2)  . '/' .  substr($data['batchInfo'], 6, 2) . '/' . substr($data['batchInfo'], 0, 4);
      $data['batch'] = substr($data['batchInfo'], 3, 9);
      $data['job']   = substr($data['batchInfo'], -6, 6);
      return $data;
    } else{
      Helper::echoJsonError($this->_getErrorMsg('_validateAndGetEachEntry'), 'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  private function _getTableHtml($row, $file){
    $hiddenField = '';
    $button = Html::repeatChar('&nbsp', 2) . Html::button(Html::i('',['class'=>'fa fa-fw fa-check']) . ' Submit',['id'=>'delete','class'=>'btn btn-success']);
    $tableData = [
      [ 'col1'=>['val'=>$button, 'param'=>['colspan'=>8]]],
      [ 'date1'   => ['val'=>Html::b('Date1')],
        'prop'    => ['val'=>Html::b('Prop')],
        'check_no'=> ['val'=>Html::b('Check No')],
        'bk_acct' => ['val'=>Html::b('Bank Acount')],
        'batch'   => ['val'=>Html::b('Batch')],
        'gl_acct' => ['val'=>Html::b('Gl Acct')],
        'amount'  => ['val'=>Html::b('Amount')],
        'remark'  => ['val'=>Html::b('Remark')],
      ]
    ];
    $_getEachId = function($i, $fl){
      $fl = $fl . '['.$i.']';
      return ['id'=>$fl, 'name'=>$fl];
    };
    
    foreach($row as $i=>$v){
      $tableData[] = [
        'date1'   => ['val'=>$v['date1']],
        'prop'    => ['val'=>$v['prop']],
        'check_no'=> ['val'=>$v['check_no']],
        'bk_acct' => ['val'=>$v['bk_acct']],
        'batch'   => ['val'=>$v['batch']],
        'gl_acct' => ['val'=>$v['gl_acct']],
        'amount'  => ['val'=>Format::usMoney($v['amount'])],
        'remark'  => ['val'=>Html::input('', $_getEachId($i, 'remark'))],
      ];
      
      $hiddenField .= Html::input($v['prop'],    ['type'=>'hidden'] + $_getEachId($i, 'prop'));
      $hiddenField .= Html::input($v['check_no'],['type'=>'hidden'] + $_getEachId($i, 'check_no'));
      $hiddenField .= Html::input($v['bk_acct'], ['type'=>'hidden'] + $_getEachId($i, 'bk_acct'));
      $hiddenField .= Html::input($v['batch'],   ['type'=>'hidden'] + $_getEachId($i, 'batch'));
      $hiddenField .= Html::input($v['gl_acct'], ['type'=>'hidden'] + $_getEachId($i, 'gl_acct'));
      $hiddenField .= Html::input($v['amount'],  ['type'=>'hidden'] + $_getEachId($i, 'amount'));
      $hiddenField .= Html::input($v['trust'],   ['type'=>'hidden'] + $_getEachId($i, 'trust'));
      $hiddenField .= Html::input($v['job'],     ['type'=>'hidden'] + $_getEachId($i, 'job'));
      $hiddenField .= Html::input($this->_rProp[$v['prop']]['ar_bank'], ['type'=>'hidden'] + $_getEachId($i, 'bank'));
    }
    $hiddenField .= Html::input($file['uploadData']['name'],    ['type'=>'hidden', 'name'=>'file']);
    
    return Html::tag('form', $hiddenField . Html::buildTable(['data'=>$tableData, 'isHeader'=>0, 'isOrderList'=>0, 'tableParam'=>['class'=>'table table-bordered table-hover']]), ['id'=>'subApplicationForm']);
  }
}