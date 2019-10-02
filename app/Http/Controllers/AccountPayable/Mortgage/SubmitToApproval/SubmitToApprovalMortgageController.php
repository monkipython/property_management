<?php
namespace App\Http\Controllers\AccountPayable\Mortgage\SubmitToApproval;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use App\Library\{Elastic, Form, Html, Helper, V, HelperMysql, TableName AS T};
use App\Http\Models\{Model,AccountPayableModel AS M}; // Include the models class
use Illuminate\Support\Facades\DB;
class SubmitToApprovalMortgageController extends Controller{
  private $_viewTable = '';
  
  public function __construct(){
    $this->_viewTable  = T::$vendorMortgageView;
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
      
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'orderField'   => ['vendor_mortgage_id'],
      'tablez'       => [T::$vendorMortgage],
      'includeCdate' => 0,
      'setting'      => [
        'rule'       => [
          'vendor_mortgage_id' => 'nullable',
        ]
      ]
    ]);
    
    $vData    = $valid['data'];
    $must     = !empty($vData['vendor_mortgage_id'])  ? ['vendor_mortgage_id'=>$vData['vendor_mortgage_id']] : [];
    $data     = Helper::getElasticResult(M::getMortgageElastic($must,['vendor_mortgage_id','gl_acct_ap','gl_acct_liability']));
    
    $glAcct   = [];
    foreach($data as $i => $v){
      $source    = Helper::getValue('_source',$v,[]);
      $glAcct    = array_merge($glAcct,!empty($source['gl_acct_ap']) ? [$source['gl_acct_ap']] : []);
      $glAcct    = array_merge($glAcct,!empty($source['gl_acct_liability']) ? [$source['gl_acct_liability']] : []);
    }
    
    $glAcct   = array_unique($glAcct);
    $glOption = array_combine($glAcct,$glAcct);
    $r        = ['gl_acct_ap'=>$glOption,'vendor_mortgage_id'=>$vData['vendor_mortgage_id']];
    $form     = Form::generateForm([
      'tablez'      => $this->_getTable(__FUNCTION__),
      'orderField'  => $this->_getOrderField(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__,$req,$r),
      'button'      => $this->_getButton(__FUNCTION__),
    ]);
    $html     = Html::tag('form',$form,['id'=>'mortgageApprovalForm', 'class'=>'form-horizontal']);
    return ['html'=>$html,'ids'=>$vData['vendor_mortgage_id']];
  }
