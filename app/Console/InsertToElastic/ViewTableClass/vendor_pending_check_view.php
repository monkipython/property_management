<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;

class vendor_pending_check_view{
  private static $_vendor_pending_check = 'vendor_pending_check_id, vendor_id, vendid, prop, unit, tenant, amount, remark, invoice_date, gl_acct, service_code, invoice, type, due_date, recurring, is_need_approved, bank, is_submitted, active, cdate, udate, usid';
  private static $_vendor = 'name';
  private static $_fileUploadViewSelect = [
    'f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'
  ];
  public static $maxChunk = 20000;
  /* SCRIPT NEED TO RUN BEFORE GO LIVE
ALTER TABLE `ppm`.`vendor_pending_check` 
ADD COLUMN `vendor_id` INT(10) NOT NULL DEFAULT '0' AFTER `vendid`;
ALTER TABLE `ppm`.`vendor_pending_check` 
ADD COLUMN `is_submitted` VARCHAR(255) NOT NULL DEFAULT 'no' AFTER `vendor_id`;
ALTER TABLE `ppm`.`vendor_pending_check` 
ADD COLUMN `bank` TINYINT(4) NOT NULL AFTER `is_submitted`;
ALTER TABLE `ppm`.`vendor_pending_check` 
ADD INDEX `index-vendor_id` (`vendor_id` ASC);
UPDATE ppm.vendor_pending_check AS vp, ppm.vendor AS v 
SET vp.vendor_id=v.vendor_id WHERE v.vendid=vp.vendid;
UPDATE ppm.vendor_pending_check AS vp, ppm.prop AS p
SET vp.bank=CAST(p.ap_bank AS UNSIGNED INT) WHERE p.prop=vp.prop AND p.ap_bank <> '';
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `active`) VALUES ('0', 'Account Payable', 'fa fa-fw fa-credit-card', 'pendingCheck', 'Pending Check', 'fa fa-fw fa-cog', 'Account Payable', '', 'approvePendingCheck', '', 'To Submit Pending Check', '1');
   * 
   * 
   */
  
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorPendingCheck, T::$fileUpload, T::$prop,T::$bank];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    
    $data    = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props   = array_column($data,'prop');
    
    $rProp   = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      '_source'  => ['prop','trust','street','city','group1','bank.bank','ap_bank','bank.name','bank.cp_acct'],
      'query'    => [
        'must'   => [
          'prop.keyword'    => $props,
        ],
        'must_not'  => [
          'prop_class.keyword' => 'X',
        ]
      ]
    ]),'prop');
    foreach($data as $i=>$val){
      if(isset($rProp[$val['prop']])){
        $data[$i]['id'] = $val['vendor_pending_check_id'];
        $prop           = Helper::getValue($val['prop'],$rProp,[]); 
        $bank           = Helper::getValue(T::$bank,$prop,[]);
        $fileUpload     = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
        unset($data[$i][T::$fileUpload]);
  
        $data[$i]['street'] = title_case(Helper::getValue('street',$prop));
        $data[$i]['city']   = title_case(Helper::getValue('city',$prop));
        $data[$i]['trust']  = Helper::getValue('trust',$prop);
        $data[$i]['ap_bank']= Helper::getValue('ap_bank',$prop);
        $data[$i]['group1'] = Helper::getValue('group1',$prop);
        # DEAL WITH FILEUPLOAD
        if(!empty($fileUpload)){
          foreach($fileUpload as $j=>$v){
            $p = explode('~', $v);
            foreach(self::$_fileUploadViewSelect as $k=>$field){
              $field = preg_replace('/f\./', '', $field);
              $data[$i][T::$fileUpload][$j][$field] = isset($p[$k]) ? $p[$k] : '';
            }
          }
        }
        # DEAL WITH BANK
        if(!empty($bank)){
          foreach($bank as $j => $v){
            foreach($v as $k => $value){
              $data[$i]['bank_id'][$j][$k] = $value;    
            }
          }
        }
      } else {
        unset($data[$i]);   
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    $whereStr  = !empty($where) ? preg_replace('/^ AND /',' WHERE ',Model::getRawWhere($where)) : '';
    return 'SELECT '. Helper::joinQuery('vp',self::$_vendor_pending_check) . Helper::joinQuery('v',self::$_vendor)   .
      Helper::groupConcatQuery(self::$_fileUploadViewSelect, T::$fileUpload,1) . 
      ' FROM ' . T::$vendorPendingCheck . ' AS vp ' . 
      ' INNER JOIN ' . T::$vendor . ' AS v ON v.vendor_id=vp.vendor_id' .
      ' LEFT JOIN ' . T::$fileUpload . ' AS f ON vp.vendor_pending_check_id=f.foreign_id AND f.type="pending_check" AND f.active=1 ' . $whereStr  .   
      ' GROUP BY vp.vendor_pending_check_id'; 
  }
}