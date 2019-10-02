<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\LedgerCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountReceivable\CashRec\CashRecController AS P;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper, TenantTrans,Format, HelperMysql};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CashRecModel AS M; // Include the models class

//#DEPOSIT SCRIPT 
//insert into tnt_security_deposit (service_code,tx_code,date1, cntl_no, prop, unit, tenant, amount, batch, check_no, appyto,gl_acct, remark, cdate,usid)
//SELECT service_code,tx_code,date1,cntl_no, prop, unit, tenant, amount, batch, check_no,appyto, gl_acct, remark, '2019-04-25', 'SYS' from tnt_trans where (gl_acct = '607' AND tx_code='P')  OR gl_acct='755';
//#UPdate
//update tnt_security_deposit set amount = (amount * -1) where (gl_acct = '607' AND tx_code='P')

class LedgerCardController extends Controller{
  private $_mapping   = [];
  private $_tab = [];
  private static $_instance;
  private $_maxMonthAllowToFix = 9;
  private $_cutOffDateAllowToFix = '';
  private $_perm = [];
  private $_tenant = [];
  
  public function __construct(){
    $this->_tab = P::getInstance()->tab;
    $this->_mapping = Helper::getMapping(['tableName'=>T::$tntTrans]);
    $this->_mappingTenant = Helper::getMapping(['tableName'=>T::$tenant]);
  }
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
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable('show'), 
      'orderField'      => ['prop', 'unit', 'tenant', 'dateRange'], 
      'setting'         => $this->_getSetting('show', $req), 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tenant . '|prop,unit,tenant'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $r       = HelperMysql::getTenant(Helper::getPropUnitTenantMustQuery($vData, [], 0));
    $deposit = Helper::getValue('dep_held1', $r, 0);
    $balance = TenantTrans::getApplyToSumResult($vData)['balance'];
    
    return [
      'html'=>
        Html::span(Html::icon('fa fa-fw fa-user') . ' Account: ' . Html::b($r['prop'] . '-' . $r['unit'] . '-' . $r['tenant'], ['class'=>'text-info']), ['class'=>'callout-info']) . ' | '  .
        Html::span(Html::icon('fa fa-fw fa-leaf') . ' Status: ' .  Html::b($this->_mappingTenant['status'][$r['status']] . ' (' . $r['spec_code'] . ')', ['class'=>'text-yellow']), ['class'=>'callout-info']) . ' | '  .
        Html::span(Html::icon('fa fa-fw fa-balance-scale') . ' Balance: ' . Html::b(Format::usMoney($balance), ['class'=>($balance <= 0 ? 'text-green' : 'text-danger')]), ['class'=>'callout-info']) . ' | ' .
        Html::span(Html::icon('fa fa-fw fa-dollar') . 'Deposit: ' . Html::b(Format::usMoney($deposit), ['class'=>'text-info']), ['class'=>'callout-info']) . ' | ' .
        Html::span(Html::icon('fa fa-fw fa-link') . 'Link: ' . Html::getLinkIcon($r, ['prop', 'unit', 'tenant', 'group1']))
    ];
  }
