<?php
namespace App\Http\Controllers\PropertyManagement\Group\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class GroupCsv {
  
  public static function getCsv($vData){
    $selectFields            = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$prop),'Field','Field'));
    $vData['selectedField']  = Helper::formElasticSelectedField($selectFields);
    $indexMain               = T::$groupView . '/' . T::$groupView . '/_search?';
    $qData                   = GridData::getQuery($vData,T::$groupView);
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
      'filename' => 'group_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'     => $returnData,
    ]);
  }
}  