<?php
namespace App\Library;
use App\Library\{Helper};
use Storage;

class PositivePay{
  private static $_newLine = "\r\n";
  public static function getFormatedData($bankName, $data){
    $text = '';
    if(preg_match('/^EAST WEST BANK/', $bankName)){
      $text = self::_eastWestBank($data);
    } else if(preg_match('/^FARMERS/', $bankName)){
      $text = self::_farmersMerchantsBank($data);
    } else if(preg_match('/^MECHANICS BANK/', $bankName)){
      $text = self::_mechanicsBank($data);
    } else if(preg_match('/^TORREY PINES/', $bankName)){
      $text = self::_torryPines($data);
    } else if(preg_match('/^NANO BANC/', $bankName)){
      $text = self::_nanoBanc($data);
    }
    return $text; 
  }
//------------------------------------------------------------------------------
  public static function putDataSftp($bankName, $csv){
    if(preg_match('/^EAST WEST BANK/', $bankName)){
      Storage::disk('sftpEastWest')->put(self::_eastWestBankFile(), $csv);
    } else if(preg_match('/^FARMERS/', $bankName)){
      Storage::disk('sftpFarmer')->put(self::_farmersMerchantsBankFile(), $csv);
    } else if(preg_match('/^MECHANICS BANK/', $bankName)){
      Storage::disk('sftpMechanicsBank')->put(self::_mechanicsBankFile(), $csv);
    } else if(preg_match('/^TORREY PINES/', $bankName)){
      Storage::disk('sftpTorryPines')->put(self::_torryPinesFile(), $csv);
//    } else if(preg_match('/^NANO BANC/', $bankName)){
//      Storage::disk('sftpNanoBanc')->put(self::_nanoBancFile(), $csv);
    }
  }
//------------------------------------------------------------------------------
  public static function getBankFileName($bankName){
    if(preg_match('/^EAST WEST BANK/', $bankName)){
      return self::_eastWestBankFile();
    } else if(preg_match('/^FARMERS/', $bankName)){
      return self::_farmersMerchantsBankFile();
    } else if(preg_match('/^MECHANICS BANK/', $bankName)){
      return self::_mechanicsBankFile();
    } else if(preg_match('/^TORREY PINES/', $bankName)){
      return self::_torryPinesFile();
    } else if(preg_match('/^NANO BANC/', $bankName)){
      return self::_nanoBancFile();
    }
  }
################################################################################
##########################   EACH LINE FORMAT FUNCTION   #######################  
################################################################################  
  private static function _eastWestBank($data){
    $oData = [];
    foreach($data as $v){
      $issue = (isset($v['isIssue']) && $v['isIssue']) ? 'I' : 'V'; // 00 for issue check and 08 for void check
      $oData[] = substr(implode('', [ 
         'constant_val'  =>'C',
         'contact_val'   =>'928',
         'filler'        =>'00',
         'account'       => self::cleanAcct($v['bankDetail']['cp_acct']),
   //      sprintf("%010s", self::clean_acct(rtrim($iData['cp_acct']))),
         'filler_space1' =>' ',
         'issue_type'    =>$issue, // I for issue and V for void
         'issue_action'  =>'A', // A for add and D for delete
         'filler_space2' =>' ',
         'check_number'  =>sprintf("%010s", $v['check_no']),
         'amount'        =>sprintf("%010s", $v['balance'] * 100),
         'issue_date'    =>date('mdy', strtotime($v['posted_date'])),
         'payee_name'    =>sprintf("%- 96s", title_case($v['vendor_name'])),
      ]), 0, 142);
    }
    return implode(self::$_newLine, $oData);
  }
//------------------------------------------------------------------------------
  private static function _farmersMerchantsBank($data){
    $oData = [];
    foreach($data as $v){
      $issue = (isset($v['isIssue']) && $v['isIssue']) ? '00' : '08'; // 00 for issue check and 08 for void check
      $oData[] = substr(implode('', [
        'account'      =>sprintf("%08s", self::cleanAcct($v['bankDetail']['cp_acct'])),
        'serial_number'=>sprintf("%010s", $v['check_no']), // serial_number = check_number
        'tran_code'    =>$issue, // 00 for issue check and 08 for void check
        'amount'       =>sprintf("%010s", $v['balance'] * 100),
        'void_code'    =>$issue, // 00 for issue check and 08 for void check
        'issue_date'   =>date('mdy', strtotime($v['posted_date'])),
        'filler'       =>'0', 
        'payee_name'   =>sprintf("%- 35s", title_case($v['vendor_name']))
      ]), 0, 74);
    }
    return implode(self::$_newLine, $oData);
  }
//------------------------------------------------------------------------------
  private static function _nanoBanc($data){
    $oData = [];
    foreach($data as $v){
      $issue = (isset($v['isIssue']) && $v['isIssue']) ? '00' : '08'; // 00 for issue check and 08 for void check
      $oData[] = substr(implode('', [
        'account'      =>sprintf("%010s", self::cleanAcct($v['bankDetail']['cp_acct'])),
        'serial_number'=>sprintf("%010s", $v['check_no']), // serial_number = check_number
        'tran_code'    =>$issue, // 00 for issue check and 08 for void check
        'amount'       =>sprintf("%010s", $v['balance'] * 100),
        'void_code'    =>$issue, // 00 for issue check and 08 for void check
        'issue_date'   =>date('mdy', strtotime($v['posted_date'])),
        'filler'       =>'0', 
        'payee_name'   =>sprintf("%- 35s", title_case($v['vendor_name']))
      ]), 0, 76);
    }
    return implode(self::$_newLine, $oData);
  }
//------------------------------------------------------------------------------  
  private static function _torryPines($data){
    $oData = [];
    foreach($data as $v){
      $issue = (isset($v['isIssue']) && $v['isIssue']) ? 'I' : 'V'; // 00 for issue check and 08 for void check
      $oData[] = implode('', [
        'checkNo'=>sprintf("%015s", $v['check_no']), // serial_number = check_number
        'account'    => sprintf("%015s", self::cleanAcct($v['bankDetail']['cp_acct'])),
        'issue_date' => date('mdy', strtotime($v['posted_date'])),
        'amount'     => sprintf("%010s", $v['balance']),
        'issue_type' => $issue, // 00 for issue check and 08 for void check
        'payee_name' => sprintf("%- 96s", title_case($v['vendor_name']))
      ]);
    }
    return implode(self::$_newLine, $oData);
  }
//------------------------------------------------------------------------------
  private static function _mechanicsBank($data){
    $oData = [];
    foreach($data as $v){
      $issue   = (isset($v['isIssue']) && $v['isIssue']) ? 'I' : 'V'; // 00 for issue check and 08 for void check
      $oData[] = implode(',', [
        'tran_code'    =>$v['bankDetail']['transit_cp'],
        'account'      =>self::cleanAcct($v['bankDetail']['cp_acct']),
        'checkNo'      =>sprintf("%06s", $v['check_no']), 
        'void_code'    =>$issue, // 00 for issue check and 08 for void check
        'issue_date'   =>date('m/d/y', strtotime($v['posted_date'])),
        'amount'       =>$v['balance'],
        'payee_name'   =>title_case($v['vendor_name'])
      ]);
    }
    return implode(self::$_newLine, $oData);
  }
################################################################################
##########################   FILE NAME FUNCTION   ##############################  
################################################################################  
  private static function _eastWestBankFile(){
    $path = Helper::isProductionEnvironment() ? 'UPLOAD/' : 'TEST/UPLOAD/';
    return $path . 'BEB928ARI1P.IE_BEB928ARI1P_1710050.' . date('mdyHis') . '.txt';
  }
//------------------------------------------------------------------------------
  private static function _farmersMerchantsBankFile(){
    $path = Helper::isProductionEnvironment() ? 'PosPay_Prod/' : 'PosPay_Test/';
    return $path . 'PAMA' . date('mdyHis') . '.txt';
  }
//------------------------------------------------------------------------------
  private static function _nanoBancFile(){
    $path = Helper::isProductionEnvironment() ? 'Incoming/' : 'Test/Incoming/';
    return $path . 'PAMA' . date('mdyHis') . '.txt';
  }
//------------------------------------------------------------------------------
  private static function _mechanicsBankFile(){
    $path = Helper::isProductionEnvironment() ? 'PositivePay/' : 'PositivePay/';
    return $path . 'PAMA' . date('mdyHis') . '.txt';
  }
//------------------------------------------------------------------------------
  private static function _torryPinesFile(){
    $path   = Helper::isProductionEnvironment() ? 'POS_Processed/' : 'Test/';
    $prefix = Helper::isProductionEnvironment() ? 'test_NijjarPosPay' : 'NijjarPosPay';
    return $path . $prefix . date('mdyHis') . '.txt';
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private static function cleanAcct($acct){
    return trim(preg_replace('/[^0-9]+/', '', $acct));
  }
}