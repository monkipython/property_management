<?php
namespace App\Http\Controllers\AccountPayable\ApprovalHistory\ExportCsv;
use App\Library\{Elastic, GridData,TableName AS T,Format, Helper};
use App\Http\Controllers\Report\ReportController AS P;

class ApprovalHistoryPdf {
  public static function getPdf($vData){
    $column                  = self::_getColumnParam();
    $selectFields            = array_column($column,'field');
    $vData['selectedField']  = Helper::formElasticSelectedField($selectFields);
    $vData['defaultFilter']  = ['print'=>1];
    $indexMain               = T::$vendorPaymentView . '/' . T::$vendorPaymentView . '/_search?';
    $qData                   = GridData::getQuery($vData,T::$vendorPaymentView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $gridData                = self::_getGridData($r);
    return P::getPdf(P::getPdfData($gridData,$column),['title'=>'Approval History Report','chunk'=>49]);
  }
//------------------------------------------------------------------------------
  private static function _getColumnParam(){
    $data   =   [];
    $data[] = ['field'=>'bank','title'=>'Bank','hWidth'=>25];
    $data[] = ['field'=>'invoice','title'=>'Invoice','hWidth'=>60];
    $data[] = ['field'=>'invoice_date','title'=>'Invoice Date','hWidth'=>55];
    $data[] = ['field'=>'remark','title'=>'Remark','hWidth'=>150];
    $data[] = ['field'=>'vendid','title'=>'Vendor','hWidth'=>40];
    $data[] = ['field'=>'amount','title'=>'Amount','hWidth'=>40];
    $data[] = ['field'=>'high_bill','title'=>'H. Bill','hWidth'=>35];
    $data[] = ['field'=>'avg_amount','title'=>'Avg. Amount','hWidth'=>40];
    $data[] = ['field'=>'name','title'=>'Vendor Name','hWidth'=>150];
    $data[] = ['field'=>'trust','title'=>'Trust','hWidth'=>50];
    $data[] = ['field'=>'prop','title'=>'Prop','hWidth'=>35];
    $data[] = ['field'=>'prop_class','title'=>'Prop Class','hWidth'=>50];
    $data[] = ['field'=>'unit','title'=>'Unit','hWidth'=>35];
    $data[] = ['field'=>'number_of_units','title'=>'Unit#','hWidth'=>30];
    $data[] = ['field'=>'gl_acct','title'=>'Gl Acct','hWidth'=>60];
    return $data;
  }
//------------------------------------------------------------------------------
  private static function _getGridData($r){
    $result    = Helper::getElasticResult($r,0,1);
    $data      = Helper::getValue('data',$result,[]);
    $mapping   = Helper::getMapping(['tableName'=>T::$vendorPayment]);
    $rows      = []; 
    foreach($data as $i => $v){
      $source                      = $v['_source'];
      $source['amount']            = Format::usMoney($source['amount']);
      $source['invoice_date']      = Format::usDate($source['invoice_date']);
      $source['remark']            = title_case($source['remark']);
      $source['high_bill']         = Helper::getValue($source['high_bill'],$mapping['high_bill']);
      $source['prop_class']        = Helper::getValue($source['prop_class'],$mapping['prop_class']);
      $rows[]                      = $source;
    }   
    return P::getRow($rows,[]);
  }
}

