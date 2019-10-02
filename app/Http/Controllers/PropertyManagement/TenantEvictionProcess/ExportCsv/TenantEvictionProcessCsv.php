<?php
namespace App\Http\Controllers\PropertyManagement\TenantEvictionProcess\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper, Format};

class TenantEvictionProcessCsv {
  
  public static function getCsv($vData){
    $mapping                = Helper::getMapping(['tableName'=>T::$tntEvictionEvent]);
    $selectFields           = ['group1','prop','unit','tenant','tnt_name', 'tnt_eviction_event','street', 'city', 'state', 'zip', 'base_rent', 'dep_held1', 'move_in_date', 'move_out_date', 'spec_code', 'attorney'];
    $vData['selectedField'] = Helper::formElasticSelectedField($selectFields);
    $indexMain              = T::$tntEvictionProcessView . '/' . T::$tntEvictionProcessView . '/_search?';
    $qData                  = GridData::getQuery($vData,T::$tntEvictionProcessView);
    $r                      = Elastic::gridSearch($indexMain . $qData['query']);
    $result                 = Helper::getElasticResult($r);
    $returnData = [];
    foreach($result as $data) {
      $source = $data['_source'];
      $tempArr = [];
      $tempArr['remark'] = $tempArr['status'] = '';
      foreach($selectFields as $field) {
        if(isset($source[$field])) {
          if($field == 'tnt_eviction_event') {
            foreach($source[$field] as $v) {
              $tempArr['status'] = isset($tempArr['status']) ? ($tempArr['status'] > $v['status']) ? $tempArr['status'] : $v['status'] : $v['status'];
              $newLine = next($source[$field]) ? "\r\n" : '';
              $tempArr['remark'] .= Format::usDate($v['date']) . ' '. $v['subject'] . $newLine;
            }
            $tempArr['status'] = $mapping['status'][$tempArr['status']];
          }else {
            $tempArr[$field] = $source[$field];
          }
          $remark = $tempArr['remark'];
          unset($tempArr['remark']);
          $tempArr['remark'] = $remark;
        }
      }
      $returnData[] = $tempArr;
    }
    return Helper::exportCsv([
      'filename' => 'tnt_eviction_process_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'     => $returnData,
    ]);
  }
}