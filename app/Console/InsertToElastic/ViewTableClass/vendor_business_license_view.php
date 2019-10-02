<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;

/*
ALTER TABLE `ppm`.`vendor_util_payment` 
ADD COLUMN `vendor_id` INT(10) NOT NULL DEFAULT '0' AFTER `usid`;;
ALTER TABLE `ppm`.`vendor_util_payment` 
ADD INDEX `index-vendor_id` (`vendor_id` ASC);
UPDATE ppm.vendor_util_payment AS vu, ppm.vendor AS v
SET vu.vendor_id=v.vendor_id WHERE vu.vendid=v.vendid;
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `subController`, `active`) VALUES ('0', 'Account Payable', 'fa fa-fw fa-credit-card', 'businessLicense', 'Business License', 'fa fa-fw fa-cog', 'Account Payable', '', 'businessLicense', '', 'To Access Business License', 'businessLicenseedit,businessLicenseupdate,businessLicensecreate,businessLicensestore,uploadBusinessLicense,accountPayableBankInfo', '1');
 */

class vendor_business_license_view {
  private static $_vendor_business_license     = 'vendor_util_payment_id, vendid, vendor_id, prop, unit, gl_acct, invoice, remark, type, meter_number, due_date, recurring, pay_by, active, cdate, udate, usid';
  private static $_vendor                      = 'name';
  private static $_fileUploadViewSelect        = [
    'f.fileUpload_id','f.name','f.type','f.ext','f.uuid','f.path','f.active','f.file',
  ];
  private static $_vendor_payment        = [
    'vp.vendor_payment_id','vp.foreign_id','vp.invoice_date','vp.amount','vp.print','vp.invoice','vp.remark','vp.void',
  ];
  public  static $maxChunk               = 50000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorUtilPayment,T::$vendor,T::$fileUpload,T::$prop,T::$propBank,T::$vendorPayment];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $data        = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props       = array_column($data,'prop');
    
    $rProp       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$propView,
      '_source'   => ['prop','trust','group1','entity_name','number_of_units'],
      'query'     => [
        'must'      => [
           'prop.keyword'   => $props
        ],
        'must_not'  => [
          'prop_class.keyword' => 'X'
        ]
      ]
    ]),'prop');
    
    foreach($data as $i => $val){
      if(isset($rProp[$val['prop']])){
        $data[$i]['id']         = $val['vendor_util_payment_id'];
        $prop                   = Helper::getValue($val['prop'],$rProp,[]);
        $fileUpload             = !empty($val[T::$fileUpload]) ? explode('|',$val[T::$fileUpload]) : [];
        $vendorPayment          = !empty($val[T::$vendorPayment]) ? explode('|',$val[T::$vendorPayment]) : [];
        unset($data[$i][T::$fileUpload],$data[$i][T::$vendorPayment]);

        $data[$i]['trust']            = Helper::getValue('trust',$prop);
        $data[$i]['group1']           = Helper::getValue('group1',$prop);
        $data[$i]['number_of_units']  = Helper::getValue('number_of_units',$prop);
        $data[$i]['entity_name']      = Helper::getValue('entity_name',$prop);
        ## DEAL WITH FILEUPLOAD ##
        if(!empty($fileUpload)){
          foreach($fileUpload as $j => $v){
            $p = explode('~',$v);
            foreach(self::$_fileUploadViewSelect as $k => $field){
              $field = preg_replace('/f\./','',$field);
              $data[$i][T::$fileUpload][$j][$field]    = isset($p[$k]) ? $p[$k] : '';
            }
          }
        }

        ## DEAL WITH VENDOR PAYMENT ##
        if(!empty($vendorPayment)){
          foreach($vendorPayment as $j => $v){
            $p = explode('~',$v);
            foreach(self::$_vendor_payment as $k => $field){
              $field = preg_replace('/vp\./','',$field);
              $data[$i][T::$vendorPayment][$j][$field] = isset($p[$k]) ? $p[$k] : '';
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
    return 'SELECT ' . Helper::joinQuery('vu',self::$_vendor_business_license) . Helper::joinQuery('v',self::$_vendor) . 
      Helper::groupConcatQuery(self::$_vendor_payment,T::$vendorPayment) . Helper::groupConcatQuery(self::$_fileUploadViewSelect,T::$fileUpload,1) . 
      ' FROM ' . T::$vendorUtilPayment . ' AS vu ' .
      ' INNER JOIN ' . T::$vendor . ' AS v ON vu.vendor_id=v.vendor_id ' . 
      ' LEFT JOIN ' . T::$vendorPayment . ' AS vp ON vu.vendor_util_payment_id=vp.foreign_id AND vp.type="business_license" AND vp.void=0 ' . 
      ' LEFT JOIN ' . T::$fileUpload . ' AS f ON vu.vendor_util_payment_id=f.foreign_id AND f.type="business_license" AND f.active=1 ' . $whereStr .
      ' GROUP BY vu.vendor_util_payment_id';
  }
}
