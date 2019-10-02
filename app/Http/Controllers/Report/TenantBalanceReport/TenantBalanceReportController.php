<?php
namespace App\Http\Controllers\Report\TenantBalanceReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController AS P;
use App\Library\{V, Form, Html, GridData, TableName AS T, Helper, Format};
use App\Http\Models\ReportModel AS M; // Include the models class
use Illuminate\Support\Facades\DB;

class TenantBalanceReportController extends Controller{
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping   = [];
  private $typeOption = ['group1'=>'Group','prop'=>'Property'];
  
  public function __construct(Request $req){
    $this->_viewTable = T::$tenantView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$prop]);
  }
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
      'date'       => ['id'=>'date','label'=>'Date', 'class'=>'date','type'=>'text', 'value'=>date("m/d/Y"), 'req'=>1],
      'type'       => ['id'=>'type','name'=>'type','label'=>'Display By','type'=>'option','option'=>$this->typeOption,'req'=>1],
      'prop'       => ['id'=>'prop','name'=>'prop','label'=>'Prop','type'=>'textarea','value'=>'0001-9999'],
      'prop_type'  => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type']],
      'group1'     => ['id'=>'group1','name'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'       => ['id'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Los Angeles'],
      'trust'      => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, *ZA67'],  
      'cons1'      => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'****83-**83,Z64'],
      'amount '    => ['id'=>'amount', 'label'=>'Balance Over', 'class'=>'decimal', 'type'=>'text', 'value'=>0, 'req'=>1],
    ];
    $tab = [];
    return [
      'html'=>implode('',Form::generateField($fields)), 
      'tab' =>$tab,
    ];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData = $valid['data'];
    $op    = $valid['op'];
    $vData['amount']   = Format::numberOnly($vData['amount']);
    $vData['toamount'] = '*';
    $column = $this->_getColumnButtonReportList()['columns'];
    if(!empty($vData['selected']) && $op == 'show') {
      $vData[$vData['type']] = $vData['type'] == 'group1' ? '#'. ucfirst($vData['selected']) : $vData['selected'];
    }
    $vData['prop'] = Helper::explodeField($vData,['prop','group1','prop_type','trust','city','cons1'])['prop'];
    
    if(!empty($op)){
      if($op == 'csv' || $op == 'pdf') {
        unset($vData['selected']);
      }
      switch ($op) {
        case 'tab':
          $result    = $this->_getRentBal($vData);
          $tabValues = array_unique(array_column($result, $vData['type']));
          $tabValues = array_filter($tabValues, 'strlen');
          return $this->_getTabData($tabValues);
        case 'show': return $this->_getGridData($vData); 
        case 'csv':  return P::getCsv($this->_getGridData($vData, 1), ['column'=>$column]);
        case 'pdf':  return P::getPdf($this->_getPdfData($this->_getGridData($vData), $column), ['title'=>'Tenant Balance Report'], 1);
      }
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'type'       => 'required|string',
      'group1'     => 'nullable|string', 
      'prop'       => 'nullable|string', 
      'amount'     => 'required|numeric',
      'date'       => 'required|string',
      'prop_type'  => 'nullable|string|between:0,1',
      'selected'   => 'nullable|string|between:1,6',
      'cons1'      => 'nullable|string', 
      'city'       => 'nullable|string',
      'trust'      => 'nullable|string',
    ] + GridData::getRuleReport(); 
  }
//------------------------------------------------------------------------------
  private function _getTenantsWithBalance($vData) {
    $rRentBal = $this->_getRentBal($vData);
    $result   = M::getTenantsWithProp($vData);
    $rows     = [];
    foreach($result as $v) {
      $source  = $v['_source'];
      $rentBal = isset($rRentBal[$source['prop'] . $source['unit'] . $source['tenant']]) ? $rRentBal[$source['prop'] . $source['unit'] . $source['tenant']] : '';
      if(!empty($rentBal)) {
        $source['amount']   = $rentBal['amount'];
        $source['sys_date'] = $rentBal['sys_date'];
        $source['phone1']   = !empty($source['phone1']) ? $source['phone1'] : '';
        $rows[] = $source;
      }
    }
    $tenantByGroup = Helper::groupBy($rows,$vData['type']);
    ksort($tenantByGroup);
    return $tenantByGroup;
  }
