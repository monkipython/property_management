<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;
use Illuminate\Support\Facades\DB;


/*
UPDATE ppm.vendor_payment AS vp, ppm.vendor AS v 
SET vp.vendor_id=v.vendor_id WHERE v.vendid=vp.vendid;
 */

class vendor_payment_view{
//  private static $_fileUploadViewSelect = ['f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'];
  private static $_vendorPayment = 'vendor_payment_id,foreign_id,vendid,prop,unit,tenant,bank,amount,invoice_date,invoice,posted_date,
    approve,pay_type,pay_code,send_approval_date,approve_date,gl_acct,tx_code,batch,print,print_signature,noLimit,
    print_type,print_by,remark,remarks,lock_by_usid,batch_group,check_pdf,check_no,positivePayFile,belongTo,
    high_bill,type,seq,active,void,cdate,udate,usid,is_with_signature';
  private static $_vendor = 'vendor_id,name,line2,street,city,state,zip,phone,fax,e_mail';
  public static $maxChunk = 50000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$vendorPayment, T::$vendor, T::$fileUpload,T::$prop,T::$bank];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props        = array_column($data,'prop');
    $vendorIds    = array_column($data,'vendor_payment_id');
    $rFileUpload  = Helper::groupBy(DB::table(T::$fileUpload)->select(['foreign_id','fileUpload_id','name','file','uuid','path','type','ext'])->whereIn('foreign_id',$vendorIds)->where('type', 'approval')->get(),'foreign_id');
    $rProp        = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$propView,
      '_source'   => ['prop','number_of_units','bank.bank','bank.name','bank.cp_acct','ap_bank','group1','trust','prop_class'],
      'query'     => [
        'must'       => [
          'prop.keyword' => $props,
        ],
        'must_not'   => [
          'prop_class.keyword' => 'X',
        ]
      ]
    ]),'prop');
    foreach($data as $i=>$v){
      if(isset($rProp[$v['prop']])){
        $data[$i]['id'] = $v['vendor_payment_id'];
        $prop           = Helper::getValue($data[$i]['prop'],$rProp,[]);
        $bank           = Helper::getValue(T::$bank,$prop,[]);

        $data[$i]['street']                  = title_case($v['street']);
        $data[$i]['name']                    = title_case($v['name']);
        $data[$i]['line2']                   = title_case($v['line2']);
        $data[$i]['number_of_units']         = Helper::getValue('number_of_units',$prop,0);
        $data[$i]['prop_class']              = Helper::getValue('prop_class',$prop);
        $data[$i]['trust']                   = Helper::getValue('trust',$prop);

        # DEAL WITH FILEUPLOAD
        $files     = isset($rFileUpload[$v['vendor_payment_id']]) ? $rFileUpload[$v['vendor_payment_id']] : [];
        foreach($files as $idx => $r){
          $data[$i][T::$fileUpload][$idx]=$r;
        }

        ## DEAL WITH BANK ##
        if(!empty($bank)){
          foreach($bank as $j => $v){
            foreach($v as $k => $value){
              $data[$i]['bank_id'][$j][$k] = $value;
            }
          }
        }
      } else{
        unset($data[$i]);
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    $whereStr = empty($where) ? '' : preg_replace('/^ AND /', ' WHERE ', Model::getRawWhere($where));
    return 'SELECT '. Helper::joinQuery('vp',self::$_vendorPayment) . Helper::joinQuery('v',self::$_vendor,1) . ' 
      FROM ' . T::$vendorPayment . ' AS vp 
      INNER JOIN ' . T::$vendor . ' AS v ON v.vendor_id=vp.vendor_id AND vp.active=1 AND vp.bank <> "" AND vp.type <> "" ' . $whereStr;
  }
}
