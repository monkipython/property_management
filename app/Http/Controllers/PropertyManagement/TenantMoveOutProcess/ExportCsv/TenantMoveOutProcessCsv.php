<?php
namespace App\Http\Controllers\PropertyManagement\TenantMoveOutProcess\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class TenantMoveOutProcessCsv {
  
  public static function getCsv($vData){
    $selectFields           = ['group1','prop','unit','tenant','tnt_name','status','street', 'city', 'state', 'zip', 'base_rent', 'dep_held1', 'move_in_date', 'move_out_date', 'spec_code'];
    $vData['selectedField'] = Helper::formElasticSelectedField($selectFields);
    $indexMain              = T::$tntMoveOutProcessView . '/' . T::$tntMoveOutProcessView . '/_search?';
    $qData                  = GridData::getQuery($vData,T::$tntMoveOutProcessView);
    $r                      = Elastic::gridSearch($indexMain . $qData['query']);
    $result                 = Helper::getElasticResult($r);
    $returnData = [];
    foreach($result as $data) {
      $source = $data['_source'];
      $tempArr = [];
      foreach($selectFields as $field) {
        if(isset($source[$field])) {
          if($field == 'status') {
            $tempArr['dep_issued'] = $source[$field];
          }else {
            $tempArr[$field] = $source[$field];
          }
        }
      }
      $returnData[] = $tempArr;
    }
    return Helper::exportCsv([
      'filename' => 'tnt_move_out_process_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'     => $returnData,
    ]);
  }
}