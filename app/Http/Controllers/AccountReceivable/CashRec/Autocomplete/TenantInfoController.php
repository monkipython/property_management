<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\Autocomplete;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper, HelperMysql, TenantTrans, Format};
use App\Http\Models\CashRecModel AS M; // Include the models class
class TenantInfoController extends Controller{
  private static $_instance;
  /**
  * @desc this getInstance is important because to activate __contract we need to call getInstance() first
  */
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }
//------------------------------------------------------------------------------  
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $orderField = isset($req['tenant']) && $req['tenant'] != '' ? ['prop', 'unit', 'tenant'] : ['prop', 'unit'];
//    $orderField = isset($req['tenant']) ? ['prop', 'unit'] : ['prop', 'unit'];
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$tenant], 
      'orderField'      => $orderField, 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          T::$unit . '|prop,unit',
        ]
      ]
    ]);
    $vData = $valid['data'];
    ##### tnt_security_depsoit #####      
    $rDeposit = M::getTableData(T::$tntSecurityDeposit, Helper::selectData(['prop', 'unit'], $vData), ['tenant', 'amount', 'date1', 'gl_acct', 'usid', 'tx_code']);
    $rDeposit = Helper::groupBy($rDeposit, 'tenant');
    $includeField = ['prop','unit','tenant', 'tnt_name','move_in_date', 'move_out_date', 'street', 'city', 'base_rent', 'status' , 'state','zip', 'bedrooms', 'bathrooms' , 'billing.amount', 'billing.stop_date', 'billing.gl_acct', 'billing.schedule'];
    $r = HelperMysql::getTenant(Helper::getPropUnitMustQuery($vData, [], 0), $includeField,['sort'=>['status.keyword'=>'ASC', 'tenant'=>'DESC']], 0);
    $option = $tenantInfo = [];
    $sltTenant = '';
    foreach($r as $i=>$v){
      $v = $v['_source'];
      $sltTenantDefault = ($i == 0) ? $v['tenant'] : $sltTenant;
      $sltTenant = isset($vData['tenant']) ? $vData['tenant'] : $sltTenantDefault;
      $option[$v['tenant']] = $v['tenant'] . ' ('.$v['status'].')' . ' - ' . $v['tnt_name'];
      
      $tableData = [
        ['col1'=>['val'=>Html::ub('TENANT INFORMATION'), 'param'=>['colspan'=>2, 'class'=>'text-center text-info']]],
//        ['col1'=>['val'=>Html::b('Tenant Name:')],  'col2'=>['val'=>$v['tnt_name']]],
        ['col1'=>['val'=>Html::b('Tenat Rent:')],  'col2'=>['val'=>Format::usMoney(Helper::getRentFromBilling($v))]],
        ['col1'=>['val'=>Html::b('Move In - Out Date:')], 'col2'=>['val'=>Format::usDate($v['move_in_date']) . ' - ' . Format::usDate($v['move_out_date'])]],
        ['col1'=>['val'=>Html::b('Street:')], 'col2'=>['val'=>$v['street']]],
        ['col1'=>['val'=>Html::b('City/State/Zip:')], 'col2'=>['val'=>$v['city'] . ', ' . $v['state'] . ' ' . $v['zip']]],
        ['col1'=>['val'=>Html::b('Bed/bath:')], 'col2'=>['val'=>$v['bedrooms'] . '/' . $v['bathrooms']]],
      ];
      
      ##### DEAL WITH DEPOSIT ##### 
      $deposit = isset($rDeposit[$v['tenant']]) ? $rDeposit[$v['tenant']] : [];
      $tenantInfo[$v['tenant']]  = Html::buildTable(['data'=>$tableData,'isHeader'=>0, 'isOrderList'=>0, 'tableParam'=>['class'=>'table']]);
      $tenantInfo[$v['tenant']] .= $this->getTenantDepositDetail($v, $deposit, $perm);
    }
    return ['html'=>Html::buildOption($option, $sltTenant), 'sltTenant'=>$sltTenant, 'tenantInfo'=>$tenantInfo];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  public static function getTenantDepositDetail($vData, $deposit, $perm, $isIncludeDiv = 1){
    $rVendorPayment   = M::getTableData(T::$vendorPayment, ['type'=>'deposit_refund', 'vendid'=>implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $vData))], '*', 1);
    $tableDepositData = [];
    $totalDeposit     = 0;
    $depositBtn       = '';
    
    $tableDepositData[] = ['col1'=>['val'=>Html::ub('TENANT DEPOSIT DETAIL'), 'param'=>['colspan'=>4, 'class'=>'text-center text-info']]];
    $tableDepositData[] = ['col1'=>['val'=>Html::ub('Date')], 'col3'=>['val'=>Html::ub('Updated By')],'col4'=>['val'=>Html::ub('GL')], 'col5'=>['val'=>Html::ub('Amount'), 'param'=>['class'=>'text-right']]];
    foreach($deposit as $val){
      $tableDepositData[] = ['col1'=>['val'=>$val['date1']],'col3'=>['val'=>explode('@', $val['usid'])[0]],'col4'=>['val'=>$val['gl_acct']], 'col5'=>['val'=>Format::usMoney($val['amount']),'param'=>['class'=>'text-right']]];
      $totalDeposit += $val['amount'];
    }
    $totalDepositLabel = ($totalDeposit > 0) ? 'Security Deposit Held as of ' . Helper::usDate() : 'Move-Out Charges: ';
    $totalDepositcolor = ($totalDeposit > 0) ? ' text-green' : ' text-red';


    if(((!empty($rVendorPayment) && $rVendorPayment['print']) || empty($rVendorPayment)) && $totalDeposit > 0 && isset($perm['depositRefund'])){
      $depositBtn = Html::span(Html::icon('fa fa-fw fa-money'). ' Refund Deposit', ['class'=>'btn btn-xs btn-success', 'id'=>'issueDeposit']);
    } else if(!empty($rVendorPayment) && isset($perm['depositRefund']) && empty($rVendorPayment['print'])){
      $depositBtn = Html::span(Html::icon('fa fa-fw fa-money'). ' Reverse Deposit', ['class'=>'btn btn-xs btn-danger', 'id'=>'reverseIssueDeposit']);
      $depositBtn .= Html::br() . 'Pending Deposit Refund'. Html::br() . 'Check In Approval';
    }
    
    $depositBtn = (isset($vData['status']) && $vData['status'] == 'P') ? '' : $depositBtn;
    if(!empty($rVendorPayment) && $rVendorPayment['print']){
      $depositBtn .= isset($rVendorPayment['check_no']) ? Html::b('Check Issued (' . $rVendorPayment['check_no'] . ') ' . Html::br() . 'On ' . Format::usDate($rVendorPayment['posted_date']) . ' ' . Html::icon('fa fa-fw fa-check'), ['class'=>'text-success']) : '';
    }
    
