<?php
namespace App\Http\Controllers\PropertyManagement\RentRaise\ExportCsv;
use App\Library\{Elastic, TableName AS T,Format, GridData, Helper};
use App\Http\Controllers\Report\ReportController AS P;

class RentRaisePdf {
  public static function getPdf($vData,$onlyPending=1){
    $indexMain = T::$rentRaiseView  . '/' . T::$rentRaiseView . '/_search?';
    $column    = self::_getColumnParam();
    $vData     += $onlyPending ? ['defaultFilter'=>['isCheckboxChecked'=>1]] : [];
    $vData['defaultSort']    = ['group1.keyword:asc','prop.keyword:asc','unit.keyword:asc','tenant:desc'];
    $qData                   = GridData::getQuery($vData,T::$rentRaiseView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);

    $gridData  = self::_getGridData($r);
    return P::getPdf(P::getPdfData($gridData,$column),['title'=>'Rent Raise Report','chunk'=>37]);
  }
//------------------------------------------------------------------------------
  private static function _getColumnParam(){
    $data   =   [];
    
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'width'=>15,'hWidth'=>30];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>15,'hWidth'=>25];
    $data[] = ['field'=>'unit','title'=>'Unit','sortable'=>true,'width'=>15,'hWidth'=>25];
    //$data[] = ['field'=>'tenant','title'=>'Tnt','sortable'=>true,'width'=>15,'hWidth'=>25];
    $data[] = ['field'=>'street','title'=>'Addr.','sortable'=>true,'width'=>350,'hWidth'=>95];
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true, 'width'=>125,'hWidth'=>50];
    $data[] = ['field'=>'tnt_name','title'=>'Name','sortable'=>true,'width'=>350,'hWidth'=>120];
    $data[] = ['field'=>'isManager','title'=>'Mgr','sortable'=>true,'width'=>15,'hWidth'=>25];
    $data[] = ['field'=>'rent','title'=>'Org Rnt','sortable'=>true,'width'=>25,'hWidth'=>50];
    $data[] = ['field'=>'raise','title'=>'New Rent','sortable'=>true,'width'=>25,'hWidth'=>50];
    $data[] = ['field'=>'raise_pct','title'=>'Rs%.','sortable'=>true,'width'=>25,'hWidth'=>35];
    $data[] = ['field'=>'notice','title'=>'Notice','sortable'=>true,'width'=>20,'hWidth'=>30];
    $data[] = ['field'=>'effective_date','title'=>'Eff Date','sortable'=>true,'width'=>20,'hWidth'=>45];
    $data[] = ['field'=>'last_raise_date','title'=>'Last Raise Date','sortable'=>true,'width'=>40,'hWidth'=>65];
    $data[] = ['field'=>'submitted_date','title'=>'Sub Date','sortable'=>true,'width'=>20,'hWidth'=>45];
    $data[] = ['field'=>'unit_type','title'=>'Unit Type','sortable'=>true,'width'=>45,'hWidth'=>55];
    $data[] = ['field'=>'move_in_date','title'=>'Move In','sortable'=>true,'width'=>25,'hWidth'=>45];
    $data[] = ['field'=>'bedrooms','title'=>'Bds','sortable'=>true,'width'=>15,'hWidth'=>20];
    $data[] = ['field'=>'bathrooms','title'=>'Bth','sortable'=>true,'width'=>15,'hWidth'=>20];
    
    return $data;
  }
//------------------------------------------------------------------------------
  private static function _getGridData($r){
    $result    = Helper::getElasticResult($r,0,1);
    $data      = Helper::getValue('data',$result,[]);
    $mapping   = Helper::getMapping(['tableName'=>T::$rentRaise]);
    
    $rows      = [];
    
    foreach($data as $i => $v){
      $source                      = $v['_source'];
      $source['raise']             = Format::usMoney($source['raise']);         
      $source['submitted_date']    = !empty($source['submitted_date']) && $source['submitted_date'] != '1000-01-01' && $source['submitted_date'] != '1969-12-31' ? $source['submitted_date'] : '';
      $source['last_raise_date']   = !empty($source['last_raise_date']) && $source['last_raise_date'] != '1000-01-01' && $source['last_raise_date'] != '1969-12-31' ? $source['last_raise_date'] : '';
      $source['effective_date']    = !empty($source['effective_date']) && $source['effective_date'] != '1000-01-01' && $source['effective_date'] != '1969-12-31' ? $source['effective_date'] : '';
      $source['unit_type']         = !empty($mapping['unit_type'][$source['unit_type']]) ? $mapping['unit_type'][$source['unit_type']] : $source['unit_type'];
      $source['isManager']         = !empty($mapping['isManager'][$source['isManager']]) ? $mapping['isManager'][$source['isManager']] : $source['isManager'];
      $source['rent']              = Format::usMoney($source['rent']);
      $source['raise_pct']         = !empty($source['raise_pct']) ? Format::percent($source['raise_pct']) : 0;
      $userPieces                  = explode('@',$source['usid']);
      $source['usid']              = Helper::getValue(0,$userPieces);
      $source['notice']            = Helper::getValue($source['notice'],$mapping['notice']);
      $rows[]                      = $source;
    }
    
    return $rows;
  }
}

