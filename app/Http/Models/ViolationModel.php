<?php
namespace App\Http\Models;
use App\Library\{TableName AS T, Helper, Elastic};
use Illuminate\Support\Facades\DB;

class ViolationModel extends DB {
  public static function getViolation($must, $source = [], $isFirstRow = 1){
    return Helper::getElasticResultSource(Elastic::searchQuery([
      'index'   => T::$violationView,
      '_source' => $source,
      'query'   => ['must'=>$must]
    ]), $isFirstRow);
  }
}

