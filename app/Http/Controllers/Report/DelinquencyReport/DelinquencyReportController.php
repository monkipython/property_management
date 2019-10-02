<?php
namespace App\Http\Controllers\Report\DelinquencyReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Report\ReportController AS P;
use App\Library\{V, Form, Elastic, Html, GridData, TableName AS T, Helper, Format, PDFMerge, HelperMysql};
use App\Http\Models\ReportModel AS M; // Include the models class
use Storage;
use PDF;

class DelinquencyReportController extends Controller{
  private $_viewTable = '';
  private $_indexMain = '';
  private $_folderName = 'public/tmp/delinquency/';
  private $_mapping = [];
  public function __construct(){
    $this->_viewTable   = T::$tntTransView;
    $this->_mapping     = Helper::getMapping(['tableName'=>T::$tenant]);
    $this->_propMapping = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_indexMain   = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
    ]);
    $vData  = $valid['data'];
    $op     = $valid['op'];
    $vData += Helper::splitDateRate($vData['dateRange'] ,'date1');
    unset($vData['dateRange']);
    // Validate two date at the same year and month.
    if(!$this->_validateDateRange($vData['date1'],$vData['todate1'])){
      return ['error'=>['dateRange'=>Html::errMsg('The date range cannot be greater than 31 days and they must be the same month and year.')]];
    }
    $propList  = Helper::explodeField($vData,['prop','group1','prop_type'])['prop'];
    $rTenant = [];
    $balForwardWhere = '(';
    $balForwardData = [];
    $rDelinquencyBalForward = [];
    $rDelinquencyRentAmount = M::getDelinquencyRentAmount($vData['date1'],$vData['todate1'],$propList,$vData['status']);
    if(!empty($rDelinquencyRentAmount)){
      // Build the bal forward prop, unit, tenant 
      $first = true;
      foreach($rDelinquencyRentAmount as $v){
        $balForwardWhere .= (!$first) ? ' OR ' : '';
        $balForwardWhere .= '(prop=? AND unit=? AND tenant=?)';
        $first = false;
        $balForwardData[] = $v['prop'];
        $balForwardData[] = $v['unit'];
        $balForwardData[] = $v['tenant'];
      }
      $balForwardWhere .= ') AND date1<?';
      $balForwardData[] = $vData['date1'];
      $rDelinquencyRentAmount = Helper::keyFieldName($rDelinquencyRentAmount, ['prop', 'unit', 'tenant'], 'amount');
      $rTenant = $this->_getTenantFromElasticSearch(['prop'=>$propList],['match'=>['status'=>$vData['status']]],['billing', 'group1', 'prop','unit','tenant','tnt_name','street','city','zip','base_rent','county','state','mangtgroup'],['prop.keyword','unit.keyword','tenant']);
      $rDelinquencyBalForward = M::getDelinquencyBalForward($balForwardWhere,$balForwardData);
      $rDelinquencyBalForward = Helper::keyFieldName($rDelinquencyBalForward, ['prop', 'unit', 'tenant'], 'amount');
    }
    
    $columnReportList = $this->_getColumnButtonReportList($req);
    $column = $columnReportList['columns'];

    if(!empty($op) ){
      $field = P::getSelectedField($columnReportList, 1);
      $gridData = $this->_getGridData($rTenant,$rDelinquencyBalForward,$rDelinquencyRentAmount);
      switch ($op) {
        case 'show':  return $gridData;
        case 'csv':   return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':   return P::getPdf(P::getPdfData($gridData, $column), ['title'=>'Delinquency Report From '.$vData['date1'].' To '.$vData['todate1']]);
        case 'notice':  return $this->_exportNotice($gridData,$vData['date1'],$vData['todate1']); 
      }
    }
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $propGroup = $this->_getPropGroup();
    $fields = [
      'dateRange'=>['id'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange', 'req'=>1],
      'prop'     =>['id'=>'prop','label'=>'Prop', 'type'=>'textarea', 'value'=>'0001-9999', 'req'=>1],
      'group1'   =>['id'=>'group1','label'=>'Group','type'=>'option', 'option'=>$propGroup, 'req'=>1],
      'status'   =>['id'=>'status','label'=>'Tenant Status', 'type'=>'option', 'option'=>$this->_mapping['status'], 'value'=>'C', 'req'=>1],
      'prop_type'=>['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_propMapping['prop_type'],'req'=>0],
    ];
    return ['html'=>implode('',Form::generateField($fields)), 'column'=>$this->_getColumnButtonReportList($req)];
  }

################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'dateRange' =>'required|string|between:21,23',
      'prop'      => 'required|string',
      'group1'    => 'required|string',
      'status'    => 'required|string',
      'prop_type' => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport(); 
  }
