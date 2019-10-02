<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, HelperMysql};
use App\Http\Models\Model; // Include the models class

/*
ALTER TABLE `ppm`.`vendor_insurance` 
ADD COLUMN `vendor_id` INT(10) NOT NULL DEFAULT '0' AFTER `vendid`,
ADD COLUMN `broker` VARCHAR(255)  DEFAULT '' AFTER `purchase_value`,
ADD COLUMN `carrier` VARCHAR(255)  DEFAULT '' AFTER `broker`,
ADD COLUMN `date_insured` DATE  DEFAULT '1000-01-01' AFTER `carrier`,
ADD COLUMN `occ` VARCHAR(45)  DEFAULT '' AFTER `date_insured`,
ADD COLUMN `building_value` DECIMAL(10,2)  DEFAULT '0.00' AFTER `occ`,
ADD COLUMN `deductible` DECIMAL(10,2)  DEFAULT 0.0 AFTER `building_value`;
ADD COLUMN `lor` VARCHAR(255) DEFAULT '' AFTER `deductible`,
ADD COLUMN `building_ordinance` VARCHAR(255)  DEFAULT '' AFTER `lor`,
ADD COLUMN `general_liability_limit` VARCHAR(255)  DEFAULT '' AFTER `building_ordinance`,
ADD COLUMN `general_liability_deductible` VARCHAR(255)  DEFAULT '' AFTER `general_liability_limit`,
ADD COLUMN `insurance_company` VARCHAR(255) DEFAULT '' AFTER `general_liability_deductible`,
ADD COLUMN `insurance_premium` DECIMAL(10,2)  DEFAULT '0.00' AFTER `insurance_company`,
ADD COLUMN `down_payment` DECIMAL(10,2)  DEFAULT '0.00' AFTER `insurance_premium`,
ADD COLUMN `installments` DECIMAL(10,2)  DEFAULT '0.00' AFTER `down_payment`;

ALTER TABLE `ppm`.`vendor_insurance` 
ADD INDEX `index-vendor_id` (`vendor_id` ASC);

UPDATE ppm.vendor_insurance AS vi, ppm.vendor AS v 
SET vi.vendor_id=v.vendor_id WHERE vi.vendid=v.vendid;
 */
class vendor_insurance_view{
  private static $_vendor_insurance = 'vendor_insurance_id, vendor_id, vendid, prop, bank, policy_num, invoice_date, effective_date, gl_acct, auto_renew, amount, ins_total, ins_building_val, ins_rent_val, ins_sf, remark, number_payment, monthly_vendid, monthly_payment, monthly_track_date, payer, start_pay_date, note, broker, carrier, date_insured, occ, building_value, deductible, lor, building_ordinance, general_liability_limit, general_liability_deductible, insurance_company, insurance_premium, down_payment, installments, active, cdate, udate, usid';
  private static $_fileUploadViewSelect = ['f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'];
  private static $_vendor = 'name';
  public static $maxChunk = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorInsurance, T::$fileUpload, T::$prop, T::$bank, T::$vendor];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props   = array_column($data,'prop');
    $rProp = Helper::keyFieldNameElastic(HelperMysql::getProp($props, ['prop', 'trust', 'prop_name', 'street', 'number_of_units', 'bank.bank', 'bank.cp_acct', 'bank.name', 'entity_name','po_value','start_date']), 'prop');
    
    foreach($data as $i=>$val){
      if(isset($rProp[$data[$i]['prop']])){
        $data[$i]['id'] = $val['vendor_insurance_id'];
        $fileUpload = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
        unset($data[$i][T::$fileUpload]);
      
        ## DEAL WITH BANK AND PROP DATA
        $data[$i]['trust']           = $rProp[$data[$i]['prop']]['trust'];
        $data[$i]['prop_name']       = $rProp[$data[$i]['prop']]['prop_name'];
        $data[$i]['po_value']        = $rProp[$data[$i]['prop']]['po_value'];
        $data[$i]['entity_name']     = $rProp[$data[$i]['prop']]['entity_name'];
        $data[$i]['street']          = $rProp[$data[$i]['prop']]['street'];
        $data[$i]['number_of_units'] = $rProp[$data[$i]['prop']]['number_of_units'];
        $data[$i]['start_date']      = $rProp[$data[$i]['prop']]['start_date'];
        foreach($rProp[$data[$i]['prop']]['bank'] as $j => $v){
          foreach($v as $field => $value){
            $value = $field == 'name' ? title_case($value) : $value;
            ## Put bank data list in the bank_id
            $data[$i]['bank_id'][$j][$field] = isset($value) ? $value : '';
          }
        }
     

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
      } else {
        unset($data[$i]);
      }

    }
    unset($rProp, $props);
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    $isEndSelect = 1;
    return 'SELECT '. Helper::joinQuery('vi',self::$_vendor_insurance) . Helper::joinQuery('v',self::$_vendor) . Helper::groupConcatQuery(self::$_fileUploadViewSelect, T::$fileUpload, $isEndSelect) . 
      ' FROM ' . T::$vendorInsurance . ' AS vi 
        INNER JOIN ' . T::$vendor . ' AS v ON v.vendor_id=vi.vendor_id ' . Model::getRawWhere($where) . '  
        LEFT JOIN ' . T::$fileUpload . ' AS f ON vi.vendor_insurance_id=f.foreign_id AND f.type="insurance" AND f.active=1 
        GROUP BY vi.vendor_insurance_id'; 
  }
}