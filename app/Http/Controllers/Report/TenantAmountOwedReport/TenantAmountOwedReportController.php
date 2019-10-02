<?php
namespace App\Http\Controllers\Report\TenantAmountOwedReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};
use App\Http\Models\{ReportModel AS M}; // Include the models class

/*
ALTER TABLE `ppm`.`tnt_security_deposit` 
ADD INDEX `prop_unit_tnt_idx` (`prop` ASC, `unit` ASC, `tenant` ASC);
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `active`) VALUES ('0', 'Report', 'fa fa-fw fa-file-pdf-o', 'report', 'Report', 'fa fa-fw fa-cog', 'Report', 'fa fa-fw fa-users', 'tenantAmountOwedReport', '', 'To Access Tenant Amount Owed Report', '1');
 */

class TenantAmountOwedReportController extends Controller {
  public $typeOption  = ['city'=>'City', 'group1'=>'Group','cons1'=>'Owner','prop'=>'Property','trust'=>'Trust'];
  private $_mapping   = [];
  private static $_instance;
  public function __construct(){
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
      'prop'      => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea', 'value'=>'0001-9999', 'req'=>0],
      'group1'    => ['id'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'      => ['id'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Torrance'],
      'cons1'     => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'****83-**83,Z64'],
      'trust'     => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'****83-**83,*ZA67'],
      'prop_type' => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type']]
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList($req)];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData = $valid['data'];
    $op    = $valid['op'];
    $type  = isset($this->typeOption[Helper::getValue('type',$vData)]) ? $vData['type'] : 'group1';
    $prop  = Helper::explodeField($vData,['prop','group1','trust','cons1','city','prop_type'])['prop'];
    $vData['prop'] = $prop;
    
    $columnReportList = $this->_getColumnButtonReportList($type,$vData);
    $column = $columnReportList['columns'];
    if(!empty($op)){
      $r = M::getTenantAmountOwed('prop',$vData['prop']);
      $gridData = $this->_getGridData($r, $type,$column,$vData);
      switch ($op) {
        case 'show':  return $gridData; 
        case 'csv' :  return P::getCsv($gridData, ['column'=>$this->_getColumnButtonReportList($type)['columns']]);
        case 'pdf' :  return P::getPdf(P::getPdfData($gridData,$this->_getColumnButtonReportList($type)['columns']),['title'=>'Rent Raise Summary Report by ' . $this->typeOption[$vData['type']],'orientation'=>'P','chunk'=>75,'titleSpace'=>75]);
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
    $data[] = ['field'=>'prop','title'=>'Prop-Unit-Tnt','sortable'=>true,'width'=>60,'hWidth'=>75];
    $data[] = ['field'=>'tnt_name','title'=>'Tenant Name','sortable'=>true,'width'=>350,'hWidth'=>210];
    $data[] = ['field'=>'forward_address','title'=>'Forward Address','sortable'=>true,'width'=>350,'hWidth'=>160];
    $data[] = ['field'=>'amount','title'=>'Amount Owed','sortable'=>true,'width'=>70,'hWidth'=>80];
    $data[] = ['field'=>'move_out_date','title'=>'Move Out','sortable'=>true,'hWidth'=>65];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'report'    => 'required|string',
      'prop'      => 'nullable|string',
      'type'      => 'nullable|string|between:4,6',
      'group1'    => 'nullable|string',
      'city'      => 'nullable|string',
      'cons1'     => 'nullable|string',
      'trust'     => 'nullable|string',
      'prop_type' => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getGridData($r,$type='group1',$columns=[],$vData=[]){
    $rows        = $data = [];
    $rTenant     = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'  => ['tenant_id','prop','unit','tenant','tnt_name','move_out_date'],
      'size'     => 120000,
      'query'    => [
        'must'   => [
          'prop.keyword'  => $vData['prop'],
          'satus.keyword' => 'P'
        ]
      ]
    ]),['prop','unit','tenant']);
    
    $rProp       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   => T::$propView,
      'sort'    => [$type . '.keyword'=>'asc'],
      '_source' => ['prop','trust','cons1','group1','city'],
      'size'    => 50000,
      'query'   => [
        'must'  => [
          'prop.keyword'  => $vData['prop'],
        ]
      ]
    ]),'prop');
    
    $tenantVendorIds = [];
    foreach($r as $i => $v){
      $tenant    = Helper::getValue($v['prop'] . $v['unit'] . $v['tenant'],$rTenant,[]);
      $tntName   = Helper::getValue('tnt_name',$tenant);
      $moveOut   = Helper::getValue('move_out_date',$tenant);
      
      $prop               = Helper::getValue($v['prop'],$rProp,[]);
      $groupVal           = Helper::getValue($type,$prop);
      $tenantVendorIds[]  = '#' . implode('-',[$v['prop'],$v['unit'],$v['tenant']]);
      $data[$groupVal][]  = [
        'prop'          => implode('-',[$v['prop'],$v['unit'],$v['tenant']]),
        'amount'        => $v['amount_owed'],
        'tnt_name'      => $tntName,
        'move_out_date' => $moveOut,
      ];
    }
    
    ksort($data);
    $rVendor      = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$vendorView,
      '_source'  => ['vendid','street'],
      'size'     => 120000,
      'query'    => [
        'must'   => [
          'vendid.keyword' => $tenantVendorIds,
        ]
      ]
    ]),'vendid','street');
    
    $grandTotal   = ['amount'=>0];
    foreach($data as $group => $val){
      $groupSum   = ['amount'=>0];
      $rows[]     = ['prop'=>Html::b($group == 'city' ? title_case($group) : $group)];
      foreach($val as $i => $v){
        $vendorId             = '#' . $v['prop'];
        $fwdAddress           = Helper::getValue($vendorId,$rVendor);
        $groupSum['amount']  += $v['amount'];
        $grandTotal['amount']+= $v['amount'];
        $rows[]               = [
          'prop'            => $v['prop'],
          'amount'          => Format::usMoney($v['amount']),
          'forward_address' => title_case($fwdAddress),
          'tnt_name'        => title_case($v['tnt_name']),
          'move_out_date'   => Format::usDate($v['move_out_date']),
        ];
      }
      $groupSum['prop']    = Html::b($group == 'city' ? title_case($group) : $group);
      $groupSum['amount']  = Html::b(Format::usMoney($groupSum['amount']));
      $rows[]              = $groupSum;
      $rows[]              = [];
    }
    
    $grandTotal['prop']    = Html::b(Html::u('Grand Total: '));
    $grandTotal['amount']  = Html::b(Html::u(Format::usMoney($grandTotal['amount'])));
    //$rows[]                = $grandTotal;
    return P::getRow($rows,$grandTotal);
  }
}

