<?php
namespace App\Http\Controllers\Report\CheckRegisterReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, HelperMysql, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};
use App\Http\Models\{ReportModel AS M}; // Include the models class

class CheckRegisterReportController extends Controller {
  public $typeOption    = ['trust'=>'Trust', 'vendor'=>'Vendor', 'prop'=>'Prop'];
  public $journalOption = ['all'=>'All', 'CR'=>'CR', 'CP'=>'CP', 'JE'=>'JE'];
  public $sortOption    = ['check_no'=>'Check No', 'date1'=>'Date'];
  private $_viewTable   = '';
  private static $_instance;
  public function __construct(){
    $this->_viewTable = T::$glTransView;
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
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
    ]);
    return $this->getData($valid);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $fields = [
      'type'         => ['id'=>'type','label'=>'Group By','type'=>'option', 'option'=>$this->typeOption, 'req'=>1],
      'trust'        => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, *ZA67'],
      'prop'         => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea','placeHolder'=>'Ex. 0001-9999, 0028', 'value'=>'0001-9999'],
      'gl_acct'      => ['id'=>'gl_acct','label'=>'Gl Acct.','type'=>'text','value'=>'110'],
      'togl_acct'    => ['id'=>'togl_acct','label'=>'Gl Acct To.','type'=>'text','value'=>'999'],
      'batch'        => ['id'=>'batch','label'=>'From Batch', 'type'=>'text'],
      'tobatch'      => ['id'=>'tobatch','class'=>'copyTo', 'label'=>'To Batch', 'type'=>'text'],
      'vendid'       => ['id'=>'vendid','label'=>'From Vendor', 'type'=>'text', 'class'=>'autocomplete', 'autocomplete'=>'false', 'hint'=>'You can type vendor name or number for autocomplete'],
      'tovendid'     => ['id'=>'tovendid','class'=>'copyTo autocomplete', 'label'=>'To Vendor', 'type'=>'text', 'autocomplete'=>'false', 'hint'=>'You can type vendor name or number for autocomplete'],
      'check_no'     => ['id'=>'check_no','label'=>'From Check No', 'type'=>'text', 'value'=>'000001','req'=>1],
      'tocheck_no'   => ['id'=>'tocheck_no','class'=>'copyTo', 'label'=>'To Check No', 'type'=>'text', 'value'=>'999999','req'=>1], 
      'dateRange'    => ['id'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange','req'=>1],
      'journal'      => ['id'=>'journal', 'label'=>'Journal', 'type'=>'option', 'option'=>$this->journalOption,'req'=>1],
      'sort1'        => ['id'=>'sort1', 'label'=>'Sort', 'type'=>'option', 'option'=>$this->sortOption,'req'=>1],
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList(), 'tab'=>[]];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData   = $valid['data'];
    $op      = $valid['op'];
    $type    = isset($this->typeOption[$vData['type']]) ? $vData['type'] : 'trust';
    $prop    = Helper::explodeField($vData,['prop','trust'])['prop'];

    $vData  += Helper::splitDateRate($vData['dateRange'],'date1');
    $vData  += Helper::getGlAcctRange($vData);
    unset($vData['dateRange']);
    $selected = Helper::getValue('selected', $vData, 'expand');
    $columnReportList = $this->_getColumnButtonReportList($selected);
    $column = $columnReportList['columns'];
    $sortKey = $vData['sort1'] == 'check_no' ? $vData['sort1'] . '.keyword' : $vData['sort1'];

    if(!empty($op)){
      $must = [];
      $field = P::getSelectedField($columnReportList, 1);
      $must['raw']['must'][]['range']['date1'] = [
        'gte' => $vData['date1'],
        'lte' => $vData['todate1']
      ];
      if(!empty($prop)) {
        $must['raw']['must'][]['terms']['prop'] = $prop;
      }
      if(!empty($vData['check_no']) && !empty($vData['tocheck_no'])) {
        $must['raw']['must'][]['range']['check_no'] = [
          'gte' => $vData['check_no'],
          'lte' => $vData['tocheck_no']
        ];
      }
      if(!empty($vData['batch']) && !empty($vData['tobatch'])) {
        $must['raw']['must'][]['range']['batch'] = [
          'gte' => $vData['batch'],
          'lte' => $vData['tobatch']
        ];
      }
      if(!empty($vData['vendid']) && !empty($vData['tovendid'])) {
        $must['raw']['must'][]['range']['vendor'] = [
          'gte' => strtolower($vData['vendid']),
          'lte' => strtolower($vData['tovendid'])
        ];
      }
      if(!empty($vData['gl_acct']) && !empty($vData['togl_acct'])) {
        $must['raw']['must'][]['bool']['should'] = [
          [
            'range' => [
              'gl_acct_num' => [
                'gte' => $vData['smallGlAcct'],
                'lte' => $vData['smallToGlAcct'],
              ]
            ]
          ],
          [
            'range' => [
               'gl_acct_num' => [
                  'gte' => $vData['bigGlAcct'],
                  'lte' => $vData['bigToGlAcct'] 
               ]
            ]
          ]
        ];
      }
      if($vData['journal'] != 'all') {
        $must['raw']['must'][]['term']['journal.keyword'] = $vData['journal'];
      }
      if($op != 'tab') {
        $r = Elastic::searchQuery([
          'index'  => $this->_viewTable,
          '_source'=> array_merge($field, ['bank','vendor_payment_id','gl_acct_num', 'prop', 'check_no', 'amount', 'seq']),
          'size'   => 20000,
          'sort'   => [$sortKey=>'asc', 'date1'=>'asc'],
          'query'  => $must 
          ]
        );
        $gridData = $this->_getGridData($r, $type, $selected);
      }
      switch ($op) {
        case 'tab':   return $this->_getTabData();
        case 'show':  return $gridData; 
        case 'csv':   return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':   return P::getPdf(P::getPdfData($gridData, $column), ['title'=>'Check Register Report By ' . $this->typeOption[$vData['type']], 'chunk'=>44]);
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'dateRange'      => 'required|string|between:21,23',
      'trust'          => 'nullable|string',
      'prop'           => 'nullable|string',
      'gl_acct'        => 'nullable|string',
      'togl_acct'      => 'nullable|string',
      'batch'          => 'nullable|string',
      'tobatch'        => 'nullable|string',
      'vendid'         => 'nullable|string',
      'tovendid'       => 'nullable|string',
      'check_no'       => 'required|string',
      'tocheck_no'     => 'required|string',
      'type'           => 'required|string',
      'journal'        => 'required|string',
      'sort1'          => 'required|string',
      'selected'       => 'nullable|string|between:6,8'
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($display = 'Expand'){
    $reportList = [
      'pdf'  =>'Download PDF',
      'csv'  =>'Download CSV',
    ];
    $data = [];
    if($display == 'Expand') {
      $data[]   = ['field'=>'empty1','title'=>' ','sortable'=>true,'width'=>400,'hWidth'=>165];
      $data[]   = ['field'=>'check_no','title'=>'Check No','sortable'=>true,'width'=>300,'hWidth'=>115];
      $data[]   = ['field'=>'amount','title'=>'Amount','sortable'=>true,'width'=>100,'hWidth'=>60];
      $data[]   = ['field'=>'remark','title'=>'Description','sortable'=>true,'width'=>300,'hWidth'=>125];
      $data[]   = ['field'=>'vendor','title'=>'Vendor Code','sortable'=>true,'width'=>100,'hWidth'=>65];
      $data[]   = ['field'=>'date1','title'=>'Date','sortable'=>true,'width'=>75,'hWidth'=>50];
      $data[]   = ['field'=>'batch','title'=>'Batch','sortable'=>true,'width'=>75,'hWidth'=>45];
      $data[]   = ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>40,'hWidth'=>40];
      $data[]   = ['field'=>'gl_acct','title'=>'GL','sortable'=>true,'width'=>60,'hWidth'=>45];
      $data[]   = ['field'=>'invoice','title'=>'Invoice','sortable'=>true,'width'=>60,'hWidth'=>40];
      $data[]   = ['field'=>'usid','title'=>'Usid','sortable'=>true,'hWidth'=>60];
    }else if($display == 'Collapse') {
      $data[]   = ['field'=>'empty1','title'=>' ','sortable'=>true,'width'=>300,'hWidth'=>215];
      $data[]   = ['field'=>'check_no','title'=>'Check No','sortable'=>true,'width'=>400,'hWidth'=>200];
      $data[]   = ['field'=>'amount','title'=>'Amount','sortable'=>true,'width'=>300,'hWidth'=>200];
      $data[]   = ['field'=>'vendor','title'=>'Vendor Code','sortable'=>true,'width'=>200,'hWidth'=>125];
      $data[]   = ['field'=>'date1','title'=>'Date','sortable'=>true,'hWidth'=>75];
    }
    
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getTabData() {
    $displays = ['Expand', 'Collapse'];
    $displayTabs = $columns = [];
    foreach($displays as $display) {
      $displayTabs[$display] = Html::table('', ['id'=> $display]);
      $columns[$display] = $this->_getColumnButtonReportList($display);
    }
    $tab = Html::buildTab($displayTabs, ['tabClass'=>'']);
    return ['tab' => $tab, 'column'=>$columns];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r, $type, $selected){
    $result    = Helper::getElasticResult($r);    
    $rows = $data = $props = $totalType = $totalCheckNo = $totalBank = [];
    $lastRow = ['empty1'=>0, 'amount'=>0];

    $props = Helper::keyFieldNameElastic(HelperMysql::getProp([], ['trust', 'prop'], 0), 'prop', 'trust');
    $banks = Helper::keyFieldName(M::getBankList(['prop', 'bank', 'name']), ['bank', 'prop'], 'name');
    $seqList = array_column(array_column($result, '_source'), 'seq');
    
    $rVendorPayment = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   =>T::$vendorPaymentView,
      '_source' =>['seq', 'check_pdf','vendor_payment_id'],
      'query'   =>['must'=>['seq'=> $seqList]]
    ]), 'vendor_payment_id', 'vendor_payment_id');
    $dt = [];
    foreach($result as $val){
      $v = $val['_source'];
      $v['trust']     = Helper::getValue($v['prop'], $props);
      $v['bank_name'] = isset($banks[$v['bank'] . $v['trust']]) ? $banks[$v['bank'] . $v['trust']] : '';
      $dt[$v[$type]][$v['bank']][$v['check_no']][] =  $v;
    }
    ksort($dt);
    // Get the total amount of each grouping
    foreach($dt as $iType=>$type_v){
      foreach($type_v as $bank=>$bank_v){
        foreach($bank_v as $check_no=>$check_no_v){
          foreach($check_no_v as $i=>$v){
              if(!isset($totalType[$iType])) { $totalType[$iType] = 0; }
              if(!isset($totalBank[$bank . $iType])) { $totalBank[$bank . $iType] = 0; }
              if(!isset($totalCheckNo[$check_no])) { $totalCheckNo[$check_no] = 0; }
              $totalType[$iType] += $v['amount'];
              $totalBank[$bank . $iType] += $v['amount'];
              $totalCheckNo[$check_no] += $v['amount'];
              $lastRow['amount'] += $v['amount'];
              $lastRow['empty1']++;
          }
        }
      }
    }
    foreach($dt as $iType => $type_v) {
      $rows[] = [
        'empty1' => Html::bu(title_case($type).':'. $iType ) . ' | '. Html::bu('Grand Total: ' . Html::span(Format::usMoneyMinus($totalType[$iType]), ['class'=>'text-red'])),
      ];
      foreach($type_v as $bank => $bank_v) {
        $rows[] = [
          'empty1'   => Html::repeatChar('&nbsp;',3) . 'Bank: '.$bank.' | Subtotal: '. Html::span(Format::usMoneyMinus($totalBank[$bank . $iType]), ['class'=>'text-red']),
          'check_no' => title_case($v['bank_name']),
        ];
        foreach($bank_v as $check_no => $check_no_v) {
          $empty1Val  = $selected != 'Collapse' ? Html::repeatChar('&nbsp;',6) . 'Check No: '. $check_no . ' | Subtotal: '.Html::span(Format::usMoneyMinus($totalCheckNo[$check_no]), ['class'=>'text-red']) : '';
          $checkNoVal = $selected != 'Collapse' ? '' : $check_no;
          $amountVal  = $selected != 'Collapse' ? '' : Html::span(Format::usMoneyMinus($totalCheckNo[$check_no]), ['class'=>'text-red']);
          $rows[] = [
            'empty1'   => $empty1Val,
            'check_no' => $checkNoVal,
            'amount'   => $amountVal,
            'remark'   => '',
            'vendor'   => $selected != 'Collapse' ? '' : Helper::getValue('vendor', $check_no_v[0]),
            'date1'    => $selected != 'Collapse' ? '' : Format::usDate(Helper::getValue('date1', $check_no_v[0])),
            'batch'    => '',
            'prop'     => '',
            'gl_acct'  => '',
            'invoice'  => '',
            'usid'     => '',
          ];
          if($selected != 'Collapse') {
            foreach($check_no_v as $i=>$v){
              $checkNo = isset($rVendorPayment[$v['vendor_payment_id']]) ? Html::span(Helper::getValue('check_no', $v).' '.Html::icon('fa fa-fw fa-download'), ['class'=>'text-green pointer text_underline checkNo', 'data-vendor-payment-id'=>$rVendorPayment[$v['vendor_payment_id']]]) : Helper::getValue('check_no', $v);
              $rows[] = [
                'empty1'   => '',
                'check_no' => $checkNo,
                'amount'   => Format::usMoneyMinus(Helper::getValue('amount', $v)),
                'remark'   => Helper::getValue('remark', $v),
                'vendor'   => Helper::getValue('vendor', $v),
                'date1'    => Format::usDate(Helper::getValue('date1', $v)),
                'batch'    => Helper::getValue('batch', $v),
                'prop'     => Helper::getValue('prop', $v),
                'gl_acct'  => Helper::getValue('gl_acct', $v),
                'invoice'  => Helper::getValue('invoice', $v),
                'usid'     => Helper::getValue(0,explode('@',Helper::getValue('usid', $v))),
              ];
            }
          }
        }
      }
    }
    $lastRow['empty1']   = Html::b('Total Prop: '. Html::span($lastRow['empty1'], ['class'=>'text-red']) . ' | All Grand Total: '.Html::span(Format::usMoneyMinus($lastRow['amount']),['class'=>'text-red']));
    unset($lastRow['amount']);
    array_unshift($rows, $lastRow);
    return $rows;
  }
}

