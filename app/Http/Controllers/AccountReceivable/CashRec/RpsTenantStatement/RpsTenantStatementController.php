<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\RpsTenantStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Upload, TableName AS T, Helper, HelperMysql, TenantTrans};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use App\Http\Models\Model; // Include the models class
use Storage;

class RpsTenantStatementController extends Controller{
  private $_maxLine = 100;
  
  public function create(Request $req){
    $text = 'To Record Collected Rent Scanned with Tenant Statements<br>Please Click the Upload File Button or Drag & Drop the RPS .TXT File on the Left' . Html::br(2);
    $text .= 'The RPS Upload Tenant Statement function allows users to upload RPS .TXT files containing scanned money with tenant statements'. Html::br(2);
    $text .= Html::span(Html::icon('fa fa-fw  fa-exclamation-triangle') . 'Please Note: This process can take up to 10 minutes.', ['class'=>'text-yellow']) .  Html::br(3);
    $text .= Html::span(Html::icon('fa fa-fw fa-file-text-o') . 'All the Files located at T:\PpmNet\RPS_PPM', ['class'=>'alert alert-info alert-dismissible']);
    return ['html'=>P::getUploadForm(), 'isUpload'=>true, 'text'=>Html::h2(Html::br() . $text, ['class'=>'text-center text-muted'])];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    set_time_limit(600);
    $batch      = '';
    $glTransDataset = [T::$glTrans=>[], T::$batchRaw=>[], 'summaryAcctReceivable'=>[], T::$tntSecurityDeposit => []];
    $usid       = Helper::getUsid($req);
    $fileData   = P::getFileUploadContent($req);
    $textList   = $fileData['data'];
    $file       = $fileData['fileInfo']['uploadData']['name'];
    $allList    = []; 
    
    foreach($textList as $i=>$val){
      if(!empty($val)){
        $allList[] = $this->_validateAndGetEachEntry($val, $file, $i);
      }
    }
    if(empty($allList)){
      Helper::echoJsonError($this->_getErrorMsg('storeEmptyList'), 'popupMsg');
    }
    
    $rTenant = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'=>T::$tenantView,
      '_source'=>['prop', 'unit', 'tenant', 'return_no', 'ar_bank'],
      'query'=>['must'=>['return_no'=>array_column($allList, 'return_no')]]
    ]), 'return_no');
    
    $this->_validateDefaultBank($rTenant);
    
    foreach($allList as $i=>$entry){
      $batch = $entry['batch'];

      // If there something wrong, we need to error out right away
      Helper::exitIfError($entry['return_no'], $rTenant, $this->_getErrorMsg('store', ['return_no'=>$entry['return_no']]));

      $rTenant[$entry['return_no']]['bank'] = $rTenant[$entry['return_no']]['ar_bank'];
      $entry = array_merge($entry, $rTenant[$entry['return_no']]);
      // GET INTO THE RIGHT FORMAT SO THAT THE VALIDATE LIB CAN VALIDATE THE DATA
      foreach($entry as $k=>$v){
        $row[$k][$i] = $v;
      }
    }
    
    // VALIDATE EACH DATA
    $valid = V::startValidate([
      'rawReq'          => $row,
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['check_no','return_no','date1','amount','batch','job', 'prop', 'unit', 'tenant', 'bank'], 
      'includeCdate'    => 0,
      'isExistIfError'  =>0,
      'validateDatabase'=> [
        'mustExist' => [
          T::$tenant . '|return_no',
        ]
      ]
    ]); 
    $vData = $valid['dataArr'];
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])), 'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>'Z64'])), 'service');
    $check = [];
    foreach($vData as $v){
      $tntTransDataset = [T::$tntTrans=>[]];
      $id = $v['prop'].$v['unit'].$v['tenant'];
      if(!isset($check[$id])){
        TenantTrans::cleanTransaction($v);
        $check[$id] = 1;
      }
      
      $tmp = TenantTrans::getPostPayment($v)['store'];
      ##### THE REAONS WE SEPERATE GL_TRANS AND TNT_TRANS INSERT INTO DATABASE #####
      ##### BECAUSE WE NEED TO REFRESH THE OPEN ITEMS EVERY TIME THERE IS A NEW PAYMENT #####
      $tntTransDataset[T::$tntTrans] = $tmp[T::$tntTrans];
      $glTransDataset[T::$glTrans] = array_merge($glTransDataset[T::$glTrans], $tmp[T::$tntTrans]);
      if(!empty($tmp[T::$tntSecurityDeposit])){
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
    if(empty($glTransDataset[T::$tntSecurityDeposit])){
      unset($glTransDataset[T::$tntSecurityDeposit]);
    }
    
    
    $glTransDataset[T::$batchRaw] = $glTransDataset[T::$glTrans];
    $insertData = HelperMysql::getDataSet($glTransDataset, $usid, $glChart, $service);
//    dd($insertData);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success = Model::insert($insertData);
      $elastic = [T::$glTransView=>['seq'=>$success['insert:' . T::$glTrans]]];
      
      $response['sideMsg'] = $this->_getSuccessMsg('store', ['batch'=>$batch]);
      $response['success'] = 1;
      $response['file']    = P::renameRPSFileToDone($file);
      
      
      if(!empty($glTransDataset[T::$tntSecurityDeposit])){
        $tenantData = TenantTrans::getUpdateTenantDepositData($glTransDataset[T::$tntSecurityDeposit]);
        $success   += Model::update($tenantData['updateData']);
        $elastic[T::$tenantView] = $tenantData['elastic'][T::$tenantView];
      }
      
//      if(Storage::disk('RPS')->has(preg_replace('/\.TXT/i', '.done', $file))){
//        Storage::disk('RPS')->delete(preg_replace('/\.TXT/i', '.done', $file));
//      }
//      $response['file']    = preg_replace('/\.TXT/i', '.TXT', $file);
//      Storage::disk('RPS')->copy($response['file'], preg_replace('/\.TXT/i', '.done', $file));
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic]
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function destroy($id){
    // ALWAYS SET THIS TO 1 BECAUSE WE DON'T WANT TO DO ANYTHING, BUT JUST ALLOW FRONT END TO CLEAR WHEN THEY CLICK DELETE
    return ['success'=>1];
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getTable($fn){
    return [T::$tenant, T::$glTrans];
  }
//------------------------------------------------------------------------------  
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeEmptyList'=>Html::errMsg('The file is empty. Please double check your file content again.'),
      '_validateAndGetEachEntry'=>Html::errMsg('Your file format is not correct at line '.Helper::getValue('line', $vData).'. This is RPS Upload for Tenant Statement. Please double check it and try it again.'),
      '_validateDefaultBank'=>Html::errMsg('Prop: '.Helper::getValue('prop', $vData).' and Bank: ' . Helper::getValue('ar_bank', $vData) . ' does not exist. Please double check it.'),
      'store' =>Html::errMsg('The Return No ('. Helper::getValue('return_no', $vData) .') does not exist'),
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
  private function _validateAndGetEachEntry($v, $file){
    $entry = explode(',', trim($v));
    if(count($entry ) == 5 && preg_match('/^20/', $file)){
      $batchInfo = $entry[4];
      $data = [
        'check_no'=>sprintf("%06s", $entry[0]), 
        'return_no'=>$entry[2], 
        'amount'=>$entry[3] / 100, 
      ];
      $data['date1'] = substr($batchInfo, 4, 2)  . '/' .  substr($batchInfo, 6, 2) . '/' . substr($batchInfo, 0, 4);
      $data['batch'] = substr($batchInfo, 3, 9);
      $data['job']   = substr($batchInfo, -6, 6);
      return $data;
    } else{
      Helper::echoJsonError($this->_getErrorMsg('_validateAndGetEachEntry', ['line'=>1]), 'popupMsg');
    }
  }
//------------------------------------------------------------------------------
  private function _validateDefaultBank($rTenant){
    foreach($rTenant as $returnNo=>$v){
      $r = HelperMysql::getBank(['prop.keyword'=>$v['prop'], 'bank'=>$v['ar_bank']], ['prop']);
      if(empty($r)){
        Helper::echoJsonError($this->_getErrorMsg('_validateDefaultBank', $v), 'popupMsg');
      }
    }
  }
}