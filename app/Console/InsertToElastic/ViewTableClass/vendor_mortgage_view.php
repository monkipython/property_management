<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;

/*
ALTER TABLE `ppm`.`vendor_mortgage` 
ADD COLUMN `vendor_id` INT(11) NOT NULL AFTER `udate`;
ALTER TABLE `ppm`.`vendor_mortgage` 
ADD INDEX `index-vendor_id` (`vendor_id` ASC);
UPDATE ppm.vendor_mortgage AS vm, ppm.vendor AS v SET vm.vendor_id=v.vendor_id WHERE vm.vendid=v.vendid;
 */

class vendor_mortgage_view {
  private static $_vendor_mortgage       = 'vendor_mortgage_id, vendor_id, vendid, prop, margin, bank, invoice, amount, init_principal, add_principal_paid, allocation, interest_rate, loan_date, maturity_date, dcr, index_title, index, loan_option, loan_term, last_payment, prepaid_penalty, prop_tax_impound, escrow, reserve, additional_principal, due_date, payment_type, loan_type, recourse, gl_acct_liability, gl_acct_ap, principal_bal, note, paid_off_loan, active, cdate, udate, usid';
  private static $_vendor                = 'name';
  private static $_fileUploadViewSelect  = [
    'f.fileUpload_id','f.name','f.type','f.ext','f.uuid','f.path','f.active','f.file',
  ];
  public  static $maxChunk               = 50000;
  
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorMortgage,T::$vendor,T::$fileUpload,T::$prop,T::$propBank,T::$bank];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'entity_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $data  = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props = array_column($data,'prop');
    
    $rProp = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$propView,
      '_source'   => ['prop','number_of_units','entity_name','street','city','bank.bank','bank.name','bank.cp_acct','cons1'],
      'query'     => [
        'must'    => [
          'prop.keyword'  => $props,
        ],
        'must_not'   => [
          'prop_class.keyword'  => 'X',
        ]
      ]
    ]),'prop');
    
    foreach($data as $i => $val){
      if(isset($rProp[$val['prop']])){
        $data[$i]['id']               = $val['vendor_mortgage_id'];
        $fileUpload                   = !empty($val[T::$fileUpload]) ? explode('|',$val[T::$fileUpload]) : [];
        unset($data[$i][T::$fileUpload]);
        
        $prop                         = $rProp[$val['prop']];
        $bank                         = Helper::getValue(T::$bank,$prop,[]);
        $data[$i]['street']           = Helper::getValue('street',$prop);
        $data[$i]['city']             = Helper::getValue('city',$prop);
        $data[$i]['cons1']            = Helper::getValue('cons1',$prop);
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
  public static function getSelectQuery($where=[]){
    $whereStr  = !empty($where) ? preg_replace('/^ AND /',' WHERE ',Model::getRawWhere($where)) : '';
    return 'SELECT ' . Helper::joinQuery('vm',self::$_vendor_mortgage) . Helper::joinQuery('v',self::$_vendor) . 
      Helper::groupConcatQuery(self::$_fileUploadViewSelect,T::$fileUpload,1) . 
      ' FROM ' . T::$vendorMortgage . ' AS vm ' .
      ' INNER JOIN ' . T::$vendor . ' AS v ON vm.vendor_id=v.vendor_id ' . 
      ' LEFT JOIN ' . T::$fileUpload . ' AS f ON vm.vendor_mortgage_id=f.foreign_id AND f.type="mortgage" AND f.active=1 ' . $whereStr .
      ' GROUP BY vm.vendor_mortgage_id';
  }
}