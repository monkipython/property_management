<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\DepositUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Elastic, Html, TableName AS T, Helper, HelperMysql, TenantTrans, Format};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use \App\Http\Controllers\AccountReceivable\CashRec\DepositCheck\DepositCheckController;
use App\Http\Models\Model; // Include the models class

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `active`) VALUES ('0', 'Account Receivable', 'fa fa-fw fa-money', 'cashRec', 'Cash Receive', 'fa fa-fw fa-cog', 'Upload', '', 'depositUpload', '', 'To Access Deposit Check/Cash Upload', '1');
 */

class DepositUploadController extends Controller {
  private $_maxLine = 100;
  public function create(Request $req){
    $text   = 'To Use Deposit Check/Cash Upload, Please Drag & Drop a CSV File' . Html::br(2);
    $text  .= 'Deposit Check/Cash Upload function allows users to record single and multiple Invoices.' . Html::br() . 'For the CSV file formatting instructions, please click link below.' . Html::br(3);
    $text  .= Html::span(Html::icon('fa fa-fw  fa-exclamation-triangle') . 'Please Note: This process can take up to 10 minutes.', ['class'=>'text-yellow']) .  Html::br();
    $text  .= Html::span('The max invoice allowed to be post at once time is only ' . $this->_maxLine, ['class'=>'text-yellow']) .  Html::br(3);
    $text  .= Html::span(Html::icon('fa fa-fw fa-file-text-o') . Html::a('Click Here for File Format Instruction',['href'=>'/instruction/Deposit-Check-Cash-Upload-Instruction.docx']),['class'=>'alert alert-info alert-dismissable']);
    return ['html'=>P::getUploadForm(),'isUpload'=>true, 'text'=>Html::h2(Html::br() . $text, ['class'=>'text-center text-muted'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    set_time_limit(600);
    $usid       = Helper::getUsid($req);
    $fileData   = P::getFileUploadContent($req, ['csv']);
    $textList   = $fileData['data'];
    $file       = $fileData['fileInfo']['uploadData']['name'];
    $allList    = $row = [];
    $dataset    = [T::$glTrans=>[], T::$batchRaw=>[], T::$tntSecurityDeposit=>[], 'summaryAcctReceivable'=>[]];
    $glTransDataset = [T::$glTrans=>[], T::$batchRaw=>[], T::$clearedCheck=>[]];
    
    unset($textList[0]);
    foreach($textList as $i=>$val){
      if(!empty($val)){
        $allList[] = $this->_validateAndGetEachEntry($val, $file, $i);
      }
    }
    if(empty($allList)){
      Helper::echoJsonError($this->_getErrorMsg('storeEmptyList'), 'popupMsg');
    } else if(count($allList) > $this->_maxLine){
      Helper::echoJsonError($this->_getErrorMsg('storeMaxLine'), 'popupMsg');
    }
    
    $batch = HelperMysql::getBatchNumber();
    $rProp = Helper::keyFieldNameElastic(HelperMysql::getProp(array_column($allList, 'prop'), ['prop', 'ar_bank', 'group1', 'bank']), 'prop');
    $rPropBank = self::_groupPropBank($rProp);
    
    // PREPARE DATA FOR VALIDATION
    foreach($allList as $i=>$entry){
      if(!isset($rProp[$entry['prop']])){
        Helper::echoJsonError($this->_getErrorMsg('storeNotExist', ['line'=>($i + 2), 'prop'=>$entry['prop']]), 'popupMsg');
      } else if(!empty($entry['ar_bank']) && !isset($rPropBank[$entry['prop'] . $entry['ar_bank']])){
        Helper::echoJsonError($this->_getErrorMsg('storePropBankNotExist', ['line'=>($i + 2)] + $entry), 'popupMsg');
      }
      
      // GET THE DEDEFAULT IF THE BANK IS EMPTY
      if($entry['ar_bank'] == ''){
        $entry['ar_bank'] = $rProp[$entry['prop']]['ar_bank'];
      }
      
      // GET INTO THE RIGHT FORMAT SO THAT THE VALIDATE LIB CAN VALIDATE THE DATA
      foreach($entry as $k=>$v){
        $row[$k][$i] = $v;
      }
    }
    
    // VALIDATE EACH DATA
    $valid = V::startValidate([
      'rawReq'          => $row,
      'tablez'          => $this->_getTable(), 
      'orderField'      => ['date1','amount', 'prop','unit','gl_acct', 'remark', 'check_no', 'ar_bank'], 
      'includeCdate'    => 0,
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'isPopupMsgError' => 1,
      'validateDatabase'=> [
        'mustExist' => [
          T::$prop . '|prop',
          T::$glChart . '|prop,gl_acct',
        ]
      ]
    ]);
    
    $vData = $valid['dataArr'];
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])), 'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>'Z64'])), 'service');

    foreach($vData as $i=>$v){
      if(preg_match('/a-zA-Z/', $v['prop'])){
        $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$v['prop']])), 'gl_acct');
        $service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$v['prop']])), 'service');
      }
      
      $v['usid']     = $usid;
      $v['remark']   = !empty($v['remark']) ? $v['remark'] : $glChart[$v['gl_acct']]['title'];  
      $v['_batch']   = $batch;
      $v['_glChart'] = $glChart;
      $v['_service'] = $service;
      $v['_rProp']   = Helper::selectData(['ar_bank','group1'], $rProp[$v['prop']]);
      $v['_rBank']   = Helper::keyFieldName($rProp[$v['prop']]['bank'], 'bank');
      $v   = DepositCheckController::getInstance()->getStoreData($v);
      $tmp = TenantTrans::getTntSurityDeposit($v);
      
      $dataset[T::$glTrans]  = array_merge($dataset[T::$glTrans], $tmp[T::$glTrans]);
      $dataset[T::$batchRaw] = array_merge($dataset[T::$batchRaw], $tmp[T::$glTrans]);
      
      if(isset($tmp[T::$tntSecurityDeposit])){
        $dataset[T::$tntSecurityDeposit] = array_merge($dataset[T::$tntSecurityDeposit], $tmp[T::$glTrans]);
      }
    }
    if(empty($dataset[T::$tntSecurityDeposit])){
      unset($dataset[T::$tntSecurityDeposit]);
    }
    $insertData = HelperMysql::getDataSet($dataset, $usid, $glChart, $service);
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = $updateData = [];
    try{
      $success += Model::insert($insertData);

      if(!empty($success['insert:'.T::$glTrans])){
        $elastic[T::$glTransView] = ['gl.seq'=>$success['insert:' . T::$glTrans]];
      }
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic]
      ]);
      $response['sideMsg'] = $this->_getSuccessMsg(__FUNCTION__, ['batch'=>$batch]);
      $response['success'] = 1;
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getTable(){
    return [T::$glTrans, T::$glChart, T::$prop];
  }
