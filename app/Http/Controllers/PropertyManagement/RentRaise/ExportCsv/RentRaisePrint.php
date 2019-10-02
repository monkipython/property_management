<?php
namespace App\Http\Controllers\PropertyManagement\RentRaise\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper, RentRaiseNotice};
use Illuminate\Support\Facades\DB;

class RentRaisePrint {
  public static function getPrint($vData,$onlyPending=1){
    $indexMain              = T::$rentRaiseView . '/' . T::$rentRaiseView . '/_search?';
    $vData                 += $onlyPending ? ['defaultFilter'=>['isCheckboxChecked'=>1]] : [];
    $qData                  = GridData::getQuery($vData,T::$rentRaiseView);
   
    $r                      = array_column(Helper::getElasticResult(Elastic::gridSearch($indexMain . $qData['query'])),'_source');

    return RentRaiseNotice::getPdf($r,true,0);
  }
}
