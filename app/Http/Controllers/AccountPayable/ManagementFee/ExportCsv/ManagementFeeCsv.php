<?php
namespace App\Http\Controllers\AccountPayable\ManagementFee\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class ManagementFeeCsv  {
  public static function getCsv($vData){
    $returnData              = [];
    $indexMain               = T::$vendorManagementFeeView . '/' . T::$vendorManagementFeeView . '/_search?';
    //$fields                  = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$vendorUtilPayment),'Field','Field'));
    $fields                  = ['trust','group1','entity_name','prop_type','prop_class','prop','street','city','zip','county','start_date'];
    $vData['selectedField']  = Helper::formElasticSelectedField($fields);
    $qData                   = GridData::getQuery($vData,T::$vendorManagementFeeView);
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
      'filename'   => 'managementFee_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'       => $returnData,
    ]);
  }
}

