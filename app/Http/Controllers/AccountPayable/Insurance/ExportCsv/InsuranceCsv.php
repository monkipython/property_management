<?php
namespace App\Http\Controllers\AccountPayable\Insurance\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class InsuranceCsv {
  
  public static function getCsv($vData){
    $selectFields            = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$vendorInsurance),'Field','Field'));
    $vData['selectedField']  = Helper::formElasticSelectedField($selectFields);
    $indexMain               = T::$vendorInsuranceView . '/' . T::$vendorInsuranceView . '/_search?';
    $qData                   = GridData::getQuery($vData,T::$vendorInsuranceView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $result                  = Helper::getElasticResult($r);
    $returnData = [];
    foreach($result as $data) {
      $source = $data['_source'];
      $tempArr = [];
      foreach($selectFields as $field) {
        if(isset($source[$field])) {
          $tempArr[$field] = $source[$field];
        }
      }
      $returnData[] = $tempArr;
    }
    return Helper::exportCsv([
      'filename' => 'insurance_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'     => $returnData,
    ]);
  }
}