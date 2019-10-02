<?php
namespace App\Http\Controllers\AccountPayable\Mortgage\MortgageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{Html, TableName AS T, Helper, HelperMysql};
use App\Http\Models\{Model};
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use \App\Http\Controllers\AccountPayable\Mortgage\MortgageController as ParentClass;

class MortgageUploadController extends Controller {
  private $_viewTable = 'app/AccountPayable/Mortgage/uploadMortgage/';  
  private $_helpDoc   = 'help/uploadMortgage/Upload_Mortgage_Instructions.pdf';
  public function create(){
    $page          = $this->_viewTable . 'create';
    $uploadForm    = P::getUploadForm();
    $helpText      = Html::br() . 'To Use Upload Mortgage, Please Drag & Drop a CSV File';
    $helpText     .= Html::br(2) . 'Upload Mortgage allows users to record and add single and multiple mortgages';
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
          T::$vendorMortgageView   => ['vm.vendor_mortgage_id' => $success['insert:'.T::$vendorMortgage]]
        ]
      ];
      Model::commit([
        'success'  => $success,
        'elastic'  => $elastic,
      ]);
      $response['sideMsg'] = $this->_getSuccessMsg(__FUNCTION__,$insertData[T::$vendorMortgage]); 
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
      'store'  => [T::$vendorMortgage],
    ];
    return $tablez[$name];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($name,$req=[]){
    $orderField = [
      'store'   => ['prop','allocation','vendid','invoice','amount','init_principal','interest_rate','loan_term','gl_acct_ap','gl_acct_liability','due_date','loan_date','journal_entry_date','maturity_date','dcr','loan_option','loan_type','payment_option','payment_type','recourse','index_title','index','margin','last_payment','prepaid_penalty','prop_tax_impound','additional_principal','note'],
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
          'bank'                   => 'nullable|string',
          'note'                   => 'nullable|string',
          'prepaid_penalty'        => 'nullable',
          'additional_principal'   => 'nullable',
          'margin'                 => 'nullable|string',
          'prop_tax_impound'       => 'nullable',
          'escrow'                 => 'nullable',
          'reserve'                => 'nullable',
        ]
      ]
    ];
    return $setting[$name];
  }
//------------------------------------------------------------------------------  
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeEmptyList'=>Html::errMsg('The file is empty. Please double check your file content again.'),
      '_validateAndGetEachEntry'=>Html::errMsg('Your file format is not correct. This is the Mortgage Upload. Please double check and try it again.'),
      'store' =>Html::errMsg('Error Processing Mortgage File'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$vData = []){
    $data = [
      'store'  => Html::sucMsg('Successfully inserted ' . (!empty($vData[0]) ? count($vData) : 1) . ' mortgage(s)'),
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
    if(count($entry) == 30){
      $row['prop']               = is_numeric($entry[0]) ? str_pad($entry[0],4,'0',STR_PAD_LEFT) : $entry[0];
      $row['allocation']         = $entry[1];
      $row['vendid']             = $entry[2];
      $row['invoice']            = $entry[3];
      $row['amount']             = $entry[4];
      $row['init_principal']     = $entry[5];
      $row['interest_rate']      = $entry[6];
      $row['loan_term']          = $entry[7];
      $row['gl_acct_ap']         = $entry[8];
      $row['gl_acct_liability']  = $entry[9];
      $row['due_date']           = $entry[10];
      $row['loan_date']          = $entry[11];
      $row['journal_entry_date'] = $entry[12];
      $row['maturity_date']      = $entry[13];
      $row['dcr']                = $entry[14];
      $row['loan_option']        = $entry[15];
      $row['loan_type']          = $entry[16];
      $row['payment_option']     = $entry[17];
      $row['payment_type']       = $entry[18];
      $row['recourse']           = $entry[19];
      $row['index']              = $entry[20];
      $row['index_title']        = $entry[21];
      $row['margin']             = $entry[22];
      $row['last_payment']       = $entry[23];
      
      $row['gl_acct']            = $row['gl_acct_ap'];
      $row['bank']               = Helper::getValue('ap_bank',$rProp);
      
      $row += !empty($entry[24]) ? ['prepaid_penalty'=>$entry[24]] : [];
      $row += !empty($entry[25]) ? ['prop_tax_impound'=>$entry[25]] : [];
      $row += !empty($entry[26]) ? ['escrow'=>$entry[26]] : [];
      $row += !empty($entry[27]) ? ['reserve'=>$entry[27]] : [];
      $row += !empty($entry[28]) ? ['additional_principal'=>$entry[28]] : [];
      $row += !empty($entry[29]) ? ['note'=>$entry[29]] : [];
    } else {
      Helper::echoJsonError($this->_getErrorMsg(__FUNCTION__),'popupMsg');
    }
    return $row;
  }
}

