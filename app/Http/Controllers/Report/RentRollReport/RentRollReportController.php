<?php
namespace App\Http\Controllers\Report\RentRollReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController AS P;
use App\Library\{V, Form, Elastic, Html, GridData, TableName AS T, Helper, Format, HelperMysql};
use App\Http\Models\ReportModel AS M; // Include the models class
use App\Http\Controllers\PropertyManagement\Tenant\TenantController;

class RentRollReportController extends Controller{
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping   = [];
  private $_unitTypeAbbr = ['A'=>'APT','B'=>'COMM','C'=>'COND','G'=>'G','H'=>'H','I'=>'I','L'=>'L', 'M'=>'MH','O'=>'O','P'=>'P','Q'=>'SP', 'S'=>'STO', 'R'=>'STU','T'=>'T','W'=>'W'];
  public function __construct(Request $req){
    $this->_viewTable = T::$tenantView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$prop]);
  }
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
      'prop'      => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea','placeHolder'=>'Ex. 0001-9999, 0028', 'value'=>'0001-9999'],
      'trust'     => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, *ZA67'],
      'group1'    => ['id'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'      => ['id'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Los Angeles'],
      'date'      => ['id'=>'date','label'=>'Move In Date', 'class'=>'date','type'=>'text', 'value'=>date("m/d/Y")],
      'prop_type' => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type']],
    ];
    return [
      'html'=>implode('',Form::generateField($fields)), 
      'tab'=>[],
    ];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData     = $valid['data'];
    $op        = $valid['op'];
    $vData['prop']           = empty($vData['prop']) ? '0001-9999' : $vData['prop'];
    $vData['tomove_in_date'] = $vData['move_out_date']   = Format::mysqlDate($vData['date']);
    
    $propList = Helper::explodeField($vData,['prop','trust', 'group1', 'city','prop_type'])['prop'];
    $results  = M::getPropsWithTenant($propList);
    $props    = array_column($results, 'key');
    sort($props);
    $vData['prop'] = isset($vData['selected']) && $op == 'show' ? $vData['selected'] : $props;
    $column        = $this->_getColumnButtonReportList()['columns'];

    if(!empty($op)){
      if($op == 'csv' || $op == 'pdf') {
        unset($vData['selected']);
      }
      switch ($op) {
        case 'tab': return $this->_getTabData($vData['prop']);
        case 'show': 
          unset($vData['selected']);
          return $this->_getGridData($vData); 
        case 'csv': return P::getCsv($this->_getGridData($vData, 1), ['column'=>$column]);
        case 'pdf': return P::getPdf($this->_getPdfData($this->_getGridData($vData), $column), ['title'=>'Rent Roll Report', 'chunk'=>40], 1);
      }
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'prop'     => 'nullable|string', 
      'trust'    => 'nullable|string',
      'group1'   => 'nullable|string',
      'city'     => 'nullable|string',
      'date'     => 'required|string',
      'prop_type'=> 'nullable|string|between:0,1',
      'selected' => 'nullable|string|between:1,4'
    ] + GridData::getRuleReport(); 
  }
  private function _getPropRow($result, $vData) {
    $rows       = [];
    $propKeys   = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      '_source'  => ['prop','start_date','city','street','state','zip'],
    ]),'prop');
    $futureTenant = M::getFutureCurrentTenant();

    foreach($result as $i=>$v){
      $source      = $v['_source']; 
      $tenant = $this->_formatTenantData($source, $vData, $rows);
      if(!empty($tenant)) {
        $rows[$source['prop'].$source['unit']] = $tenant;
      }
    }
    $unitR = M::getVacantUnit($vData['prop']);
    $vacantUnitList = [];
    ## Add Vacant Unit
    foreach($unitR as $i=>$v){
      $src = $v['_source']; 
      if(!isset($rows[$src['prop'][0]['prop'] . $src['unit']])) {
        ## Check if this vacant unit has a future tenant
        if(isset($futureTenant[$src['prop'][0]['prop'].$src['unit']])) {
          ## Get all the tenants in this unit that has move_in_date greater than the input date
          $rTenant = HelperMysql::getTenant(['prop.keyword'=>$src['prop'][0]['prop'], 'unit.keyword'=>$src['unit'], 'range'=>['move_in_date'=>['gte'=>Format::mysqlDate($vData['date'])]]],[], [], 0);
          ## Only show the future tenant instead of vacant if the result returns only 1
          if(count($rTenant) == 1) {
            $vacantUnitList[] = $this->_formatTenantData($futureTenant[$src['prop'][0]['prop'].$src['unit']], $vData, $rows);
            continue;
          }
        }
        $src['tnt_name']  = '*** Vacant ***';
        $src['street']    = title_case($src['street']);
        $src['billing']   = 0;
        $src['dep_held1'] = $src['sec_dep'];
        $src['base_rent'] = $src['rent_rate'];
        $src['prop']      = $src['prop'][0]['prop'];
        $src['spec_code'] = $src['phone1'] = $src['lease_start_date'] = $src['dep_int_last_date'] = $src['status'] = '';
        $vacantUnitList[] = $src;
      }
    }
    $vacantGroup = Helper::keyFieldName($vacantUnitList,['prop','unit']);
    $allList = $rows + $vacantGroup;
    ksort($allList);
    
    ## Combine tenant units and vacant units
    $allListByProp = Helper::groupBy($allList,'prop');
    ksort($allListByProp);
    $unitType = ['A', 'B', 'C', 'H', 'I', 'M', 'O', 'R', 'T', 'W'];
    foreach($allListByProp as $p => $data) {
      $count = 1;
      ## Calculate the sum and count of the prop units
      $countList = ['unit'=>0, 'vacant'=>0];
      $headerRow = $propData  = ['base_rent'=>0, 'rent_rate'=>0, 'billing'=>0, 'dep_held1'=>0, 'dep_int_last_date'=>'', 'phone1'=>'', 'unit'=>'', 'bedBath'=>'', 'count'=>'', 'unit_type'=>'', 'spec_code'=>'', 'status'=>'', 'lease_start_date'=>'', 'street'=>'', 'count'=>'','pad_size'=>'','unit_size'=>'','mh_owner'=>'','mh_serial_no'=>''];
      foreach($data as $k => $v) {
        ## Check to increase Unit Count and Vacant Count
        if(in_array($v['unit_type'], $unitType)) {
          $countList['unit']++;
        }
        if(empty($v['status'])) {
          $countList['vacant']++;
        }
        $propData['base_rent'] += $v['base_rent'];
        $propData['rent_rate'] += $v['rent_rate'];
        $propData['billing']   += $v['billing'];
        $propData['dep_held1'] += $v['dep_held1'];

        $arrowIcon = $v['base_rent'] > $v['rent_rate'] + $v['billing'] ? Html::icon('fa fa-arrow-up text-green') . ' ' : '';
        
        $allListByProp[$p][$k]['prop']      = $data[$k]['prop'] . '-' . $data[$k]['unit'];
        $allListByProp[$p][$k]['count']     = Html::b($count++);
        $allListByProp[$p][$k]['tnt_name']  = current(explode(",", $v['tnt_name']));
        $allListByProp[$p][$k]['bedBath']   = !empty($v['bedrooms']) && !empty($v['bathrooms']) ? $v['bedrooms'] . ' | ' . $v['bathrooms'] : ''; 
        $allListByProp[$p][$k]['base_rent'] = Format::usMoney($v['base_rent']);
        $allListByProp[$p][$k]['rent_rate'] = $arrowIcon . Format::usMoney($v['rent_rate']);
        $allListByProp[$p][$k]['billing']   = Format::usMoney($v['billing']);
        $allListByProp[$p][$k]['unit_type'] = isset($this->_unitTypeAbbr[$v['unit_type']]) ? $this->_unitTypeAbbr[$v['unit_type']] : '';
        $allListByProp[$p][$k]['dep_held1'] = Format::usMoney($v['dep_held1']);
        $allListByProp[$p][$k]['lease_start_date']  = !empty($v['lease_start_date']) ? Format::usDate($v['lease_start_date']) : '';
        $allListByProp[$p][$k]['dep_int_last_date'] = !empty($v['dep_int_last_date']) ? Format::usDate($v['dep_int_last_date']) : '';
        $allListByProp[$p][$k]['pad_size']          = Helper::getValue('pad_size',$v);
        $allListByProp[$p][$k]['unit_size']         = Helper::getValue('unit_size',$v);
        $allListByProp[$p][$k]['mh_owner']          = Helper::getValue('mh_owner',$v);
        $allListByProp[$p][$k]['mh_serial_no']      = Helper::getValue('mh_serial_no',$v);
      }
      
      $lastIndex = $k + 1;
      $allListByProp[$p][$lastIndex]['prop']              = is_array($vData['prop']) ? 'Summary:' : '';
      $allListByProp[$p][$lastIndex]['tnt_name']          = Html::b('Total ' . $p . ' : ' . ($count-1));
      $allListByProp[$p][$lastIndex]['count']             = Html::b($count++);
      $allListByProp[$p][$lastIndex]['bedBath']           = '';
      $allListByProp[$p][$lastIndex]['phone1']            = '';
      $allListByProp[$p][$lastIndex]['unit_type']         = '';
      $allListByProp[$p][$lastIndex]['pad_size']          = '';
      $allListByProp[$p][$lastIndex]['spec_code']         = '';
      $allListByProp[$p][$lastIndex]['unit_size']         = '';
      $allListByProp[$p][$lastIndex]['mh_owner']          = '';
      $allListByProp[$p][$lastIndex]['mh_serial_no']      = '';
      $allListByProp[$p][$lastIndex]['lease_start_date']  = '';
      $allListByProp[$p][$lastIndex]['dep_int_last_date'] = '';
      $allListByProp[$p][$lastIndex]['status']            = '';
      $allListByProp[$p][$lastIndex]['base_rent']         = Html::b(Format::usMoney($propData['base_rent']));
      $allListByProp[$p][$lastIndex]['rent_rate']         = Html::b(Format::usMoney($propData['rent_rate']));
      $allListByProp[$p][$lastIndex]['billing']           = Html::b(Format::usMoney($propData['billing']));
      $allListByProp[$p][$lastIndex]['dep_held1']         = Html::b(Format::usMoney($propData['dep_held1']));
      $allListByProp[$p][$lastIndex]['street']            = Html::b('Units: ' . $countList['unit'] . ' | ' . 'Vacants: ' . $countList['vacant']);
      if(is_array($vData['prop'])) {
        ## PDF and CSV | Property info and summary
        $headerRow['property']         = 'Property';
        $headerRow['bedBath']          = '';
        $headerRow['prop']             = $p;
        $headerRow['tnt_name']         = title_case($propKeys[$p]['street']) . ', '. title_case($propKeys[$p]['city']);
        $headerRow['spec_code']        = $propKeys[$p]['state'];
        $headerRow['status']           = $propKeys[$p]['zip'];
        $headerRow['lease_start_date'] = Format::usDate($propKeys[$p]['start_date']);
        $headerRow['base_rent'] = $headerRow['rent_rate'] = $headerRow['billing'] = $headerRow['dep_held1'] = '';
        array_unshift($allListByProp[$p],$headerRow);    
      }else {
        ## Grid Table | Property info and summary
        $headerData = [];
        $headerData['property']         = 'Property';
        $headerData['prop']             = Html::b($p);
        $headerData['tnt_name']         = Html::b(title_case($propKeys[$p]['street']) . ', '. title_case($propKeys[$p]['city']));
        $headerData['spec_code']        = Html::b($propKeys[$p]['state']);
        $headerData['status']           = Html::b($propKeys[$p]['zip']);
        $headerData['lease_start_date'] = Html::b(Format::usDate($propKeys[$p]['start_date']));
        array_unshift($allListByProp[$p],$headerData);
      }
    }
    return is_array($vData['prop']) ? $allListByProp : $allListByProp[$vData['prop']];
  }
