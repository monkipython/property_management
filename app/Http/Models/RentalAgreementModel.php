<?php 
namespace App\Http\Models;
use App\Library\{TableName as T};
use Illuminate\Support\Facades\DB;

class RentalAgreementModel extends DB {
  public static function getTableData($table,$where,$select='*',$firstRowOnly=1){
    $r = DB::table($table)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getRentalAgreement($where,$select='*',$firstRowOnly=1){
    $r = DB::table(T::$rentalAgreement)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------  
  public static function getDownloadLink($where,$firstRowOnly=1){
    $r = DB::table(T::$rentalAgreement)->select('pdfLink')->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------  
  public static function fetchAgreementsByColVals($columnName,$colVals,$select='*',$firstRowOnly=0){
    $columnVals = is_array($colVals) ? $colVals : [$colVals];
    $r = DB::table(T::$rentalAgreement)->select($select)->whereIn($columnName,$columnVals);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
}