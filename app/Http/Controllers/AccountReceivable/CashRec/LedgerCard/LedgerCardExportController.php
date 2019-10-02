<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\LedgerCard;
use Illuminate\Http\Request;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper, TenantTrans,Format,TenantStatement};
use App\Http\Controllers\Controller;
use PDF;
use Storage;
use App\Http\Controllers\AccountReceivable\CashRec\LedgerCard\LedgerCardController as LedgerCard;
use App\Http\Controllers\AccountReceivable\CashRec\LedgerCard\{LedgerCardPdf, LedgerCardCsv};

class LedgerCardExportController extends Controller{
  private $_mapping   = [];
  private $_headerCss = 'border-bottom:1px solid #000;border-top:1px solid #000;font-weight:bold;';
  public function __construct(){
    $this->_mapping = Helper::getMapping(['tableName'=>T::$tntTrans]);
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => ['prop', 'unit', 'tenant', 'dateRange'], 
      'setting'         => $this->_getSetting(__FUNCTION__, $req), 
      'includeCdate'    => 0,
      'validateDatabase'=>[
        'mustExist'=>[
          'tenant|prop,unit,tenant'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $op    = $valid['op'];
    $rTenant  = Helper::getElasticResult(Elastic::searchQuery([
      'index'   =>T::$tenantView,
      '_source' =>['tenant_id'],
      'query'   =>['must'=>['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant']]]
    ]), 1); 
    switch($op){
      case 'tenantStatement': return TenantStatement::getPdf([$rTenant['_source']['tenant_id']]);
      case 'ledgerCard': return LedgerCardPdf::getPdf($vData);
      case 'ledgerCardDetail': return LedgerCardPdf::getPdf($vData ,'ledgerCardDetail');
      case 'csv': return LedgerCardCsv::getCsv($vData);
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'index' =>[T::$tntTrans],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    $data = [
      'index'=>[
        'rule'=>[
          'dateRange'=>'required|string|between:21,23',
        ]
      ]
    ];
    return $data[$fn];
  }
}