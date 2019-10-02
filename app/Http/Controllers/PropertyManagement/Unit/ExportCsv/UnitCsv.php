<?php
namespace App\Http\Controllers\PropertyManagement\Unit\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;
class UnitCsv {
  public static function getCsv($vData){
    $returnData              = [];
    $indexMain               = T::$unitView . '/' . T::$unitView . '/_search?';
    $fields                  = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$unit),'Field','Field'));
    $vData['selectedField']  = Helper::formElasticSelectedField($fields);
    
    $qData                   = GridData::getQuery($vData,T::$unitView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $result                  = Helper::getElasticResult($r);
    
    foreach($result as $data){
      $source         = $data['_source'];
      $source['prop'] = $source['prop'][0]['prop'];
      $row            = [];
      foreach($fields as $f){
        if(isset($source[$f])){
          $row[$f] = $source[$f];
        }
      }
      unset($row['rock']);
      $returnData[] = $row;
    }
    return Helper::exportCsv([
      'filename'  => 'unit_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'      => $returnData,
    ]);
  }
}
