<?php
namespace App\Http\Controllers\AccountPayable\PendingCheck\SubmitToApproval;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\AccountPayableController  AS P;
use App\Library\{Elastic, Html, Helper, V, HelperMysql, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
use App\Http\Models\AccountPayableModel AS M; // Include the models class
class SubmitToApprovalPendingCheckController extends Controller{
  private $_viewTable = '';
  
  public function __construct(){
    $this->_viewTable  = T::$vendorPendingCheckView;
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
      
  }
//------------------------------------------------------------------------------
  public function store(Request $req){      
    $valid = V::startValidate([
      'rawReq'            => $req->all(),
      'orderField'        => $this->_getOrderField(__FUNCTION__,$req),
      'setting'           => $this->_getSetting(__FUNCTION__,$req),
      'tablez'            => $this->_getTable(__FUNCTION__),
      'includeCdate'      => 0,
      'validateDatabase'  => [
        'mustExist' => [
          T::$vendorPendingCheck . '|vendor_pending_check_id',
        ]
      ]
    ]);
    $vData   = $valid['dataArr'];
    $usid    = Helper::getUsid($req);
    $ids     = array_column($vData,'vendor_pending_check_id');
    $r       = Helper::keyFieldNameElastic(M::getPendingCheckElastic(['vendor_pending_check_id'=>$ids]),'vendor_pending_check_id');
    $insertData = $insertDeleteData = $updateData = $deleteIds = $updateIds = $fileUploads = [];
    foreach($vData as $i => $v){
      $payment  = [];
      $row      = Helper::getValue($v['vendor_pending_check_id'],$r,[]);
      $fileR    = Helper::getValue(T::$fileUpload,$row,[]);
      $prop     = Helper::getValue('prop',$row);
      $rService = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$prop])),'service');
      $rGlChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$prop])),'gl_acct');
      $payment += Helper::selectData(['prop','unit','tenant','amount','invoice','invoice','gl_acct','remark','invoice_date','bank','vendor_id','vendid'],$row);
      $payment += ['active'=>1,'foreign_id'=>$v['vendor_pending_check_id'],'type'=>'pending_check'];
      
      $payment['prop']               = Helper::getValue('prop',$payment);
      $payment['unit']               = Helper::getValue('unit',$payment);
      $payment['tenant']             = Helper::getValue('tenant',$payment,0);
      $payment['vendid']             = Helper::getValue('vendid',$payment);
      $payment['is_with_signature']  = isset($row['is_need_approved']) && $row['is_need_approved'] == 0 ? 0 : 1;
      $payment['approve']            = isset($row['is_need_approved']) && $row['is_need_approved'] == 0 ? 'Approved' : 'Pending Submission';
      $insertRow           = HelperMysql::getDataset([T::$vendorPayment=>$payment],$usid,$rService,$rGlChart);

      $insertData[T::$vendorPayment][] = $insertRow[T::$vendorPayment];
      $updateIds[]                     = $v['vendor_pending_check_id'];
    }
    
    $updateData = !empty($updateIds) ? [
      T::$vendorPendingCheck => [
        'whereInData' => ['field'=>'vendor_pending_check_id','data'=>$updateIds],
        'updateData'  => ['is_submitted'=>'yes'],
      ]
    ]: [];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try {
      $success    += !empty($insertData) ? Model::insert($insertData) : [];
      $success    += !empty($updateData) ? Model::update($updateData) : [];
      
      $fileUploads = !empty($success['insert:'.T::$vendorPayment]) ? P::generateCopyOfFiles([
        'generatedIds'   => $success['insert:'.T::$vendorPayment],
        'oldType'        => 'pending_check',
      ]) : [];
      
      $success    += !empty($fileUploads) ? Model::insert([T::$fileUpload=>$fileUploads]) : [];
      $commit['elastic']['insert']  = [T::$vendorPaymentView => ['vp.vendor_payment_id'=>$success['insert:'.T::$vendorPayment]]];
      $commit['elastic']['insert'] += !empty($updateData) ? [T::$vendorPendingCheckView=>['vp.vendor_pending_check_id'=>$updateIds]] : [];
      $commit['success']   = $success;
      Model::commit($commit);
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
    } catch(\Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField  = [
      'store'  => ['vendor_pending_check_id'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getTable($fn){
    $tablez   = [
      'store' => [T::$vendorPendingCheck],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $setting = [
      'store'  => [
        'field' => [
          
        ]
      ]
    ];
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store'  => Html::sucMsg('Pending Check(s) Successfully Submitted for Approval'),
    ];
    return $data[$name];
  }
################################################################################
############################ HELPER FUNCTIONS ##################################
################################################################################
//------------------------------------------------------------------------------ 
  private function _getForeignIds($data,$foreignData){
    $newData = [];
    foreach($data as $i => $v){
      foreach($v as $val){
        $val['foreign_id'] = $foreignData[$i];
        $newData[]         = $val;
      }
    }
    return $newData;   
  }
}

