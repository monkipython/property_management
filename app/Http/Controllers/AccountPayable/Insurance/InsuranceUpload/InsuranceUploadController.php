<?php
namespace App\Http\Controllers\AccountPayable\Insurance\InsuranceUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{Html, TableName AS T, Helper, HelperMysql, Format};
use App\Http\Models\{Model};
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use \App\Http\Controllers\AccountPayable\Insurance\InsuranceController as ParentClass;

class InsuranceUploadController extends Controller {
  private $_viewTable = 'app/AccountPayable/Insurance/uploadInsurance/';  
  private $_helpDoc   = 'help/uploadInsurance/Upload_Insurance_Instructions.pdf';
  public function create(){
    $page          = $this->_viewTable . 'create';
    $uploadForm    = P::getUploadForm();
    $helpText      = Html::br() . 'To Use Upload Insurance, Please Drag & Drop a CSV File';
    $helpText     .= Html::br(2) . 'Upload Insurance allows users to record and add single and multiple insurances';
    $helpText     .= Html::br() . 'For the CSV file formatting instructions please click link below';
    $helpText     .= Html::br(4) . Html::span(Html::icon('fa fa-fw fa-file-text-o') . Html::a('Click Here for File Format Instruction',['href'=>$this->_helpDoc,'target'=>'_blank','title'=>'View Help Document']),['class'=>'alert alert-info alert-dismissable']);
    $html          = view($page,['data'=>[
      'uploadForm'  => $uploadForm,
      'helpText'    => $helpText,
    ]])->render();
    return ['html' => $html,'isUpload'=>true];
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
      
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $allList    = [];
    $usid       = Helper::getUsid($req);
    $fileData   = P::getFileUploadContent($req,['csv']);
    $textList   = $fileData['data'];
    $propIndex  = 0;
    unset($textList[0]);
    $rProp      = Helper::keyFieldNameElastic(HelperMysql::getProp($this->_padProp(array_column($allList,$propIndex))),'prop');
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
      'setting'          => $this->_getSetting(__FUNCTION__,$req),
      'includeCdate'     => 0,
      'isPopupMsgError'  => 1,
      'usid'             => $usid,
      'validateDatabase' => [
        'mustExist'      => [
          T::$vendor . '|vendid',
          T::$glChart. '|gl_acct',
          T::$prop   . '|prop',
        ]
      ],
    ]);
    $insertData    = $storeData['insertData'];
    ############### DATABASE SECTION ######################
    $response = $success = $elastic = [];
    DB::beginTransaction();
    try {
      $success += Model::insert($insertData);
      $elastic  = [
        'insert' => [
          T::$vendorInsuranceView   => ['vi.vendor_insurance_id' => $success['insert:'.T::$vendorInsurance]]
        ]
      ];
      Model::commit([
        'success'  => $success,
        'elastic'  => $elastic,
      ]);
      $response['sideMsg'] = $this->_getSuccessMsg(__FUNCTION__,$insertData[T::$vendorInsurance]); 
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
      'store'  => [T::$vendorInsurance],
    ];
    return $tablez[$name];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($name,$req=[]){
    $orderField = [
      'store'   => ['vendid','invoice_date','amount','effective_date','policy_num','prop','gl_acct','remark','auto_renew','ins_total','ins_building_val','ins_rent_val','ins_sf','payer','number_payment'],
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
          'vendor_id'                    => 'nullable|integer',
          'bank'                         => 'nullable|string',
          'monthly_payment'              => 'nullable|numeric',
          'start_pay_date'               => 'nullable',
          'broker'                       => 'nullable|string',
          'carrier'                      => 'nullable|string',
          'date_insured'                 => 'nullable',
          'lor'                          => 'nullable|string',
          'deductible'                   => 'nullable|numeric',
          'building_ordinance'           => 'nullable|string',
          'occ'                          => 'nullable|string',
          'building_value'               => 'nullable|numeric',
          'general_liability_limit'      => 'nullable|numeric',
          'general_liability_deductible' => 'nullable|numeric',
          'insurance_company'            => 'nullable|string',
          'insurance_premium'            => 'nullable|numeric',
          'down_payment'                 => 'nullable|numeric',
          'installments'                 => 'nullable|numeric',
        ]
      ]
    ];
    return $setting[$name];
  }