//------------------------------------------------------------------------------
  // private function _getTable(){
  //   return [T::$prop];
  // }
//------------------------------------------------------------------------------
  private function _validateDateRange($fromDate, $toDate){
    $date1 = date_create($fromDate);
    $date2 = date_create($toDate);
    return (date_diff($date1,$date2)->days <= 31 && date_format($date1,'Y-m') == date_format($date2,'Y-m'));
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $reportList = ['pdf'=>'Download PDF','csv'=>'Download CSV','notice'=>'3-Day Notice'];
    $_getCreateButton = function($perm){
      $button =  Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=>'new', 'class'=>'btn btn-success']);
      return isset($perm['creditCheckcreate']) ? Html::a($button, ['href'=>'/creditCheck/create', 'style'=>'color:#fff;']) : '';
    };
    
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'sortable'=> true, 'width'=> 30, 'hWidth'=>30];
    $data[] = ['field'=>'unit', 'title'=>'Unit', 'sortable'=> true, 'width'=> 30, 'hWidth'=>30];
    $data[] = ['field'=>'tnt_name', 'title'=>'Tenant', 'sortable'=> true, 'width'=> 180, 'hWidth'=>180];
    $data[] = ['field'=>'street', 'title'=>'Street', 'sortable'=> true, 'width'=> 180, 'hWidth'=>180];
    $data[] = ['field'=>'city', 'title'=>'City', 'sortable'=> true,'width'=> 70, 'hWidth'=>70];
    $data[] = ['field'=>'zip',      'title'=>'Zip','sortable'=> true, 'width'=> 30, 'hWidth'=>30];
    $data[] = ['field'=>'base_rent', 'title'=>'Base Rent','sortable'=> true,'width'=> 75, 'hWidth'=>75];
    $data[] = ['field'=>'balFoward', 'title'=>'Bal Forward','sortable'=> true,'width'=> 75, 'hWidth'=>75];
    $data[] = ['field'=>'rent', 'title'=>'Rent','sortable'=> true,'width'=> 75, 'hWidth'=>75];
    $data[] = ['field'=>'endBal', 'title'=>'End Bal','sortable'=> true];
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getCreateButton($perm)]; 
  }
//------------------------------------------------------------------------------
  private function _getGridData($rTenant, $rBalForward, $rRentAmount){
    $rows = [];
    $lastRow = [];
    foreach($rTenant as $source){
      if(isset($rRentAmount[$source['prop'].$source['unit'].$source['tenant']])){
        $row = [];
        $rentAmount = $rRentAmount[$source['prop'].$source['unit'].$source['tenant']];
        $balFoward = (isset($rBalForward[$source['prop'].$source['unit'].$source['tenant']])) ? $rBalForward[$source['prop'].$source['unit'].$source['tenant']] : 0;
        $total = $balFoward + $rentAmount;
        if(Format::floatNumber($total) > 0){
          $row['prop'] = $source['prop'];
          $row['unit'] = $source['unit'];
          $row['tenant'] = $source['tenant'];
          $row['tnt_name'] = $source['tnt_name'];
          $row['street'] = $source['street'];
          $row['city'] = $source['city'];
          $row['zip'] = $source['zip'];
          $row['county'] = $source['county'];
          $row['state'] = $source['state'];
          $row['mangtgroup'] = $source['mangtgroup'];
          $row['group1'] = $source['group1'];
          $row['base_rent'] = Format::usMoney($source['base_rent']);
          $row['balFoward'] = Format::usMoney($balFoward);
          $row['rent'] = Format::usMoney($rentAmount);
          $row['endBal'] = Format::usMoney($total);
          $rows[] = $row;
        }
      }
    }
    return P::getRow($rows, $lastRow);
  }
