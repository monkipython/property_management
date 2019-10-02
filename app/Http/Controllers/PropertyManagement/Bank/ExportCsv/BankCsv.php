<?php
namespace App\Http\Controllers\PropertyManagement\Bank\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use Illuminate\Support\Facades\DB;

class BankCsv {
  
  public static function getCsv($vData){
    $selectFields            = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$bank),'Field','Field'));
    $selectFields            = array_merge($selectFields, ['gl_acct']);
    $vData['selectedField']  = self::_formSelectedField($selectFields);
    $indexMain               = T::$bankView . '/' . T::$bankView . '/_search?';
    $qData                   = GridData::getQuery($vData,T::$bankView);
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
      'filename' => 'bank_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'     => $returnData,
    ]);
  }
//------------------------------------------------------------------------------
  private static function _formSelectedField($fields){
    $data          = [];
    foreach($fields as $v){
      $data[] = $v . '~' . $v;
    }
    
    $selectedField = implode('-',$data);
    return $selectedField;
  }
} 