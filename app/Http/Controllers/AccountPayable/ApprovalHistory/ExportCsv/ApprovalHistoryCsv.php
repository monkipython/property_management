<?php
namespace App\Http\Controllers\AccountPayable\ApprovalHistory\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class ApprovalHistoryCsv {
  public static function getCsv($vData){
    $returnData              = [];
    $indexMain               = T::$vendorPaymentView . '/' . T::$vendorPaymentView . '/_search?';
    $fields                  = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$vendorPayment),'Field','Field'));
    $vData['selectedField']  = Helper::formElasticSelectedField($fields);
    $vData['defaultFilter']  = ['print'=>1];
    $qData                   = GridData::getQuery($vData,T::$vendorPaymentView);
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
      'filename'   => 'approvalHistory_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'       => $returnData,
    ]);
  }
}

