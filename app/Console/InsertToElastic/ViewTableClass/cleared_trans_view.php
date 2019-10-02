<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;
use Illuminate\Support\Facades\DB;

/*
CREATE TABLE `cleared_trans` (
  `cleared_trans_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_id` int(10) NOT NULL DEFAULT '0',
  `bank_id` int(10) NOT NULL DEFAULT '0',
  `match_id` int(10) unsigned NOT NULL DEFAULT '0',
  `bank` char(2) NOT NULL DEFAULT '',
  `trust` varchar(6) NOT NULL DEFAULT '',
  `prop` varchar(6) NOT NULL DEFAULT '',
  `check_no` varchar(6) NOT NULL DEFAULT '',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_clear` varchar(45) NOT NULL DEFAULT '',
  `cleared_date` date NOT NULL DEFAULT '1000-01-01',
  `batch` int(11) NOT NULL DEFAULT '0',
  `date1` date NOT NULL DEFAULT '1000-01-01',
  `journal` varchar(2) NOT NULL DEFAULT '',
  `source` varchar(80) NOT NULL DEFAULT '',
  `created_by` varchar(255) NOT NULL DEFAULT '',
  `updated_by` varchar(255) NOT NULL DEFAULT '',
  `cdate` datetime NOT NULL,
  `udate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `usid` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`cleared_trans_id`),
  KEY `index-vendor_id` (`vendor_id`),
  KEY `index-bank_id` (`bank_id`),
  KEY `index-bank` (`bank`),
  KEY `index-match_id` (`match_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1572841 DEFAULT CHARSET=latin1

INSERT INTO ppm.cleared_trans (trust, prop, check_no, amount, is_clear, cleared_date, bank, batch, date1, usid, match_id, journal,source,created_by,updated_by,vendor_id,cdate,udate) 
SELECT c.prop AS trust, c.orgprop AS prop, c.ref1 AS check_no,  c.amt AS amount, 
c.cxl AS is_clear, c.cxldate AS cleared_date,c.bank,c.batch, c.date1, c.usid,
c2.match_id, c2.journal, c2.source, c2.created_by, c2.updated_by, IF(v.vendor_id IS NOT NULL,v.vendor_id,0), c2.cdate, c2.udate
FROM ppm.cleared_check AS c
INNER JOIN ppm.cleared_check_extend AS c2 ON c.cleared_check_id=c2.cleared_check_id
LEFT JOIN ppm.vendor AS v ON v.name=c.vendorname AND v.name <> '' 
GROUP BY c.cleared_check_id;

 */
class cleared_trans_view {
  private static $_cleared_trans = 'cleared_trans_id, vendor_id, bank_id, match_id, bank, trust, prop, check_no, amount, is_clear, cleared_date, batch, date1, journal, source, created_by, updated_by, cdate, udate, active, usid';
  public static $maxChunk = 50000;
  
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$clearedTrans,T::$prop,T::$vendor,T::$bank,T::$propBank];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $data       = !empty($data) ? Helper::encodeUtf8($data) : [];
    
    $vendorIds  = array_column($data,'vendor_id');
    $props      = array_column($data,'prop');
    $rVendor    = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$vendorView,
      '_source'   => ['vendor_id','vendid','name'],
      'query'     => [
        'must'    => [
          'vendor_id' => $vendorIds,
        ]
      ]
    ]),'vendor_id');
    
    $rProp      = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      '_source'  => ['prop','street','city','state','zip','entity_name'],
      'query'    => [
        'must'      => [
          'prop.keyword' => $props,
        ],
        'must_not'  => [
          'prop_class.keyword' => 'X'
        ]
      ]
    ]),'prop');
    
    
    foreach($data as $i => $v){
      if(isset($rProp[$v['prop']])){
        $data[$i]['id']    = $v['cleared_trans_id'];
        $prop              = $rProp[$v['prop']];
        $vendor            = Helper::getValue($v['vendor_id'],$rVendor,[]);
        
        $data[$i]['street']       = $prop['street'];
        $data[$i]['city']         = $prop['city'];
        $data[$i]['state']        = $prop['state'];
        $data[$i]['zip']          = $prop['zip'];
        $data[$i]['entity_name']  = $prop['entity_name'];
        
        $data[$i]['name']         = Helper::getValue('name',$vendor);
        $data[$i]['vendid']       = Helper::getValue('vendid',$vendor);
      } else { 
        unset($data[$i]);
      }
    }
    
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    return 'SELECT ' . Helper::joinQuery('c',self::$_cleared_trans,1) . ' FROM ' . T::$clearedTrans . ' AS c ' . preg_replace('/^ AND /',' WHERE ',Model::getRawWhere($where));
  }
}
