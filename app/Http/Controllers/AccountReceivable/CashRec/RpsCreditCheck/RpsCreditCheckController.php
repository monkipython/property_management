<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\RpsCreditCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Upload, TableName AS T, Helper, HelperMysql, TenantTrans, Format};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use App\Http\Models\Model; // Include the models class
use Storage; 

class RpsCreditCheckController extends Controller{
  public function create(Request $req){
    $text = 'To Record Collected and Scanned Credit Check Fees<br>Please Click the Upload File Button or Drag & Drop the RPS .TXT File on the Left' . Html::br(2);
    $text .= 'The RPS Upload Credit Check function allows users to upload RPS .TXT files containing scanned credit check fee payments.' . Html::br(3);
    $text .= Html::span(Html::icon('fa fa-fw fa-file-text-o') . 'All the Files located at T:\PpmNet\RPS_PPM', ['class'=>'alert alert-info alert-dismissible']);
    return ['html'=>P::getUploadForm(), 'isUpload'=>true, 'text'=>Html::h2(Html::br() . $text, ['class'=>'text-center text-muted'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $batch      = $prop = '';
//    $insertData = [T::$glTrans=>[], T::$batchRaw=>[], 'cleared_check'=>[]];
    $dataset    = [T::$glTrans=>[], T::$batchRaw=>[], 'summaryAcctReceivable'=>[]];
    $usid       = Helper::getUsid($req);
    $fileData   = P::getFileUploadContent($req);
    $textList   = $fileData['data'];
    $file       = $fileData['fileInfo']['uploadData']['name'];
    $allList    = $row = $updateData = $applicationId = [];
    unset($textList[0]);
    
    foreach($textList as $i=>$val){
      if(!empty($val)){
        $allList[] = $this->_validateAndGetEachEntry($val, $file, $i);
      }
    }
    if(empty($allList)){
      Helper::echoJsonError($this->_getErrorMsg('storeEmptyList'), 'popupMsg');
    }
    
    $rApplication = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'=>T::$creditCheckView,
      '_source'=>['application_id', 'application'],
      'query'=>['must'=>['application_id'=>array_column($allList, 'application_id')]]
    ]), 'application_id');
    $rProp = Helper::keyFieldNameElastic(HelperMysql::getProp(array_column($allList, 'prop')), 'prop');

    // PREPARE DATA FOR VALIDATION
    foreach($allList as $i=>$entry){
      // If there something wrong, we need to error out right away
      Helper::exitIfError($entry['application_id'], $rApplication, $this->_getErrorMsg('store', ['application_id'=>$entry['application_id']]));

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
      'orderField'      => ['application_id','application_info_id','amount','prop','batch','job', 'bank', 'date1', 'check_no'], 
      'includeCdate'    => 0,
      'isExistIfError'  =>0,
      'validateDatabase'=> [
        'mustExist' => [
          T::$application . '|application_id',
        ]
      ]
    ]);
    $vData = $valid['dataArr'];
    foreach($vData as $v){
      $batch = $v['batch'];
      $prop  = $v['prop'];
      $v['unit']    = $v['tenant'] = '';
      $v['tx_code'] = 'P';
      $v['amount']  = $v['amount'] * -1;
      $v['remark']  = 'Credit Check Dep';
      $v['gl_acct'] = '635';
      $v['service_code'] = '635';
      $dataset[T::$glTrans][] = $v;
      
      $applicationId[] = $v['application_id'];
      $updateData[T::$applicationInfo][] = [ 
        'whereData'=>['application_info_id'=>$v['application_info_id']], 
        'updateData'=>['app_fee_recieved'=>1, 'app_fee_recieved_date'=>$v['date1']],
      ];
    }

    $dataset[T::$batchRaw] = $dataset[T::$glTrans];
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$prop])), 'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$prop])), 'service');
    $insertData = HelperMysql::getDataSet($dataset, $usid, $glChart, $service);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert($insertData);
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[
          T::$glTransView=>['seq'=>$success['insert:' . T::$glTrans]],
          T::$creditCheckView=>['application_id'=>$applicationId]
        ]
      ];

      $response['sideMsg'] = $this->_getSuccessMsg('store', ['batch'=>$batch]);
      $response['success'] = 1;
      $response['file']    = P::renameRPSFileToDone($file);      

      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
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
      '_validateAndGetEachEntry'=>Html::errMsg('Your file format is not correct at line '.Helper::getValue('line', $vData).'. This is RPS Upload for Credit Check. Please double check it and try it again.'),
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
  private function _getSetting($name){
    $data = [
      'store'=>[
        'field'=>[],
        'rule'=>[]
      ],
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _validateAndGetEachEntry($v, $file, $i){
    $entry = explode(',', trim($v));
    if(count($entry ) == 7 && preg_match('/^credit/', $file)){
      $batchInfo = $entry[6];
      $data = [
        'check_no'           =>sprintf("%06s", $entry[1]), 
        'application_id'     =>$entry[3], 
        'application_info_id'=>$entry[4], 
        'amount'             =>$entry[5] / 100, 
      ];
      list($data['trust'], $data['bk_acct'], $data['prop']) = explode('-', $entry [2]);
      $data['date1'] = substr($batchInfo, 4, 2)  . '/' .  substr($batchInfo, 6, 2) . '/' . substr($batchInfo, 0, 4);
      $data['batch'] = substr($batchInfo, 3, 9);
      $data['job']   = substr($batchInfo, -6, 6);
      return $data;
    } else{
      Helper::echoJsonError($this->_getErrorMsg('_validateAndGetEachEntry',['line'=>$i+1]), 'popupMsg');
    }
  }
}