//------------------------------------------------------------------------------
  private function _getEachTenantHtml($noticeData,$fromDate,$toDate){
    ##### TABLE DATA #####
    $balForward = (float)preg_replace('/\$|,/', '', $noticeData['balFoward']);
    $endBal     = (float)preg_replace('/\$|,/', '', $noticeData['endBal']);
    $rent       = (float)preg_replace('/\$|,/', '', $noticeData['base_rent']);
    $noticeData['openItem'] = [];
    $lastMonth  = strtotime('-1 month', strtotime($fromDate));
    $groupInfo  = $noticeData['groupInfo'];
    $officeHour = !empty($groupInfo['ap_inv_edit']) ? 'Office Hours: ' . preg_replace('/,/', '<br>', $groupInfo['ap_inv_edit']) : 'Office Hours: MONDAY THROUGH SATURDAY 9:00 A.M. TO 6:00 P.M.' . Html::br() . 'SUNDAY 10:00 A.M. TO 5:00 P.M.';
    
    $lastMonthNote    = ($balForward > 0) ? Html::bu(date('m/01/Y', $lastMonth)) . Html::repeatChar('_',9) . ' to ' . Html::bu(date('m/t/Y', $lastMonth)) . Html::repeatChar('_',9) . ' = ' . Html::bu(Format::usMoney($balForward))
                                          : Html::repeatChar('_',18).' to '.Html::repeatChar('_',18).' = $'.Html::repeatChar('_',20);
    $currentMonthRent = ($endBal - $rent > 0) ? $endBal - $balForward : $endBal;
   
    if(Format::floatNumber($endBal - $rent) <= 0){
      $noticeData['openItem'][] = ['amount'=>$endBal, 'date1'=>$fromDate];
    } else{
      $noticeData['openItem'][] = ['amount'=>$rent, 'date1'=>$fromDate];
      $noticeData['openItem'][] = ['amount'=>$endBal - $rent, 'date1'=>date('m/d/Y', strtotime('-1 month', strtotime($fromDate)))];
    }
    $tntName = $noticeData['tnt_name'];
    $footingText = 'At this time we have not been informed that your unit is in need of any repairs. We take our ';
    $footingText .= 'responsibility as a landlord very seriously. If you believe that items need to be corrected,';
    $footingText .= 'please address those issues in writing and we will immediately inspect and make necessary repairs.';
    $footingText .= 'Of course, if we do not receive any written repair requests, we will assume that there are no items';
    $footingText .= 'that need to be corrected at this time.'.Html::br(2);
    
    $listBalance = '';
    foreach($noticeData['openItem'] as $v){
      $listBalance .= 'Balance of Rent from ' . Html::bu(date('m/01/Y', strtotime($v['date1']))) . Html::repeatChar('_',9) . ' to ' . Html::bu(date('m/t/Y', strtotime($v['date1']))) . Html::repeatChar('_',9);
      $listBalance .= ' = ' . Html::bu(Format::usMoney($v['amount'])) . Html::br();
    }
    
    $fullAddress   = $noticeData['street'].', '.$noticeData['city'].', '.$noticeData['state'].' '.$noticeData['zip'];
    $companyName   = 'PAMA MANAGEMENT INC';
    $companyStreet = $groupInfo['street'];
    $companyCity   = $groupInfo['city'];
    $companyState  = $groupInfo['state'];
    $companyZip    = $groupInfo['zip'];
    $phone         = Format::phone($groupInfo['phone']);
    $tableData = [
      ['row'=>['val'=>Html::h1(Html::bu('3-Day Notice to Pay Rent or Quit')).HTML::br(),'param'=>['width'=>'100%', 'align'=>'center']]],
      ['row'=>['val'=>'To: '.Html::bu($tntName)]],
      ['row'=>['val'=>'DOE I, Through DOE X, Tenants in possession:'.Html::br(),'param'=>['width'=>'100%', 'align'=>'right']]],
      ['row'=>['val'=>'WITHIN THREE DAYS, after the service on you of this notice, you are hereby required to pay the']],
      ['row'=>['val'=>'rent of the premises hereinafter describred, of which you now hold possession, amounting to the']], 
      ['row'=>['val'=>'sum of '.Html::bu($noticeData['endBal']) . ' at the rental rate of '.Html::bu($noticeData['base_rent']). ' per '.Html::bu('month'). ' payable on the ' .Html::b('1st.') .Html::br()]],
      
      
      ['row'=>['val'=>$listBalance]],
//      ['row'=>['val'=>'Balance of Rent from '.Html::bu(Format::usDate($fromDate)).Html::repeatChar('_',9).' to '.Html::bu(Format::usDate($toDate)).Html::repeatChar('_',9).' = '.Html::bu(Format::usMoney($currentMonthRent))]],
//      ['row'=>['val'=>'Balance of Rent from '.$lastMonthNote]],
//      ['row'=>['val'=>'Balance of Rent from '.Html::repeatChar('_',18).' to '.Html::repeatChar('_',18).' = $'.Html::repeatChar('_',20).Html::br()]],
      
      
      ['row'=>['val'=>'Or you are here by required to deliver up possession of the premises hereinafter described within three']],
      ['row'=>['val'=>' days after the service on you of this notice, to the undersigned.'.Html::br()]],
      ['row'=>['val'=>'Or the undersigned will institute legal proceedings against you to recover possessions of said property']],
      ['row'=>['val'=>'with all rents due and damages and to declare the forfeiture of the lease or rental agreement under']],
      ['row'=>['val'=>'which you now hold possession of those premises located within the county of '.Html::bu($noticeData['county'])]],
      ['row'=>['val'=>'commonly known as ' . Html::bu($fullAddress) . Html::br()]],
      ['row'=>['val'=>'You are further notified that the undersigned does hereby elect to declare the forfeiture of your lease or']],
      ['row'=>['val'=>'rental agreement under which you now hold possession of the above described property'.Html::br()]],
      ['row'=>['val'=>'Dated: '.Html::bu(date('m/d/Y')).Html::space(25).'X'.Html::repeatChar('_',50)]],
      ['row'=>['val'=>'Property No: ' . Html::bu(implode('-', Helper::selectData(['prop', 'unit'], $noticeData))) . Html::space(40) . '(Agent / Owner) Signature'.Html::br(),'param'=>['width'=>'100%']]],
      ['row'=>['val'=>'LOCATION TO PAY RENT: '. Html::b($companyName),'param'=>['width'=>'100%', 'align'=>'right']]],
      ['row'=>['val'=>Html::b($companyStreet),'param'=>['width'=>'100%', 'align'=>'right']]],
      ['row'=>['val'=>Html::b($companyCity.', '.$companyState.' '.$companyZip),'param'=>['width'=>'100%', 'align'=>'right']]],
      ['row'=>['val'=>Html::b($phone),'param'=>['width'=>'100%', 'align'=>'right']]],
      ['row'=>['val'=>'AGENT: Brian Jimenez' .Html::br() ,'param'=>['width'=>'100%', 'align'=>'right']]],
      ['row'=>['val'=>$officeHour,'param'=>['width'=>'100%', 'align'=>'right']]],
      ['row'=>['val'=>Html::bu('24 Hour Drop-Box Available') . Html::br(),'param'=>['width'=>'100%', 'align'=>'right']]],
      ['row'=>['val'=>$footingText, 'param'=>['align'=>'center']]],
    ];
    return Html::buildTable(['data'=>$tableData,'isHeader'=>0,'isOrderList'=>0]);
  }
