<?php
namespace App\Http\Controllers\PropertyManagement\Prop\ExportCsv;
use App\Library\{Elastic, TableName AS T,GridData, Helper};
use App\Http\Controllers\Report\ReportController AS P;

class PropPdf {
  public static function getPdf($vData){
    $column                  = self::_getColumnParam();
    $selectFields            = array_column($column,'field');
    $vData['selectedField']  = Helper::formElasticSelectedField($selectFields);
    $indexMain               = T::$propView . '/' . T::$propView . '/_search?';
    $qData                   = GridData::getQuery($vData,T::$propView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $gridData                = self::_getGridData($r);
    return P::getPdf(P::getPdfData($gridData,$column),['title'=>'Property List Report','orientation'=>'P','chunk'=>75,'titleSpace'=>75]);
  }
//------------------------------------------------------------------------------
  private static function _getColumnParam(){
    $data = [];
    $data[]  = ['field'=>'prop','title'=>'Prop','hWidth'=>65];
    $data[]  = ['field'=>'group1','title'=>'Group','hWidth'=>50];
    $data[]  = ['field'=>'street','title'=>'Street','hWidth'=>155];
    $data[]  = ['field'=>'city','title'=>'City','hWidth'=>80];
    $data[]  = ['field'=>'zip','title'=>'Zip','hWidth'=>40];
    $data[]  = ['field'=>'county','title'=>'County','hWidth'=>70];
    $data[]  = ['field'=>'state','title'=>'State','hWidth'=>35];
    $data[]  = ['field'=>'prop_type','title'=>'Prop Type','hWidth'=>55];
    $data[]  = ['field'=>'number_of_units','title'=>'Unit#','hWidth'=>30];
    return $data;
  }
//------------------------------------------------------------------------------
  private static function _getGridData($r){
    $result   = Helper::getElasticResult($r);
    $mapping  = Helper::getMapping(['tableName'=>T::$prop]);
    
    $rows     = [];
    foreach($result as $i => $v){
      $source                = $v['_source'];
      $source['prop_type']   = Helper::getValue($source['prop_type'],$mapping['prop_type'],$source['prop_type']);
      $rows[]                = $source;
    }
    return P::getRow($rows,[]);
  }
}

