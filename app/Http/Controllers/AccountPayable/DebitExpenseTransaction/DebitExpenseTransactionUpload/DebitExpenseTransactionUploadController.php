<?php
namespace App\Http\Controllers\AccountPayable\DebitExpenseTransaction\DebitExpenseTransactionUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Html, TableName AS T, Helper, HelperMysql};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use \App\Http\Controllers\AccountPayable\DebitExpenseTransaction\DebitExpenseTransactionController;
use App\Http\Models\Model; // Include the models class

class DebitExpenseTransactionUploadController extends Controller{
  public function create(Request $req){
    $text = 'To Enter Debit / Expense Booking, Please Drag & Drop a CSV File' . Html::br(2);
    $text .= 'Debit / Expense Booking Upload function allows users to record single and multiple transactions.<br>For the CSV file formatting instructions, please click link below.' . Html::br(3);
    $text .= Html::span(Html::icon('fa fa-fw fa-file-text-o') . Html::a('Click Here for File Format Instruction', ['href'=>'/instruction/Debit-Expense-Transaction-Upload-Instruction.docx']), ['class'=>'alert alert-info alert-dismissible']);
    return ['html'=>P::getUploadForm(), 'isUpload'=>true, 'text'=>Html::h2(Html::br() . $text, ['class'=>'text-center text-muted'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $insertData = [T::$glTrans=>[], T::$batchRaw=>[], T::$clearedCheck=>[]];
    $fileData   = P::getFileUploadContent($req, ['csv']);
    $textList   = $fileData['data'];
    $file       = $fileData['fileInfo']['uploadData']['name'];
    $allList    = $row = [];
    unset($textList[0]);
    foreach($textList as $i=>$val){
      if(!empty($val)){
        $allList[] = $this->_validateAndGetEachEntry($val, $file, $i);
      }
    }
    if(empty($allList)){
      Helper::echoJsonError($this->_getErrorMsg('storeEmptyList'), 'popupMsg');
    }
    
    $batch = HelperMysql::getBatchNumber();
    $rProp = Helper::keyFieldNameElastic(HelperMysql::getProp(array_column($allList, 'prop')), 'prop');

    // PREPARE DATA FOR VALIDATION
    foreach($allList as $i=>$entry){
      if(!isset($rProp[$entry['prop']])){
        Helper::echoJsonError($this->_getErrorMsg('storeNotExist', ['line'=>($i + 2), 'prop'=>$entry['prop']]), 'popupMsg');
      }
      // GET INTO THE RIGHT FORMAT SO THAT THE VALIDATE LIB CAN VALIDATE THE DATA
      foreach($entry as $k=>$v){
        $row[$k][$i] = $v;
      }
    }
    // VALIDATE EACH DATA
    $valid = V::startValidate([
      'rawReq'          => $row,
      'tablez'          => [T::$glTrans, T::$prop, T::$vendor], 
      'orderField'      => ['date1','amount','prop', 'unit','gl_acct','remark','vendid','check_no','ar_bank','batch','invoice'], 
      'includeCdate'    => 0,
      'isPopupMsgError' => 1,
      'validateDatabase'=> [
        'mustExist' => [
          T::$prop . '|prop',
          T::$glChart . '|prop,gl_acct'
        ]
      ]
    ]); 
    $vData = $valid['dataArr'];
    $usid  = Helper::getUsid($req);

    foreach($vData as $v){
      $v['batch']   = $batch;
      ##### BUILD THE DATASET FOR INSERT INTO DATABASE #####
      $insertDataTmp = HelperMysql::getDataSet(DebitExpenseTransactionController::getInstance()->getStoreData($v), $usid);
      $insertData[T::$glTrans]      = array_merge($insertData[T::$glTrans], $insertDataTmp[T::$glTrans]);
      $insertData[T::$batchRaw]     = array_merge($insertData[T::$batchRaw], $insertDataTmp[T::$batchRaw]);
      $insertData[T::$clearedCheck] = array_merge($insertData[T::$clearedCheck], $insertDataTmp[T::$clearedCheck]);
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert($insertData);
     
      $elastic = [
        T::$glTransView=>['seq'=>$success['insert:' . T::$glTrans]]
      ];

      $response['sideMsg'] = $this->_getSuccessMsg('store', ['batch'=>$batch]);
      $response['success'] = 1;

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
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeEmptyList' =>Html::errMsg('The file is empty. Please double check your file content again.'),
      'storeNotExist'  =>Html::errMsg('The property number "'.Helper::getValue('prop', $vData) .'" is not exist at line '.Helper::getValue('line', $vData).'.'),
      '_validateAndGetEachEntry' =>Html::errMsg('Your file format is not correct at line '.Helper::getValue('line', $vData).'. This is Upload for Debit Expense Transaction. Please double check it and try it again.'),
      'store'          =>Html::errMsg('The Return No ('. Helper::getValue('application_id', $vData) .') does not exist'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------  
  private function _getSuccessMsg($name, $vData = []){
    $data = [
      'store'  =>Html::sucMsg('Successfully inserted with batch #: '. Helper::getValue('batch', $vData)),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _validateAndGetEachEntry($v, $file, $i){
    $entry = explode(',', trim($v));
    if(count($entry ) == 11 && preg_match('/^debit/i', $file)){
      return [
        'date1'    => $entry[0], 
        'amount'   => $entry[1], 
        'prop'     => sprintf("%04s", $entry[2]), 
        'unit'     => sprintf("%04s", $entry[3]), 
        'gl_acct'  => $entry[4], 
        'remark'   => $entry[5], 
        'vendid'   => $entry[6], 
        'check_no' => $entry[7], 
        'ar_bank'  => $entry[8], 
        'batch'    => $entry[9],
        'invoice'  => $entry[10]
      ];
    } else{
      Helper::echoJsonError($this->_getErrorMsg('_validateAndGetEachEntry', ['line'=>$i+1]), 'popupMsg');
    }
  }
}