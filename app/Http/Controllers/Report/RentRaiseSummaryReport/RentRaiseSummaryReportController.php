<?php
namespace App\Http\Controllers\Report\RentRaiseSummaryReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `active`) VALUES ('0', 'Report', 'fa fa-fw fa-file-pdf-o', 'report', 'Report', 'fa fa-fw fa-cog', 'Report', 'fa fa-fw fa-users', 'rentRaiseSummaryReport', '', 'To Access Rent Raise Summary Report', '1');
 */

class RentRaiseSummaryReportController extends Controller {
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
      'dateRange' => ['id'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange','value'=>date('01/01/Y') . ' - ' . date('12/31/Y'),'req'=>1],
      'prop'      => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea', 'value'=>'0001-9999', 'req'=>0],
      'group1'    => ['id'=>'group1','label'=>'Group','type'=>'textarea', 'placeHolder'=>'Ex. #S82-#S93,#S96'],
      'city'      => ['id'=>'city','label'=>'City','type'=>'textarea','placeHolder'=>'Ex. Alameda-Fresno,Torrance'],
      'cons1'     => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'****83-**83,Z64'],
      'trust'     => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'****83-**83,*ZA67'],
      'prop_type' => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type']]
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList(), 'isIncludeDefaultDaterange'=>false];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData = $valid['data'];
    $op    = $valid['op'];
    $type  = isset($this->typeOption[Helper::getValue('type',$vData)]) ? $vData['type'] : 'group1';
    $prop  = Helper::explodeField($vData,['prop','group1','trust','cons1','city','prop_type'])['prop'];
    $vData+= Helper::splitDateRate($vData['dateRange'],'submitted_date');
    unset($vData['dateRange']);
    
    $this->_validateDateRange($vData);
    $columnReportList = $this->_getColumnButtonReportList($type,$vData);
    $column = $columnReportList['columns'];
    $sortKey = $type . '.keyword';
    if(!empty($op)){
      $r = Elastic::searchQuery([
        'index'  =>T::$rentRaiseView,
        '_source'=>['rent_raise_id','prop','cons1','prop_type','city','trust','submitted_date','rent','raise','group1','unit','tenant','tenant_id',T::$rentRaise],
        'sort'   =>[$sortKey=>'asc', T::$rentRaise . '.submitted_date'=>'asc','prop_type.keyword'=>'asc', 'prop.keyword'=>'asc', 'unit.keyword'=>'asc'],
        'query'  =>[
          'must' => [
            'prop.keyword' => $prop,
            'range'        => [
              T::$rentRaise . '.submitted_date' => [
                'gte'      => $vData['submitted_date'],
                'lte'      => $vData['tosubmitted_date'],
              ],
            ]
          ],
        ] 
      ]);
      $gridData = $this->_getGridData($r, $type,$column,$vData);
      switch ($op) {
        case 'show':  return $gridData; 
        case 'csv' :  return P::getCsv($gridData, ['column'=>$this->_getColumnButtonReportList($type)['columns']]);
        case 'pdf' :  return P::getPdf(P::getPdfData($gridData,$this->_getColumnButtonReportList($type)['columns']),['title'=>'Rent Raise Summary Report by ' . $this->typeOption[$vData['type']]]);
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'report'    => 'required|string',
      'prop'      => 'nullable|string',
      'dateRange' => 'required|string|between:21,23',
      'type'      => 'nullable|string|between:4,6',
      'group1'    => 'nullable|string',
      'city'      => 'nullable|string',
      'cons1'     => 'nullable|string',
      'trust'     => 'nullable|string',
      'prop_type' => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($type = 'group1',$vData=[]){
    $reportList = [
      'pdf'  =>'Download PDF',
      'csv'  =>'Download CSV',
    ];
    
    $stopDate  = date('Y-m',strtotime(Helper::getValue('tosubmitted_date',$vData,date('Y-m'))));
    //$stopDate = date('Y-m',strtotime(Helper::getValue('tosubmitted_date',$vData)));
    $stopTs    = strtotime($stopDate);

    $startDate = date('Y-m',strtotime(Helper::getValue('submitted_date',$vData,date('Y-m') . ' -1 year')));
    //$startDate = date('Y-m',Helper::getValue('submitted_date',$vData,strtotime(date('Y-m') . ' -1 year')));
    //$startDate = date('Y-m',strtotime(Helper::getValue('submitted_date',$vData)));
    $startTs   = strtotime($startDate);
    $data = [];
    
    $maxCols  = 12;
    $numCols  = 0;
    
    $dateCols = [];
    
    $currentTs = $stopTs;
    for($i = 0; $i < $maxCols; $i++){
      $dateCols[intval(date('n',$currentTs))] = date('Y-m',$currentTs);
      $currentTs  = strtotime(date('Y-m',$currentTs) . ' -1 month');
      if($currentTs < $startTs){
        break;
      }
    }
    
    ksort($dateCols);

    $dateCols = array_values($dateCols);
    $data[]   = ['field'=>'groupBy','title'=>'Group','width'=>60,'hWidth'=>72];
    foreach($dateCols as $i => $v){
      $data[] = ['field'=>$v,'title'=>date('F',strtotime($v)),'sortable'=>true,'width'=>40,'hWidth'=>62,'param'=>['align'=>'right'],'headerParam'=>['align'=>'right']];
    }
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r, $type = 'group1',$columns=[]){
    $result       = Helper::getElasticResult($r);
    $rows         = $data = [];

    $lastRow      = ['groupBy'=>''];
    $grandTotal   = ['groupBy'=>'Grand Total'];
    
    $originalCols = Helper::keyFieldName($this->_getColumnButtonReportList($type)['columns'],'field','field');
    foreach($originalCols as $k => $v){
      $originalCols[$k] = $k != 'groupBy' ? date('m',strtotime($v)) : $v;
    }
    $originalCols     = array_flip($originalCols);
    $translationCols  = [];
    foreach($columns as $i => $v){
      if($v['field'] != 'groupBy'){
        $month            = date('m',strtotime($v['field']));
        $translationCols += !empty($originalCols[$month]) ? [$v['field'] => $originalCols[$month]] : [];
      }
    }
    
    foreach($columns as $i => $v){
      $lastRow[$v['field']]    = 0;
      $grandTotal[$v['field']] = 0;
    }
    
    $groupedData   = [];
    $allowedFields = array_column($columns,'field');
    $allowedFields = array_combine($allowedFields,$allowedFields);
    $tenantIds     = array_column(array_column($result,'_source'),'tenant_id');
    
    $rTenant       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'       => T::$tenantView,
      'size'        => 50000,
      '_source'     => ['tenant_id',T::$billing],
      'query'       => [
        'must'      => [
          'status.keyword'   => 'C',
          'tenant_id'        => $tenantIds,
        ]
      ]
    ]),'tenant_id');
    foreach($result as $i => $v){
      $source      = $v['_source'];
      $groupVal    = Helper::getValue($type,$source);
      $groupedData[$groupVal] = Helper::getValue($groupVal,$groupedData,$lastRow);
      $groupedData[$groupVal]['groupBy'] = $groupVal;
      $rentRaise   = Helper::getValue(T::$rentRaise,$source,[]);
      $billing     = Helper::getValue(T::$billing,Helper::getValue($source['tenant_id'],$rTenant,[]),[]);
      if(!empty($rentRaise)){
        $dateSums = [];
        foreach($rentRaise as $idx => $val){
          $subDate    = Helper::getValue('submitted_date',$val);
          $colVal     = date('Y-m',strtotime($subDate));
      
          $hud        = $this->_getHudTotal($billing,$colVal);
          $raiseDiff  = ((Helper::getValue('raise',$val,0) + $hud) - Helper::getValue('rent',$val,0));
          $dateSums[$colVal] = $raiseDiff;
        }
        
        foreach($dateSums as $key => $val){
          if(!empty($allowedFields[$key]) && !empty($translationCols[$key])){
            $newKey                          = $translationCols[$key];
            $groupedData[$groupVal][$newKey] = !empty($groupedData[$groupVal][$newKey]) ? $groupedData[$groupVal][$newKey] + $val : $val;
            $grandTotal[$newKey]             = !empty($grandTotal[$newKey]) ? $grandTotal[$newKey] + $val : $val;
          }
        }
      }
    }
    
    $yearRow  = [];
    foreach($allowedFields as $k => $v){
      $newKey            = $k !== 'groupBy' ? $translationCols[$k] : $k;
      $yearRow[$newKey]  = $k !== 'groupBy' ? Html::b(date('Y',strtotime($k))) : '';
    }
    
    $rows[]                 = $yearRow;
    $allowedTranslationCols = [];
    foreach($allowedFields as $k => $v){
      $newKey                           = $k !== 'groupBy' ? $translationCols[$k] : $k;
      $allowedTranslationCols[$newKey]  = $v;
    }
    
    foreach($groupedData as $k => $v){
      foreach($v as $key => $value){
        if($key !== 'groupBy' && !empty($allowedTranslationCols[$key])){
          $v[$key] = !empty($value) ? Format::usMoney($value) : '-';
        }
      }
        
      $rows[]  = $v;
    }
    
    foreach($allowedTranslationCols as $k => $v){
      $value           = !empty($grandTotal[$k]) ? $grandTotal[$k] : 0;
      $grandTotal[$k]  = $k !== 'groupBy' ? Html::b(Format::usMoney($value)) : Html::b('Grand Total');
    }

   //$rows[]  = $grandTotal;
    return P::getRow($rows, $grandTotal);
  }
//------------------------------------------------------------------------------
  private function _validateDateRange($vData){
    $fromDate    = $vData['submitted_date'];
    $toDate      = $vData['tosubmitted_date'];
    
    $difference  = Helper::getdateDifference($fromDate,$toDate,'%y %m');

    list($year,$month) = explode(' ',$difference);
    if($year > 1 || ($year == 1 && $month >= 1)){
      Helper::echoJsonError(Html::errMsg('Error: the maximum date range is up to 1 year'));
    }
  }
//------------------------------------------------------------------------------
  private function _getHudTotal($billing,$month){
    $hud = 0;
    
    foreach($billing as $i => $v){
      if($v['stop_date'] == '9999-12-31' && $v['schedule'] == 'M' && $v['service_code'] == 'HUD'){
        $hud += $v['amount'];
      }
    }
    
    return $hud;
  }
}

