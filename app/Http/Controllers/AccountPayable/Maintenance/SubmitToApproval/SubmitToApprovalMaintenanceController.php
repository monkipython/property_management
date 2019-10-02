<?php
namespace App\Http\Controllers\AccountPayable\Maintenance\SubmitToApproval;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\AccountPayableController AS P;
use App\Library\{Elastic, Form, Html, Helper, V, Format, HelperMysql, TableName AS T};
use Illuminate\Support\Facades\DB;
use App\Http\Models\{Model,AccountPayableModel AS M}; // Include the models class

class SubmitToApprovalMaintenanceController extends Controller{
  private $_viewTable = '';
  private $_viewPath  = 'app/AccountPayable/Maintenance/submitToApproval/';
  
  public function __construct(){
    $this->_viewTable  = T::$vendorMaintenanceView;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page     = $this->_viewPath . 'create';
    $form     = implode('',Form::generateField($this->_getFields()));
    return view($page,[
        'data'  => [
          'submitForm'  => $form
        ]
     ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){  
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'rule'            => $this->_getRule(),
      'includeCdate'    => 0,
      'isPopupMsgError' => 1,
    ]);

    $vData      = $valid['data'];
    $usid       = Helper::getUsid($req);
    $groupArr   = $this->_explodeFieldInput($vData['group1'],T::$prop,'group1');
    $vendidArr  = $this->_explodeFieldInput($vData['vendid'],T::$vendor,'vendid');
    $payPeriod  = Format::mysqlDate($vData['paid_period']);
    $groupByOpt = $vData['group_by'];
    
    $groupBySql = [
      'group1-vendid-gl_acct'  => 'vm.vendid,p.group1,vm.gl_acct',
      'vendid-gl_acct'         => 'vm.vendid,vm.gl_acct',
      'vendid'                 => 'vm.vendid',
    ];
    
    $groupBySqlCol= Helper::getValue($groupByOpt,$groupBySql,'vm.vendid');
    
    $whereStr     = '';
    $whereStr    .= !empty($vendidArr) ? Model::getRawWhere(['vm.vendid'=>$vendidArr]) : '';
    $whereStr    .= !empty($groupArr) ? Model::getRawWhere(['p.group1'=>$groupArr]) : '';
    
    $day              = date('d',strtotime($payPeriod));
    $month            = date('m',strtotime($payPeriod));
    $lastDayMonth     = date('d',strtotime(date('Y-m-t',strtotime($payPeriod))));
    $year             = date('Y',strtotime($payPeriod));
    
    $firstDay         = $day > 15 ? 16 : 1;
    $lastDay          = $day > 15 ? $lastDayMonth : 15;
    
    $fromDate         = Format::mysqlDate($year . '-' . $month . '-' . $firstDay);
    $toDate           = Format::mysqlDate($year . '-' . $month . '-' . $lastDay);
    
    $queryBody    = ['index'=>T::$vendorMaintenanceView,'_source'=>['vendor_maintenance_id','prop','vendid','gl_acct','group1','monthly_amount','control_unit']];
    $mustBody     = [];
    $mustBody    += !empty($vendidArr) ? ['vendid.keyword'=>$vendidArr] : [];
    $mustBody    += !empty($groupArr) ? ['group1.keyword'=>$groupArr] : [];
    $mustBody    += ['range'=>['control_unit'=>['gt'=>0]]];
    $queryBody   += !empty($mustBody) ? ['query'=>['must'=>$mustBody]] : [];
    $r            = array_column(Helper::getElasticResult(Elastic::searchQuery($queryBody)),'_source');
    $rProp            = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'      => T::$propView,
      '_source'    => ['prop','ap_bank','street'],
      'query'      => [
        'must_not' => [
          'prop_class.keyword' => 'X'
        ]
      ]
    ]),'prop');
    
    $rVendorPayment      = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$vendorPaymentView,
      '_source'   => ['prop','vendid','gl_acct','foreign_id'],
      'size'      => 50000,
      'query'     => [
        'must'    => [
          'prop.keyword' => array_keys($rProp),
          'type'         => 'maintenance',
          'range'        => [
            'invoice_date'  => [
              'gte'         => $fromDate,
              'lte'         => $toDate,
            ]
          ],
          'active'       => 1,
        ] + (!empty($vendidArr) ? ['vendid.keyword'=>$vendidArr] : []),
      ]
    ]),['prop','vendid','gl_acct']);
    
    $rVendorIds       = Helper::keyFieldNameElastic(M::getVendorElastic(['vendid.keyword'=>array_column($r,'vendid')]),'vendid','vendor_id');
    $rControlUnits    = $this->_groupControlUnitById(M::getTotalControlUnit($vData,$whereStr,$groupBySqlCol),$vData);

    $dataset = $updateIds = $monthlyAmount = $sumAmount = $errorData = $existData = [];

    foreach($r as $i => $source){
      if(!isset($rVendorPayment[$source['prop'] . $source['vendid'] . $source['gl_acct']])){
        $id             = $this->_getIds($source,$vData);
        $totalUnit      = Helper::getValue($id,$rControlUnits,0);
        $controlUnit    = Helper::getValue('control_unit',$source,0);
        $amount         = Helper::getValue('monthly_amount',$source,0);
        $adjustedAmount = $this->_calculateAdjustedAmount($amount,$controlUnit,$totalUnit);
        $rGlChart       = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$source['prop']])),'gl_acct');
        $rService       = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$source['prop']])),'service');
        $vendorId       = Helper::getValue($source['vendid'],$rVendorIds,0);
        $street         = !empty($rProp[$source['prop']]['street']) ? $rProp[$source['prop']]['street'] : '';

        $remark     = $street . ' *' . $month . '/' . $firstDay . '-' . $month . '/' . $lastDay;
        
        $row        = Helper::selectData(['prop','vendid','gl_acct'],$source);
        $row       += [
          'foreign_id'     => $source['vendor_maintenance_id'],
          'type'           => 'maintenance',
          'bank'           => Helper::getValue('ap_bank',Helper::getValue($source['prop'],$rProp,[]),0),
          'invoice_date'   => $payPeriod,
          'vendor_id'      => $vendorId,
          'amount'         => $adjustedAmount,
          'unit'           => '',
          'tenant'         => 255,
          'remark'         => $remark,
        ];
        
        $data           = HelperMysql::getDataSet([T::$vendorPayment=>$row],$usid,$rGlChart,$rService);
        $dataset[$id][] = $data[T::$vendorPayment];
        
        $updateIds[]        = $source['vendor_maintenance_id'];
        $sumAmount[$id]     = isset($sumAmount[$id]) ? $sumAmount[$id] + $adjustedAmount : $adjustedAmount;
        $monthlyAmount[$id] = round($source['monthly_amount'] / 2.0,2);
      } else {
        $existData[$source['prop'] . $source['vendid']] = Helper::selectData(['prop','vendid'],$source);
      }
    }
    $insertData = [];
    if(!empty($dataset)){
      foreach($dataset as $id =>$val){
        $remain   = round($sumAmount[$id] - $monthlyAmount[$id],2);
        foreach($val as $i => $v){
          $v['amount']  = ($i == 0) ? abs($v['amount'] - $remain) : abs($v['amount']);
          $insertData[T::$vendorPayment][] = $v;
        }
      }
    }

    ############### DATABASE SECTION ######################
    $success = $elastic = $commit = $response = [];
    try {
      if(!empty($insertData)){
        DB::beginTransaction();
        $success = Model::insert($insertData);
        $files   = P::generateCopyOfFiles(['generatedIds'=>$success['insert:'.T::$vendorPayment],'oldType'=>P::getInstance()->viewFileUploadTypes[T::$vendorMaintenanceView]]);

        $success  += !empty($files) ? Model::insert([T::$fileUpload=>$files]) : [];
        $elastic = [
          'insert'  => [
            T::$vendorMaintenanceView => ['vm.vendor_maintenance_id'=>$updateIds],
            T::$vendorPaymentView     => ['vp.vendor_payment_id'  =>$success['insert:' . T::$vendorPayment]]
          ]
        ];
        Model::commit([
          'success' => $success,
          'elastic' => $elastic,
        ]);
      }
      
      
      $response            +=  !empty($existData) ? ['sideErrMsg' => $this->_getSideErrMsg($existData,$payPeriod)] : [];
      if(empty($response['sideErrMsg']) && empty($insertData)){
          $response['sideErrMsg'] = $this->_getErrorMsg(__FUNCTION__);
      }
      $response            +=  !empty($updateIds)    ? ['sideMsg'    => $this->_getSuccessMsg(__FUNCTION__,$updateIds)] : [];
      $response['success']  = 1; 
    } catch (\Exception $e) {
      $response['error']['msg']  = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _getFields(){
    $groupOptions   = [
      'vendid'                 => 'Vendid',
      'vendid-gl_acct'         => 'Vendid-Gl_Acct',
      'group1-vendid-gl_acct'  => 'Group-Vendid-Gl_Acct',
    ];
    
    return [
      'vendid'      => ['id'=>'vendid','label'=>'Vendor Code','type'=>'textarea','value'=>'ALL','req'=>1],
      'group1'      => ['id'=>'group1','label'=>'Groups','type'=>'textarea','value'=>'ALL','req'=>1],
      'paid_period' => ['id'=>'paid_period','label'=>'Paid Period','type'=>'text','value'=>date('m/d/Y'),'class'=>'date','req'=>1],
      'group_by'    => ['id'=>'group_by','label'=>'Group By','type'=>'option','option'=>$groupOptions,'value'=>'vendid','req'=>1],
    ];
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'vendid'         => 'required|string',
      'group1'         => 'required|string',
      'paid_period'    => 'required|string|between:8,10',
      'group_by'       => 'required|string',
    ];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$ids=[]){
    $num  = !empty($ids) ? count($ids) : 1;
    $data = [
      'store'  => Html::sucMsg($num . ' Maintenance payment(s) successfully submitted'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store'  => Html::errMsg('The maintenance records you are trying to generate payments for do not exist.' . Html::br() . 'Check your input and please try again.'),
    ];
    return $data[$name];
  }
################################################################################
############################ HELPER FUNCTIONS ##################################
################################################################################
//------------------------------------------------------------------------------
  private function _explodeFieldInput($inputStr,$table,$field,$allToken='all'){
    $tokens = trim(strtolower($inputStr)) !== $allToken ? explode(',',preg_replace('/\s+|\n+/','',$inputStr)) : [];
    if(!empty($tokens)){
      V::validateionDatabase(['mustExist'=>[$table . '|' . $field]],['data'=>[$field=>$tokens]]);
    }
    return $tokens;
  }
//------------------------------------------------------------------------------
  private function _getSideErrMsg($r,$date){
    $msg = [];
    foreach($r as $i => $v){
      $msg[] = 'Payment for Vendor: ' . Helper::getValue('vendid',$v) . ' and Property Number: ' . Helper::getValue('prop',$v) . ' for ' . date('M Y',strtotime($date)) . ' already exists.';
    }
    return Html::errMsg(implode(Html::br(),$msg));
  }
//------------------------------------------------------------------------------
  private function _getIds($v,$vData){
    $option   = Helper::getValue('group_by',$vData,'vendid');
    $getKeys  = explode('-',$option);
    $key      = '';
    foreach($getKeys as $val){
      $key   .= Helper::getValue($val,$v);
    }
    return $key;
  }
//------------------------------------------------------------------------------
  private function _calculateAdjustedAmount($amount,$controlUnit,$totalControlUnit){
    return !empty($totalControlUnit) ? round( (($amount * $controlUnit) / $totalControlUnit)/ 2.0,0,PHP_ROUND_HALF_UP): 0;
  }
//------------------------------------------------------------------------------
  private function _groupControlUnitById($r,$vData){
    $data = [];
    foreach($r as $i => $v){
      $id          = $this->_getIds($v,$vData);
      $controlUnit = Helper::getValue('totalControlUnit',$v,0);
      $data[$id]   = isset($data[$id]) ? $data[$id] + $controlUnit : $controlUnit;
    }
    return $data;
  }
}
