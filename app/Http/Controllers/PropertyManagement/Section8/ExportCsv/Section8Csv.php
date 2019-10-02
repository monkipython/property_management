<?php
namespace App\Http\Controllers\PropertyManagement\Section8\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData,Helper};
use Illuminate\Support\Facades\DB;

class Section8Csv {
  private static $_additionalCols = ['group1','tnt_name','trust','prop_name','street','city'];
  private static $_titles = [
    'prop'                    => 'Prop',
    'unit'                    => 'Unit',
    'tenant'                  => 'Tnt',
    'tnt_name'                => 'Name',
    'group1'                  => 'Group',
    'trust'                   => 'Trust',
    'prop_name'               => 'Property Name',
    'city'                    => 'City',
    'first_inspection_date'   => '1st Inspec.',
    'status'                  => 'Status',
    'second_inspection_date'  => '2nd Inspec.',
    'status2'                 => 'Status 2',
    'third_inspection_date'   => '3rd Inspec.',
    'status3'                 => 'Status 3',
    'remarks'                 => 'Remarks',
  ];
  
  public static function getCsv($vData){
    $returnData              = [];
    $indexMain               = T::$section8View .  '/' . T::$section8View . '/_search?';
    $fields                  = array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$section8),'Field','Field'));
    $vData['selectedField']  = Helper::formElasticSelectedField(array_merge($fields,self::$_additionalCols));
    $qData                   = GridData::getQuery($vData,T::$section8View);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $result                  = Helper::getElasticResult($r);
    $mapping                 = Helper::getMapping(['tableName'=>T::$section8]);
    
    foreach($result as $data){
      $source     = $data['_source'];
      $row        = [];

      foreach(self::$_titles as $k => $v){
        $row[$v]  = !empty($source[$k]) ? $source[$k] : '';
      }
      
      $row['Status']        = (strtotime($source['first_inspection_date']) > strtotime(date('Y-m-d'))) && !empty($row['Status']) ?  $mapping['status'][$row['Status']] : '';
      $row['Status 2']      = (strtotime($source['second_inspection_date']) > strtotime(date('Y-m-d'))) && !empty($row['Status 2']) ? $mapping['status2'][$row['Status 2']] : '';
      $row['Status 3']      = (strtotime($source['third_inspection_date']) > strtotime(date('Y-m-d'))) && !empty($row['Status 3']) ? $mapping['status3'][$row['Status 3']] : '';

      $row['1st Inspec.']   = $row['1st Inspec.'] !== '1000-01-01' ? $row['1st Inspec.']  : '';
      $row['2nd Inspec.']   = $row['2nd Inspec.'] !== '1000-01-01' ? $row['2nd Inspec.']  : '';
      $row['3rd Inspec.']   = $row['3rd Inspec.'] !== '1000-01-01' ? $row['3rd Inspec.']  : '';
      
      $returnData[]         = $row;
    }
    return Helper::exportCsv([
      'filename'  => 'section_8_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'      => $returnData,
    ]);
  }
}


