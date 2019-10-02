<?php
namespace App\Http\Controllers\AccountPayable\FundsTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Format, Account, TableName AS T};
use App\Http\Models\{Model,AccountPayableModel AS M}; // Include the models class

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `subController`, `active`) VALUES ('0', 'Account Payable', 'fa fa-fw fa-file-pdf-o', 'fundsTransfer', 'Funds Transfer', 'fa fa-fw fa-cog', 'Account Payable', 'fa fa-fw fa-users', 'fundsTransfer', '', 'To Access Funds Transfer', 'accountPayableBankInfo', '1');
 */

class FundsTransferController extends Controller {
  private $_viewPath          = 'app/AccountPayable/FundsTransfer/fundsTransfer/';
  private $_viewTable         = '';
  private $_indexMain         = '';
  private static $_instance;
  
  public function __construct(){
    $this->_viewTable = T::$glTransView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
//------------------------------------------------------------------------------
  public static function getInstance(){
    if(is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  } 
//------------------------------------------------------------------------------
  public function index(Request $req){
    $page = $this->_viewPath . 'index';
    $form    = implode('',Form::generateField($this->_getFields('fromForm')));
    $formTo  = implode('',Form::generateField($this->_getFields('toForm')));
    
    return view($page,['data'=>
      [
        'nav'        => $req['NAV'],
        'account'    => Account::getHtmlInfo($req['ACCOUNT']),
        'form'       => $form,
        'formTo'     => $formTo,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid   = V::startValidate([
      'rawReq'        => $req->all(),
      'rule'          => $this->_getRule(),
      'includeUsid'   => 1,
      'includeCdate'  => 0,
    ]);
    
    $vData        = $valid['data'];
    $invoiceDate  = Format::mysqlDate($vData['date1']);
    $msgData      = Helper::selectData(['amount','prop','toprop'],$vData);
    $usid         = $vData['usid'];
    
    $insertData = $updateData = $bankIds = [];
    $glAcct = ['from'=>[],'to'=>[]];
    
    $rProp        = M::getPropElastic(['prop.keyword'=>$vData['toprop']],['prop','prop_class'],1);

    if(empty($rProp) || $rProp['prop_class'] == 'X'){
      Helper::echoJsonError(Html::errMsg('Property ' . $vData['toprop'] . ' does not exist or is inactive'));
    }

    foreach($vData as $k => $v){
      $key = preg_match('/^to/',$k) ? 'to' : 'from';
      $glAcct[$key][preg_replace('/^to/','',$k)] = $v;
    }
    
    foreach($glAcct as $k => $v){
      $prop                        = $v['prop'];
      $journal                     = $k == 'to' ? 'CR' : 'CP';
      
      $rGlChart                    = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$prop])),'gl_acct');
      $rService                    = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$prop])),'service');
      
      $glAcct[$k]['amount']        = $k == 'to' ? $vData['amount'] : -1 * $vData['amount'];
      $glAcct[$k]['tx_code']       = $k == 'to' ? 'CR' : 'CP';
      $glAcct[$k]['date1']         = $invoiceDate;
      $glAcct[$k]['remark']        = $k == 'to' ? 'Fund Transfer From ' . $vData['prop'] . ' to ' . $vData['toprop'] : $v['remark'];
      $glAcct[$k]['vendor']        = $k == 'to' ? '' : preg_replace('/^[\*]+/','',$vData['totrust']);
      $glAcct[$k]['journal']       = $journal;
      $glAcct[$k]['service_code']  = '';
      $glAcct[$k]['unit']          = '';
      $glAcct[$k]['tenant']        = 255;
      $glAcct[$k]['batch']         = HelperMysql::getBatchNumber();
      if($journal === 'CP'){
        //$bank                   = M::getBank(Model::buildWhere(['prop'=>$v['trust'],'bank'=>$v['bank']]),['bank_id','last_check_no']);
        $bank                   = HelperMysql::getBank(Helper::getPropBankMustQuery($v,[],0),['bank_id','last_check_no'],['bank_id'=>'asc'],1);
        $newCheck               = Helper::getValue('last_check_no',$bank,0) + 1;
        $bankId                 = $bank['bank_id'];
        $glAcct[$k]['check_no'] = $newCheck;
        $updateData[T::$bank][] = [
          'whereData'    => ['bank_id' => $bankId],
          'updateData'   => ['last_check_no' => $newCheck],
        ];
        $bankIds[]              = $bankId;
      }
      unset($glAcct[$k]['trust']);
      
      $data                     = HelperMysql::getDataSet([T::$glTrans=>$glAcct[$k]],$usid,$rGlChart,$rService);
      $insertData[T::$glTrans][]= $data[T::$glTrans];
    }
    $dataset       = $insertData + [T::$batchRaw => $insertData[T::$glTrans],'summaryAcctPayable'=>[]];
    $insertDataset = HelperMysql::getDataset($dataset,$usid,$rGlChart,$rService);

    $fromBankAcct = HelperMysql::getBank(Helper::getPropBankMustQuery($vData,[],0),['cp_acct'], [], 1);
    $toBankAcct   = HelperMysql::getBank(['prop.keyword'=>$vData['toprop'], 'bank.keyword'=>$vData['tobank']],['cp_acct'], [], 1);

    $insertDataset[T::$trackFundTransfer] = [
      'prop'         => $vData['prop'],
      'to_prop'      => $vData['toprop'],
      'bank'         => $vData['bank'],
      'to_bank'      => $vData['tobank'],
      'bank_acct'    => !empty($fromBankAcct) ? $fromBankAcct['cp_acct'] : '',
      'to_bank_acct' => !empty($toBankAcct) ? $toBankAcct['cp_acct'] : '',
      'gl_acct'      => $vData['gl_acct'],
      'to_gl_acct'   => $vData['togl_acct'],
      'amount'       => $vData['amount'],
      'batch'        => HelperMysql::getBatchNumber(),
      'post_date'    => $invoiceDate,
      'usid'         => $usid
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $commit = $response = [];
    try {
      $success    += Model::insert($insertDataset);
      $success    += !empty($updateData) ? Model::update($updateData) : [];
        
      $elastic                      = [
        'insert'    => [
          T::$glTransView   => ['gl.seq'   =>$success['insert:' . T::$glTrans]],
          T::$bankView      => ['b.bank_id'=>$bankIds],
          T::$trackFundTransferView => ['track_fund_transfer_id'=>$success['insert:' . T::$trackFundTransfer]]
        ]
      ];
      
      Model::commit([
        'success' => $success,
        'elastic' => $elastic,
      ]);
      $response['sideMsg']          = $this->_getSuccessMsg(__FUNCTION__,$msgData);
      $response['success']          = 1;
    } catch(\Exception $e){
      $response['error']['popupMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getFields($name){
    $fields = [
      'toForm'      => [
        'totrust'   => ['id'=>'totrust','label'=>'To Trust','req'=>1,'type'=>'text','readonly'=>1],
        'toprop'    => ['id'=>'toprop','label'=>'To Prop','type'=>'text','class'=>'autocomplete','req'=>1],
        'tobank'    => ['id'=>'tobank','label'=>'To Bank','type'=>'option','option'=>[],'req'=>1],
        'togl_acct' => ['id'=>'togl_acct','label'=>'To Gl Acct','class'=>'autocomplete','type'=>'text','req'=>1],
      ],
      'fromForm'    => [
        'trust'     => ['id'=>'trust','label'=>'From Trust','type'=>'text','req'=>1,'readonly'=>1],
        'prop'      => ['id'=>'prop','label'=>'From Property','type'=>'text','class'=>'autocomplete','req'=>1],
        'bank'      => ['id'=>'bank','label'=>'From Bank','type'=>'option','option'=>[],'req'=>1],
        'amount'    => ['id'=>'amount','label'=>'Amount','type'=>'text','class'=>'decimal','req'=>1],
        'gl_acct'   => ['id'=>'gl_acct','label'=>'From Gl Acct','type'=>'text','class'=>'autocomplete','req'=>1],
        'date1'     => ['id'=>'date1','label'=>'Post Date','type'=>'text','class'=>'date','value'=>date('m/d/Y'),'req'=>1],
        'remark'    => ['id'=>'remark','label'=>'Remark','type'=>'text','req'=>1],
      ]
    ];
    return $fields[$name];
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'trust'        => 'required|string|between:1,7',
      'totrust'      => 'required|string|between:1,7',
      'prop'         => 'required|string|between:1,7',
      'toprop'       => 'required|string|between:1,7',
      'bank'         => 'required|string|between:1,2',
      'tobank'       => 'required|string|between:1,2',
      'amount'       => 'required|numeric',
      'date1'        => 'required|string|between:8,10',
      'gl_acct'      => 'required|string|between:1,9',
      'togl_acct'    => 'required|string|between:1,9',
      'remark'       => 'required|string|between:1,50',
    ];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getErrorMsg($name,$data=[]){
    $msg = [
      'store'   => Html::errMsg('Error transferring fund of ' . Format::usMoney(Helper::getValue('amount',$data,0)) . ' from Property ' . Helper::getValue('prop',$data) . ' to ' . Helper::getValue('toprop',$data)),
    ];
    return $msg[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$data=[]){
    $msg = [
      'store'  => Html::sucMsg('Successfully transferred fund of ' . Format::usMoney(Helper::getValue('amount',$data,0)) . ' from Property ' . Helper::getValue('prop',$data) . ' to ' . Helper::getValue('toprop',$data)),
    ];
    return $msg[$name];
  }

}