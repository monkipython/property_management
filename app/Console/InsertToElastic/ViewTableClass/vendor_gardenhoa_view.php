<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;

/*
ALTER TABLE `ppm`.`vendor_gardenhoa` 
ADD COLUMN `stop_pay` VARCHAR(255) NOT NULL DEFAULT 'no' AFTER `account_id`;
ALTER TABLE `ppm`.`vendor_gardenhoa` 
ADD COLUMN `vendor_id` INT(10) NOT NULL DEFAULT '0' AFTER `usid`;
ALTER TABLE `ppm`.`vendor_gardenhoa` 
ADD COLUMN `account_id` INT(11) NOT NULL DEFAULT '0' AFTER `vendor_id`;
ALTER TABLE `ppm`.`vendor_gardenhoa` 
ADD INDEX `index-account_id` (`account_id` ASC);
ALTER TABLE `ppm`.`vendor_gardenhoa`
ADD INDEX `index-vendor_id` (`vendor_id` ASC);
UPDATE ppm.vendor_gardenhoa AS vg, ppm.vendor AS v SET vg.vendor_id=v.vendor_id WHERE vg.vendid=v.vendid;
UPDATE ppm.vendor_gardenhoa AS vg, ppm.account AS a SET vg.account_id=a.account_id WHERE TRIM(UPPER(vg.supervisor))=TRIM(UPPER(a.firstname));
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `subController`, `active`) VALUES ('0', 'Account Payable', 'fa fa-fw fa-credit-card', 'gardenHoa', 'Garden HOA', 'fa fa-fw fa-cog', 'Account Payable', 'fa fa-fw fa-users', 'gardenHoa', '', 'To Access Garden Hoa', 'gardenHoaedit,gardenHoaupdate,uploadGardenHoa,gardenHoaExport,submitGardenHoa', '1');
 */


class vendor_gardenhoa_view {
  private static $_vendor_garden_hoa     = 'vendor_gardenHoa_id, vendid, vendor_id, account_id, prop, gl_acct, amount, invoice, remark, note, not_pay, stop_pay, active, cdate, udate, usid';
  private static $_vendor                = 'name';
  private static $_fileUploadViewSelect  = [
    'f.fileUpload_id','f.name','f.type','f.ext','f.uuid','f.path','f.active','f.file',
  ];
  private static $_vendor_payment        = [
    'vp.vendor_payment_id','vp.foreign_id','vp.invoice_date','vp.amount','vp.print','vp.void',
  ];
  public  static $maxChunk               = 20000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorGardenHoa,T::$vendor,T::$fileUpload,T::$prop,T::$vendorPayment,T::$account];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $data        = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props       = array_column($data,'prop');
    $accountIds  = array_column($data,'account_id');
    
    $rProp       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$propView,
      '_source'   => ['prop','trust','group1','city','street'],
      'query'     => [
        'must'      => [
           'prop.keyword'   => $props
        ],
        'must_not'  => [
          'prop_class.keyword' => 'X'
        ]
      ]
    ]),'prop');
    
    $rAcct       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$accountView,
      '_source'  => ['account_id','firstname','lastname'],
      'query'    => [
        'must'   => [
          'account_id'  => $accountIds
        ]
      ]
    ]),'account_id');
    
    foreach($data as $i => $val){
      if(isset($rProp[$val['prop']])){
        $data[$i]['id']         = $val['vendor_gardenHoa_id'];
        $prop                   = Helper::getValue($val['prop'],$rProp,[]);
        $account                = Helper::getValue($val['account_id'],$rAcct,[]);
        $fileUpload             = !empty($val[T::$fileUpload]) ? explode('|',$val[T::$fileUpload]) : [];
        $vendorPayment          = !empty($val[T::$vendorPayment]) ? explode('|',$val[T::$vendorPayment]) : [];
        unset($data[$i][T::$fileUpload],$data[$i][T::$vendorPayment]);
      
        $firstName              = Helper::getValue('firstname',$account);
        $lastName               = Helper::getValue('lastname',$account);
        $space                  = !empty($lastName) ? ' ' : '';
        $data[$i]['street']     = title_case(Helper::getValue('street',$prop));
        $data[$i]['city']       = title_case(Helper::getValue('city',$prop));
        $data[$i]['trust']      = Helper::getValue('trust',$prop);
        $data[$i]['group1']     = Helper::getValue('group1',$prop);
        $data[$i]['supervisor'] = title_case($firstName . $space . $lastName);
        $data[$i]['note']       = title_case(Helper::getValue('note',$data[$i]));
        
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
    return 'SELECT ' . Helper::joinQuery('vg',self::$_vendor_garden_hoa) . Helper::joinQuery('v',self::$_vendor) . 
      Helper::groupConcatQuery(self::$_vendor_payment,T::$vendorPayment) . Helper::groupConcatQuery(self::$_fileUploadViewSelect,T::$fileUpload,1) . 
      ' FROM ' . T::$vendorGardenHoa . ' AS vg ' .
      ' INNER JOIN ' . T::$vendor . ' AS v ON vg.vendor_id=v.vendor_id ' . 
      ' LEFT JOIN ' . T::$vendorPayment . ' AS vp ON vg.vendor_gardenHoa_id=vp.foreign_id AND vp.type="gardenHoa" AND vp.void=0 ' . 
      ' LEFT JOIN ' . T::$fileUpload . ' AS f ON vg.vendor_gardenHoa_id=f.foreign_id AND f.type="gardenHoa" AND f.active=1 ' . $whereStr .
      ' GROUP BY vg.vendor_gardenHoa_id';
  }
}

