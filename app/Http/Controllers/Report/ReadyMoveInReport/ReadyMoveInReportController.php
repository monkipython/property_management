<?php
namespace App\Http\Controllers\Report\ReadyMoveInReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};
use App\Http\Models\{Model,ReportModel AS M}; // Include the models class

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `active`) VALUES ('0', 'Report', 'fa fa-fw fa-file-pdf-o', 'report', 'Report', 'fa fa-fw fa-cog', 'Report', 'fa fa-fw fa-users', 'readyMoveInReport', '', 'To Access Ready Move In Report', '1');
 */

class ReadyMoveInReportController extends Controller {
  private $_viewTable = '';
  private $_maxSize   = 50000;
  private $_mapping   = [];
  private static $_instance;
  public function __construct(){
    $this->_viewTable = T::$creditCheckView;
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$application]);
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
    $fields = [];
    return ['html'=>'','column'=>$this->_getColumnButtonReportList()];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData = $valid['data'];
    $op    = $valid['op'];
    $columnReportList = $this->_getColumnButtonReportList($vData);
    $column = $columnReportList['columns'];
    
    if(!empty($op)){
      $field = ['application_id','prop','unit','tenant','street','city','state','new_rent','sec_deposit',T::$fileUpload,T::$application];
      $r     = Elastic::searchQuery([
        'index'     => $this->_viewTable,
        '_source'   => $field,
        'sort'      => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
        'query'     => [
          'raw'     => [
            'must'  => [
              [
                'bool' => [
                  'should' => [
                    ['term'=>['is_upload_agreement'=>1]],
                    ['term'=>['raw_agreement'=>1]],
                  ]
                ]
              ],
              [
                'term'   => [
                  'application_status.keyword'=>'Approved',
                ]
              ],
              [
                'term'   => [
                  'status.keyword' => 'Approved',
                ]
              ],
              [
                'term'   => [
                  'moved_in_status' => 0,
                ]
              ]
            ],
          ]
        ]
      ]);
      $numRows  = count(Helper::getElasticResult($r));
      $gridData = $this->_getGridData($r);
      switch ($op) {
        case 'show': return $gridData; 
        case 'csv':  return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':  return P::getPdf(P::getPdfData($gridData,$column), ['title'=>'Ready Move In Report']);
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getColumnButtonReportList($req=[]){
    $reportList = [
      'pdf' => 'Download PDF',
      'csv' => 'Download CSV',
    ];
    
    $data   = [];
    $data[] = ['field'=>'num','title'=>'#','sortable'=>true,'width'=>25,'hWidth'=>30];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>25,'hWidth'=>40];
    $data[] = ['field'=>'unit','title'=>'Unit','sortable'=>true,'width'=>25,'hWidth'=>40];
    $data[] = ['field'=>'tnt_name','title'=>'Tenant Name','sortable'=>true,'width'=>350,'hWidth'=>210];
    $data[] = ['field'=>'new_rent','title'=>'Rent Rate','sortable'=>true,'width'=>50,'hWidth'=>60];
    $data[] = ['field'=>'sec_deposit','title'=>'Deposit','sortable'=>true,'width'=>50,'hWidth'=>60];
    $data[] = ['field'=>'street','title'=>'Address','sortable'=>true,'width'=>250,'hWidth'=>140];
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true,'width'=>160,'hWidth'=>110];
    $data[] = ['field'=>'state','title'=>'State','sortable'=>true,'width'=>25,'hWidth'=>45];
    $data[] = ['field'=>'agreement_date','title'=>'Agreement Signed','hWidth'=>65];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
//      'dateRange'    => 'required|string|between:21,23',

    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getGridData($r){
    $result      = Helper::getElasticResult($r);
    
    $grandTotal  = ['tnt_name'=>0,'new_rent'=>0,'sec_deposit'=>0];
    $rows        = [];
    foreach($result as $i=>$v){
      $v                             = $v['_source'];
      $grandTotal['new_rent']      += $v['new_rent'];
      $grandTotal['sec_deposit']   += $v['sec_deposit'];
      $grandTotal['tnt_name']++;

      $tntName        = !empty($v[T::$application]) ? implode(', ',array_column($v[T::$application],'tnt_name')) : '';
      $agreementDate  = $this->_getAgreementDate(Helper::getValue(T::$fileUpload,$v,[]));
      $rows[]   = [
        'num'                     => $i + 1,
        'prop'                    => $v['prop'],
        'unit'                    => $v['unit'],
        'tnt_name'                => title_case($tntName),
        'new_rent'                => Format::usMoney($v['new_rent']),
        'sec_deposit'             => Format::usMoney($v['sec_deposit']),
        'street'                  => title_case($v['street']),
        'city'                    => title_case($v['city']),
        'state'                   => $v['state'],
        'agreement_date'          => !empty($agreementDate) ? Format::usDate($agreementDate) : $agreementDate,
      ];  
    }
    
    $grandTotal['tnt_name']      = Html::b(Html::u('Grand Total: ' . $grandTotal['tnt_name']));
    $grandTotal['new_rent']      = Html::b(Html::u(Format::usMoney($grandTotal['new_rent'])));
    $grandTotal['sec_deposit']   = Html::b(Html::u(Format::usMoney($grandTotal['sec_deposit'])));
    //$rows[] = $grandTotal;
    
    return P::getRow($rows,$grandTotal);
  }
//------------------------------------------------------------------------------
  private function _getAgreementDate($fileUpload=[]){
    $signedDates = [];
    foreach($fileUpload as $v){
      if($v['type'] == 'agreement' && $v['ext'] == 'pdf'){
        $signedDates[strtotime($v['cdate'])] = $v['cdate'];
      }
    }
    krsort($signedDates);
    $signedDates = array_values($signedDates);
    return Helper::getValue(0,$signedDates);
  }
}

