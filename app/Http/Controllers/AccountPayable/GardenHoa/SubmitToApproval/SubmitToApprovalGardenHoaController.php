<?php
namespace App\Http\Controllers\AccountPayable\GardenHoa\SubmitToApproval;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use App\Library\{Elastic, Form, Html, Helper, V, Format, HelperMysql, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
class SubmitToApprovalGardenHoaController extends Controller{
  private $_viewTable = '';
  private $_viewPath  = 'app/AccountPayable/GardenHoa/submitToApproval/';
  
  public function __construct(){
    $this->_viewTable  = T::$vendorGardenHoaView;
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
      
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page     = $this->_viewPath . 'create';
    $form     = Form::generateForm([
      'tablez'      => $this->_getTable(__FUNCTION__),
      'button'      => $this->_getButton(__FUNCTION__),
      'orderField'  => $this->_getOrderField(__FUNCTION__,$req),
      'setting'     => $this->_getSetting(__FUNCTION__,$req),
    ]);
    
    return view($page,[
        'data'  => [
          'submitGardenHoaForm'  => $form
        ]
     ]);

  }
//------------------------------------------------------------------------------
  public function store(Request $req){  
    $valid = V::startValidate([
      'rawReq'            => $req->all(),
      'orderField'        => $this->_getOrderField(__FUNCTION__,$req),
      'setting'           => $this->_getSetting(__FUNCTION__,$req),
      'tablez'            => $this->_getTable(__FUNCTION__),
      'includeCdate'      => 0,
      'isPopupMsgError'   => 1,
    ]);
    
    $vData      = $valid['data'];
    $usid       = Helper::getUsid($req);
    $props      = Helper::getValue('prop',Helper::explodeField($vData,['prop','group1']),[]);
    $vendorId   = Helper::getValue('vendor_id',Helper::explodeFieldTable($vData,['vendid'],T::$vendor,['vendid','vendor_id']),[]);
    $invoiceDate= Format::mysqlDate($vData['invoice_date']);
    $queryBody  = [
      'index'    => T::$vendorGardenHoaView,
      '_source'  => ['vendor_gardenHoa_id','invoice','amount','prop','vendid','gl_acct','vendor_id','remark','stop_pay',T::$vendorPayment],
      'query'    =>[
        'must'   =>[
          'prop' =>$props
        ]
      ]
    ];
    
    $queryBody['query']['must'] += !empty($vendorId) ? ['vendor_id'=>$vendorId] : [];
    $r          = Helper::getElasticResult(Elastic::searchQuery($queryBody));
    $insertData = $hoaIds = $fileInsertData = $response = $success = $elastic = $existKeys = [];
    foreach($r as $i => $v){
      $source              = $v['_source'];
      if(!$this->_hasPaymentDate($source,$invoiceDate)){
        $hoaIds[]            = $source['vendor_gardenHoa_id'];
        $row                 = Helper::selectData(['vendor_id','vendid','gl_acct','prop','remark'],$source);
        $row['invoice']      = Helper::getValue('invoice',$source);
        $row['amount']       = $source['stop_pay'] === 'yes' ? 0 : Helper::getValue('amount',$source,0);
        $row['invoice_date'] = $invoiceDate;
        $row['type']         = 'gardenHoa';
        $row['unit']         = '';
        $row['tenant']       = '';
        $row['foreign_id']   = $source['vendor_gardenHoa_id'];
      
        $rService            = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$source['prop']])),'service');
        $rGlChart            = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$source['prop']])),'gl_acct');
      
        $insertRow           = HelperMysql::getDataSet([T::$vendorPayment=>$row],$usid,$rService,$rGlChart);
        $insertData[T::$vendorPayment][] = $insertRow[T::$vendorPayment];
      } else {
        $existKeys[$source['prop'] . $source['vendid']] = Helper::selectData(['prop','vendid'],$source);
      }
    }
    ############### DATABASE SECTION ######################
    
    try {
      if(!empty($hoaIds)){
        DB::beginTransaction();
        $success = Model::insert($insertData);
        $files   = P::generateCopyOfFiles(['generatedIds'=>$success['insert:'.T::$vendorPayment],'oldType'=>'gardenHoa']);

        $success  += !empty($files) ? Model::insert([T::$fileUpload=>$files]) : [];
        $elastic = [
          'insert'  => [
            T::$vendorGardenHoaView => ['vg.vendor_gardenhoa_id'=>$hoaIds],
            T::$vendorPaymentView   => ['vp.vendor_payment_id'  =>$success['insert:' . T::$vendorPayment]]
          ]
        ];
        Model::commit([
          'success' => $success,
          'elastic' => $elastic,
        ]);
      }
      
      $response            +=  !empty($existKeys) ? ['sideErrMsg' => $this->_getSideErrMsg($existKeys,$invoiceDate)] : [];
      $response            +=  !empty($hoaIds)    ? ['sideMsg'    => $this->_getSuccessMsg(__FUNCTION__,$hoaIds)] : [];
      $response['success']  = 1; 
    } catch (\Exception $e) {
      $response['error']['msg']  = $this->_getErrorMsg(__FUNCTION__);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField  = [
      'create' => ['prop','group1','vendid','invoice_date'],
      'store'  => ['prop','group1','vendid','invoice_date'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $tablez   = [
      'create'   => [T::$vendorGardenHoa,T::$vendorPayment,T::$prop],
      'store'    => [T::$vendorGardenHoa,T::$vendorPayment],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $setting = [
      'create' => [
        'field' => [
          'prop'         => ['id'=>'prop','label'=>'Prop','type'=>'textarea','placeHolder'=>'Ex. 0001-9999, 0028', 'value'=>'0001-9999','req'=>1],
          'group1'       => ['id'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
          'vendid'       => ['id'=>'vendid','label'=>'Vendor Id','type'=>'textarea','placeHolder'=>'Ex. ABSOLU-GAR016, ALFRIO,ZAVALA'],
          'invoice_date' => ['id'=>'invoice_date','label'=>'Paid Period','class'=>'date','value'=>date('m/d/Y'),'req'=>1],
        ],
        'rule'  => [
          'prop'    => 'required|string',
          'group1'  => 'nullable|string',
          'vendid'  => 'nullable|string',
        ]
      ]
    ];
    
    $setting['store']   = $setting['create'];
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $button = [
      'create'  => ['submit' => ['id'=>'submit','value'=>'Generate Garden HOA(s)','class'=>'col-sm-12']],
    ];
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$ids=[]){
    $num  = !empty($ids) ? count($ids) : 1;
    $data = [
      'store'  => Html::sucMsg($num . ' Garden HOA payment(s) successfully submitted'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store'  => Html::errMsg('Error generating Garden HOA payment(s)'),
    ];
    return $data[$name];
  }
################################################################################
############################ HELPER FUNCTIONS ##################################
################################################################################
//------------------------------------------------------------------------------
  private function _hasPaymentDate($source,$invoiceDate){
    $payment   = Helper::getValue(T::$vendorPayment,$source,[]);
    $code      = date('M-Y',strtotime($invoiceDate));
    foreach($payment as $i => $v){
      $date       = Helper::getValue('invoice_date',$v,'1969-12-31');
      $dateCode   = date('M-Y',strtotime($date));
      if($code === $dateCode){
        return true;
      }
    }
    return false;
  }
//------------------------------------------------------------------------------
  private function _getSideErrMsg($r,$invoiceDate){
    $msg = [];
    foreach($r as $i => $v){
      $msg[] = 'Payment for Vendor: ' . Helper::getValue('vendid',$v) . ' and Property Number: ' . Helper::getValue('prop',$v) . ' for ' . date('M Y',strtotime($invoiceDate)) . ' already exists.';
    }
    return Html::errMsg(implode(Html::br(),$msg));
  }
}
