<?php
namespace App\Http\Controllers\Report\Section8Report;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form,Elastic, Format, Html, GridData, TableName AS T, Helper};

class Section8ReportController extends Controller {
  public $typeOption   = ['group1'=>'Group','city'=>'City'];
  private $_viewTable  = '';
  private $_mapping    = [];
  private $_propMapping= [];
  private $_maxSize    = 5000;
  private static $_instance;
  
  public function __construct(){
    $this->_viewTable   = T::$section8View;
    $this->_mapping     = Helper::getMapping(['tableName'=>T::$section8]);
    $this->_propMapping = Helper::getMapping(['tableName'=>T::$prop]); 
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
      'includeCdate'=> 0,
    ]);
    return $this->getData($valid);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $fields = [
      'type'        => ['id'=>'type','name'=>'type','label'=>'Order By','type'=>'option', 'option'=>$this->typeOption, 'req'=>1],
      'dateRange'   => ['id'=>'dateRange','name'=>'dateRange','label'=>'Date Range','type'=>'text','class'=>'daterange','req'=>1],
      'prop'        => ['id'=>'prop','name'=>'prop','label'=>'Prop', 'type'=>'textarea', 'value'=>'0001-9999', 'req'=>1],
      'group1'      => ['id'=>'group1','name'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'        => ['id'=>'city','name'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Torrance'],
      'prop_type'   => ['id'=>'prop_type','name'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_propMapping['prop_type']],
    ];
    
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList()];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData             = $valid['data'];
    $op                = $valid['op'];
    $vData            += Helper::splitDateRate($vData['dateRange'],'first_inspection_date');
    unset($vData['dateRange']);

    $type              = !empty($vData['type']) && isset($this->typeOption[$vData['type']]) ? $vData['type'] : 'group1';
    $prop              = Helper::explodeField($vData,['prop','group1','city','prop_type'])['prop'];
    $columnReportList  = $this->_getColumnButtonReportList($type);
    $column            = $columnReportList['columns'];
    $sortKey           = $type . '.keyword';
    if(!empty($op)){
      $r = Elastic::searchQuery([
          'index'     => $this->_viewTable,
          'sort'      => [$sortKey=>'asc','first_inspection_date'=>'asc','prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
          '_source'   => P::getSelectedField($columnReportList,1),
          'size'      => $this->_maxSize,
          'query'     => [
            'must'      => [
              'prop'     => $prop,
              'range'    => [
                  'first_inspection_date' => [
                    'gte'    => $vData['first_inspection_date'],
                    'lte'    => $vData['tofirst_inspection_date'],
                    'format' => 'yyyy-MM-dd',
                  ],
              ],
            ],
          ],
      ]);
      
      $gridData      = $this->_getGridData($r,$type);
      switch($op){
        case 'show' : return $gridData;
        case 'csv'  : return P::getCsv($gridData,['column'=>$column]);
        case 'pdf'  : return P::getPdf(P::getPdfData($gridData,$column),['title'=>'Section 8 Report Ordered by ' . $this->typeOption[$type]]);
      }
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'type'         => 'nullable|string|between:4,6',
      'city'         => 'nullable|string',
      'dateRange'    => 'required|string|between:21,23',
      'prop'         => 'nullable|string',
      'group1'       => 'nullable|string',
      'prop_type'    => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($type = 'group1'){
    $reportList = [
      'pdf'  =>'Download PDF',
      'csv'  =>'Download CSV',
    ];
    
    $data   = [];
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'width'=>90,'hWidth'=>55];
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true,'width'=>500,'hWidth'=>70];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>90,'hWidth'=>55];
    $data[] = ['field'=>'unit','title'=>'Unit','sortable'=>true,'width'=>60,'hWidth'=>40];
    $data[] = ['field'=>'tnt_name','title'=>'Tnt','sortable'=>true,'width'=>500,'hWidth'=>110];
    $data[] = ['field'=>'street','title'=>'Address','sortable'=>true,'width'=>500,'hWidth'=>120];
    $data[] = ['field'=>'first_inspection_date','title'=>'1st Inspec.','sortable'=>true,'width'=>40,'hWidth'=>50];
    $data[] = ['field'=>'status','title'=>'Status','sortable'=>true,'width'=>55,'hWidth'=>30];
    $data[] = ['field'=>'second_inspection_date','title'=>'2nd Inspec.','sortable'=>true,'width'=>40,'hWidth'=>50];
    $data[] = ['field'=>'status2','title'=>'Status 2','sortable'=>true,'width'=>55,'hWidth'=>30];
    $data[] = ['field'=>'third_inspection_date','title'=>'3rd Inspec.','sortable'=>true,'width'=>40,'hWidth'=>50];
    $data[] = ['field'=>'status3','title'=>'Status 3','sortable'=>true,'width'=>55,'hWidth'=>30];
    $data[] = ['field'=>'remarks','title'=>'Remark','sortable'=>true,'hWidth'=>150];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r,$type='group1'){
    $rows    = $data = [];
    $lastRow = ['tnt_name'=>0];
    $result  = Helper::getElasticResult($r);

    foreach($result as $i => $v){
      $lastRow['tnt_name']++;
      $source  = $v['_source'];
      $rows[]  = [
        'group1'                   => $source['group1'],
        'city'                     => title_case($source['city']),
        'prop'                     => $source['prop'],
        'unit'                     => $source['unit'],
        'tnt_name'                 => title_case($source['tnt_name']),
        'street'                   => title_case($source['street']),
        'first_inspection_date'    => $source['first_inspection_date'] !== '1000-01-01' ? Format::usDate($source['first_inspection_date']) : '',
        'status'                   => !empty($source['status']) ? $this->_mapping['status'][$source['status']] : '',
        'second_inspection_date'   => $source['second_inspection_date'] !== '1000-01-01' ? Format::usDate($source['second_inspection_date']): '',
        'status2'                  => !empty($source['status2']) ? $this->_mapping['status2'][$source['status2']] : '',
        'third_inspection_date'    => $source['third_inspection_date'] !== '1000-01-01' ? Format::usDate($source['third_inspection_date']) : '',
        'status3'                  => !empty($source['status3']) ? $this->_mapping['status3'][$source['status3']] : '',
        'remarks'                  => title_case($source['remarks']),
      ];
    }

    $lastRow['tnt_name']  = Html::b(Html::u('Total Inspections: ' . $lastRow['tnt_name']));
    //$rows[]               = $lastRow;
    return P::getRow($rows,$lastRow);
  }
}