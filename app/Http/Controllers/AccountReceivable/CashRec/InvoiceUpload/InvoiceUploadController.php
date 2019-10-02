<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\InvoiceUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Elastic, Html, TableName AS T, Helper, HelperMysql, TenantTrans, Format};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use \App\Http\Controllers\AccountReceivable\CashRec\PostInvoice\PostInvoiceController;
use App\Http\Models\Model; // Include the models class

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `active`) VALUES ('0', 'Account Receivable', 'fa fa-fw fa-money', 'cashRec', 'Cash Receive', 'fa fa-fw fa-cog', 'Upload', '', 'invoiceUpload', '', 'To Access Invoice Upload', '1');
 */

class InvoiceUploadController extends Controller {
  private $_maxLine = 100;
  public function create(Request $req){
    $text   = 'To Use Invoice Upload, Please Drag & Drop a CSV File' . Html::br(2);
    $text  .= 'Invoice Upload function allows users to record single and multiple Invoices.' . Html::br() . 'For the CSV file formatting instructions, please click link below.' . Html::br(3);
    $text  .= Html::span(Html::icon('fa fa-fw  fa-exclamation-triangle') . 'Please Note: This process can take up to 10 minutes.', ['class'=>'text-yellow']) .  Html::br();
    $text  .= Html::span('The max invoice allowed to be posted at once time is ' . $this->_maxLine, ['class'=>'text-yellow']) .  Html::br(3);
    $text  .= Html::span(Html::icon('fa fa-fw fa-file-text-o') . Html::a('Click Here for File Format Instruction',['href'=>'/instruction/Invoice-Upload-Instruction.docx']),['class'=>'alert alert-info alert-dismissable']);
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
    $glTransDataset = [T::$glTrans=>[]];
    
    
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
    
    // PREPARE DATA FOR VALIDATION
    foreach($allList as $i=>$entry){
      if(!isset($rProp[$entry['prop']])){
        Helper::echoJsonError($this->_getErrorMsg('storeNotExist', ['line'=>($i + 2), 'prop'=>$entry['prop']]), 'popupMsg');
      }
      
      // GET INTO THE RIGHT FORMAT SO THAT THE VALIDATE LIB CAN VALIDATE THE DATA
      foreach($entry as $k=>$v){
        if($k == 'tenant' && empty($v)){
          ##### GET CURRENT TENANT #####
          $r = Helper::keyFieldNameElastic(Elastic::searchQuery([
            'index'=>T::$tenantView, 
            '_source'=>['tenant'],
            'query'=>Helper::getPropUnitMustQuery($entry, ['status.keyword'=>'C'])
          ]), 'tenant', 'tenant');
          
          if(count($r) == 1){
            $v  = current($r);
          } else{
            Helper::echoJsonError($this->_getErrorMsg('storeMoreThanOneCurrentTenant', ['line'=>$i+2]), 'popupMsg');
          }
        }
        $row[$k][$i] = $v;
      }
    }
    
    // VALIDATE EACH DATA
    $valid = V::startValidate([
      'rawReq'          => $row,
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['prop','unit','tenant', 'service','date1', 'amount', 'remark'], 
      'includeCdate'    => 0,
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'isPopupMsgError' => 1,
      'validateDatabase'=> [
        'mustExist' => [
          T::$tenant . '|prop,unit,tenant',
          T::$service . '|service'
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
      $v['remark']   = !empty($v['remark']) ? $v['remark'] : $service[$v['service']]['remark'];  
      $v['appyto']   = 0;
      $v['batch']    = $batch;
      $v['_glChart'] = $glChart;
      $v['_service'] = $service;
      $v['_rProp']   = Helper::selectData(['ar_bank','group1'], $rProp[$v['prop']]);
      $v['_rBank']   = Helper::keyFieldName($rProp[$v['prop']]['bank'], 'bank');
      $v['tnt_name'] = current(Helper::keyFieldNameElastic(Elastic::searchQuery([
        'index'=>T::$tenantView,
        '_source'=>['tnt_name'], 
        'query'=>Helper::getPropUnitTenantMustQuery($v)
      ]), 'tnt_name'))['tnt_name'];
      
      $v = PostInvoiceController::getInstance()->getStoreData($v);
      $dataset = TenantTrans::getTntSurityDeposit($v);
      $insertDataTmp = HelperMysql::getDataSet($dataset, $usid, $glChart, $service);
      if(isset($insertDataTmp[T::$glTrans])){
        $glTransDataset[T::$glTrans] = array_merge($glTransDataset[T::$glTrans], $insertDataTmp[T::$glTrans]);
      }
      unset($insertDataTmp[T::$glTrans]);
      
      DB::beginTransaction();
      try{
        $success = Model::insert($insertDataTmp);
        $insertIds = $success['insert:'.T::$tntTrans];
        $success += Model::update([
          T::$tntTrans=>[ 
            'whereInData'=>[['field'=>'cntl_no', 'data'=>$insertIds], ['field'=>'appyto', 'data'=>[0]]], 
            'updateData'=>['appyto'=>DB::raw('cntl_no')],
          ]
        ]);
        $success += Model::update([
          T::$tntTrans=>[ 
            'whereInData'=>['field'=>'cntl_no', 'data'=>$insertIds], 
            'updateData'=>['invoice'=>DB::raw('appyto')],
          ]
        ]);
        $elastic[T::$tntTransView] = ['tt.cntl_no'=>$insertIds];
        Model::commit([
          'success' =>$success,
          'elastic' =>['insert'=>$elastic]
        ]);
      } catch(\Exception $e){
        $response['error']['mainMsg'] = Model::rollback($e);
      }
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = $updateData = [];
    try{
      if(!empty($glTransDataset[T::$glTrans])){
        $success += Model::insert($glTransDataset);
        
        if(!empty($success['insert:'.T::$glTrans])){
          $elastic[T::$glTransView] = ['gl.seq'=>$success['insert:' . T::$glTrans]];
        }
        Model::commit([
          'success' =>$success,
          'elastic' =>['insert'=>$elastic]
        ]);
      }
      
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
  private function _getTable($fn){
    return [T::$tntTrans, T::$service];
  }
//------------------------------------------------------------------------------
  private function _getSetting(){
    return [
      'field'=>[
      ],
      'rule'=>[
        'amount' =>'required|numeric|between:0.01,1000000.00',
        'tenant' =>'nullable|integer|between:0,255',
        'remark' =>'nullable|string|between:5,255'
      ]
    ];
  }
//------------------------------------------------------------------------------  
  private function _getSuccessMsg($name, $vData = []){
    $data = [
      'store'  =>Html::sucMsg('Successfully invoice tenants with batch #: '. Helper::getValue('batch', $vData)),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------  
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeEmptyList'=>Html::errMsg('The file is empty. Please double check your file content again.'),
      'storeMaxLine'=>Html::errMsg('You cannot upload more than ' . $this->_maxLine . ' transactions per upload'),
      'storeNotExist'=>Html::errMsg('The property number "'.Helper::getValue('prop', $vData) .'" is not exist at line '.Helper::getValue('line', $vData).'.'),
      '_validateAndGetEachEntry'=>Html::errMsg('Your file format is not correct at line '.Helper::getValue('line', $vData).'. This is Invoice Upload payment. Please double check it and try it again.'),
      'storeMoreThanOneCurrentTenant'=>Html::errMsg('There are more than one current tenant at line '.Helper::getValue('line', $vData).'. Please double check it and try it again.'),
      'store' =>Html::errMsg('The Return No ('. Helper::getValue('application_id', $vData) .') does not exist'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _validateAndGetEachEntry($v, $file, $i){
    $entry = explode(',', trim($v));
    if(count($entry ) == 7){
      return [
        'date1' =>$entry[0], 
        'prop'  =>!preg_match('/[a-zA-Z]/', $entry[1]) ? sprintf("%04s", $entry[1]) : $entry[1], 
        'unit'  =>preg_match('/[a-zA-Z]/', $entry[1]) ? $entry[2] : sprintf("%04s", $entry[2]), 
        'amount'=>$entry[3], 
        'service'=>$entry[4], 
        'tenant'=>$entry[5], 
        'remark'=>trim($entry[6]), 
      ];
    } else{
      Helper::echoJsonError($this->_getErrorMsg(__FUNCTION__, ['line'=>$i+1]), 'popupMsg');
    }
  }
}