//------------------------------------------------------------------------------
  private function _getRentBal($vData) {
    $select = ['tt.prop', 'tt.unit', 'tt.tenant', 'p.group1', 'tt.sys_date',DB::raw("SUM(tt.amount) as amount")];
    $results = DB::table(T::$tntTrans . ' as tt')
                  ->select($select)
                  ->join(T::$prop . ' as p', 'p.prop', '=', 'tt.prop')
                  ->whereIn('tt.prop', $vData['prop'])
                  ->groupBy('tt.prop', 'tt.unit', 'tt.tenant')
                  ->havingRaw('SUM(tt.amount) > ?', [$vData['amount']])
                  ->get()
                  ->toArray();
    return Helper::keyFieldName($results, ['prop', 'unit', 'tenant']);
  }
################################################################################
##########################   GRID FUNCTION   ###################################  
################################################################################  
  private function _getColumnButtonReportList($req = []){
    $perm = Helper::getPermission($req);
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV'];
    $data[] = ['field'=>'count', 'title'=>' ','sortable'=> true, 'filterControl'=> 'input', 'width'=>25,'hWidth'=>25];
    $data[] = ['field'=>'group1', 'title'=>'Group','sortable'=> true, 'filterControl'=> 'input', 'width'=>25,'hWidth'=>30];
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>30];
    $data[] = ['field'=>'unit', 'title'=>'Unit', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>30];
    $data[] = ['field'=>'tenant', 'title'=>'Tenant','sortable'=> true, 'filterControl'=> 'input', 'width'=>25,'hWidth'=>25];
    $data[] = ['field'=>'amount', 'title'=>'Balance','sortable'=> true, 'filterControl'=> 'input', 'width'=>100,'hWidth'=>60];
    $data[] = ['field'=>'lease_opt_date', 'title'=>'Will Pay By', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 55, 'hWidth'=>70];
    $data[] = ['field'=>'tnt_name', 'title'=>'Tenant Name', 'sortable'=> true,'filterControl'=> 'input', 'width'=>300, 'hWidth'=>150];
    $data[] = ['field'=>'street', 'title'=>'Address','sortable'=> true, 'filterControl'=> 'input','width'=> 250, 'hWidth'=>110];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input','width'=> 150, 'hWidth'=>60];
    $data[] = ['field'=>'bedrooms', 'title'=>'Bed', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>25];
    $data[] = ['field'=>'bathrooms', 'title'=>'Bath', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 25, 'hWidth'=>25];
    $data[] = ['field'=>'move_in_date', 'title'=>'Move In', 'sortable'=> true,'filterControl'=> 'input', 'width'=> 50, 'hWidth'=>50];
    $data[] = ['field'=>'phone1', 'title'=>'Phone', 'sortable'=> true,'filterControl'=> 'input', 'width'=>50, 'hWidth'=>50];
    $data[] = ['field'=>'sys_date', 'title'=>'Last Updated', 'sortable'=> true,'filterControl'=> 'input', 'hWidth'=>90];

    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($vData, $isCsv = 0){
    $tenantByGroup = $this->_getTenantsWithBalance($vData);
    foreach($tenantByGroup as $g => $data) {
      ## Calculate the sum and count of the tenants
      $groupLastRow = ['count'=>'', 'group1'=>'', 'prop'=>'', 'unit'=>'', 'tenant'=>'', 'amount'=>0, 'lease_opt_date'=>'', 'tnt_name'=>0, 'street'=>'', 'city'=>'', 'bedrooms'=>'', 'bathrooms'=>'', 'move_in_date'=>'', 'phone1'=>'', 'sys_date'=>''];
      $rCount = 1;
      foreach($data as $k => $v) {
        $groupLastRow['amount'] += $v['amount'];
        $groupLastRow['tnt_name']++;
        $tenantByGroup[$g][$k]['count']  = $rCount++;
        $tenantByGroup[$g][$k]['amount'] = Format::usMoney($data[$k]['amount']);
        $tenantByGroup[$g][$k]['lease_opt_date'] = Format::usDate($data[$k]['lease_opt_date']);
        $tenantByGroup[$g][$k]['move_in_date']   = Format::usDate($data[$k]['move_in_date']);
      }
      $groupLastRow['count']    = 'Summary:';
      $groupLastRow['tnt_name'] = 'Total: ' . $groupLastRow['tnt_name'];
      $groupLastRow['amount']   = Format::usMoney($groupLastRow['amount']);
      $tenantByGroup[$g][] = $groupLastRow;
    } 
    $returnRow = $tenantByGroup;
    ## merge array of props into one array if its csv
    if($isCsv){
      $allCombined = [];
      foreach($tenantByGroup as $k=>$v) {
        $allCombined = array_merge($allCombined, $v);
      }
      $returnRow = $allCombined;
    }
    return isset($vData['selected']) ? reset($returnRow) : $returnRow;
  }
