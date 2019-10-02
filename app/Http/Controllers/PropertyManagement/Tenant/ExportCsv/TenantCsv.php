<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class TenantCsv {
  public static function getCsv($vData){
    $returnData              = [];
    $indexMain               = T::$tenantView . '/' . T::$tenantView . '/_search?';
    $fields                  = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$tenant),'Field','Field'));
    $vData['selectedField']  = Helper::formElasticSelectedField($fields);
    $qData                   = GridData::getQuery($vData,T::$tenantView);
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
      'filename'   => 'tenant_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'       => $returnData,
    ]);
  }
}