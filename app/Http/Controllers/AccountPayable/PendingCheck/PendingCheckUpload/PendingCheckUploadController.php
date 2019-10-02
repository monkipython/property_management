<?php
namespace App\Http\Controllers\AccountPayable\PendingCheck\PendingCheckUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Elastic, Html, TableName AS T, Helper, HelperMysql, Format};
use App\Http\Models\{Model};
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use App\Http\Controllers\AccountPayable\PendingCheck\PendingCheckController as ParentClass;

class PendingCheckUploadController extends Controller {
  private $_viewTable = 'app/AccountPayable/PendingCheck/uploadPendingCheck/';  
  private $_helpDoc   = 'help/uploadPendingCheck/Upload_Pending_Check_Instructions.pdf';
  public function create(){
    $page          = $this->_viewTable . 'create';
    $uploadForm    = P::getUploadForm();
    $helpText      = Html::br() . 'To Use Upload Pending Check, Please Drag & Drop a CSV File';
    $helpText     .= Html::br(2) . 'Upload Pending Check allows users to record add single and multiple pending checks';
    $helpText     .= Html::br() . 'For the CSV file formatting instructions please click link below';
    $helpText     .= Html::br(4) . Html::span(Html::icon('fa fa-fw fa-file-text-o') . Html::a('Click Here for File Format Instruction',['href'=>$this->_helpDoc,'target'=>'_blank','title'=>'View Help Document']),['class'=>'alert alert-info alert-dismissable']);
    $html          = view($page,['data'=>[
      'uploadForm'  => $uploadForm,
      'helpText'    => $helpText,
    ]])->render();
    return ['html' => $html,'isUpload'=>true];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $allList    = [];
    $usid       = Helper::getUsid($req);
    $fileData   = P::getFileUploadContent($req,['csv']);
    $textList   = $fileData['data'];
    $propIndex  = 6;
    unset($textList[0]);
    $rProp      = Helper::keyFieldNameElastic(HelperMysql::getProp(array_column($allList,$propIndex)),'prop');
    foreach($textList as $v){
      if(!empty($v)){
        $allList[] = $this->_validateAndGetEachEntry($v,$rProp); 
      }
    }
    if(empty($allList)){
      Helper::echoJsonError($this->_getErrorMsg('storeEmptyList'), 'popupMsg');
    }
    $row        = P::parseCsvDatatoRequestData($allList);
    $storeData  = ParentClass::getInstance()->getStoreData([
      'rawReq'           => $row,
      'tablez'           => $this->_getTable(__FUNCTION__),
      'orderField'       => $this->_getOrderField(__FUNCTION__),
      'setting'          => $this->_getSetting(__FUNCTION__),
      'includeCdate'     => 0,
      'isPopupMsgError'  => 1,
      'usid'             => $usid,
    ]);
    $insertData    = $storeData['insertData'];
    ############### DATABASE SECTION ######################
    $response = $success = $elastic = [];
    DB::beginTransaction();
    try {
      $success += Model::insert($insertData);
      $elastic  = [
        'insert' => [
          T::$vendorPendingCheckView   => ['vp.vendor_pending_check_id' => $success['insert:'.T::$vendorPendingCheck]]
        ]
      ];
      Model::commit([
        'success'  => $success,
        'elastic'  => $elastic,
      ]);
      $response['sideMsg'] = $this->_getSuccessMsg(__FUNCTION__,$insertData[T::$vendorPendingCheck]); 
      $response['success'] = 1;
    } catch(\Exception $e) {
      $response['error']['mainMsg'] = $this->_getErrorMsg(__FUNCTION__);
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getTable($name){
    $tablez = [
      'store'  => [T::$vendorPendingCheck],
    ];
    return $tablez[$name];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($name,$req=[]){
    $orderField = [
      'store'   => ['vendid','invoice','invoice_date','due_date','amount','prop','unit','tenant','gl_acct','remark','is_need_approved','recurring','bank'],
    ];
    return $orderField[$name];
  }
//------------------------------------------------------------------------------
  private function _getSetting($name,$req=[],$default=[]){
    $setting = [
      'store' => [
        'field' => [
          
        ],
        'rule'  => [
          'due_date'    => 'required|integer|between:0,31',
        ]
      ]
    ];
    return $setting[$name];
  }
//------------------------------------------------------------------------------  
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeEmptyList'=>Html::errMsg('The file is empty. Please double check your file content again.'),
      '_validateAndGetEachEntry'=>Html::errMsg('Your file format is not correct. This is the Pending Check Upload. Please double check and try it again.'),
      'store' =>Html::errMsg('Error Processing Pending Check File'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$vData = []){
    $data = [
      'store'  => Html::sucMsg('Successfully inserted ' . (!empty($vData[0]) ? count($vData) : 1) . ' pending check(s)'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _validateAndGetEachEntry($v,$rProp=[]){
    $entry = explode(',', trim($v));
    $cols  = ['vendid','invoice','invoice_date','due_date','amount','prop','unit','tenant','gl_acct','remark','is_need_approved','recurring','prop_to_remark'];
    $row   = count($entry) == count($cols) ? array_combine($cols,$entry) : Helper::echoJsonError($this->_getErrorMsg(__FUNCTION__),'popupMsg');
    $prop                     = Helper::getValue($row['prop'],$rProp,[]);
    $row['bank']              = Helper::getValue('ap_bank',$prop);
    $row['recurring']         = strtolower(Helper::getValue('recurring',$row));
    $row['is_need_approved']  = strtolower(trim($row['is_need_approved'])) == 'no' ? 0 : 1;
    $row['remark']            = strtolower(trim($row['prop_to_remark'])) == 'yes' ? $this->_getPropAddress($row['remark'],$prop) : $row['remark'];
    unset($row['prop_to_remark']);
    return $row;
  }
//------------------------------------------------------------------------------
  private function _getPropAddress($remark,$prop){
    $space = !empty($remark) ? ' ' : '';
    return $remark . $space . implode(', ',Helper::selectData(['street','city','state','zip'],$prop));
  }
}

