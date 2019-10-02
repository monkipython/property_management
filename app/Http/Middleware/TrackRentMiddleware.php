<?php
namespace App\Http\Middleware;
use Illuminate\Support\Facades\DB;
use App\Library\{TableName AS T, Helper};
use Closure;

class TrackRentMiddleware {
 public function handle($request,Closure $next){
    $classes    = ['creditCheck','tenant','unit','fullbilling','rentRaise'];
    $rentFields = [
      'creditCheck'  => ['columns'=>['new_rent'],'methods' =>['update','store']],
      'tenant'       => ['columns'=>['base_rent'],'methods'=>['update']],
      'unit'         => ['columns'=>['rent_rate','market_rent'],'methods'=>['update']],
      'fullbilling'  => ['columns'=>['amount'],'methods'=>['update','store']],
      'rentRaise'    => ['columns'=>['rent','rent_old','raise'],'methods'=>['update','store']],
    ];
    
    $routeName  = \Request::route()->getName();
    list($cls,$method) = explode('.',$routeName);
    $methods    = !empty($rentFields[$cls]['methods']) ? $rentFields[$cls]['methods'] : [];
    if(in_array($method,$methods) && in_array($cls,$classes)){
      $data = \Request::all();
      if(!empty(array_intersect($rentFields[$cls]['columns'],array_keys($data)))){
        $usid  = Helper::getUsid($data);
        unset($data['ACCOUNT'], $data['PERMISSION'], $data['NAV'], $data['PERM'], $data['ALLPERM'], $data['ISADMIN']);
        DB::table(T::$trackRent)->insert([
          'controller'   => $cls,
          'route'        => $method,
          'data'         => strip_tags(json_encode($data)),
          'usid'         => $usid,
        ]);
      }
    }

    return $next($request);
  }

}
