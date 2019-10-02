<?php
namespace App\Http\Controllers\AccountPayable\ManagementFee\SubmitToApproval;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Elastic, Form, Html, Helper, V, Format, HelperMysql, TableName AS T};
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
use App\Http\Models\AccountPayableModel AS M; // Include the models class

class SubmitToApprovalManagementFeeController extends Controller{
  private $_viewTable = '';
  private $_viewPath  = 'app/AccountPayable/ManagementFee/submitToApproval/';
  private $_mapping   = [];
  
  public function __construct(){
    $this->_viewTable  = T::$vendorManagementFeeView;
    $this->_mapping    = Helper::getMapping(['tableName'=>T::$prop]);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page     = $this->_viewPath . 'create';
    $form     = implode('',Form::generateField($this->_getFields()));
    return ['html'=>view($page,[
        'data'  => [
          'submitForm'  => $form
        ]
     ])->render()];

  }
//------------------------------------------------------------------------------
  public function store(Request $req){  
    $valid    = V::startValidate([
      'rawReq'            => $req->all(),
      'rule'              => $this->_getRule(),
      'includeCdate'      => 0,
      'includeUsid'       => 0,
      'isPopupMsgError'   => 1,
      'validateDatabase'  => [
        'mustExist'       => [
          T::$vendor . '|vendid'
        ]
      ]
    ]);  
    
    $vData                  = $valid['data'];
    $vData['invoice_date']  = Format::mysqlDate($vData['invoice_date']);
    $propData               = Helper::explodeProp($vData['prop']);
    $props                  = $propData['prop'];
    
    $vData                 += Helper::splitDateRate($vData['dateRange'],'date1');
    $glAcctSelected         = $this->_explodeGlAcctStr($vData['income_account']);
    $usid                   = Helper::getUsid($req);
    unset($vData['income_account'],$vData['prop'],$vData['dateRange'],$vData['usid']);
    
    $propQuery              = ['prop.keyword'=>$props];
    $propQuery             += !empty($vData['prop_type']) ? ['prop_type.keyword'=>$vData['prop_type']] : [];
    $propQuery             += !empty($vData['mangtgroup']) ? ['mangtgroup.keyword'=>$vData['mangtgroup']] : [];
    $rProp                  = Helper::keyFieldNameElastic(M::getPropElastic($propQuery,['prop']),'prop','prop');

    $rMgtFee                = Helper::getElasticResult(Elastic::searchQuery([
      'index'      => T::$vendorManagementFeeView,
      'query'      => [
        'must'     => ['prop.keyword'=>array_values($rProp)],
        'must_not' => ['prop_class.keyword'=>'X'],
      ]
    ]));

    $vendorId               = Helper::getElasticResultSource(M::getVendorElastic(['vendid.keyword'=>$vData['vendid']],['vendor_id']),1)['vendor_id'];
    $sumParams              = ['prop'=>array_values($props),'gl_acct'=>$glAcctSelected] + Helper::selectData(['date1','todate1'],$vData);
    $managementFeeTotals    = Helper::keyFieldName(M::getManagementFeeInGl($sumParams),'prop');
    $pastPayments        = Helper::keyFieldNameElastic(M::getVendorPaymentElastic([
      'prop'             => $props,
      'vendid.keyword'   => $vData['vendid'],
      'range'            => [
        'invoice_date'   => [
          'gte'          => date('Y-m-01',strtotime($vData['invoice_date'])),
          'lt'           => date('Y-m-01',strtotime($vData['invoice_date'] . ' +1 month'))
        ]
      ],
      'type'             => 'managementfee',
    ],['vendor_payment_id','foreign_id'],0),'foreign_id');

    
    $rGlChart     = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])),'gl_acct');
    $rService     = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>'Z64'])),'service');
    
    $dataset = $existProps = $mgtIds = [];
    foreach($rMgtFee as $i => $v){
      $source     = $v['_source'];
      
      if(empty($pastPayments[$source['prop_id']])){
        $row        = [];
      
        $row              += Helper::selectData(['vendid','invoice_date','gl_acct','remark'],$vData);
        $row              += Helper::selectData(['prop'],$source);
        $row['unit']       = '';
        $row['vendor_id']  = $vendorId;
        $row['type']       = 'managementfee';
        $row['amount']     = !empty($managementFeeTotals[$source['prop']]['result']) ? abs($managementFeeTotals[$source['prop']]['result']) : 0;
        $row['tenant']     = 255;
        $row['bank']       = !empty($managementFeeTotals[$source['prop']]['ap_bank']) ? $managementFeeTotals[$source['prop']]['ap_bank'] :  HelperMysql::getDefaultBank($source['prop']);
        $row['foreign_id'] = $source['prop_id'];
        $mgtIds[]          = $source['prop_id'];
        $dataset[T::$vendorPayment][] = $row;
      } else {
        $existProps[$source['prop']] = $source['prop'];
      }

    }
    
    $insertDataset = !empty($dataset) ? HelperMysql::getDataset($dataset,$usid,$rGlChart,$rService) : [];
    ############### DATABASE SECTION ######################
    $response = $elastic = $success = $commit = [];  
    try {
      if(!empty($mgtIds)){
        DB::beginTransaction();
        $success = Model::insert($insertDataset);
        $elastic = [
          'insert'  => [
            T::$vendorManagementFeeView => ['p.prop_id'=>$mgtIds],
            T::$vendorPaymentView       => ['vp.vendor_payment_id'  =>$success['insert:' . T::$vendorPayment]]
          ]
        ];
        Model::commit([
          'success' => $success,
          'elastic' => $elastic,
        ]);
      }
      
      $response            +=  !empty($existProps) ? ['sideErrMsg'  => $this->_getSideErrMsg($existProps,$vData['invoice_date'])] : [];
      $response            +=  !empty($mgtIds)    ? ['sideMsg'    => $this->_getSuccessMsg(__FUNCTION__,$mgtIds)] : [];
      $response['success']  = 1; 
    } catch (\Exception $e) {
      $response['error']['msg']  = $this->_getErrorMsg(__FUNCTION__);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _getFields(){
    return [
      'prop'           => ['id'=>'prop','label'=>'Property','type'=>'textarea','placeHolder'=>'Ex. 0001-9999, 0028', 'value'=>'0001-9999','req'=>1],
      'mangtgroup'     => ['id'=>'mangtgroup','label'=>'Mgt Group','type'=>'option','option'=>[''=>'Select Management Group'] + $this->_mapping['mangtgroup'],'req'=>0],
      'prop_type'      => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type'],'req'=>0],
      'invoice_date'   => ['id'=>'invoice_date','label'=>'Invoice Date','type'=>'text','value'=>date('m/d/Y'),'class'=>'date','req'=>1],
      'dateRange'      => ['id'=>'dateRange','name'=>'dateRange','label'=>'From/To Journal Date','type'=>'text','class'=>'daterange','req'=>1],
      'income_account' => ['id'=>'income_account','name'=>'income_account','label'=>'Income Account','type'=>'textarea','value'=>'602,606,607,608,609,610,611,612,615,616,617,619','req'=>1],
      'vendid'         => ['id'=>'vendid','label'=>'Mangnt Vendor','type'=>'text','req'=>1,'class'=>'autocomplete'],
      'gl_acct'        => ['id'=>'gl_acct','label'=>'Exp. Account','type'=>'text','value'=>751,'req'=>1],
      'remark'         => ['id'=>'remark','label'=>'Remark','type'=>'text','value'=>'Management Fee ' . date('F Y')],
    ];
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'prop'             => 'required|string',
      'mangtgroup'       => 'nullable|string|between:1,6',
      'prop_type'        => 'nullable|string|between:1,1',
      'invoice_date'     => 'required|string',
      'dateRange'        => 'required|string|between:21,23',
      'income_account'   => 'required|string',
      'vendid'           => 'required|string|between:1,255',
      'gl_acct'          => 'required|string|between:1,9',
      'remark'           => 'required|string|between:1,50',
    ];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$ids=[]){
    $num  = !empty($ids) ? count($ids) : 1;
    $data = [
      'store'  => Html::sucMsg($num . ' Management Fee(s) successfully submitted'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store'  => Html::errMsg('Error submitting Management Fee(s)'),
    ];
    return $data[$name];
  }
################################################################################
############################ HELPER FUNCTIONS ##################################
################################################################################
//------------------------------------------------------------------------------
  private function _explodeGlAcctStr($str){
    $str    = preg_replace('/\s+|\n+/','',$str);
    
    $pieces = explode(',',$str);
    foreach($pieces as $i => $v){
      $validateData  = ['data' => ['gl_acct'=>$v]];
      V::validateionDatabase(['mustExist'=>[T::$glTrans . '|gl_acct']],$validateData);
    }
    return $pieces;
  }
//------------------------------------------------------------------------------
  private function _getSideErrMsg($r,$invoiceDate){
    $msg = [];
    foreach($r as $i => $v){
      $msg[] = 'Management Fee for Property Number: ' . $v. ' for ' . date('F Y',strtotime($invoiceDate)) . ' already exists.';
    }
    return Html::errMsg(implode(Html::br(),$msg));
  }
}
