<?php
namespace App\Http\Models;
use App\Library\{Helper, Elastic, TableName AS T};
use Illuminate\Support\Facades\DB;

class Section8Model extends DB {
//------------------------------------------------------------------------------
  public static function getUnit($where, $select = '*', $firstRowOnly = 1){
    $r = DB::table(T::$unit)->select($select)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
}