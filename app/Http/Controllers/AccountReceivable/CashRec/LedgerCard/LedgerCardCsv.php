<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\LedgerCard;
use App\Library\{Elastic, TableName AS T, Helper};

class LedgerCardCsv{
  public static function getCsv($vData){
    $must = Helper::selectData(['prop', 'unit', 'tenant'], $vData);
    $tenant  = Elastic::searchQuery([
      'index'   =>T::$tenantView,
      '_source' =>['tenant_id','group1', 'prop','unit','tenant'],
      'query'   =>['must'=>$must]
    ]);
    $rTntTrans = Elastic::searchQuery([
      'index'=>T::$tntTransView,
      'query'=>['must'=>$must]
    ]);
    
    $tenant = Helper::getElasticResult($tenant, 1)['_source'];
    return Helper::exportCsv([
      'filename'=>$tenant['prop'] . $tenant['unit'] . $tenant['tenant'] . '.csv',
      'data'=>Helper::getElasticResult($rTntTrans)
    ]);
  }
}