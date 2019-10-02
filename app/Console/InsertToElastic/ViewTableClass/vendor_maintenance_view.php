<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic, HelperMysql};
use App\Http\Models\Model; // Include the models class

/*
ALTER TABLE `ppm`.`vendor_maintenance` 
ADD COLUMN `vendor_id` INT(11) NOT NULL DEFAULT '0' AFTER `udate`;
ALTER TABLE `ppm`.`vendor_maintenance` 
ADD INDEX `index-vendor_id` (`vendor_id` ASC);
UPDATE ppm.vendor_maintenance AS vm, ppm.vendor AS v SET vm.vendor_id=v.vendor_id WHERE vm.vendid=v.vendid;
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `subController`, `active`) VALUES ('0', 'Account Payable', 'fa fa-fw fa-file-pdf-o', 'maintenance', 'Maintenance', 'fa fa-fw fa-cog', 'Account Payable', 'fa fa-fw fa-users', 'maintenance', '', 'To Access Maintenance', 'approveMaintenance,uploadMaintenance,maintenanceResetControlUnit', '1');
 */

class vendor_maintenance_view {
  private static $_vendor_maintenance = 'vendor_maintenance_id, vendor_id, vendid, prop, gl_acct, monthly_amount, control_unit, paid_period, cdate, udate, usid, active';
  private static $_vendor = 'name';
  private static $_vendor_payment        = [
    'vp.vendor_payment_id','vp.foreign_id','vp.invoice_date','vp.amount','vp.print','vp.void',
  ];
  private static $_fileUploadViewSelect = ['f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'];
  public static $maxChunk = 10000;
  
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorMaintenance,T::$vendor,T::$prop,T::$fileUpload,T::$vendorPayment];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $data  = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props = array_column($data,'prop');
    
    $rProp = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'        => T::$propView,
      '_source'      => ['prop','trust','group1','street','number_of_units','city'],
      'query'        => [
        'must'       => [
          'prop.keyword'     => $props,
        ],
        'must_not'   => [
          'prop_class.keyword' => 'X',
        ],
      ]
    ]),'prop');
    
    foreach($data as $i => $val){
      if(isset($rProp[$val['prop']])){
        $data[$i]['id'] = $val['vendor_maintenance_id'];
        $prop           = $rProp[$val['prop']];
        $fileUpload     = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
        $vendorPayment  = !empty($val[T::$vendorPayment]) ? explode('|',$val[T::$vendorPayment]) : [];
        unset($data[$i][T::$fileUpload],$data[$i][T::$vendorPayment]);
        
        $data[$i]['trust']            = Helper::getValue('trust',$prop);
        $data[$i]['number_of_units']  = Helper::getValue('number_of_units',$prop,0);
        $data[$i]['group1']           = Helper::getValue('group1',$prop);
        $data[$i]['street']           = Helper::getValue('street',$prop);
        $data[$i]['city']             = Helper::getValue('city',$prop);
        
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
        
        # DEAL WITH VENDOR PAYMENT
        if(!empty($vendorPayment)){
          foreach($vendorPayment as $j => $v){
            $p = explode('~',$v);
            foreach(self::$_vendor_payment as $k => $field){
              $field = preg_replace('/vp\./','',$field);
              $data[$i][T::$vendorPayment][$j][$field] = isset($p[$k])? $p[$k] : '';
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
  public static function getSelectQuery($where=[]){
    $whereStr  = !empty($where) ? preg_replace('/^ AND /',' WHERE ',Model::getRawWhere($where)) : '';
    return 'SELECT ' . Helper::joinQuery('vm',self::$_vendor_maintenance) . Helper::joinQuery('v',self::$_vendor) . 
           Helper::groupConcatQuery(self::$_vendor_payment,T::$vendorPayment) . Helper::groupConcatQuery(self::$_fileUploadViewSelect,T::$fileUpload,1) . 
           ' FROM ' . T::$vendorMaintenance . ' AS vm ' .
           ' INNER JOIN ' . T::$vendor . ' AS v ON vm.vendor_id=v.vendor_id ' . 
           ' LEFT JOIN ' . T::$vendorPayment . ' AS vp ON vm.vendor_maintenance_id=vp.foreign_id AND vp.type="maintenance" AND vp.void=0 ' . 
           ' LEFT JOIN ' . T::$fileUpload . ' AS f ON vm.vendor_maintenance_id=f.foreign_id AND f.type="maintenance" AND f.active=1 ' . $whereStr . 
           ' GROUP BY vm.vendor_maintenance_id';
  }
}