//------------------------------------------------------------------------------
  public function store(Request $req){  
    $valid = V::startValidate([
      'rawReq'            => $req->all(),
      'orderField'        => $this->_getOrderField(__FUNCTION__,$req),
      'setting'           => $this->_getSetting(__FUNCTION__,$req),
      'tablez'            => $this->_getTable(__FUNCTION__),
      'includeCdate'      => 0,
    ]);
    $vData      = $valid['data'];
    $ids        = $vData['vendor_mortgage_id'];
    $amountCol  = $vData['note'];
    $usid       = Helper::getUsid($req);
    V::validateionDatabase(['mustExist'=>[T::$glChart . '|gl_acct']],['data'=>['gl_acct'=>$vData['gl_acct_ap']]]);
    $must       = !empty($ids) ? ['query' =>['must'=>['vendor_mortgage_id' => $ids]]] : [];
    $queryBody  = [
      'index'    => T::$vendorMortgageView,
      '_source'  => ['vendor_mortgage_id','vendor_id','vendid','street','prop','note','amount','additional_principal','loan_date','invoice',T::$fileUpload],
    ];
    $queryBody += $must;
    $r          = Helper::getElasticResult(Elastic::searchQuery($queryBody));
    $r          = array_column($r,'_source');
    
    $mortgageIds     = array_column($r,'vendor_mortgage_id');
    $vendorPayments  = Helper::keyFieldNameElastic(M::getVendorPaymentElastic(['foreign_id'=>$mortgageIds,'type'=>'mortgage'],['vendor_payment_id','foreign_id'],0),'foreign_id','foreign_id');
    $insertData = $foreignIds = $response = $fileInsertData = $success = $elastic = $existKeys = $foreignIdMap = $existIds = [];
    foreach($r as $i => $source){
      if(empty($vendorPayments[$source['vendor_mortgage_id']])){
        $row                 = Helper::selectData(['vendor_id','vendid','prop','invoice'],$source);
        $remark              = Helper::getValue('street',$source) . '*' . $source['invoice'];
        $row['remark']       = $remark;
        $row['gl_acct']      = $vData['gl_acct_ap'];
        $row['amount']       = Helper::getValue($amountCol,$source,0);
        $row['type']         = 'mortgage';
        $row['foreign_id']   = $source['vendor_mortgage_id'];
        $row['invoice_date'] = $vData['invoice_date'];
        $row['unit']         = '';
        $row['tenant']       = '';
      
        $rService            = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$source['prop']])),'service');
        $rGlChart            = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$source['prop']])),'gl_acct');
        $insertRow           = HelperMysql::getDataSet([T::$vendorPayment=>$row],$usid,$rGlChart,$rService);
        $insertData[T::$vendorPayment][]  = $insertRow[T::$vendorPayment];
      } else {
        $existIds[]          = $source['vendor_mortgage_id'];
      }
    }
    ############### DATABASE SECTION ######################   
    try {
      if(!empty($insertData[T::$vendorPayment])){
        DB::beginTransaction();
        $success       = Model::insert($insertData);
        $files         = P::generateCopyOfFiles([
          'generatedIds'       => $success['insert:'.T::$vendorPayment],
          'oldType'            => 'mortgage'
        ]);

        $success      += !empty($files) ? Model::insert([T::$fileUpload=>$files]) : [];
        $elastic = [
          'insert'  => [
            T::$vendorPaymentView   => ['vp.vendor_payment_id'  =>$success['insert:' . T::$vendorPayment]]
          ]
        ];
        Model::commit([
          'success' => $success,
          'elastic' => $elastic,
        ]); 
      }
      
      $response['msg']   = $this->_getSuccessMsg(__FUNCTION__,Helper::getValue('insert:'.T::$vendorPayment,$success,[]),$existIds);
    } catch (\Exception $e) {
      $response['error']['mainMsg']  = $this->_getErrorMsg(__FUNCTION__);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField  = [
      'create'   => ['index_title','note','gl_acct_ap','invoice_date'],
      'store'    => ['vendor_mortgage_id','note','gl_acct_ap','invoice_date'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $tablez   = [
      'create'   => [T::$vendorMortgage,T::$vendorPayment],
      'store'    => [T::$vendorMortgage,T::$vendorPayment],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $glOpt   = Helper::getValue('gl_acct_ap',$default,[]);
    $option  = [0=>'Request All Mortgages to be Approved'];
    $num     = !empty($default['vendor_mortgage_id']) ? count($default['vendor_mortgage_id']) : 0;
    $option += !empty($num) ? [$num => 'Request ' . $num . ' Mortgage(s) to be Approved'] : [];
    $setting = [
      'create'  => [
        'field' => [
          'gl_acct_ap'    => ['type'=>'option','option'=>$glOpt,'label'=>'Gl Acct','req'=>1],
          'invoice_date'  => ['value'=>date('m/d/Y'),'req'=>1],
          'note'          => ['label'=>'Choose Payment Type','type'=>'option','option'=>['amount'=>'Monthly Amount','additional_principal'=>'Additional Principal'],'value'=>'amount','req'=>1],
          'index_title'   => ['type'=>'option','label'=>'How Many Transactions ?','option'=>$option,'req'=>1],
        ],
        'rule'  => [
          'vendor_mortgage_id' => 'nullable',
          'gl_acct_ap'         => 'required|string',
          'note'               => 'required|string',
        ]
      ],
      
    ];
    $setting['store']   = $setting['create'];
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $button = [
      'create'  => ['submit' => ['id'=>'submit','value'=>'Send Mortgage(s) to Approval','class'=>'col-sm-12']],
    ];
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$ids=[],$error=[]){
    $num  = !empty($ids) ? count($ids) : 1;
    $data = [
      'store'  => (!empty($ids) ? Html::sucMsg($num . ' Mortgage(s) successfully submitted') : '') . (!empty($error) ? Html::br() . Html::errMsg(count($error) . ' Mortgage(s) already submitted') : ''),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store'  => Html::errMsg('Error generating Mortgage(s)'),
    ];
    return $data[$name];
  }
################################################################################
############################ HELPER FUNCTIONS ##################################
################################################################################
}
