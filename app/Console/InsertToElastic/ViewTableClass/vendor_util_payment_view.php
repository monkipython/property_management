<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;

class vendor_util_payment_view{
  private static $_vendor_util_payment = 'vendor_util_payment_id, vendor_id, vendid, prop, unit, gl_acct, invoice, type, meter_number, due_date, recurring, active, pay_by, remark, cdate, udate, usid';
  private static $_fileUploadViewSelect = ['f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'];
  private static $_vendor_payment = ['vp.vendor_payment_id', 'vp.bank', 'vp.amount', 'vp.invoice_date', 'vp.invoice', 'vp.approve', 'vp.gl_acct', 'vp.batch', 'vp.print', 'vp.remark', 'vp.batch_group', 'vp.check_no', 'vp.high_bill', 'vp.type', 'vp.active', 'vp.void', 'vp.usid'];
  private static $_vendor = 'name';
  public static $maxChunk = 50000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorUtilPayment,T::$vendor,T::$fileUpload,T::$prop,T::$propBank,T::$vendorPayment];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data   = !empty($data) ? Helper::encodeUtf8($data) : [];
    
    $props  = array_column($data,'prop');
    $rProp  = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      '_source'  => ['prop','street','city','county','number_of_units','group1','trust','entity_name'],
      'query'    => [
        'must'   => [
          'prop.keyword' => $props
        ]
      ]
    ]),'prop');
    foreach($data as $i=>$val){
      if(isset($rProp[$val['prop']])){
        $data[$i]['id'] = $val['vendor_util_payment_id'];
        $fileUpload     = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
        $vendorPayment  = !empty($val[T::$vendorPayment]) ? explode('|',$val[T::$vendorPayment]) : [];
        $prop           = Helper::getValue($val['prop'],$rProp,[]);
        unset($data[$i][T::$fileUpload], $data[$i][T::$vendorPayment]);

        # DEAL WITH PROPERTY VIEW
        $data[$i]['street']           = Helper::getValue('street',$prop);
        $data[$i]['trust']            = Helper::getValue('trust',$prop);
        $data[$i]['entity_name']      = Helper::getValue('entity_name',$prop);
        $data[$i]['city']             = Helper::getValue('city',$prop);
        $data[$i]['number_of_units']  = Helper::getValue('number_of_units',$prop,0);
        $data[$i]['county']           = Helper::getValue('county',$prop);
        $data[$i]['group1']           = Helper::getValue('group1',$prop);
      
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
  public static function getSelectQuery($where = []){
    $whereStr  = !empty($where) ? preg_replace('/^ AND /',' WHERE ',Model::getRawWhere($where)) : ''; 
    return  'SELECT ' . Helper::joinQuery('up',self::$_vendor_util_payment) . Helper::joinQuery('v',self::$_vendor) . 
            Helper::groupConcatQuery(self::$_fileUploadViewSelect,T::$fileUpload) . Helper::groupConcatQuery(self::$_vendor_payment,T::$vendorPayment,1) .
            ' FROM '       . T::$vendorUtilPayment . ' AS up ' . 
            ' INNER JOIN ' . T::$vendor . ' AS v ON v.vendor_id=up.vendor_id ' .  
            ' LEFT JOIN '  . T::$fileUpload . ' AS f ON up.vendor_util_payment_id=f.foreign_id AND f.type="util_payment" AND f.active=1 ' . 
            ' LEFT JOIN '  . T::$vendorPayment . ' AS vp ON vp.foreign_id=up.vendor_util_payment_id AND vp.void=0 ' . $whereStr . 
            ' GROUP BY up.vendor_util_payment_id';
  }
}