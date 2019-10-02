<?php
namespace App\Http\Controllers\PropertyManagement\RentRaise\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData,Helper};

class RentRaiseCsv {
  public static function getCsv($vData,$onlyPending=1){
    $returnData              = [];
    $indexMain               = T::$rentRaiseView . '/' . T::$rentRaiseView . '/_search?';
    //$fields                  = array_merge(['group1'],array_values(Helper::keyFieldName(DB::select('SHOW COLUMNS FROM ' . T::$rentRaise),'Field','Field')));
    $fields                  = ['group1','prop','unit','tenant','street','city','tnt_name','isManager','rent','raise','raise_pct','notice','effective_date','last_raise_date','submission_date','usid','unit_type','move_in_date','bedrooms','bathrooms'];
    $vData['filter']         = !empty($vData['filter']) ? json_decode($vData['filter'],true) : [];
    unset($vData['filter']['checkbox']);
    $vData                  += $onlyPending ? ['defaultFilter'=>['isCheckboxChecked'=>1]] : [];
    $vData['filter']         = json_encode($vData['filter']);
    $vData['limit']          = !empty($vData['limit']) ? $vData['limit'] : -1;
    $vData['selectedField']  = Helper::formElasticSelectedField($fields);

    $qData                   = GridData::getQuery($vData,T::$rentRaiseView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $result                  = Helper::getElasticResult($r);
    
    foreach($result as $data){
      $source         = $data['_source'];
      $row            = [];
      foreach($fields as $f){
        if(isset($source[$f])){
          $row[$f] = $source[$f];
        }
      }
      $returnData[] = $row;
    }
    return Helper::exportCsv([
      'filename'  => 'rent_raise_export_' . date('Y-m-d-H-i-s') . '.csv',
      'data'      => $returnData,
    ]);
  }
}