//------------------------------------------------------------------------------
  public function show($gridId, Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['prop', 'unit', 'tenant', 'dateRange'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tenant . '|prop,unit,tenant'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $this->_perm   = Helper::getPermission($req);
    $this->_tenant = HelperMysql::getTenant(Helper::getPropUnitTenantMustQuery($vData)['must'], ['status']);
    $this->_cutOffDateAllowToFix = Helper::isAdmin($req) ? '2019-01-01' : '2019-08-01';
    
    TenantTrans::cleanTransaction($vData);
    $sort = $gridId == camel_case($this->_tab['detail']) ? ['cntl_no'=>'asc'] : ['date1'=>'asc', 'tx_code.keyword'=>'asc','cntl_no'=>'asc'];
    $r = TenantTrans::searchTntTrans(['query'=>Helper::getPropUnitTenantMustQuery($vData) ], $sort);
    return $this->getGridData($r, $gridId, Helper::getDateRange($vData));
  }  
//------------------------------------------------------------------------------
  public function create(Request $req){
    $default= P::getDefaulValue($req);
    $fields = [
      'dateRange' => ['id'=>'dateRange','label'=>'View LC Fr/To','type'=>'text','class'=>'daterange','value'=>Helper::getValue('dateRange', $default['value']), 'req'=>1],
      'prop'      => ['id'=>'prop','label'=>'Prop','class'=>'autocomplete', 'type'=>'text','value'=>Helper::getValue('prop', $default['value']), 'req'=>1],
      'unit'      => ['id'=>'unit','label'=>'Unit','class'=>'autocomplete', 'type'=>'text','value'=>Helper::getValue('unit', $default['value']), 'req'=>1],
      'tenant'    => ['id'=>'tenant','label'=>'Tenant','type'=>'option', 'option'=>Helper::getValue('tenant', $default['option']), 'value'=>Helper::getValue('tenant', $default['value']), 'req'=>1],
    ];
    return P::getCreateContent($fields, $this->_tab);
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getTable($fn){
    $tablez = [
      'show'     =>[T::$tntTrans],
      'store'     =>[T::$tntTrans]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    $data = [
      'show'=>[
        'field'=>[
        ],
        'rule'=>[
          'dateRange'=>'required|string|between:21,23',
        ]
      ],
    ];
    return $data[$fn];
  }
//------------------------------------------------------------------------------
  public function getGridData($r, $gridId, $dateRange = ''){
    switch ($gridId) {
      case camel_case($this->_tab['ledgerCard']): return $this->_getLedgerCard(Helper::getElasticResult($r), $dateRange);
      case camel_case($this->_tab['ledgerCardDetail']): return $this->_getLedgerCardDetail(Helper::getElasticResult($r), $dateRange);
      case camel_case($this->_tab['openItem']): return $this->_getOpenItem($r);
      case camel_case($this->_tab['detail']): return $this->_getDetail(Helper::getElasticResult($r));
    }
  }
//------------------------------------------------------------------------------
  private function _getLedgerCard($r, $dateRange){
    $data = [];
    $balance = 0;
    $fromDate = $endDate = '';
    $cntlNo = [];
    foreach($r as $i=>$v){
      $v = $v['_source']; 
      $uid = $v['tx_code'] =='P' ? P::getLedgerCardGroupUID($v) : P::getLedgerCardDetailUID($v);

      $fromDate          = $i == 0 ? $v['date1'] : $fromDate;
      $endDate           = $v['date1'];
      $v['remark']       = $v['tx_code'] =='P' ? 'Payment' : $v['remark']; 
      $v['service_code'] = $v['tx_code'] =='P' ? '' : $v['service_code']; 
      $v['gl_acct']      = $v['tx_code'] =='P' ? '' : $v['gl_acct']; 
      $v['appyto']       = $v['tx_code'] =='P' ? '' : $v['appyto']; 
      $v['invoice']      = $v['tx_code'] =='P' ? '' : $v['invoice']; 
      
      $v['amount'] += isset($data[$uid]['amount']) ? $data[$uid]['amount'] : 0;
      $data[$uid] = $v;
      
      $cntlNo[] = $v['cntl_no'];
    }
    ##### NEED TO DISABLE USING $rCntlNO ####
    $rCntlNo = Helper::keyFieldNameElastic(M::getDataFromTable(T::$trackLedgerCardFixView, ['cntl_no'=>$cntlNo], ['cntl_no']), 'cntl_no', 'cntl_no');
    foreach($data as $v){
//      $v['tx_code'] = $v['tx_code'] == 'S' ? 'P' : $v['tx_code'];
      if($v['tx_code'] != 'S'){
        $balance      += $v['amount'];
        $v['balance']  = $balance;
        if(Helper::isBetweenDateRange($v['date1'], $dateRange)){
          $row[] = $this->_formatData($v, $rCntlNo);
        }
      }
    }
    $endingBalIcon = Html::i('', ['class'=>'fa fa-fw fa-angle-double-right']);
    $row[] = [
      'inAmount'=> '',
      'pAmount' => '',
      'remark' => Html::b($endingBalIcon.'Ending Balance ('. Format::usDate($fromDate) . ' - ' . Format::usDate($endDate) . ')', ['class'=>($balance <= 0 ? 'text-green' : 'text-danger')]) ,
      'balance' => Html::b(Format::usMoneyMinus(Format::floatNumber($balance)) , ['class'=>($balance <= 0 ? 'text-green' : 'text-danger')]) ,
      'balancePdf' => Html::b($endingBalIcon.'Ending Balance ('. Format::usDate($fromDate) . ' - ' . Format::usDate($endDate) . '): ' . Format::usMoneyMinus($balance)) 
    ];    
    return $row;
  }
//------------------------------------------------------------------------------
  private function _getLedgerCardDetail($r, $dateRange){
    $data = [];
    $balance = 0;
    $fromDate = $endDate = '';
    $cntlNo = [];
    foreach($r as $i=>$v){
      $v = $v['_source']; 
      $uid = P::getLedgerCardDetailUID($v);

      $fromDate     = $i == 0 ? $v['date1'] : $fromDate;
      $endDate      = $v['date1'];
      $v['amount'] += isset($data[$uid]['amount']) ? $data[$uid]['amount'] : 0;
      $data[$uid]   = $v;
      
      $cntlNo[] = $v['cntl_no'];
    }
    ##### NEED TO DISABLE USING $rCntlNO ####
    $rCntlNo = Helper::keyFieldNameElastic(M::getDataFromTable(T::$trackLedgerCardFixView, ['cntl_no'=>$cntlNo], ['cntl_no']), 'cntl_no', 'cntl_no');
    foreach($data as $v){
//      $v['tx_code'] = $v['tx_code'] == 'S' ? 'P' : $v['tx_code'];
      if($v['tx_code'] != 'S'){
        $balance      += $v['amount'];
        $v['balance']  = $balance;
        if(Helper::isBetweenDateRange($v['date1'], $dateRange)){
          $row[] = $this->_formatData($v, $rCntlNo);
        }
      }
    }
    
    $endingBalIcon = Html::i('', ['class'=>'fa fa-fw fa-angle-double-right']);
    $row[] = [
      'inAmount'=> '',
      'pAmount' => '',
      'remark' => Html::b($endingBalIcon.'Ending Balance ('. Format::usDate($fromDate) . ' - ' . Format::usDate($endDate) . ')', ['class'=>($balance <= 0 ? 'text-green' : 'text-danger')]) ,
      'balance' => Html::b(Format::usMoneyMinus($balance) , ['class'=>($balance <= 0 ? 'text-green' : 'text-danger')]) ,
      'balancePdf' => Html::b($endingBalIcon.'Ending Balance ('. Format::usDate($fromDate) . ' - ' . Format::usDate($endDate) . '): ' . Format::usMoneyMinus($balance)) 
    ];    
    return $row;
  }
//------------------------------------------------------------------------------
  public function _getOpenItem($r){
    $data = TenantTrans::getOpenItem($r)['data'];
    $balance = 0;
    foreach($data as $v){
      ##### THERE IS AN ISSUE THAT THE GL_ACCT 375 WITH S DO NOT SHOW UP WHEN THERE IS OPEN ITEM #####
//      if($v['tx_code'] != 'S' || ($v['tx_code'] == 'S' && $v['gl_acct'] == '375') ){
        $v['tx_code']  = Format::floatNumber($v['amount']) > 0 ? 'IN' : 'P';
        $balance      += $v['amount'];
        $v['balance']  = $balance;
        $row[] = $this->_formatData($v);
//      }
    }
    $endingBalIcon = Html::i('', ['class'=>'fa fa-fw fa-angle-double-right']);
    $row[] = [
      'inAmount'=> '',
      'pAmount' => '',
      'balance' => Html::b($endingBalIcon.'Ending Balance: ' . Format::usMoneyMinus($balance) , ['class'=>($balance <= 0 ? 'text-green' : 'text-danger')]), 
      'balanceRaw' =>$balance 
    ];
    return $row;
  }
//------------------------------------------------------------------------------
  private function _getDetail($r){
    $row = [];
    $balance = 0;
    foreach($r as $v){
      $v = $v['_source'];
      $balance      += $v['amount'];
      $v['balance']  = $balance;
      $row[] = $this->_formatData($v);
    }
    return $row;
  }
//------------------------------------------------------------------------------
  private function _formatData($v, $rCntlNo = []){
    ##### CHARGE AND PAYMENT SECTION #####
    $v['inAmount']   = ($v['tx_code'] != 'P') ? $v['amount'] : '';
    $v['pAmount']    = ($v['tx_code'] == 'P') ? $v['amount'] : '';
    $v['isEditable'] = $this->_isAllowToFix($rCntlNo, $v);
//    strtotime($v['date1']) < strtotime($this->_cutOffDateAllowToFix) ||  isset($rCntlNo[$v['cntl_no']]) || $maxAllowToFix > strtotime($v['date1']) || !isset($this->_perm['ledgerCardFix']) ? false : true;

    ###### TEXT CODE SECTION ######
    $iconTxcode   = Html::i('', ['class'=>($v['tx_code']=='P' ? 'fa fa-fw fa-dollar' : 'fa fa-fw fa-file-text-o')]);
    $classTxcode  = $v['tx_code'] =='P' ? 'text-green' : 'text-danger';
    $classTxcode  .= $v['tx_code'] =='P' && $v['batch'] >= 100000000 ? ' pointer text_underline' : '';
    $txCode       = isset($this->_mapping['tx_code'][$v['tx_code']]) ? $this->_mapping['tx_code'][$v['tx_code']] : $v['tx_code'];
    $txCode       .= $v['tx_code'] =='P' && $v['batch'] >= 100000000 && !empty($v['job']) ? ' ' . Html::icon('fa fa-fw fa-download') : '';
    $v['tx_code'] = Html::span($iconTxcode . $txCode, ['class'=>$classTxcode]);

    ###### CHARGE AND PAYMENT AND AMOUNT SECTION ######
    $v['inAmount'] = !empty($v['inAmount']) ? Format::usMoneyMinus($v['inAmount']) : '';
    $v['pAmount']  = !empty($v['pAmount'])  ? Format::usMoneyMinus($v['pAmount']) : '';
    $v['amount']   = !empty($v['amount'])  ? Format::usMoneyMinus($v['amount']) : '';

    ######BALANCE SECTION ######
    $balance      = Format::floatNumber($v['balance']);
    $iconBalance  = $balance == 0 ? Html::i('', ['class'=>'fa fa-fw fa-check']) : '';
    $classBalance = $balance <= 0 ? 'text-green' : 'text-danger';
    $v['balance'] = Html::span(Format::usMoneyMinus($balance) . $iconBalance, ['class'=>$classBalance]);
    return $v;
  }
//------------------------------------------------------------------------------
  private function _isAllowToFix($rCntlNo, $v){
    $maxAllowToFix   = strtotime('-' . $this->_maxMonthAllowToFix . ' months');
    $status  = isset($this->_tenant['status']) ? $this->_tenant['status'] : 'C'; 
    return  strtotime($v['date1']) < strtotime($this->_cutOffDateAllowToFix) ||  
            isset($rCntlNo[$v['cntl_no']]) || 
            $maxAllowToFix > strtotime($v['date1']) || 
//            $status == 'P' ||
            !isset($this->_perm['ledgerCardFix']) ? false : true;
  }
}