//------------------------------------------------------------------------------
  private function _getSetting(){
    return [
      'field'=>[
      ],
      'rule'=>[
        'amount' =>'required|numeric|between:0.01,1000000.00',
        'unit'   =>'nullable|string',
        'remark' =>'nullable|string',
      ]
    ];
  }
//------------------------------------------------------------------------------  
  private function _getSuccessMsg($name, $vData = []){
    $data = [
      'store'  =>Html::sucMsg('Successfully Deposit Check/Cash with batch #: '. Helper::getValue('batch', $vData)),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------  
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeEmptyList'=>Html::errMsg('The file is empty. Please double check your file content again.'),
      'storeMaxLine'=>Html::errMsg('You cannot upload more than ' . $this->_maxLine . ' transactions per upload'),
      'storePropBankNotExist'=>Html::errMsg('Prop ' . Helper::getValue('prop', $vData) . ' and Bank ' . Helper::getValue('ar_bank', $vData) . ' does not exist at line '. Helper::getValue('line', $vData) . '. Please double check.'),
      'storeNotExist'=>Html::errMsg('The property number "'.Helper::getValue('prop', $vData) .'" is not exist at line '.Helper::getValue('line', $vData).'.'),
      '_validateAndGetEachEntry'=>Html::errMsg('Your file format is not correct at line '.Helper::getValue('line', $vData).'. This is Invoice Upload payment. Please double check it and try it again.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _validateAndGetEachEntry($v, $file, $i){
    $entry = explode(',', trim($v));
    if(count($entry ) == 8){
      return [
        'date1'    => $entry[0], 
        'amount'   => $entry[1], 
        'prop'     => sprintf("%04s", $entry[2]), 
        'unit'     => sprintf("%04s", $entry[3]), 
        'gl_acct'  => $entry[4], 
        'remark'   => trim($entry[5]), 
        'check_no' => !empty($entry[6]) ? sprintf("%06s", $entry[6]) : '000000', 
        'ar_bank'  => $entry[7], 
      ];
    } else{
      Helper::echoJsonError($this->_getErrorMsg(__FUNCTION__, ['line'=>$i+1]), 'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  private function _groupPropBank($rProp){
    $data = [];
    foreach($rProp as $val){
      foreach($val['bank'] as $v){
        $data[$val['prop'] . $v['bank']] = $v['bank'];
      }
    }
    return $data;
  }
}