<?php
namespace App\Http\Controllers\Report\ViolationReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};
use App\Http\Models\ReportModel AS M; // Include the models class

class ViolationReportController extends Controller {
  public $typeOption     = ['city'=>'City', 'group1'=>'Group'];
  private $_viewTable    = '';
  private $_mapping      = [];
  private $_propMapping  = [];
  private static $_instance;
  
  public function __construct(){
    $this->_mapping      = Helper::getMapping(['tableName'=>T::$violation]);
    $this->_propMapping  = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_viewTable    = T::$violationView;
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
    $status     = ['' => 'All'] + $this->_mapping['status'];
    $fields = [
      'type'       => ['id'=>'type','label'=>'Display By','type'=>'option', 'option'=>$this->typeOption, 'value'=>'group1','req'=>1],
      'prop'       => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea', 'value'=>'0001-9999', 'req'=>1],
      'prop_type'  => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_propMapping['prop_type']],
      'group1'     => ['id'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'       => ['id'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Torrance'],
      'trust'      => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, *ZA67'],  
      'cons1'      => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'****83-**83,Z64'],
      'status'     => ['id'=>'status','label'=>'Status','type'=>'option','option'=>$status],
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList()];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData = $valid['data'];
    $op    = $valid['op'];
    $type  = isset($this->typeOption[$vData['type']]) ? $vData['type'] : 'city';
    $prop  = Helper::explodeField($vData,['prop','group1','prop_type','trust','city','cons1'])['prop'];
    $columnReportList = $this->_getColumnButtonReportList($type);
    $column = $columnReportList['columns'];
    $sortKey = $type === 'city' ? 'city.keyword' : 'group1.keyword';
   
    if(!empty($op)){
      $query = [
        'index'  =>$this->_viewTable,
        'sort'   =>[$sortKey=>'asc', 'prop_type.keyword'=>'asc', 'prop.keyword'=>'asc'],
        'query'  =>[
            'filter'=>['prop'=>$prop],
        ]
      ];
      if(!empty($vData['status'])){
        $vData['status'] = explode(',', $vData['status']);
        $query['query']['must'] = ['status.keyword'=>$vData['status']];
      }
      $r = Elastic::searchQuery($query);
      $gridData = $this->_getGridData($r, $type);
      switch ($op) {
        case 'show': return $gridData; 
        case 'csv':  return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':  return P::getPdf(P::getPdfData($gridData, $column), ['chunk'=>25, 'title'=>'Violation Report By ' . $this->typeOption[$vData['type']]]);
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'prop'       => 'nullable|string',
      'type'       => 'nullable|string|between:4,6',
      'group1'     => 'nullable|string',
      'cons1'      => 'nullable|string', 
      'city'       => 'nullable|string',
      'trust'      => 'nullable|string',
      'status'     => 'nullable|string',
      'prop_type'  => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($type = 'city'){
    $reportList = [
      'pdf'  =>'Download PDF',
      'csv'  =>'Download CSV',
    ];
    $data = [];
    $data[]   = ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>90,'hWidth'=>55];
    $data[]   = ['field'=>'street','title'=>'Address','sortable'=>true,'width'=>500,'hWidth'=>100];
    $data[]   = ['field'=>'city','title'=>'City','sortable'=>true,'width'=>500,'hWidth'=>50];
    $data[]   = ['field'=>'prop_type','title'=>'Type','sortable'=>true,'width'=>40,'hWidth'=>20];
    $data[]   = ['field'=>'date_recieved','title'=>'Date Rec.','sortable'=>true,'width'=>30,'hWidth'=>50];
    $data[]   = ['field'=>'date_comply','title'=>'Date Comply','sortable'=>true,'width'=>25,'hWidth'=>50];
    $data[]   = ['field'=>'date_complete','title'=>'Date Complete','sortable'=>true,'width'=>25,'hWidth'=>60];
    $data[]   = ['field'=>'status','title'=>'Status','sortable'=>true,'width'=>25,'hWidth'=>40];
    $data[]   = ['field'=>'priority','title'=>'Priority','sortable'=>true,'width'=>25,'hWidth'=>40];
    $data[]   = ['field'=>'inspector_fname','title'=>'Insp. Name','sortable'=>true,'width'=>30,'hWidth'=>50];
    $data[]   = ['field'=>'inspector_phone','title'=>'Insp.  Phone','sortable'=>true,'width'=>30,'hWidth'=>50];
    $data[]   = ['field'=>'agent','title'=>'Agent','sortable'=>true,'width'=>30,'hWidth'=>50];
    $data[]   = ['field'=>'remark','title'=>'Remark','sortable'=>true, 'hWidth'=>210];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r, $type = 'city'){
    $result = Helper::getElasticResult($r);
    $rows = $data = [];
    $lastRow = ['tnt_name'=>0, 'old_rent'=>0, 'new_rent'=>0, 'dep_held1'=>0];
    foreach($result as $i=>$v){
      $src = $v['_source'];
      $src['status']   = $this->_mapping['status'][$src['status']];
      $src['priority'] = $this->_mapping['priority'][$src['priority']];
      $typeGroupBY  = $type == 'city' ? title_case($src[$type]) : $src[$type];
      $data[$typeGroupBY][$src['prop_type']][] = $src;
    }
    
    $GrandTotal = ['street'=>'Grand Total Violation', 'city'=>'=>', 'prop_type'=>'=>', 'date_recieved'=>0, 'date_comply'=>0];
    foreach($data as $typeGroupby=>$value){
      if($type == 'group1'){
        $rows[] = ['prop'=>Html::u(Html::b($typeGroupby))];
      }
      $typeGroupBySum = ['prop'=>'','street'=>'','city'=>'=>','prop_type'=>'=>','date_recieved'=>0,'date_comply'=>0];
      //$typeGroupBySum = ['street'=>'Total Violation '. $this->typeOption[$type].' ' . $typeGroupby, 'city'=>'=>', 'prop_type'=>'=>', 'date_recieved'=>0, 'date_comply'=>0];
      foreach($value as $propType=>$val){
        $rows[] = ['prop'=>'Prop Type: ' . $propType];
        $propTypeSum = ['prop'=>$propType,'street'=>'','city'=>'=>','prop_type'=>'=>','date_recieved'=>0,'date_comply'=>0];
        //$propTypeSum = ['street'=>'Total Violation Prop Type ' . $propType, 'city'=>'=>',  'prop_type'=>'=>', 'date_recieved'=>0, 'date_comply'=>0];
        foreach($val as $i=>$v){
          if($v['status'] == 'Open'){
            $GrandTotal['date_recieved']++;
            $typeGroupBySum['date_recieved']++;
            $propTypeSum['date_recieved']++;
          } else{
            $GrandTotal['date_comply']++;
            $typeGroupBySum['date_comply']++;
            $propTypeSum['date_comply']++;
          }
          
          $rows[] = [
            'prop'           =>$v['prop'] . Html::br(),
            'street'         =>title_case($v['street']),
            'city'           =>title_case($v['city']),
            'prop_type'      =>$v['prop_type'],
            'date_recieved'  =>Format::usDate($v['date_recieved']),
            'date_comply'    =>Format::usDate($v['date_comply']),
            'date_complete'  =>Format::usDate($v['date_complete']),
            'inspector_fname'=>title_case($v['inspector_fname']),
            'inspector_phone'=>$v['inspector_phone'],
            'agent'=>title_case($v['agent']),
            'status'=>$v['status'],
            'priority'=>$v['priority'],
            'remark'         =>title_case($v['remark']),
          ];
        }
        $propTypeSum['date_recieved'] = 'Open: ' . $propTypeSum['date_recieved'] ;
        $propTypeSum['date_comply'] = 'Close: ' . $propTypeSum['date_comply'];
        $rows[] = $propTypeSum;
      }
      $typeGroupBySum['street']       = $this->_boldUnderline($typeGroupBySum['street']);
      $typeGroupBySum['date_recieved']= $this->_boldUnderline('Open: ' . $typeGroupBySum['date_recieved']);
      $typeGroupBySum['date_comply']  = $this->_boldUnderline('Close: ' . $typeGroupBySum['date_comply']);
      $rows[] = $typeGroupBySum;
      $rows[] = [];
    }
    $GrandTotal['street']       = $this->_boldUnderline($GrandTotal['street']);
    $GrandTotal['date_recieved']= $this->_boldUnderline('Open: ' . $GrandTotal['date_recieved']);
    $GrandTotal['date_comply']  = $this->_boldUnderline('Close: ' . $GrandTotal['date_comply']);
    $rows[]   = $GrandTotal;
    return $rows;
  }
//------------------------------------------------------------------------------
  private function _boldUnderline($str) {
    return Html::u(Html::b($str));
  }
}
