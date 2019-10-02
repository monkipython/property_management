<?php
namespace App\Http\Controllers\AccountPayable\UtilPayment\StorePayment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Format, Upload, TableName AS T};
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use App\Http\Models\{Model, AccountPayableModel AS M}; // Include the models class

/*
DELETE FROM ppm.accountProgram where module='utilPayment';
INSERT INTO `ppm`.`accountProgram` (`category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `subController`, `cdate`, `udate`, `active`) VALUES ('Account Payable', 'fa fa-fw fa-credit-card', 'utilPayment', 'Utility Payment', 'fa fa-fw fa-cog', 'Account Payable', '', 'utilPayment', '', 'To Access Utility Payment', 'utilPaymentedit,utilPaymentupdate,utilPaymentcreate,utilPaymentstore,accountPayableBankInfo,uploadUtilPayment,utilPaymentStorePayment', now(), now(), '1');
 */

class UtilPaymentStorePaymentController extends Controller{
  private $_viewTable = '';
  private $_viewPath  = 'app/AccountPayable/UtilPayment/paymentStore/';
  
  public function __construct(){
    $this->_viewTable  = T::$vendorUtilPaymentView;
  }
  //------------------------------------------------------------------------------
  public function create(Request $req){
    $valid  = V::startValidate([
      'rawReq'             => $req->all(),
      'tablez'             => $this->_getTable('editForm'),
      'orderField'         => $this->_getOrderField('editForm'),
      'setting'            => $this->_getSetting('editForm'),
      'includeCdate'       => 0,
      'validateDatabase'   => [
        'mustExist'  => [
          T::$vendorUtilPayment . '|vendor_util_payment_id',
        ]
      ]
    ]);
    $page   = $this->_viewPath . 'create';
    $vData  = $valid['data'];
    $invoiceDate  = $vData['invoice_date'];
    $r            = !empty($vData['vendor_payment_id']) ? M::getVendorPaymentElastic(Helper::selectData(['vendor_payment_id','invoice_date'],$vData) + ['type'=>'util_payment'],
      ['vendor_payment_id','amount','invoice','remark','invoice_date','usid','print','fileUpload'],1) : $this->_getLatestPayment($vData['vendor_util_payment_id'],$invoiceDate);
    $rUtilPayment     = M::getUtilPaymentElastic(['vendor_util_payment_id'=>$vData['vendor_util_payment_id']],['vendor_util_payment_id','invoice','prop','vendid'],1);
    $r          += ['vendor_util_payment_id'=>$vData['vendor_util_payment_id'],'uuid'=>'','field'=>$vData['field']] + Helper::selectData(['prop','invoice','vendid'],$rUtilPayment);
    $uploadFiles = [];
    
    $fileUpload  = Helper::getValue('fileUpload',$r,[]);
    foreach($fileUpload as $v){
      if($v['type'] == 'Approval' || $v['type'] == P::getInstance()->viewFileUploadTypes[T::$vendorPaymentView]){
        $uploadFiles[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($uploadFiles, '/uploadUtilPayment','upload',['includeDeleteIcon'=>1]);
    
    $form   = Form::generateForm([
      'tablez'       => $this->_getTable(__FUNCTION__),
      'orderField'   => $this->_getOrderField(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__,$req,$r),
      'button'       => $this->_getButton(__FUNCTION__),
      'rule'         => ['field'=>'required|string'],
      'copyField'    => ['positivePayFile'=>'field'],
    ]);
    
    return view($page,[
        'data' => [
          'form'            => $form,
          'upload'          => Upload::getHtml(),
          'fileUploadList'  => $fileUploadList,
        ]
      ]);
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
      
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid   = V::startValidate([
      'rawReq'           => $req->all(),
      'tablez'           => $this->_getTable(__FUNCTION__),
      'orderField'       => $this->_getOrderField(__FUNCTION__),
      'setting'          => $this->_getSetting(__FUNCTION__),
      'includeUsid'      => 1,
      'validateDatabase' => [
        'mustExist'      => [
          T::$vendorUtilPayment . '|vendor_util_payment_id'
        ]
      ]
    ]);
    
    $vData       = $valid['data'];
    $invoiceDate = $vData['invoice_date'];
    $id          = $vData['vendor_util_payment_id'];
    $field       = $vData['field'];
    $verifyR     = $this->_isExistInvoiceForMonth($id,$invoiceDate,$field);
    if(!empty($verifyR)){
      return $verifyR;
    }
    $paymentId   = !empty($vData['vendor_payment_id']) ? $vData['vendor_payment_id'] : 0;
    $uuid        = !empty($vData['uuid']) ? explode(',',(rtrim($vData['uuid'],','))) : [];
    $rUtilPayment    = M::getUtilPaymentElastic(['vendor_util_payment_id'=>$id],['vendor_util_payment_id','vendor_id','prop','unit','gl_acct','vendid'],1);
    $r           = M::getVendorPaymentElastic(['vendor_payment_id'=>$paymentId],['vendor_payment_id','foreign_id','vendor_id','remark','vendid','prop','unit','bank','type','gl_acct','check_pdf'],1);
    $insertData   = $updateData = [];
    $rFile        = M::getFileUploadIn('uuid',$uuid,['name','file','ext','uuid','path','active']);
    if(!empty($r)){
      $updateData += [
        T::$vendorPayment => [
          'whereData'   => ['vendor_payment_id'=>$paymentId],
          'updateData'  => Helper::selectData(['usid','remark','invoice','invoice_date','amount'],$vData) + ['foreign_id'=>$id,'type'=>'util_payment'],
        ]
      ];
    } else {
      $rGlChart      = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$rUtilPayment['prop']])),'gl_acct');
      $rService      = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$rUtilPayment['prop']])),'service');
      $data          = Helper::selectData(['vendor_id','prop','unit','gl_acct','vendid'],$rUtilPayment) 
                        + Helper::selectData(['usid','remark','invoice','invoice_date','amount'],$vData) 
                        + ['foreign_id'=>$id,'type'=>'util_payment','tenant'=>0];
      $insertData   += HelperMysql::getDataSet([T::$vendorPayment=>$data],$vData['usid'],$rGlChart,$rService);
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try {
      $success       += !empty($insertData) ? Model::insert($insertData) : [];
      $foreignId      = !empty($success['insert:'.T::$vendorPayment][0]) ? $success['insert:'.T::$vendorPayment][0] : $paymentId;
      
      foreach($rFile as $i => $v){
        $rFile[$i]['foreign_id'] = $id;
        $rFile[$i]['type']       = P::getInstance()->viewFileUploadTypes[T::$vendorUtilPaymentView];
      }
      
      $updateData    += !empty($uuid) ? [T::$fileUpload => ['whereInData'=>['field'=>'uuid','data'=>$uuid],'updateData'=>['foreign_id'=>$foreignId]]] : [];
      $success       += !empty($updateData) ? Model::update($updateData) : [];
      $success       += !empty($rFile) ? Model::insert([T::$fileUpload=>$rFile]) : [];
      
      $elastic        = [
        'insert'     => [
          $this->_viewTable      => ['up.vendor_util_payment_id'=>[$id]],
          T::$vendorPaymentView  => ['vp.vendor_payment_id'=>[$foreignId]],
        ]
      ];
      
      Model::commit([
        'success'   => $success,
        'elastic'   => $elastic,
      ]);
      $response['msg']               = $this->_getSuccessMsg(__FUNCTION__);
    } catch (\Exception $e) {
      $response['error']['mainMsg']  = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $tablez = [
      'editForm'          => [T::$vendorUtilPayment,T::$vendorPayment],
      'create'            => [T::$vendorUtilPayment,T::$vendorPayment,T::$fileUpload],
      'store'             => [T::$vendorUtilPayment,T::$vendorPayment,T::$fileUpload],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $perm   = Helper::getPermission($req);
    $button = [
      'create'          => ['submit'=>['id'=>'submit','value'=>'Update Payment','class'=>'col-sm-12']],
      'edit'            => ['submit'=>['id'=>'submit','value'=>'Update Utility Payments','class'=>'col-sm-12']],
    ];
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm         = Helper::getPermission($req);
    $orderField   = [
      'editForm'          => ['vendor_util_payment_id','vendor_payment_id','invoice_date','field'],
      'create'            => ['uuid','vendor_util_payment_id','vendor_payment_id','amount','invoice','invoice_date','remark','prop','vendid','usid','field'],
    ];
    
    $orderField['store']    = $orderField['create'];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $perm         = Helper::getPermission($req);
    $print        = Helper::getValue('print',$default,0);
    $readOnly     = $print == 1 ? ['readonly'=>1] : [];
    $pastUsid     = Helper::getValue('usid',$default);
    $usidType     = !empty($pastUsid) ? [] : ['type'=>'hidden'];
    $disabled     = [];
    $setting      = [
      'create'    => [
        'field'   => [
          'uuid'                   => ['type'=>'hidden'],
          'vendor_util_payment_id' => ['type'=>'hidden'],
          'vendor_payment_id'      => ['type'=>'hidden'],
          'amount'                 => $readOnly + ['req'=>1],
          'invoice'                => ['readonly'=>1,'req'=>1],
          'invoice_date'           => $readOnly + ['value'=>date('m/d/Y'),'req'=>1],
          'remark'                 => $readOnly + ['req'=>1],
          'prop'                   => ['readonly'=>1],
          'vendid'                 => ['readonly'=>1],
          'usid'                   => $usidType + ['readonly'=>1,'value'=>Helper::getUsid($req),'req'=>1],
          'field'                  => ['id'=>'field','name'=>'field'] + array_flip(['type'=>'hidden']),
        ],
        'rule'    => [
          'uuid'               => 'nullable',
          'vendor_payment_id'  => 'nullable|integer',
          'field'              => 'required|string',
        ],
      ],
      'edit'       => [
        'field'    => [
          'vendor_util_payment_id'  => $disabled + ['type'=>'hidden'],
          'vendid'                  => $disabled + ['label'=>'Vendor Code', 'hint'=>'You can type prop address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'invoice'                 => $disabled + ['req'=>1],
          'prop'                    => $disabled + ['label'=>'Prop', 'hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'gl_acct'                 => $disabled + ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false','req'=>1],
          'remark'                  => $disabled + ['req'=>1], 
          'usid'                    => $disabled + ['label'=>'Last Updated By', 'readonly'=>1],
        ]
      ],
      'editForm'   => [
        'field'    => [
          'field'  => ['id'=>'field','name'=>'field','type'=>'text'],
        ],
        'rule'     => [
          'vendor_payment_id' => 'nullable|integer',
          'field'             => 'required|string',
        ]
      ]
    ];
   
    if(!empty($default)){
      $field    = Helper::getValue('field',$default);
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $k === 'invoice_date' ? Format::usDate($v) : $v;
      }
      $setting[$fn]['field']['field'][$field] = 'value';
    }
    $setting['store']    = $setting['create'];
    $setting['update']   = $setting['edit'];
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store'   => Html::sucMsg('Successfully Updated Payment'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name,$param=[]){
    $data = [
      '_verifyInvoiceDate' => Html::errMsg(date('F Y',strtotime(Helper::getValue('invoice_date',$param))).' check is already issued for this vendor. Please try different month and year.'),
    ];
    return $data[$name];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getLatestPayment($id,$invoiceDate=''){
    $r                = M::getUtilPaymentElastic(['vendor_util_payment_id'=>$id],['vendor_util_payment_id','remark',T::$vendorPayment],1);  
    $paymentHistory   = Helper::keyFieldName(Helper::getValue(T::$vendorPayment,$r,[]),'invoice_date');
    $remark           = Helper::getValue('remark',$r);
    $currentTimestamp = strtotime(date('Y-m-d'));
    $timestampHistory = [];
    foreach($paymentHistory as $k => $v){
      $timestamp                       = strtotime($k);
      if($timestamp <= $currentTimestamp){
        $timestampHistory[strtotime($k)] = $v;
      }
    }
    krsort($timestampHistory);
    $payment                 = Helper::getValue(0,array_values($timestampHistory),[]);
    $payment['remark']       = $remark;
    $payment['invoice_date'] = $invoiceDate;
    unset($payment['vendor_payment_id'],$payment['usid'],$payment['print']);
    return $payment;
  }
//------------------------------------------------------------------------------
  private function _isExistInvoiceForMonth($id,$invoiceDate,$field){
    $year         = preg_replace('/vendor_payment_hidden_date_field_/','',$field);
    $enteredYear  = date('Y-m',strtotime($invoiceDate));
    $pastDate     = date('Y-m-01',strtotime($invoiceDate));
    $futureDate   = date('Y-m-t',strtotime($invoiceDate));
    $r            = M::getVendorPaymentElastic([
      'void'        => 0,
      'print'       => 0,
      'foreign_id'  => $id,
      'range'       => [
        'invoice_date' => [
          'gte'    => $pastDate,
          'lte'    => $futureDate,
        ]
      ]
    ],['vendor_payment_id','invoice_date','void'],1);
    return $enteredYear != $year && !empty($r) ? ['msg'=>$this->_getErrorMsg(__FUNCTION__,['invoice_date'=>$invoiceDate]),'keepOpen'=>1] : [];
  }
}
