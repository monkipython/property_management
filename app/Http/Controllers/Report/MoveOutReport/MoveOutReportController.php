<?php
namespace App\Http\Controllers\Report\MoveOutReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};
use App\Http\Models\ReportModel AS M; // Include the models class

class MoveOutReportController extends Controller {
  private $_viewTable = '';
  private $_maxSize   = 5000;
  private $_mapping   = [];
  private static $_instance;
  public function __construct(){
    $this->_viewTable = T::$tenantView;
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$prop]);
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
      'dateRange' => ['id'=>'dateRange','name'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange','req'=>1],
      'prop_type' => ['id'=>'prop_type','name'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type']],
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList()];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData = $valid['data'];
    $op    = $valid['op'];
    $vData+= Helper::splitDateRate($vData['dateRange'],'move_out_date');
    unset($vData['dateRange']);
    $columnReportList = $this->_getColumnButtonReportList($vData);
    $column = $columnReportList['columns'];
    
    if(!empty($op)){
      $field = array_merge(P::getSelectedField($columnReportList, 1),[T::$remarkTnt.'.date1',T::$remarkTnt.'.remark_tnt_id']);
      $r     = Elastic::searchQuery([
        'index'    => $this->_viewTable,
        'size'     => $this->_maxSize,
        '_source'  => array_merge($field, ['prop', 'unit', 'tenant']),
        'sort'     => ['group1.keyword'=>'asc','move_out_date'=>'asc','prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
        'query'    => [
          'must' => [
            'prop'  => Helper::explodeField($vData,['prop_type'])['prop'],
            'range' => [
              'move_out_date' => [
                'gte'    => $vData['move_out_date'],
                'lte'    => $vData['tomove_out_date'],
                'format' => 'yyyy-MM-dd',
              ]
            ]
          ]
        ]
      ]);
      $numRows  = count(Helper::getElasticResult($r));
      $gridData = $this->_getGridData($r);
      switch ($op) {
        case 'show': return $gridData; 
        case 'csv':  return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':  return P::getPdf(P::getPdfData($gridData, $numRows > 0 ? $column : $this->_getBlankPdfColumn()), ['title'=>'Move Out Report']);
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
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'width'=>20,'hWidth'=>45];
    $data[] = ['field'=>'acct','title'=>'Acct','sortable'=>true,'width'=>15,'hWidth'=>60];
//    $data[] = ['field'=>'unit','title'=>'Unit','sortable'=>true,'width'=>20,'hWidth'=>40];
//    $data[] = ['field'=>'tenant','title'=>'Tnt','sortable'=>true,'width'=>15,'hWidth'=>15];
    $data[] = ['field'=>'tnt_name','title'=>'Tnt Name','sortable'=>true,'width'=>100,'hWidth'=>120];
    $data[] = ['field'=>'move_out_date','title'=>'Move Out','sortable'=>true,'width'=>40,'hWidth'=>50];
    $data[] = ['field'=>'base_rent','title'=>'Base Rent','sortable'=>true,'width'=>50,'hWidth'=>50];
    $data[] = ['field'=>'dep_held1','title'=>'Dep. Held','sortable'=>true,'width'=>50,'hWidth'=>50];
    $data[] = ['field'=>'unit_type','title'=>'Type','sortable'=>true,'width'=>10,'hWidth'=>20];
    $data[] = ['field'=>'spec_code','title'=>'Spec','sortable'=>true,'width'=>10,'hWidth'=>20];
    $data[] = ['field'=>'style','title'=>'Story','sortable'=>true,'width'=>10,'hWidth'=>40];
    $data[] = ['field'=>'bedrooms','title'=>'Bd','sortable'=>true,'width'=>25,'hWidth'=>25];
    $data[] = ['field'=>'bathrooms','title'=>'Bth','sortable'=>true,'width'=>25,'hWidth'=>25];
    $data[] = ['field'=>'street','title'=>'Street','sortable'=>true,'width'=>100,'hWidth'=>80];
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true,'width'=>100,'hWidth'=>65];
    $data[] = ['field'=>'remark_tnt.remarks','title'=>'Notes','sortable'=>true,'hWidth'=>170];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getBlankPdfColumn(){
    $data   = [];
    $data[] = ['field'=>'tnt_name','title'=>'Move Out','hWidth'=>900]; 
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'dateRange'    => 'required|string|between:21,23',
      'prop_type'    => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getGridData($r){
    $result = Helper::getElasticResult($r);
    
    $grandTotal = ['tnt_name'=>0,'base_rent'=>0,'dep_held1'=>0,'bedrooms'=>0,'bathrooms'=>0];
    $rows       = [];
    
    foreach($result as $i=>$v){
      $v                             = $v['_source'];
      $grandTotal['bedrooms']       += is_numeric($v['bedrooms']) ? $v['bedrooms'] : 0;
      $grandTotal['bathrooms']      += is_numeric($v['bathrooms']) ? $v['bathrooms'] : 0;
      $grandTotal['dep_held1']      += $v['dep_held1'];
      $grandTotal['base_rent']      += $v['base_rent'];
      $grandTotal['tnt_name']++;

      $remarks  = '';
      if(!empty($v[T::$remarkTnt])){
        $tenantRemarks = [];
        foreach($v[T::$remarkTnt] as $idx => $val){
          $tenantRemarks[strtotime($val['date1']) . $val['remark_tnt_id']] = $val;
        }
        krsort($tenantRemarks);
        $isFirstRemark  = true;
        foreach($tenantRemarks as $val){
          $remarks      .= Html::u($val['date1']) . Html::br() . $val['remarks'] . Html::br() . ($isFirstRemark ? Html::br() : '');
          $isFirstRemark = false;
        }
      }
      $rows[]   = [
        'acct'                    => $v['prop'] . '-' . $v['unit'] . '-' . $v['tenant'],
//        'unit'                    => $v['unit'],
//        'tenant'                  => $v['tenant'],
        'tnt_name'                => title_case($v['tnt_name']),
        'base_rent'               => Format::usMoney($v['base_rent']),
        'dep_held1'               => Format::usMoney($v['dep_held1']),
        'move_out_date'           => Format::usDate($v['move_out_date']),
        'unit_type'               => $v['unit_type'],
        'spec_code'               => $v['spec_code'],
        'style'                   => $v['style'],
        'bedrooms'                => $v['bedrooms'],
        'bathrooms'               => $v['bathrooms'],
        'street'                  => title_case($v['street']),
        'city'                    => title_case($v['city']),
        'group1'                  => $v['group1'],
        T::$remarkTnt.'.remarks'  => title_case(trim($remarks)),
      ];
    }
    
    $grandTotal['tnt_name']      = count($rows) > 0 ? Html::b(Html::u('Grand Total: ' . $grandTotal['tnt_name'])) : Html::b(Html::u('No Move Outs'));
    $grandTotal['base_rent']     = Html::b(Html::u(Format::usMoney($grandTotal['base_rent'])));
    $grandTotal['dep_held1']     = Html::b(Html::u(Format::usMoney($grandTotal['dep_held1'])));
    $grandTotal['bedrooms']      = Html::b(Html::u($grandTotal['bedrooms']));
    $grandTotal['bathrooms']     = Html::b(Html::u($grandTotal['bathrooms']));
    //$rows[] = $grandTotal;
    
    return P::getRow($rows,$grandTotal);
  }
}

