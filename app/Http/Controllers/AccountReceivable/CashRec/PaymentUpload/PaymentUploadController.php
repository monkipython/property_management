<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\PaymentUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Upload, TableName AS T, Helper, HelperMysql, TenantTrans, Format};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use App\Http\Models\Model; // Include the models class

class PaymentUploadController extends Controller{
  private $_maxLine = 100;
  public function create(Request $req){
    $text  = 'To Enter Payments, Please Drag & Drop a CSV File' . Html::br(2);
    $text .= 'Payment Upload function allows users to record single and multiple payments.<br>For the CSV file formatting instructions, please click link below.' . Html::br(3);
    $text .= Html::span(Html::icon('fa fa-fw  fa-exclamation-triangle') . 'Please Note: This process can take up to 10 minutes.', ['class'=>'text-yellow']) .  Html::br();
    $text .= Html::span('The max invoice allowed to be posted at once time is ' . $this->_maxLine, ['class'=>'text-yellow']) .  Html::br(3);
    $text .= Html::span(Html::icon('fa fa-fw fa-file-text-o') . Html::a('Click Here for File Format Instruction', ['href'=>'/instruction/Payment-Upload-Instruction.docx']), ['class'=>'alert alert-info alert-dismissible']);
    return ['html'=>P::getUploadForm(), 'isUpload'=>true, 'text'=>Html::h2(Html::br() . $text, ['class'=>'text-center text-muted'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    set_time_limit(600);
    $glTransDataset = [T::$glTrans=>[], T::$batchRaw=>[], 'summaryAcctReceivable'=>[], T::$tntSecurityDeposit=>[]];
    $usid       = Helper::getUsid($req);
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
    } else if(count($allList) > $this->_maxLine){
      Helper::echoJsonError($this->_getErrorMsg('storeMaxLine'), 'popupMsg');
    }
    
    $batch = HelperMysql::getBatchNumber();
    $rProp = Helper::keyFieldNameElastic(HelperMysql::getProp(array_column($allList, 'prop')), 'prop');

    // PREPARE DATA FOR VALIDATION
    foreach($allList as $i=>$entry){
      if(!isset($rProp[$entry['prop']])){
        Helper::echoJsonError($this->_getErrorMsg('storeNotExist', ['line'=>($i + 2), 'prop'=>$entry['prop']]), 'popupMsg');
      }
      $entry['bank'] = $rProp[$entry['prop']]['ar_bank'];
      // GET INTO THE RIGHT FORMAT SO THAT THE VALIDATE LIB CAN VALIDATE THE DATA
      foreach($entry as $k=>$v){
        $row[$k][$i] = $v;
      }
    }
    // VALIDATE EACH DATA
    $valid = V::startValidate([
      'rawReq'          => $row,
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['prop','unit','tenant', 'amount','prop','bank', 'date1', 'check_no'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate'    => 0,
      'isPopupMsgError' => 1,
      'validateDatabase'=> [
        'mustExist' => [
          T::$tenant . '|prop,unit,tenant',
        ]
      ]
    ]); 
    $vData = $valid['dataArr'];
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])), 'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>'Z64'])), 'service');
    foreach($vData as $v){
      $v['batch'] = $batch;
      $tntTransDataset = [T::$tntTrans=>[]];
      TenantTrans::cleanTransaction($v);
      
      $tmp = TenantTrans::getPostPayment($v)['store'];
      ##### THE REAONS WE SEPERATE GL_TRANS AND TNT_TRANS INSERT INTO DATABASE #####
      ##### BECAUSE WE NEED TO REFRESH THE OPEN ITEMS EVERY TIME THERE IS A NEW PAYMENT #####
      $tntTransDataset[T::$tntTrans] = $tmp[T::$tntTrans];
      $glTransDataset[T::$glTrans] = array_merge($glTransDataset[T::$glTrans], $tmp[T::$tntTrans]);
      if(isset($tmp[T::$tntSecurityDeposit])){
        $glTransDataset[T::$tntSecurityDeposit] = array_merge($glTransDataset[T::$tntSecurityDeposit], $tmp[T::$tntSecurityDeposit]); 
      }
      
      $insertData = HelperMysql::getDataSet($tntTransDataset, $usid, $glChart, $service);
      DB::beginTransaction();
      $response = $success = $elastic = $updateData = [];
      try{
        $success = Model::insert($insertData);
        foreach($success['insert:' . T::$tntTrans] as $cntlNo){
          $updateData[T::$tntTrans][] = [
            'whereData'=>['cntl_no'=>$cntlNo, 'appyto'=>0], 
            'updateData'=>['appyto'=>$cntlNo, 'invoice'=>$cntlNo],
          ];
        }
        $success += Model::update($updateData);
        $elastic = [T::$tntTransView =>['tt.cntl_no'=>$success['insert:' . T::$tntTrans]]];
        
        Model::commit([
          'success' =>$success,
          'elastic' =>['insert'=>$elastic]
        ]);
      } catch(\Exception $e){
        $response['error']['mainMsg'] = Model::rollback($e);
      }
    }
    
    $glTransDataset[T::$batchRaw] = $glTransDataset[T::$glTrans];
    if(empty($glTransDataset[T::$tntSecurityDeposit])){
      unset($glTransDataset[T::$tntSecurityDeposit]);
    }
      
    $insertData = HelperMysql::getDataSet($glTransDataset, $usid, $glChart, $service);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = $updateData = [];
    try{
      $success += Model::insert($insertData);
      $elastic = [T::$glTransView=>['seq'=>$success['insert:' . T::$glTrans]]];
      
      if(!empty($glTransDataset[T::$tntSecurityDeposit])){
        $tenantData = TenantTrans::getUpdateTenantDepositData($glTransDataset[T::$tntSecurityDeposit]);
        $success   += Model::update($tenantData['updateData']);
        $elastic[T::$tenantView] = $tenantData['elastic'][T::$tenantView];
      }
      
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
  private function _getTable($fn){
    return [T::$glTrans, T::$application, T::$applicationInfo];
  }
//------------------------------------------------------------------------------  
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeEmptyList'=>Html::errMsg('The file is empty. Please double check your file content again.'),
      'storeMaxLine'=>Html::errMsg('You cannot upload more than ' . $this->_maxLine . ' transactions per upload'),
      'storeNotExist'=>Html::errMsg('The property number "'.Helper::getValue('prop', $vData) .'" is not exist at line '.Helper::getValue('line', $vData).'.'),
      '_validateAndGetEachEntry'=>Html::errMsg('Your file format is not correct at line '.Helper::getValue('line', $vData).'. This is Upload for payment. Please double check it and try it again.'),
      'store' =>Html::errMsg('The Return No ('. Helper::getValue('application_id', $vData) .') does not exist'),
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
  private function _getSetting($name, $req){
    $data = [
      'store'=>[
        'field'=>[],
        'rule'=>[
          'check_no'=>'nullable|string|between:6,6'
        ]
      ],
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _validateAndGetEachEntry($v, $file, $i){
    $entry = explode(',', trim($v));
    if(count($entry ) == 6){
      return [
        'date1' =>$entry[0], 
        'prop'  =>!preg_match('/[a-zA-Z]/', $entry[1]) ? sprintf("%04s", $entry[1]) : $entry[1], 
        'unit'  =>preg_match('/[a-zA-Z]/', $entry[1]) ? $entry[2] : sprintf("%04s", $entry[2]), 
        'tenant'=>$entry[3], 
        'amount'=>$entry[4], 
        'check_no'=>!empty($entry[5]) ? sprintf("%06s", $entry[5]) : '000000', 
      ];
    } else{
      Helper::echoJsonError($this->_getErrorMsg('_validateAndGetEachEntry', ['line'=>$i+1]), 'popupMsg');
    }
  }
}