################################################################################
##########################   GRID FUNCTION   ###################################  
################################################################################  
  private function _getColumnButtonReportList($req = []){
    $perm = Helper::getPermission($req);
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV'];
    $data[] = ['field'=>'count', 'title'=>'', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>20];
    $data[] = ['field'=>'prop', 'title'=>'Prop-Unit', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>60];
    //$data[] = ['field'=>'unit', 'title'=>'Unit', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>30];
    $data[] = ['field'=>'tnt_name', 'title'=>'Tenant Name', 'sortable'=> true,'filterControl'=> 'input', 'width'=>300, 'hWidth'=>110];
    $data[] = ['field'=>'spec_code', 'title'=>'Code','sortable'=> true, 'filterControl'=> 'input', 'width'=>25,'hWidth'=>25];
    $data[] = ['field'=>'status', 'title'=>'Status','sortable'=> true, 'filterControl'=> 'input', 'width'=>25,'hWidth'=>30];
    $data[] = ['field'=>'unit_type', 'title'=>'Type','sortable'=> true,'filterControl'=> 'input','width'=> 40, 'hWidth'=>25];
    $data[] = ['field'=>'bedBath', 'title'=>'Bd|Bth', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>25];
    $data[] = ['field'=>'lease_start_date', 'title'=>'Last Raise','sortable'=> true,'filterControl'=> 'input','width'=> 75, 'hWidth'=>45];
    $data[] = ['field'=>'base_rent', 'title'=>'Rent', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>45];
    $data[] = ['field'=>'rent_rate', 'title'=>'Tnt Rent', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>45];
    $data[] = ['field'=>'billing', 'title'=>'HUD', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>40];
    $data[] = ['field'=>'dep_held1', 'title'=>'Deposit', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>40];
    $data[] = ['field'=>'dep_int_last_date', 'title'=>'Move In', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>45];
    $data[] = ['field'=>'street', 'title'=>'Unit Address','sortable'=> true, 'filterControl'=> 'input','width'=> 250, 'hWidth'=>115];
    $data[] = ['field'=>'phone1', 'title'=>'Phone', 'sortable'=> true,'filterControl'=> 'input','width'=>80, 'hWidth'=>45];
    $data[] = ['field'=>'pad_size','title'=>'Pad Size','sortable'=>true,'width'=>80,'hWidth'=>25];
    $data[] = ['field'=>'unit_size','title'=>'Unit Size','sortable'=>true,'width'=>80,'hWidth'=>25];
    $data[] = ['field'=>'mh_owner','title'=>'Owner','sortable'=>true,'width'=>25,'hWidth'=>25];
    $data[] = ['field'=>'mh_serial_no','title'=>'M Ser No.','sortable'=>true,'hWidth'=>25];
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($vData, $isCsv = 0){
    $inputDate = $vData['date'];
    unset($vData['date'], $vData['trust']);
    $fields = explode(',',P::getSelectedField($this->_getColumnButtonReportList()) . ',unit,move_out_date,bedrooms,bathrooms');
    $result = M::getRentRollTenant($vData, $fields);
    $vData['date'] = $inputDate;

    $propRow = $this->_getPropRow($result, $vData);

    ## merge array of props into one array if its pdf or csv
    $tempData = [];
    if($isCsv){
      foreach($propRow as $k=>$v) {
        $tempData = array_merge($tempData, $v);
      }
      $propRow = $tempData;
    }
    return $propRow;
  }
//------------------------------------------------------------------------------
  private function _getTabData($props) {
    $propTabs = $columns = [];
    foreach($props as $prop) {
      $propTabs[$prop] = Html::table('', ['id'=> $prop]);
      $columns[$prop]  = $this->_getColumnButtonReportList();
    }
    $tab = Html::buildTab($propTabs, ['tabClass'=>'']);
    return ['tab' => $tab, 'column'=>$columns];
  }
//------------------------------------------------------------------------------
  private function _formatTenantData($source, $vData, $rows) {
    $inputDate   = strtotime($vData['date']);
    $moveOutDate = strtotime($source['move_out_date']);
    ## Dont add duplicate units
    if(isset($rows[$source['prop'].$source['unit']]) || $inputDate >= $moveOutDate) {
      return;
    }
    $rent = TenantController::getTenantRent($source);
    
    $source['billing']   = $rent['hud'];
    $source['phone1']    = !empty($source['phone1']) ? $source['phone1'] : '';
    $source['base_rent'] = $source['rent_rate'];
    $source['rent_rate'] = $rent['tntRent'];
    return $source;
  }
################################################################################
##########################   PDF FUNCTION   #################################  
################################################################################  
  private function _getPdfData($props, $column){
    $tableCollection = [];
    ## For summary row, shift column one space left because of colspan
    $colspanColumns = ['count'=>'count', 'prop'=>'prop', 'unit'=>'tnt_name', 'tnt_name'=>'spec_code', 'spec_code'=>'status','status'=>'unit_type','unit_type'=>'bedBath','bedBath'=>'lease_start_date', 'lease_start_date'=>'base_rent', 'base_rent'=>'rent_rate', 'rent_rate'=>'billing', 'billing'=>'dep_held1', 'dep_held1'=>'dep_int_last_date', 'dep_int_last_date'=>'street', 'street'=>'phone1', 'phone1'=>'pad_size','pad_size'=>'unit_size','unit_size'=>'mh_owner','mh_owner'=>'mh_serial_no','mh_serial_no'=>'mh_serial_no'];

    foreach($props as $idx => $prop) {
      $tableData = $headerPropVal = $headerTitleVal = [];
      foreach($prop as $i => $val){
        foreach($column as $v){
          $fieldVal  = $val['prop'] == 'Summary:' && $v['field'] != 'prop' ? $val[$colspanColumns[$v['field']]] : ( !empty($val[$v['field']]) ? $val[$v['field']] : '') ;
          ## If its Property info row (First row of the prop group), save the header information in the array to apply it to all the rows within the prop
          if(!empty($val['property'])  && ($i == 0)) {  
            $headerPropVal[$v['field']] = [
              'val'  => Html::b($fieldVal), 
              'param'=>isset($v['hWidth']) ? ['width'=> $v['hWidth'], 'align'=>'center'] : [],
            ];
            $headerTitleVal[$v['field']] = [
              'val'  => Html::b($v['title']), 
              'param'=>isset($v['hWidth']) ? ['width'=> $v['hWidth'], 'align'=>'center'] : [],
            ];
          }
          $tableData[$i][$v['field']] = [
            'val'   =>$fieldVal, 
            'header'=>[
              $headerPropVal[$v['field']],
              $headerTitleVal[$v['field']]
            ],
            'param' => [
              'align'=>'center',
            ]
          ];
          ## Apply styling to the header and summary rows
          $tableData[$i][$v['field']]['param']['colspan'] = $val['prop'] == 'Summary:' && $v['field'] == 'prop' ? '2' : '';
          $tableData[$i][$v['field']]['param']['style']   = !empty($val['street']) ? 'border: 1px solid #ccc' : 'font-weight:bold';
          $tableData[$i][$v['field']]['param']['bgcolor'] = $val['prop'] == 'Summary:' || !empty($val['property']) || empty($val['prop']) ? '#FFF' : '';
        }
      }
      ## Remove the Property row since all the information is saved in the header field
      unset($tableData[0]);
      $tableCollection[$idx] = $tableData;
    }
    return $tableCollection;
  }
}
