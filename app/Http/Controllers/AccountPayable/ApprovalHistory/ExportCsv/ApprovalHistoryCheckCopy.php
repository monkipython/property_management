<?php
namespace App\Http\Controllers\AccountPayable\ApprovalHistory\ExportCsv;
use App\Library\{Elastic, GridData,File, TableName AS T,Format, Html, Helper};
use App\Http\Controllers\Report\ReportController AS P;
class ApprovalHistoryCheckCopy  {
  public static function getPdf($vData){
    $path                    = Helper::getValue('path',$vData);  
    return ['html'=>Html::tag('iframe','',['src'=>$path,'width'=>'100%','height'=>'600']),'path'=>$path,'type'=>'pdf'];
  }
//------------------------------------------------------------------------------
  public static function getUploadViewListModal($vData){
    $vData['filter']         = json_encode(['vendor_payment_id'=>Helper::getValue('vendor_payment_id',$vData)]);
    $vData['selectedField']  = Helper::formElasticSelectedField(['vendor_payment_id','check_pdf']);
    $vData['defaultFilter']  = ['print'=>1];
    $vData['limit']          = -1;
    $indexMain               = T::$vendorPaymentView . '/' . T::$vendorPaymentView . '/_search?';
    $qData                   = GridData::getQuery($vData,T::$vendorPaymentView);
    $r                       = Elastic::gridSearch($indexMain . $qData['query']);
    $result                  = Helper::getElasticResultSource($r,1);
    
    $ul                      = [];
    $isMobileIos             = preg_match('/(ipod|iphone|ipad|ios)/',strtolower($_SERVER['HTTP_USER_AGENT']));
    
    $rPdf                    = !empty($result['check_pdf']) ? [$result['check_pdf']] : [];
    $location   = File::getLocation('PrintCheck')['checkCopy'];
    $linkPrefix = \Storage::disk('public')->url(preg_replace('/(.*)storage\/app\/public\/(.*)/','$2',$location));
    
    foreach($rPdf as $i=>$v){
      $active = $i == 0 ? ' active' : '';
      $href               = $linkPrefix . $v;
      $pdfParam           = $isMobileIos ? '' : 'eachPdf';
      $hrefParam          = $isMobileIos ? ['href'=>$href,'target'=>'_blank','title'=>'Click to View File'] : [];
      $ul[]               = [
          'value'   => Html::a(Html::span('',['class'=>'fa fa-fw fa-file-pdf-o']) . ' ' . $v,$hrefParam),
          'param'   => ['class'=>$pdfParam . ' pointer ' . $active,'data-path'=>$href],
      ];
    }
    $ul = Html::buildUl($ul, ['class'=>'nav nav-pills nav-stacked', 'id'=>'checkPdfList']);
    return Html::div(
      Html::div($ul, ['class'=>'col-md-3']) . Html::div('', ['class'=>'col-md-9', 'id'=>'uploadView']), 
      ['class'=>'row']
    );
  }
}