//    else{
//      if($totalDeposit > 0 && isset($perm['depositRefund'])){
//        ##### TAKE CARE OF THE ISSUE DEPOSIT #####
//        $depositBtn = Html::span(Html::icon('fa fa-fw fa-money'). ' Refund Deposit', ['class'=>'btn btn-xs btn-success', 'id'=>'issueDeposit']);
//      } else if(!empty($rVendorPayment) || ($totalDeposit == 0 && !empty($deposit) && isset($perm['depositReverse']))){
//        ##### TAKE CARE OF THE UNDO ISSUE DEPOSIT #####
//        $depositBtn = Html::span(Html::icon('fa fa-fw fa-money'). ' Reverse Deposit', ['class'=>'btn btn-xs btn-danger', 'id'=>'reverseIssueDeposit']);
//      }  
//    }
    $tableDepositData[] = ['col1'=>['val'=>$depositBtn], 'col2'=>['val'=>Html::bu($totalDepositLabel), 'param'=>['colspan'=>2, 'class'=>'text-right' . $totalDepositcolor]], 'col3'=>['val'=>Html::bu(Format::usMoney($totalDeposit)), 'param'=>['class'=>'text-right' . $totalDepositcolor]]];
    $table = Html::buildTable(['data'=>$tableDepositData,'isHeader'=>0, 'isOrderList'=>0, 'tableParam'=>['class'=>'table']]);
    return  $isIncludeDiv ? Html::div($table , ['id'=>'tenantDepositDetailWrapper']) : $table;
  }
}