//------------------------------------------------------------------------------  
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeEmptyList'=>Html::errMsg('The file is empty. Please double check your file content again.'),
      '_validateAndGetEachEntry'=>Html::errMsg('Your file format is not correct. This is Insurance Upload. Please double check and try it again.'),
      'store' =>Html::errMsg('Error Processing Insurance File'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$vData = []){
    $data = [
      'store'  => Html::sucMsg('Successfully inserted ' . (!empty($vData[0]) ? count($vData) : 1) . ' insurance(s)'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _padProp($props){
    $data = [];
    foreach($props as $v){
      $data[] = is_numeric($v) ? str_pad($v,4,'0',STR_PAD_LEFT) : $v;
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _validateAndGetEachEntry($v,$rProp=[]){
    $entry = explode(',', trim($v));
    $row   = [];
    if(count($entry) == 31){
      $row['vendid']             = $entry[0];
      $row['invoice_date']       = $entry[1];
      $row['amount']             = $entry[2];
      $row['effective_date']     = $entry[3];
      $row['policy_num']         = $entry[4];
      $row['prop']               = is_numeric($entry[5]) ? str_pad($entry[5],4,'0',STR_PAD_LEFT) : $entry[5];
      $row['gl_acct']            = $entry[6];
      $row['remark']             = $entry[7];
      $row['auto_renew']         = trim(strtolower($entry[8]));
      $row['ins_total']          = $entry[9];
      $row['ins_building_val']   = $entry[10];
      $row['ins_rent_val']       = $entry[11];
      $row['ins_sf']             = $entry[12];
      $row['payer']              = trim(strtolower($entry[13])) == 'pama' ? 'pama' : 'owner';
      $row['number_payment']     = is_numeric($entry[14]) && $entry[14] >= 0 && $entry[14] <= 12 ? intval($entry[14]) : 0;
      
      $row += !empty($entry[15]) ? ['monthly_payment'=>$entry[15]] : [];
      $row += !empty($entry[16]) ? ['start_pay_date'=>Format::mysqlDate($entry[16])] : [];
      $row += !empty($entry[17]) ? ['broker'=>$entry[17]] : [];
      $row += !empty($entry[18]) ? ['carrier'=>$entry[18]]: [];
      $row += !empty($entry[19]) ? ['date_insured'=>Format::mysqlDate($entry[19])] : [];
      $row += !empty($entry[20]) ? ['occ'=>$entry[20]] : [];
      $row += !empty($entry[21]) ? ['building_value'=>$entry[21]] : [];
      $row += !empty($entry[22]) ? ['deductible'=>$entry[22]] : [];
      $row += !empty($entry[23]) ? ['lor'=>$entry[23]] : [];
      $row += !empty($entry[24]) ? ['building_ordinance'=>$entry[24]] : [];
      $row += !empty($entry[25]) ? ['general_liability_limit'=>$entry[25]] : [];
      $row += !empty($entry[26]) ? ['general_liability_deductible'=>$entry[26]] : [];
      $row += !empty($entry[27]) ? ['insurance_company'=>$entry[27]] : [];
      $row += !empty($entry[28]) ? ['insurance_premium'=>$entry[28]] : [];
      $row += !empty($entry[29]) ? ['down_payment'=>$entry[29]] : [];
      $row += !empty($entry[30]) ? ['installments'=>$entry[30]] : [];
      
      $row['bank']               = Helper::getValue('ap_bank',$rProp);
    } else {
      Helper::echoJsonError($this->_getErrorMsg(__FUNCTION__),'popupMsg');
    }
    return $row;
  }
}

