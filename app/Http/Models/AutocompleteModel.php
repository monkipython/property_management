<?php 
namespace App\Http\Models;
use Illuminate\Support\Facades\DB;

class AutocompleteModel extends DB{
  private static $maxLimit = 100;
  public static $db;
  private static $_debug = 0;
  public static function prop($vData, $select = 'CONCAT(p.prop, "-", p.street, "-", pb.trust) AS value, p.prop AS data'){
    return
      DB::table('prop AS p')
      ->selectRaw($select)
      ->leftjoin('prop_bank AS pb', function($join){
        $join->on('p.prop', '=', 'pb.prop')
             ->on('p.ar_bank', '=', 'pb.bank');
      })
      ->leftjoin('unit AS u', 'p.prop', '=', 'u.prop')
//      ->join('bank AS b', 'b.prop', '=', 'pb.trust')
      ->where(function($query) use ($vData){
        $query->where('p.prop', 'LIKE', $vData['query'] . '%')
          ->orWhere('p.street', 'LIKE', $vData['query'] . '%');
//          ->orWhere('pb.trust', 'LIKE', $vData['query'] . '%');
      })
      ->where([
        ['p.prop', 'NOT LIKE', '*%'],
        ['p.prop', 'NOT LIKE', '#%'],
        ['p.prop_class', '<>', 'X'],
      ])
      ->whereNotNull('pb.trust')
      ->limit(50)
      ->groupBy('p.prop')  
      ->orderby('p.prop', 'asc')  
      ->orderby('u.unit', 'asc')  
      ->get();
//      ->toSql();
  }
//------------------------------------------------------------------------------
  public static function unit($vData, $select = 'CONCAT(p.prop, "-", p.street, "-", pb.trust) AS value, p.prop AS data'){
    return
      DB::table('unit AS u')
      ->selectRaw($select)
      ->leftjoin('tenant AS t', function($join){
        $join->on('t.prop', '=', 'u.prop')
             ->on('t.unit', '=', 'u.unit');
      })
      ->where(function($query) use ($vData){
        $query->where('u.unit', 'LIKE', $vData['query'] . '%');
      })
      ->where([
        ['u.prop', '=', $vData['prop']]
      ])
      ->limit(50)
      ->groupBy('u.unit_id')  
      ->orderby('t.unit', 'ASc')  
      ->orderby('t.status', 'DESC')  
      ->get();
//      ->toSql();
  }
//------------------------------------------------------------------------------
  public static function trust($vData, $select){
    return
      DB::table('prop AS p')
      ->selectRaw($select)
      ->where([
        ['p.prop', 'NOT LIKE', '#%'],
        ['p.prop', 'NOT LIKE', '*%'],
        ['p.trust', 'LIKE', $vData['query'] . '%']
      ])
      ->whereNotNull('p.trust')
      ->limit(50)
      ->groupBy('p.trust')  
      ->orderby('p.trust', 'asc')  
      ->get();
//      ->toSql();
  }
//------------------------------------------------------------------------------
  public static function group($vData, $select){
    return
      DB::table('prop AS p')
      ->selectRaw($select)
      ->where([
        ['p.group1', 'LIKE', $vData['query'] . '%']
      ])
      ->limit(50)
      ->groupBy('p.group1')  
      ->orderby('p.group1', 'asc')  
      ->get();
//      ->toSql();
  }
//------------------------------------------------------------------------------
  public static function vendor($vData, $select) {
    return
      DB::table('vendor AS v')
      ->selectRaw($select)
      ->where(function($query) use ($vData) {
       $query->where('v.vendid', 'LIKE', $vData['query'] . '%')
           ->orWhere('v.name', 'LIKE', $vData['query'] . '%');
      })
      ->limit(50)
      ->groupBy('v.vendid')  
      ->orderby('v.vendid', 'asc')  
      ->get();
  }
//------------------------------------------------------------------------------
  public static function glAcct($vData, $select) {
    return
      DB::table('gl_chart AS g')
      ->selectRaw($select)
      ->where(function($query) use ($vData) {
       $query->where('g.gl_acct', 'LIKE', $vData['query'] . '%')
           ->orWhere('g.title', 'LIKE', $vData['query'] . '%');
      })
      ->where('prop', '=', $vData['prop'])
      ->limit(50)
      ->groupBy('g.gl_acct')  
      ->orderby('g.gl_acct', 'asc')  
      ->get();
  }
//------------------------------------------------------------------------------
  public static function service($vData, $select) {
    return
      DB::table('service')
      ->selectRaw($select)
      ->where(function($query) use ($vData) {
       $query->where('service', 'LIKE', $vData['query'] . '%')
           ->orWhere('remark', 'LIKE', $vData['query'] . '%');
      })
      ->where('prop', '=', $vData['prop'])
      ->limit(50)
      ->groupBy('service')  
      ->orderby('service', 'asc')  
      ->get();
  }
}