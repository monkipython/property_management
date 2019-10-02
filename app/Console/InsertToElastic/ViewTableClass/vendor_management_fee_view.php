<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `subController`, `active`) VALUES ('0', 'Account Payable', 'fa fa-fw fa-file-pdf-o', 'managementFee', 'Management Fee', 'fa fa-fw fa-cog', 'Account Payable', 'fa fa-fw fa-users', 'managementFee', '', 'To Access Management Fee', 'managementFeeExport,approveManagementFee', '1');
 */


class vendor_management_fee_view {
  private static $_prop        = 'prop_id, prop, prop_name, prop_class, prop_type, street, city, county, zip, start_date, group1, usid';
  private static $_propBank    = 'trust';
  private static $_vendor_payment        = [
    'vp.vendor_payment_id','vp.foreign_id','vp.invoice_date','vp.amount','vp.print','vp.void',
  ];
  public  static $maxChunk               = 20000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorUtilPayment,T::$prop,T::$vendorPayment];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $data        = !empty($data) ? Helper::encodeUtf8($data) : [];
    
    foreach($data as $i => $val){
      $data[$i]['id']     = $val['prop_id'];
      $vendorPayment         = !empty($val[T::$vendorPayment]) ? explode('|',$val[T::$vendorPayment]) : [];
      unset($data[$i][T::$vendorPayment]);
      
      $data[$i]['street']    = title_case($val['street']);
      $data[$i]['city']      = title_case($val['city']);
      $data[$i]['county']    = title_case($val['county']);
      
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
    }
    
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where=[]){
    //$whereStr  = !empty($where) ? preg_replace('/^ AND /',' WHERE ',Model::getRawWhere($where)) : '';
    return 'SELECT '  . Helper::joinQuery('p',self::$_prop) . Helper::joinQuery('pb',self::$_propBank)  . 'p2.prop_name AS entity_name, '. Helper::groupConcatQuery(self::$_vendor_payment,T::$vendorPayment,1) . 
      ' FROM ' . T::$prop . ' AS p ' . 
      ' INNER JOIN ' . T::$propBank . ' AS pb ON pb.prop=p.prop AND p.prop NOT LIKE "#%" AND p.prop NOT LIKE "*%" ' .
      ' LEFT JOIN ' . T::$vendorPayment . ' AS vp ON p.prop_id=vp.foreign_id AND vp.type="managementfee" AND vp.void=0 '  .
      ' LEFT JOIN  ' . T::$prop .  ' AS p2 ON p2.prop=pb.trust WHERE p.prop BETWEEN "0001" AND "9999" ' . Model::getRawWhere($where).
      ' GROUP BY p.prop_id';
  }
}

