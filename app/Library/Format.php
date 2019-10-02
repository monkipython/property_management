<?php
namespace App\Library;
class Format{
  public static function usDate($date) {
    return date('m/d/Y', strtotime($date));
  }
//------------------------------------------------------------------------------
  public static function getBankDisplayFormat($v, $acct = 'cr_acct'){
    return $v['bank'] . ': ' . $v['name'] . ' ('. substr(preg_replace('/\s+|c[0-9]*/', '', (!empty($v[$acct]) ? $v[$acct] : '')), -4) . ') ';
  }
//------------------------------------------------------------------------------
  public static function bankAccountDisplayFormat($acct){
    return substr(preg_replace('/\s+|c[0-9]*/','',$acct),-4);  
  }
//------------------------------------------------------------------------------
  public static function checkNumber($checkNo){
    return sprintf("%06s", $checkNo);
  }
//------------------------------------------------------------------------------
  public static function mysqlDate($date){
    return date('Y-m-d', strtotime($date));
  }
//------------------------------------------------------------------------------
  public static function percent($num){
    return number_format(preg_replace('/[^0-9]\.\-E/','',$num),2,'.','') . ' %';
  }
//------------------------------------------------------------------------------
  public static function roundDownToNearestHundredthDecimal($num){
    return floor($num *  100.0) / 100.0;
  }
//------------------------------------------------------------------------------
  public static function usMoney($amount) {
    return ($amount < 0) ? '$(' . number_format(preg_replace('/,/', '', abs($amount)), 2, '.', ',') . ')' : '$' . number_format(preg_replace('/,/', '', $amount), 2, '.', ',');
  }
//------------------------------------------------------------------------------    
  public static function usMoneyMinus($amount) {
    return ($amount < 0) ? '$-' . number_format(preg_replace('/,/', '', abs($amount)), 2, '.', ',') : '$' . number_format(preg_replace('/,/', '', $amount), 2, '.', ',');
  } 
//------------------------------------------------------------------------------
  public static function floatNumber($num) {
    // E is for exponent number
    return number_format(preg_replace('/[^0-9\.\-E]/', '', $num), 2, '.', '');
  }
//------------------------------------------------------------------------------
  public static function floatNumberSeperate($num) {
    // E is for exponent number 
    return number_format(preg_replace('/[^0-9\.\-E]/', '', $num), 2, '.', ',');
  }
//------------------------------------------------------------------------------
  public static function intNumber($num) {
     // E is for exponent number 
    return number_format(preg_replace('/[^0-9\.\-E]/', '', $num), 0, '', '');
  }
//------------------------------------------------------------------------------
  public static function intNumberSeperate($num) {
     // E is for exponent number 
    return number_format(preg_replace('/[^0-9\.\-E]/', '', $num), 0, '', ',');
  }
//------------------------------------------------------------------------------
  public static function numberOnly($num) {
    return number_format(preg_replace('/[^0-9\.\-]/', '', $num), 0, '', '');
  }
//------------------------------------------------------------------------------    
  public static function formatNumber($num) {
    return number_format($num, 0, '', ',');
  }
//------------------------------------------------------------------------------    
  public static function number($num, $sep = 0, $decimal = ',', $thousand = '.'){
    return number_format(floatval($num), $sep, $decimal, $thousand);
  }
//------------------------------------------------------------------------------
  public static function capWord($str) {
    return ucwords(strtolower($str));
  }
//------------------------------------------------------------------------------
  public static function bytes($num, $precision = 1){
    if ($num >= 1000000000000000) {
      $num = round($num / (1024 * 1024 * 1024 * 1024 * 1024), $precision);
      $unit = 'PB';
    } elseif ($num >= 1000000000000) {
      $num = round($num / (1024 * 1024 * 1024 * 1024), $precision);
      $unit = 'TB';
    } elseif ($num >= 1000000000) {
      $num = round($num / (1024 * 1024 * 1024), $precision);
      $unit = 'GB';
    } elseif ($num >= 1000000) {
      $num = round($num / (1024 * 1024), $precision);
      $unit = 'MB';
    } elseif ($num >= 1000) {
      $num = round($num / 1024, $precision);
      $unit = 'kB';
    } else {
      $unit = 'B';
      return $this->number($num).' '.$unit;
    }
    return $this->number($num, $precision).' '.$unit;
  }
//------------------------------------------------------------------------------
  public static function toBytes($sSize){
    $sSize = str_replace(' ', '', $sSize);
    //This function transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
    $sSuffix = substr($sSize, -1);
    $iValue = substr($sSize, 0, -1);
    switch (strtoupper($sSuffix)) {
      case 'P':
        $iValue *= 1024;
      case 'T':
        $iValue *= 1024;
      case 'G':
        $iValue *= 1024;
      case 'M':
        $iValue *= 1024;
      case 'K':
      case 'k':
        $iValue *= 1024;
        break;
      default:
        $iValue = intval($sSize);
    }
    return $iValue;
  }
//------------------------------------------------------------------------------
  public static function phone($phone){
    return $phone;
//    preg_match( '/^(\d{3})(\d{3})(\d{4})$/', $phone,  $matches );
//    return '(' . $matches[1] . ') ' . $matches[2] . '-' . $matches[3];
  }

//------------------------------------------------------------------------------
  public static function slugHash($id, $timestamp = null){
    $alphabet = '_zaq1OUJM2WSXcde34RFVbgt5CZAQ6YHNmju78IKlo90PLBGTED-';
    $base_count = strlen($alphabet);
    $encoded = '';

    $id = (!empty($timestamp) && is_int($timestamp) ? $timestamp : strtotime('now')).$id;

    while ($id >= $base_count) {
      $div = $id/$base_count;
      $mod = ($id-($base_count*intval($div)));
      $encoded .= $alphabet[$mod];
      $id = intval($div);
    }

    if ($id) {
      $encoded .= $alphabet[$id];
    }

    return $encoded;
  }

}