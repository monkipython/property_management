<?php
namespace App\Http\Models;
use App\Library\{TableName AS T, Helper, Elastic};
use Illuminate\Support\Facades\DB;

class VoidCheckModel extends DB {
  public static function getTrackVoidCheck($whereIn, $select='*'){
    $r = DB::table(T::$trackVoidCheck)->select($select)->whereIn('seq', $whereIn);
    return $r->get()->toArray();
  }
}

