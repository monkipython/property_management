<?php
namespace App\Http\Controllers\PropertyManagement\Violation\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class ViolationCsv {
  public static function getCsv($vData){
    $returnData              = [];
    $indexMain               = T::$violationView . '/' . T::$violationView . '/_search?';
    $fields                  = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$violation),'Field','Field'));
    $vData['selectedField']  = Helper::formElasticSelectedField($fields);
    $qData                   = GridData::getQuery($vData,T::$violationView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $result                  = Helper::getElasticResult($r);
    
    foreach($result as $data){
      $source    = $data['_source'];
      $row       = [];
      
      foreach($fields as $f){
        if(isset($source[$f])){
          $row[$f] = $source[$f];
        }
      }
      $returnData[] = $row;
    }
    return Helper::exportCsv([
      'filename'   => 'violation_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'       => $returnData,
    ]);
  }
}

