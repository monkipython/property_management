<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, HelperMysql};
use App\Http\Models\Model; // Include the models class

class vendor_prop_tax_view{
  private static $_vendor_prop_tax = 'vendor_prop_tax_id, vendor_id, vendid, prop, assessed_val, amount1, amount2, amount3, remark1, remark2, apn, gl_acct, payer, bill_num, active, cdate, udate, usid';
  private static $_fileUploadViewSelect = ['f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'];
  private static $_vendor = 'name';
  public static $maxChunk = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorPropTax, T::$fileUpload, T::$prop, T::$vendor];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props   = array_column($data,'prop');
    $rProp = Helper::keyFieldNameElastic(HelperMysql::getProp($props, ['prop', 'trust', 'street', 'city', 'state', 'zip', 'county', 'number_of_units', 'start_date', 'po_value', 'entity_name']), 'prop');
    
    foreach($data as $i=>$val){
      $data[$i]['id'] = $val['vendor_prop_tax_id'];
      $fileUpload = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
      unset($data[$i][T::$fileUpload]);

      ## DEAL WITH BANK AND PROP DATA
      if(isset($rProp[$data[$i]['prop']])){
        $data[$i]['trust']           = $rProp[$data[$i]['prop']]['trust'];
        $data[$i]['street']          = $rProp[$data[$i]['prop']]['street'];
        $data[$i]['city']            = $rProp[$data[$i]['prop']]['city'];
        $data[$i]['state']           = $rProp[$data[$i]['prop']]['state'];
        $data[$i]['zip']             = $rProp[$data[$i]['prop']]['zip'];
        $data[$i]['county']          = $rProp[$data[$i]['prop']]['county'];
        $data[$i]['number_of_units'] = $rProp[$data[$i]['prop']]['number_of_units'];
        $data[$i]['start_date']      = $rProp[$data[$i]['prop']]['start_date'];
        $data[$i]['po_value']        = $rProp[$data[$i]['prop']]['po_value'];
        $data[$i]['entity_name']     = $rProp[$data[$i]['prop']]['entity_name'];
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
    }
    unset($rProp, $props);
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){

    return 'SELECT '. Helper::joinQuery('pt',self::$_vendor_prop_tax) . Helper::joinQuery('v',self::$_vendor) . 
        Helper::groupConcatQuery(self::$_fileUploadViewSelect, T::$fileUpload, 1) .
      ' FROM ' . T::$vendorPropTax . ' AS pt 
        INNER JOIN ' . T::$vendor . ' AS v ON v.vendor_id=pt.vendor_id ' . Model::getRawWhere($where) . ' 
        LEFT JOIN ' . T::$fileUpload . ' AS f ON pt.vendor_prop_tax_id=f.foreign_id AND f.type="prop_tax" AND f.active=1 
        GROUP BY pt.vendor_prop_tax_id'; 
  }
}