//------------------------------------------------------------------------------
  private function _getTabData($groups) {
    $groupTabs = $columns = [];
    foreach($groups as $group) {
      $repGroup = str_replace('#', '', $group);
      $groupTabs[$repGroup] = Html::table('', ['id'=> $repGroup]);
      $columns[$repGroup] = $this->_getColumnButtonReportList();
    }
    $tab = Html::buildTab($groupTabs, ['tabClass'=>'']);
    return ['tab' => $tab, 'column'=>$columns];
  }
################################################################################
##########################   PDF FUNCTION   #################################  
################################################################################  
  private function _getPdfData($r, $column){
    $tableCollection = [];
    $colspanColumns = ['group1'=>'prop', 'prop'=>'unit', 'unit'=>'tenant','tenant'=>'amount','amount'=>'lease_opt_date','lease_opt_date'=>'tnt_name','tnt_name'=>'street', 'street'=>'city', 'city'=>'bedrooms', 'bedrooms'=>'bathrooms','bathrooms'=>'move_in_date', 'move_in_date'=>'phone1', 'phone1'=>'sys_date', 'sys_date'=>'sys_date'];
    foreach($r as $group => $rows) {
      $tableData = [];
      foreach($rows as $i=>$val){
        foreach($column as $j=>$v){
          $fieldVal  = $val['count'] == 'Summary:' && $v['field'] != 'count'  ? $val[$colspanColumns[$v['field']]] : $val[$v['field']];
          $tableData[$i][$v['field']] = [
            'val'=>$fieldVal, 
            'header'=>[
              'val'  =>Html::b($v['title']), 
              'param'=>isset($v['hWidth']) ? ['width'=>$v['hWidth'], 'align'=>'center'] : []
            ]
          ];
          ## Apply styling to the summary rows
          $tableData[$i][$v['field']]['param']['colspan'] = $val['count'] == 'Summary:' && $v['field'] == 'count' ? '2' : '';
          $tableData[$i][$v['field']]['param']['bgcolor'] = $val['count'] == 'Summary:' ? '#FFF' : '';
          ## Apply bold text to count field and summmary rows
          $tableData[$i][$v['field']]['param']['style']   = $v['field'] == 'count' || $val['count'] == 'Summary:' ? 'font-weight:bold' : '';
          ## Apply align left to column tenant name and Address
          $tableData[$i][$v['field']]['param']['align']   = $v['field'] == 'tnt_name' || $v['field'] == 'street' ? 'left' : 'center';
        }
      }
      $tableCollection[$group] = $tableData;
    }
    return $tableCollection;
  }
}