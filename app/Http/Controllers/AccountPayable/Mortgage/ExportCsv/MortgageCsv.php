<?php
namespace App\Http\Controllers\AccountPayable\Mortgage\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class MortgageCsv {
  public static function getCsv($vData){
    $returnData              = [];
    $indexMain               = T::$vendorMortgageView. '/' . T::$vendorMortgageView . '/_search?';
    $fields                  = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$vendorMortgage),'Field','Field'));
    $vData['selectedField']  = Helper::formElasticSelectedField($fields);
    $qData                   = GridData::getQuery($vData,T::$vendorMortgageView);
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
      unset($row['rock']);
      $returnData[] = $row;
    }
    return Helper::exportCsv([
      'filename'   => 'mortgage_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'       => $returnData,
    ]);
  }
}

