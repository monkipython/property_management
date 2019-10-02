<?php
namespace App\Http\Controllers\Report\ManagerMoveinReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController AS P;
use App\Library\{V, Form, Elastic, GridData, TableName AS T, Helper, Format};
use Illuminate\Support\Facades\DB;

class ManagerMoveinReportController extends Controller{
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping = [];
  public function __construct(Request $req){
    $this->_mapping     = Helper::getMapping(['tableName'=>T::$tenant]);
    $this->_propMapping = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_viewTable   = T::$tenantView;
    $this->_indexMain   = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
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
      'dateRange'  => ['id'=>'dateRange','label'=>'Date','type'=>'text','class'=>'daterange', 'req'=>1],
      'prop'       => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea','placeHolder'=>'Ex. 0001-9999, 0028', 'value'=>'0001-9999','req'=>1],
      'prop_type'  => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_propMapping['prop_type']],
      'group1'     => ['id'=>'group1','name'=>'group1','label'=>'Group','type'=>'textarea','placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'       => ['id'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Los Angeles'],
      'trust'      => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, *ZA67'],  
      'cons1'      => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'****83-**83,Z64'],
      'status'     => ['id'=>'status','label'=>'Tenant Status', 'type'=>'option', 'option'=>$this->_mapping['status'], 'value'=>'C'],
    ];
    return ['html'=>implode('',Form::generateField($fields)), 'column'=>$this->_getColumnButtonReportList()];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData  = $valid['data'];
    $op     = $valid['op'];
    $vData += Helper::splitDateRate($vData['dateRange'] ,'move_in_date');
    $vData['prop'] = Helper::explodeField($vData,['prop','group1','prop_type','trust','city','cons1'])['prop'];
    unset($vData['dateRange']);
    $columnReportList = $this->_getColumnButtonReportList();
    $column = $columnReportList['columns'];
    if(!empty($op)){
      $fields = explode(',',P::getSelectedField($columnReportList));
      $r = Elastic::searchQuery([
        'index'    => $this->_viewTable,
        'sort'     => ['prop.keyword'=>'asc'],
        '_source'  => $fields,
        'query'    => [
          'must' => [
            'prop.keyword'    => $vData['prop'],
            'status.keyword'  => $vData['status'],
            'isManager'       => 1,
            'range' => [
              'move_in_date' => [
                'gte'    => $vData['move_in_date'],
                'lte'    => $vData['tomove_in_date'],
                'format' => 'yyyy-MM-dd',
              ],
            ]
          ]
        ]
      ]);
      $gridData = $this->_getGridData($r); 
      switch ($op) {
        case 'show': return $gridData; 
        case 'csv':  return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':  return P::getPdf(P::getPdfData($gridData, $column), ['title' =>'Manager Move In Report']);
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'dateRange'  => 'required|string|between:21,23',
      'prop'       => 'required|nullable|string', 
      'status'     => 'nullable|string|between:1,1', 
      'group1'     => 'nullable|string', 
      'cons1'      => 'nullable|string', 
      'prop_type'  => 'nullable|string|between:0,1',
      'city'       => 'nullable|string',
      'trust'      => 'nullable|string',
    ] + GridData::getRuleReport(); 
  }
################################################################################
##########################   GRID FUNCTION   #################################  
################################################################################  
  private function _getColumnButtonReportList(){
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV'];
        
    $data[] = ['field'=>'move_in_date', 'title'=>'Move in Date', 'sortable'=> true, 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'housing_dt2', 'title'=>'Enter Date', 'sortable'=> true, 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'bedrooms', 'title'=>'Beds', 'sortable'=> true, 'width'=> 25, 'hWidth'=>20];
    $data[] = ['field'=>'bathrooms', 'title'=>'Bath', 'sortable'=> true, 'width'=> 25, 'hWidth'=>20];
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'sortable'=> true, 'width'=> 25, 'hWidth'=>20];
    $data[] = ['field'=>'unit', 'title'=>'Unit', 'sortable'=> true, 'width'=> 25, 'hWidth'=>20];
    $data[] = ['field'=>'tenant', 'title'=>'Tenant', 'sortable'=> true, 'width'=> 25, 'hWidth'=>25];
    $data[] = ['field'=>'tnt_name', 'title'=>'Tenant Name', 'sortable'=> true, 'width'=>125, 'hWidth'=>150];
    $data[] = ['field'=>'old_rent', 'title'=>'Old Rent', 'sortable'=> true, 'width'=> 25, 'hWidth'=>50];
    $data[] = ['field'=>'new_rent', 'title'=>'New Rent', 'sortable'=> true, 'width'=> 25, 'hWidth'=>50];
    $data[] = ['field'=>'dep_held1', 'title'=>'Deposit', 'sortable'=> true, 'width'=> 25, 'hWidth'=>50];
    $data[] = ['field'=>'group1', 'title'=>'Group','sortable'=> true,'width'=> 75, 'hWidth'=>40];
    $data[] = ['field'=>'street', 'title'=>'Address','sortable'=> true,'width'=> 150, 'hWidth'=>150];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'width'=> 100, 'hWidth'=>75];
    $data[] = ['field'=>'ran_by', 'title'=>'Ran By','sortable'=> true, 'hWidth'=>50];
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($r){
    $result = Helper::getElasticResult($r);
    $rows = [];
    $lastRow = ['tnt_name'=>0, 'old_rent'=>0, 'new_rent'=>0, 'dep_held1'=>0];
    $rApp =  Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   =>T::$creditCheckView,
      '_source' =>['prop', 'unit', 'tenant', 'ran_by', 'old_rent', 'new_rent']
    ]), ['prop', 'unit', 'tenant']);
    $rAcct =  Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   =>T::$accountView,
      '_source' =>['account_id', 'firstname', 'lastname']
    ]), 'account_id');
    foreach($result as $i=>$v){
      $source = $v['_source']; 
 
      $id = $source['prop']. $source['unit'] . $source['tenant'];
      if(isset($rApp[$id]['ran_by'])){
        $source['ran_by']  = isset($rAcct[$rApp[$id]['ran_by']]) ? $rAcct[$rApp[$id]['ran_by']]['firstname'] : $rApp[$id]['ran_by'];
      } else{
        $source['ran_by']  = 'Unknown';
      }
      $source['old_rent']  = isset($rApp[$id]) ? $rApp[$id]['old_rent'] : 0;
      $source['new_rent']  = isset($rApp[$id]) ? $rApp[$id]['new_rent'] : 0;
      
      ++$lastRow['tnt_name'];
      $lastRow['old_rent']  += $source['old_rent'];
      $lastRow['new_rent']  += $source['new_rent'];
      $lastRow['dep_held1'] += $source['dep_held1'];
      
      $source['new_rent']  = Format::usMoney($source['new_rent']);
      $source['old_rent']  = Format::usMoney($source['old_rent']);
      $source['dep_held1'] = Format::usMoney($source['dep_held1']);
      $rows[] = $source;
    }
    # HOW TO GET THE LAST TOTAL LAST ROW 
    $lastRow['tnt_name']  = '# Tenant: ' . $lastRow['tnt_name'];
    $lastRow['old_rent']  = Format::usMoney($lastRow['old_rent']);
    $lastRow['new_rent']  = Format::usMoney($lastRow['new_rent']);
    $lastRow['dep_held1'] = Format::usMoney($lastRow['dep_held1']);
    
    return P::getRow($rows, $lastRow);
  }
}
