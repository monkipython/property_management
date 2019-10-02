<?php
namespace App\Library;
class File{
/**
 * @desc
 * @params {__CLASS__} Always accept __CLASS__ as a param, nothing else
 * @return {void}
 */
  public static function getLocation($cls){
    $cls = preg_replace('/Controller/', '', last(explode('\\', $cls)));
    $showUpload = env('APP_URL') . '/storage/';
    list($root, $tmp) = explode('app', (__DIR__)); 
    $data = [
      'Approval' => [
        'approval'   => $root . 'storage/app/public/approval/',
        'showUpload' =>$showUpload,
      ],
      'CashRec' => [ // We don't really store anything so it's safe to delete them after use
        'rpsCheckOnly'       => $root . 'storage/app/tmp/',
        'rpsCreditCheck'     => $root . 'storage/app/tmp/',
        'rpsTenantStatement' => $root . 'storage/app/tmp/',
        'paymentUpload'      => $root . 'storage/app/tmp/',
        'invoiceUpload'      => $root . 'storage/app/tmp/',
        'depositUpload'      => $root . 'storage/app/tmp/'
      ],
      'CreditCheck'=>[
        'signature'      =>'',
        'application'    =>'',
        'storeUpload'    =>$root . 'storage/app/public/CreditCheck/',
        'showUpload'     =>$showUpload,
        'AgreementReport'=>$root . 'storage/app/public/agreement/',
      ],
      // For the upload location, make sure that the key and the last part value is the same
      'CreditCheckUpload'=>[
        'agreement'   =>$root . 'storage/app/public/agreement/',
        'application' =>$root . 'storage/app/public/application/',
        'showUpload'  =>$showUpload,
      ],
      'DebitExpenseTransaction' => [
        'debitExpenseTransactionUpload' => $root . 'storage/app/tmp/',
      ],
      'Group' => [
        'group'      => $root . 'storage/app/public/group/',
        'showUpload' => $showUpload
      ],
      'Approval' =>[
        'approval'         => $root . 'storage/app/public/approval/',
        'cashierCheck'     => 'private/cashierCheck/',
        'cashierCheckDrop' => 'private/cash/FileProcessing/incoming/',
        'showUpload'       => $showUpload,
      ],
      'ApprovalHistory' => [
        'approval_history' => $root . 'storage/app/public/approval_history/',
        'showUpload'      => $showUpload,
      ],
      'BusinessLicense' => [
        'business_license'  => $root . 'storage/app/public/business_license/',
        'showUpload' => $showUpload,
      ],
      'Insurance'=>[
        'insurance'  => $root . 'storage/app/public/insurance/',
        'showUpload' => $showUpload,  
      ],
      'Mortgage'=>[
        'mortgage'   => $root . 'storage/app/public/mortgage/',
        'showUpload' => $showUpload,
      ],
      'PendingCheck'=>[
        'pending_check'=> $root . 'storage/app/public/pending_check/',
        'showUpload'  =>$showUpload,  
      ],
      'GardenHoa'   =>[
        'gardenHoa'   => $root . 'storage/app/public/gardenHoa/',
        'showUpload'  =>$showUpload,
      ],
      'Maintenance'  => [
        'maintenance' => $root . 'storage/app/public/maintenance/',
        'showUpload'  => $showUpload,
      ],
      'PrintCheck'=>[
        'check'    =>$root . 'storage/app/public/check/',
        'checkCopy'=>$root . 'storage/app/public/checkCopy/',
        'tmpCheck'     =>$root . 'storage/app/public/check/tmpCheck/',
      ],
      'PropTax'=>[
        'prop_tax'    => $root . 'storage/app/public/prop_tax/',
        'showUpload' => $showUpload
      ],
      'report'=>[
        'FeeReport'  =>$root . 'storage/app/public/report/creditCheck/',
        'DailyReport'=>$root . 'storage/app/public/report/creditCheck/',
        'MoveinReport'=>$root . 'storage/app/public/report/creditCheck/',
        'GlChartReport' => $root . 'storage/app/public/report/glChart/',
        'ServiceReport' => $root . 'storage/app/public/report/service/',
      ],
      'Unit'=>[
        'application' => ''
      ],
      'UtilPayment'=>[
        'util_payment' => $root . 'storage/app/public/util_payment/',
        'showUpload'  => $showUpload
      ],
      'elasticSearch'=>[
        'outputResult'=>''
      ],
      'TenantMoveOut' => [
        'tenantMoveOutReport' => $root . 'storage/app/public/tenantMoveOutReport/',
        'tenantMoveOutFile'=>$root . 'storage/app/public/tenantMoveOutFile/',
        'showUpload'    => $showUpload
      ],
      'TenantEviction' => [
        'tenantEvictionEvent' => $root . 'storage/app/public/tenantEvictionEvent/',
        'evictionReport'      => $root . 'storage/app/public/evictionReport',
        'showUpload'          => $showUpload
      ],
      'Vendors'=>[
        'vendors'    =>$root . 'storage/app/public/vendors/',
        'showUpload' => $showUpload
      ],
      'Violation' => [
        'violation'  => $root . 'storage/app/public/violation/',
        'showUpload' => $showUpload
      ]
    ];
    return isset($data[$cls]) ? $data[$cls] : [];
  }
  /**
    * @desc this will file out data to the location we use it 
    * @param  
    *  $iData {array} is required 3 element in it
    *    $iData['data'] (require) can be either array or string that has the data to be out to file 
    *    $iData['file'] (require) must be a full path and file name 
    *    $iData['prefix'] (optional) a string that will go beginning of each line
    *    $iData['postfix'] (optional) a string that will go at the end of each line
    * @howToUse
    *   File::write(['data'=>['1'], 'file'=>'/tmp/test.txt', 'prefix'=>'-', 'postfix'=>"\n"]);
    */
  public static function write($iData){
    $file     = $iData['file'];
    $mode     = (!empty($iData['mode'])) ? $iData['mode'] : 'w';
    $prefix   = (!empty($iData['prefix'])) ? $iData['prefix'] : '';
    $postfix  = (!empty($iData['postfix'])) ? $iData['postfix'] : "\r\n";
    $data     = (is_array($iData['data'])) ? self::_listOutLine($iData['data'], $prefix, $postfix) : $prefix . $iData['data'] . $postfix;
    // Start to file out
    $fp   = fopen($file, $mode);
    $oData = fwrite($fp, $data); 
    fclose($fp); 
    return $oData;
  }
//------------------------------------------------------------------------------
  public static function isExist($fileName){
    return file_exists ($fileName);
  }
//------------------------------------------------------------------------------
  public static function getAllFileInDirectory($path){
    return glob($path . '/*');
  }
//------------------------------------------------------------------------------
//------------------------------------------------------------------------------
  public static function mkdirNotExist($path){
    if(!self::isExist($path)){
      mkdir($path);
    }
    return $path;
  }
//------------------------------------------------------------------------------
  public static function listFileByDir($dir){
    $data = [];
    $dh = opendir($dir);
    while (false !== ($list = readdir($dh))) {
      if (preg_match('/^[^.]+/', $list)) { // Exclude the . in the beginning
        $data[] = $list;
      }
    }
    sort($data, SORT_STRING);
    return $data;
  }
//------------------------------------------------------------------------------
  /**
    * @desc this will read data from the location provided
    * @param  
    *  $iData {array} is required 3 element in it
    *    $iData['file'] (require) must be a full path and file name 
    * @howToUse
    *   File::write(['file'=>'/tmp/test.txt']);
    */
  public static function read($iData){
    $oData  = [];
    $handle = fopen($iData['file'], 'r');
    if($handle){
      while (($line = fgets($handle)) !== false) {
        // process the line read.
        $oData[] = $line;
      }
      fclose($handle);
    } 
    else {
      echo 'Cannot open the file ' . $iData['file'];
    } 
    return $oData;
  }
}
