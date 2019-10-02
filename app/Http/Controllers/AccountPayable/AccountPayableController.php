<?php
namespace App\Http\Controllers\AccountPayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Upload, Account, TableName AS T, Helper,Format};
use App\Http\Models\{Model, AccountPayableModel AS M}; // Include the models class
use SimpleCsv;
use PDF;
use Storage;

class AccountPayableController extends Controller {
  private static $_instance;
  public $viewFileUploadTypes = [];
//------------------------------------------------------------------------------
  public function __construct(){
    $this->viewFileUploadTypes = [
      T::$vendorView                => 'vendors',
      T::$vendorBusinessLicenseView => 'business_license',
      T::$vendorGardenHoaView       => 'gardenHoa',
      T::$vendorPropTaxView         => 'prop_tax',
      T::$vendorInsuranceView       => 'insurance',
      T::$vendorMaintenanceView     => 'maintenance',
      T::$vendorMortgageView        => 'mortgage',
      T::$vendorUtilPaymentView     => 'util_payment',
      T::$vendorPendingCheckView    => 'pending_check',
    ];
  }
//------------------------------------------------------------------------------
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  } 
//------------------------------------------------------------------------------
  public static function getUploadForm(){
    $html = Upload::getHtml();
    $ul = Html::ul('', ['class'=>'nav nav-pills nav-stacked', 'id'=>'uploadList']);
    $uploadForm = 
      Html::div(Html::div($html['container'], ['class'=>'col-md-12']), ['class'=>'row fileUpload']) .
      Html::div(Html::div($ul, ['class'=>'col-md-3']) . Html::div('', ['class'=>'col-md-9', 'id'=>'uploadView']),['class'=>'row']) . 
      $html['hiddenForm'];
    return $uploadForm;    
  }
//------------------------------------------------------------------------------
  public static function getFileUploadContent($req,$extensionAllow=['txt']){
    // VALIDATE THE FILE FIRST
    $valid = V::startValidate([
      'rawReq'    => ['qqfilename'=>Upload::getName()] + $req->all(),
      'rule'      => Upload::getRule(),
      'orderField'=> Upload::getOrderField()
    ]);
    $file = Upload::startUpload($valid, $extensionAllow);
    $textList = explode("\r\n", Storage::get('public/' . $valid['data']['type'] . '/'. $file['data']['data']['qquuid'] . '/' . $file['data']['data']['file']));
    
    return ['data'=>$textList, 'fileInfo'=>$file];  
  }
//------------------------------------------------------------------------------
  public static function validateCsvRow($row,$columns,$errMsg){
    $entry       = explode(',',trim($row));
    return count($entry) == count($columns) ? array_combine($columns,$entry) : Helper::echoJsonError($errMsg,'popupMsg');
  }
//------------------------------------------------------------------------------
  public static function getCsvList($textList,$columns,$errMsg){
    $allList = [];
    unset($textList[0]);
    foreach($textList as $v){
      if(!empty($v)){
        $allList[] = self::validateCsvRow($v,$columns,$errMsg);
      }    
    }
    return $allList;
  }
//------------------------------------------------------------------------------
/*
 * @desc Rearranges an array of parsed CSV data into request data that can 
 *       be parsed and analyzed by the validation V library.
 * @params {array} $data: Parsed CSV data that is an iterative array of associative where all the keys
 *        in each item are the same for each item.
 *        @example
        [
 *          0 => ['key1' => 'ABC','key2'=>34],
 *          1 => ['key1' => 'DEF','key2'=>45],
        ]
 * @return {array} Array that can be passed into V validation library
         @example
 *      [
            'key1' => ['ABC','DEF'],
            'key2' => [34,45],
        ]
 */
  public static function parseCsvDatatoRequestData($data){
    $row = [];
    foreach($data as $i => $entry){
      foreach($entry as $k => $v){
        $row[$k][$i] = $v;
      }
    }
    return $row;
  }
//------------------------------------------------------------------------------
  public static function generateCopyOfFiles($data){
    $newIds        = Helper::getValue('generatedIds',$data,[]);
    $oldType       = Helper::getValue('oldType',$data);  
    $paymentIds    = M::getVendorPaymentIn('vendor_payment_id',$newIds,['vendor_payment_id','foreign_id']);
    $foreignIdMap  = Helper::keyFieldName($paymentIds,'foreign_id','vendor_payment_id');
    $files         = M::getFileUploadIn('foreign_id',array_column($paymentIds,'foreign_id'),'*',0,Model::buildWhere(['type'=>$oldType]));
      
    foreach($files as $i => $v){
      unset($files[$i]['fileUpload_id']);
      $fileId                     = Helper::getValue($v['foreign_id'],$foreignIdMap,0);
      $files[$i]['foreign_id']    = $fileId;
      $files[$i]['type']          = 'approval';
    }
    
    return $files;
  }
//------------------------------------------------------------------------------
  public static function getAverageAmount($invoice, $range = 0) {
    $averageList = $monthAverage = $query = [];
    $query['must'] = [
      'invoice.keyword' => $invoice,
      'print'           => 1
    ];
    if($range) {
      $prevYearMonth = date('Y-m-01',strtotime('-12 months'));
      $todayMonth    = date('Y-m-01');
      $query['must']['range']['invoice_date'] = [
        'gte' => $prevYearMonth,
        'lte' => $todayMonth
      ];
    }
    $rVendorPayments = Helper::getElasticResult(Elastic::searchQuery([
      'index' => T::$vendorPaymentView,
      'query' => $query
    ]));
    ## Divide the rows by key
    foreach($rVendorPayments as $v) {
      $data = $v['_source'];
      $key  = $data['vendid'] . $data['invoice'] . $data['type'] . $data['gl_acct'];
      $averageList[$key][] = $data;
    }
    ## Get the average per key
    foreach($averageList as $key => $val) {
      $rowTotal = 0;
      $rowCount = count($val);
      foreach($val as $i => $value) {
        $rowTotal += $value['amount'];
      }
      $monthAverage[$key] = Format::usMoney($rowTotal / $rowCount);
    }
    return $monthAverage;
  }
}