//------------------------------------------------------------------------------
  private function _exportNotice($results,$fromDate,$toDate){
    // First, build up each file.
    $group = Helper::keyFieldNameElastic(HelperMysql::getGroup(['prop', 'street', 'city', 'state', 'zip', 'phone', 'ap_inv_edit']), 'prop');
    $last = sizeof($results) - 1 ; 
    unset($results[$last]);
    $paths = [];
    $files = [];
    foreach($results as $notice){
      $id = $notice['prop'] . $notice['unit'] . $notice['tenant'];
      $fileName = $this->_folderName . $id . $fromDate . $toDate . '.pdf';
      $filePath = storage_path('app/' . $fileName);
      // Generate one PDF file for each row.
      $notice['groupInfo'] = $group[$notice['group1']];
      $this->_generatePdf($this->_getEachTenantHtml($notice,$fromDate,$toDate),$filePath);
      array_push($paths,$filePath);
      array_push($files, $fileName);
    }
    // Call library to merge all PDF files into one PDF file.
    $outputFileName = $this->_folderName . '3-Days-Notices-'.$fromDate.'-to-'.$toDate.'.pdf';
    $href = Storage::disk('public')->url(preg_replace('|public\/|', '', preg_replace('/\#/', '%23', $outputFileName)));
    return PDFMerge::mergeFiles(['paths'=>$paths,'files'=>$files,'fileName'=>$outputFileName,'href'=>$href]);
  }
