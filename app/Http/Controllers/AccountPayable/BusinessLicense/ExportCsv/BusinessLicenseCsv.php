<?php
namespace App\Http\Controllers\AccountPayable\BusinessLicense\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};

class BusinessLicenseCsv {
  public static function getCsv($vData){
    $returnData              = [];
    $indexMain               = T::$vendorBusinessLicenseView . '/' . T::$vendorBusinessLicenseView . '/_search?';
    $fields                  = ['trust','entity_name','name','vendor','prop','number_of_units','gl_acct','remark','due_date'];
    $vData['selectedField']  = Helper::formElasticSelectedField($fields);
    $qData                   = GridData::getQuery($vData,T::$vendorBusinessLicenseView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $result                  = Helper::getElasticResult($r);
    
    foreach($result as $data){
      $source     = $data['_source'];
      $row        = [];
      
      foreach($fields as $f){
        if(isset($source[$f])){
          $row[$f] = $source[$f];
        }
      }
      $returnData[] = $row;
    }
    return Helper::exportCsv([
      'filename'   => 'businessLicense_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'       => $returnData,
    ]);
  }
}

