<?php
namespace App\Http\Controllers\Report\VacancyReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Graph, Html, GridData, TableName AS T, Helper};
use App\Http\Models\ReportModel AS M; // Include the models class

class VacancyReportController extends Controller {
  public $typeOption  = ['city'=>'City', 'group1'=>'Group'];
  private $_viewTable = '';
  private $_mapping   = [];
  private static $_instance;
  public function __construct(){
    $this->_viewTable = T::$unitView;
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
      'type'      => ['id'=>'type','label'=>'Group By','type'=>'option', 'option'=>$this->typeOption, 'value'=>'group1','req'=>1],
      'prop'      => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea', 'value'=>'0001-9999', 'req'=>1],
      'group1'    => ['id'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'      => ['id'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Torrance'],
      'prop_type' => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type']]
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList()];
  }
//------------------------------------------------------------------------------
  public function getForm($valid){
    $type   = !empty($valid['type']) ? $valid['type'] : 'city';
    $fields = [
      'prop'   => ['id'=>'prop','name'=>'prop','value'=>'0001-9999','type'=>'hidden','req'=>1],
      'type'   => ['id'=>'type','name'=>'type','type'=>'hidden','req'=>1,'value'=>$type],
    ];
    return ['html' => implode('',Form::generateField($fields))];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData = $valid['data'];
    $op    = $valid['op'];
    $type  = isset($this->typeOption[$vData['type']]) ? $vData['type'] : 'city';
    $prop  = Helper::explodeField($vData,['prop','group1','city','prop_type'])['prop'];
    $columnReportList = $this->_getColumnButtonReportList($type);
    $column = $columnReportList['columns'];
    $sortKey = 'prop.' . $type . '.keyword';
    if(!empty($op)){
      $field = P::getSelectedField($columnReportList, 1);
      $r = Elastic::searchQuery([
        'index'  =>$this->_viewTable,
        '_source'=>array_merge($field, ['unit', 'prop.city', 'prop.group1', 'prop.prop_type']),
        'sort'   =>[$sortKey=>'asc', 'prop.prop_type.keyword'=>'asc', 'prop.prop.keyword'=>'asc', 'unit.keyword'=>'asc'],
        'query'  =>[
          'must'  =>['status.keyword'=>'V', 'prop.prop'=>$prop], 
        ] 
      ]);
      $gridData = $this->_getGridData($r, $type);
      switch ($op) {
        case 'show':  return $gridData; 
        case 'graph': return $this->_getGraphData($r,$type);
        case 'csv':   return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':   return P::getPdf(P::getPdfData($gridData, $column), ['title'=>'Vacancy Report By ' . $this->typeOption[$vData['type']]]);
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'prop'      => 'nullable|string',
      'type'      => 'nullable|string|between:4,6',
      'group1'    => 'nullable|string',
      'city'      => 'nullable|string',
      'prop_type' => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getGraphData($r,$type='city'){
    $result      = Helper::getElasticResult($r);
    $dataset     = $typeCounts  = $data = $labels = [];
    foreach($result as $v){
      $typeVal              = $type === 'city' ? title_case($v['_source']['prop'][0][$type]) : $v['_source']['prop'][0][$type];
      $typeCounts[$typeVal] = !empty($typeCounts[$typeVal]) ? $typeCounts[$typeVal] + 1 : 1;
    }
    ksort($typeCounts);
    $dataset[]      = ['label'=>'Unit Vacancies','data'=>array_values($typeCounts)];
    $graphOptions   = Graph::getGraphSettings([
      'labels'   => array_keys($typeCounts),
      'dataset'  => $dataset,
      'title'    => 'Unit Vacancies by ' . $this->typeOption[$type] . ' as of ' . Helper::fullDate(),
      'xLabel'   => $this->typeOption[$type],
      'yLabel'   => 'Unit Vacancies',
      'xTicks'   => [
        'autoSkip'    => false,
        'minRotation' => 90,
        'maxRotation' => 90,
        'fontSize'    => 16,
      ]
    ]);
    return [
      'options'   => $graphOptions
    ];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($type = 'city'){
    $reportList = [
      'pdf'  =>'Download PDF',
      'csv'  =>'Download CSV',
    ];
    
    $data = [];
    $data[]   = ['field'=>'prop.prop','title'=>'Prop-Unit','sortable'=>true,'width'=>90,'hWidth'=>55];
    $data[]   = ['field'=>'street','title'=>'Address','sortable'=>true,'width'=>500,'hWidth'=>130];
    $data[]   = ['field'=>'city','title'=>'City','sortable'=>true,'width'=>500,'hWidth'=>70];
    $data[]   = ['field'=>'unit_type','title'=>'Type','sortable'=>true,'width'=>40,'hWidth'=>20];
    $data[]   = ['field'=>'bedrooms','title'=>'Bd','sortable'=>true,'width'=>20,'hWidth'=>20];
    $data[]   = ['field'=>'bathrooms','title'=>'Bth','sortable'=>true,'width'=>20,'hWidth'=>20];
    $data[]   = ['field'=>'sq_feet','title'=>'Sqf','sortable'=>true,'width'=>30,'hWidth'=>20];
    $data[]   = ['field'=>'unit_features.carport_garage','title'=>'Gar','sortable'=>true,'width'=>30,'hWidth'=>20];
    $data[]   = ['field'=>'unit_features.pool_jacuzzi','title'=>'Pool','sortable'=>true,'width'=>25,'hWidth'=>20];
    $data[]   = ['field'=>'style','title'=>'Story','sortable'=>true,'width'=>25,'hWidth'=>25];
    $data[]   = ['field'=>'old_rent','title'=>'Exist Rent','sortable'=>true,'width'=>30,'hWidth'=>45];
    $data[]   = ['field'=>'rent_rate','title'=>'New Rent','sortable'=>true,'width'=>30,'hWidth'=>43];
    $data[]   = ['field'=>'sec_dep','title'=>'Deposit','sortable'=>true,'width'=>30,'hWidth'=>43];
    $data[]   = ['field'=>'total','title'=>'Total','sortable'=>true,'width'=>30,'hWidth'=>49];
    $data[]   = ['field'=>'move_out_date','title'=>'V. Date','sortable'=>true,'width'=>30,'hWidth'=>40];
    $data[]   = ['field'=>'prop.line2','title'=>'Manager/Phone','sortable'=>true,'width'=>110,'hWidth'=>120];
    $data[]   = ['field'=>'note','title'=>'Remark','sortable'=>true,'hWidth'=>40,'hWidth'=>75];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r, $type = 'city'){
    $oldRentWhere = [];
    $result = Helper::getElasticResult($r);
    $rows = $data = [];
    //$lastRow = ['tnt_name'=>0, 'old_rent'=>0, 'new_rent'=>0, 'dep_held1'=>0];
    $propWhere = $unitWhere = '';
    foreach($result as $i=>$v){
      $src = $v['_source'];
      $prop = $src['prop'][0];
      $propWhere = $prop['prop']; 
      $unitWhere = $src['unit'];
      $src['total'] = $src['rent_rate'] + $src['sec_dep'];
      $oldRentWhere[] = '("' . implode('","', [$prop['prop'], $src['unit']]) . '")';
      $unitFeatures = isset($src['unit_features'][0]) ? $src['unit_features'][0] : [];
      
      $typeGroupBY  = $type == 'city' ? title_case($prop[$type]) : $prop[$type];
      $data[$typeGroupBY][$prop['prop_type']][] = $unitFeatures + $prop + $src;
    }
    
    if(!empty($oldRentWhere)) {
      $oldRentWhere = count($result) == 1 ? 'prop="' . $propWhere . '" AND unit="' . $unitWhere .'"' :  '(prop, unit) IN (' . implode(',', $oldRentWhere) . ')';
      $r = M::getOldRent($oldRentWhere); 
      ##### GET THE LAST AMOUNT IF IT HAS MULTIPLE OF THEM##### 
      $rOldrent = Helper::keyFieldName($r, ['prop', 'unit', 'tenant']);
      ##### GROUP THEM BY PROP AND UNIT ONLY. NO NEED TO GROUP THEM BY TENANT ######
      $rOldrent = Helper::keyFieldName($r, ['prop', 'unit'], 'amount');
    }
    $GrandTotal = ['prop.prop'=>'','street'=>'Grand Total: ', 'unit_type'=>'=>','bedrooms'=>'=>', 'bathrooms'=>0, 'old_rent'=>0, 'rent_rate'=>0, 'sec_dep'=>0, 'total'=>0];
    foreach($data as $typeGroupby=>$value){
      if($type == 'group1'){
        $rows[] = ['prop.prop'=>Html::u(Html::b($typeGroupby))];
      }
      $typeGroupBySum  = ['prop.prop'=>'','street'=>'','city'=>'','unit_type'=>'=>','bedrooms'=>'=>','bathrooms'=>0,'old_rent'=>0,'rent_rate'=>0,'sec_dep'=>0,'total'=>0];
      //$typeGroupBySum = ['street'=>'Total '.$this->typeOption[$type].' : ' . $typeGroupby, 'unit_type'=>'=>','bedrooms'=>'=>', 'bathrooms'=>0, 'old_rent'=>0, 'rent_rate'=>0, 'sec_dep'=>0, 'total'=>0];
      foreach($value as $propType=>$val){
        $rows[] = ['prop.prop'=>'Prop Type: ' . $propType];
        $propTypeSum  = ['prop.prop'=>$propType,'street'=>'','unit_type'=>'=>','bedrooms'=>'=>','bathrooms'=>0,'old_rent'=>0,'rent_rate'=>0,'sec_dep'=>0,'total'=>0];
        //$propTypeSum = ['street'=>'Total Prop Type: ' . $propType, 'unit_type'=>'=>','bedrooms'=>'=>', 'bathrooms'=>0, 'old_rent'=>0, 'rent_rate'=>0, 'sec_dep'=>0, 'total'=>0];
        foreach($val as $i=>$v){
          $id = $v['prop'].$v['unit'];
          $oldRent = isset($rOldrent[$id]) ? $rOldrent[$id] : 0;
          $propTypeSum['total']     += $v['total'];
          $propTypeSum['old_rent']  += $oldRent;
          $propTypeSum['rent_rate'] += $v['rent_rate'];
          $propTypeSum['sec_dep']   += $v['sec_dep'];
          $propTypeSum['bathrooms']++;
          
          $typeGroupBySum['total']     += $v['total'];
          $typeGroupBySum['old_rent']  += $oldRent;
          $typeGroupBySum['rent_rate'] += $v['rent_rate'];
          $typeGroupBySum['sec_dep']   += $v['sec_dep'];
          $typeGroupBySum['bathrooms']++;
          
          $GrandTotal['total']     += $v['total'];
          $GrandTotal['old_rent']  += $oldRent;
          $GrandTotal['rent_rate'] += $v['rent_rate'];
          $GrandTotal['sec_dep']   += $v['sec_dep'];
          $GrandTotal['bathrooms']++;
          
          $rows[] = [
            'prop.prop'      =>$v['prop'] . '-' . $v['unit'],
            'street'         =>title_case($v['street']),
            'city'           =>title_case($v['city']),
            'unit_type'      =>$v['unit_type'],
            'bedrooms'       =>$v['bedrooms'],
            'bathrooms'      =>$v['bathrooms'],
            'move_out_date'  =>Format::usDate($v['move_out_date']),
            'old_rent'       =>Format::usMoney($oldRent),
            'rent_rate'      =>Format::usMoney($v['rent_rate']),
            'sec_dep'        =>Format::usMoney($v['sec_dep']),
            'total'          =>Format::usMoney($v['total']),
            'prop.line2'     =>title_case($v['line2']),
            'sq_feet'        =>Format::number($v['sq_feet']),
            'unit_features.carport_garage'=>isset($v['carport_garage']) ? $v['carport_garage'] : '',
            'unit_features.pool_jacuzzi'=>isset($v['pool_jacuzzi']) ? $v['pool_jacuzzi'] : '',
          ];
        }
        
        $propTypeSum['old_rent']  = Format::usMoney($propTypeSum['old_rent']);
        $propTypeSum['rent_rate'] = Format::usMoney($propTypeSum['rent_rate']);
        $propTypeSum['sec_dep']   = Format::usMoney($propTypeSum['sec_dep']);
        $propTypeSum['total']     = Format::usMoney($propTypeSum['total']);
        $rows[] = $propTypeSum;
      }
      
      $typeGroupBySum['bathrooms'] = $this->_boldUnderline($typeGroupBySum['bathrooms']);
      $typeGroupBySum['street']    = $this->_boldUnderline($typeGroupBySum['street']);
      $typeGroupBySum['old_rent']  = $this->_boldUnderline(Format::usMoney($typeGroupBySum['old_rent']));
      $typeGroupBySum['rent_rate'] = $this->_boldUnderline(Format::usMoney($typeGroupBySum['rent_rate']));
      $typeGroupBySum['sec_dep']   = $this->_boldUnderline(Format::usMoney($typeGroupBySum['sec_dep']));
      $typeGroupBySum['total']     = $this->_boldUnderline(Format::usMoney($typeGroupBySum['total']));
      $rows[] = $typeGroupBySum;
      $rows[] = [];
    }
    $GrandTotal['bathrooms'] = $this->_boldUnderline($GrandTotal['bathrooms']);
    $GrandTotal['street']    = $this->_boldUnderline($GrandTotal['street']);
    $GrandTotal['old_rent']  = $this->_boldUnderline(Format::usMoney($GrandTotal['old_rent']));
    $GrandTotal['rent_rate'] = $this->_boldUnderline(Format::usMoney($GrandTotal['rent_rate']));
    $GrandTotal['sec_dep']   = $this->_boldUnderline(Format::usMoney($GrandTotal['sec_dep']));
    $GrandTotal['total']     = $this->_boldUnderline(Format::usMoney($GrandTotal['total']));
    $rows[] = $GrandTotal;
    return $rows;
  }
//------------------------------------------------------------------------------
  private function _boldUnderline($str) {
    return Html::u(Html::b($str));
  }
}