//------------------------------------------------------------------------------
  private function _generatePdf($content,$filePath){
    $title       = '3-Days Notices';
    $orientation = 'P';
    $font        = 'times';
    $size        = '13';
    try{
      PDF::reset();
      PDF::SetTitle($title);
      PDF::setPageOrientation($orientation);
      PDF::setPrintHeader(false);
      PDF::SetPrintFooter(false);
      PDF::SetFont($font, '', $size);
      PDF::SetMargins(10, 13, 10);
      PDF::SetAutoPageBreak(TRUE, 10);
      PDF::AddPage();
      PDF::writeHTML($content,true,false,true,false,$orientation);
      PDF::Output($filePath, 'F');
    } catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
################################################################################
##########################   Elastic Search FUNCTION   #########################  
################################################################################
//------------------------------------------------------------------------------
  private function _getTenantFromElasticSearch($filters=[],$queryConditions=[],$source=[],$sort=[],$fetchAllFlag=true,$from=0,$size=1000) {
    $r = [];
//    do {  
      $search = Elastic::searchQuery([
        'index'=>T::$tenantView,
        'query'=>[
          'must'=>$filters + $queryConditions,
        ],
        '_source'=>$source,
        'sort'=>$sort,
        'from'=>$from,
        'size'=>$size
      ]);
      $from += $size;
      $total = $search['hits']['total'];
      if(!empty($search['hits']['hits'])){
        $data = Helper::getElasticResult($search);
        foreach($data as $i=>$val){
          $tmp = $val['_source'];
          if(isset($tmp['billing'])){
            $tmp['base_rent'] = 0;
            foreach($tmp['billing'] as $v){
              if($v['schedule'] == 'M' && $v['stop_date'] == '9999-12-31' && $v['gl_acct'] == '602'){
                $tmp['base_rent'] += $v['amount'];
              }
            }
          }
          array_push($r,$tmp);
        }
      }
//    } while ($fetchAllFlag && ($from<$total));
    return $r; 
  }

  private function _getPropGroup() {
    $r = [];
    $search = Elastic::searchQuery([
      'index'=>T::$groupView,
      '_source'=>['prop'],
      "sort"=>['prop.keyword']
    ]);
    if(!empty($search['hits']['hits'])){
      $data = Helper::getElasticResult($search);
      foreach($data as $i=>$val){
        $r[$val['_source']['prop']] = $val['_source']['prop']; 
      }
    }
    return $r;
  }
}