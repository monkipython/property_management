<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model; // Include the models class

class tnt_trans_view{
  public static $maxChunk   = 500000; 
  public static $maxResult  = 500000;
  public static $maxBucketChunk = 50000;
//  private static $_tntTrans = 'prop, unit, tenant, date1, cntl_no, journal, tx_code, jnl_class, building, per_code, sales_off, sales_agent, amount, dep_amt, appyto, remark, remarks, bk_transit, bk_acct, name_key, gl_acct, gl_contra, gl_acct_org, check_no, service_code, usid, sys_date, batch, job, doc_no, bank, inv_date, invoice, inv_remark, bill_seq, net_jnl, date2';
  
  //------------------------------------------------------------------------------
  public static function getTableOfView(){
    return  [T::$prop, T::$tntTrans,T::$tenant]; 
  }
  //------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data         = !empty($data) ? $data : [];
//    $rTenant      = Helper::keyFieldNameElastic(Elastic::searchQuery([
//      'index'       => T::$tenantView,
//      'size'        => 120000,
//      '_source'     => ['tenant_id','prop','unit','tenant','status'],
//      'query'       => [
//        'must'      => [
//          'prop.keyword'   => array_column($data,'prop'),
//        ]
//      ]
//    ]),['prop','unit','tenant']);
    foreach($data as $i=>$val){
      $val['name_key']= Helper::encodeUtf8($val['name_key']);
      $val['remark']= Helper::encodeUtf8($val['remark']);
      $data[$i]['id']           = $val['cntl_no'];
      
//      $tenant                   = Helper::getValue($val['prop'] . $val['unit'] . $val['tenant'],$rTenant,[]);
//      $data[$i]['tenant_id']    = Helper::getValue('tenant_id',$tenant,255);
//      $data[$i]['status']       = Helper::getValue('status',$tenant);
    }
//    unset($rTenant);
    return $data;
  }
  //------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    return 'SELECT tt.* FROM ' . T::$tntTrans . ' AS tt ' . preg_replace('/ AND /', ' WHERE ', Model::getRawWhere($where));
  }
}