<?php
namespace App\Http\Controllers\Report\FundTransferReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController AS P;
use App\Library\{V, Form, Elastic, Html, GridData, TableName AS T, Helper, Format};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\ReportModel AS M; // Include the models class

class FundTransferReportController extends Controller{
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_indexMain   = T::$trackFundTransferView . '/' . T::$trackFundTransferView . '/_search?';
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
      'dateRange'=>['id'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange', 'req'=>1],
    ];
    return ['html'=>implode('',Form::generateField($fields)), 'column'=>$this->_getColumnButtonReportList()];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData  = $valid['data'];
    $op     = $valid['op'];
    $vData += Helper::splitDateRate($vData['dateRange'] ,'post_date');
    unset($vData['dateRange']);
    $columnReportList = $this->_getColumnButtonReportList();
    $column = $columnReportList['columns'];
    
    if(!empty($op)){
      $qData = GridData::getReportQuery([
        'sort'=>$vData['sort'],
        'order'=>$vData['order'],
        'filter' => P::getFilter($vData),
        'selectedField' => P::getSelectedField($columnReportList)
      ], T::$trackFundTransferView);
      $r = Elastic::gridSearch($this->_indexMain . $qData['query']);
      $gridData = $this->_getGridData($r, $vData, $qData); 
      switch ($op) {
        case 'show': return $gridData; 
        case 'csv':  return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':  return P::getPdf(P::getPdfData($gridData, $column), ['title' =>'Fund Transfer Report']);
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'dateRange'=>'required|string|between:21,23',
    ] + GridData::getRuleReport(); 
  }
################################################################################
##########################   GRID FUNCTION   #################################  
################################################################################  
  private function _getColumnButtonReportList(){
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV'];
        
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'sortable'=> true, 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'to_prop', 'title'=>'To Prop', 'sortable'=> true, 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'bank', 'title'=>'Bank', 'sortable'=> true, 'width'=> 25, 'hWidth'=>50];
    $data[] = ['field'=>'bank_acct', 'title'=>'Bank Account', 'sortable'=> true, 'width'=> 75, 'hWidth'=>75];
    $data[] = ['field'=>'to_bank', 'title'=>'To Bank', 'sortable'=> true, 'width'=> 25, 'hWidth'=>50];
    $data[] = ['field'=>'to_bank_acct', 'title'=>'To Bank Account', 'sortable'=> true, 'width'=> 75, 'hWidth'=>75];
    $data[] = ['field'=>'gl_acct', 'title'=>'GL Acct', 'sortable'=> true, 'width'=> 50, 'hWidth'=>75];
    $data[] = ['field'=>'to_gl_acct', 'title'=>'To GL Acct', 'sortable'=> true, 'width'=>50, 'hWidth'=>75];
    $data[] = ['field'=>'amount', 'title'=>'Amount', 'sortable'=> true, 'width'=> 100, 'hWidth'=>100];
    $data[] = ['field'=>'batch', 'title'=>'Batch', 'sortable'=> true, 'width'=> 75, 'hWidth'=>75];
    $data[] = ['field'=>'post_date', 'title'=>'Post Date', 'sortable'=> true, 'width'=> 75, 'hWidth'=>75];
    $data[] = ['field'=>'usid', 'title'=>'Usid','sortable'=> true];
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($r){
    $result = Helper::getElasticResult($r);
    $rows = [];
    $lastRow = ['amount'=>0, 'count'=>0];
    
    foreach($result as $i=>$v){
      $source = $v['_source']; 
      ++$lastRow['count'];
      $lastRow['amount']  += $source['amount'];
      
      $source['amount']    = Format::usMoney($source['amount']);
      $source['post_date'] = Format::usDate($source['post_date']);
      $rows[] = $source;
    }
    # HOW TO GET THE LAST TOTAL LAST ROW 
    $lastRow['to_prop']    = '# Transfer:';
    $lastRow['bank']       = $lastRow['count'];
    $lastRow['to_gl_acct'] = 'Total Amount:';
    $lastRow['amount']     = Format::usMoney($lastRow['amount']);
    
    return P::getRow($rows, $lastRow);
  }
}
