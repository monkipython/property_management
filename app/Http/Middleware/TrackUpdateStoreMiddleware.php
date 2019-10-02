<?php
namespace App\Http\Middleware;
use Illuminate\Support\Facades\DB;
use App\Library\{TableName AS T, Helper, Html};
use Closure;

class TrackUpdateStoreMiddleware{
  /**
   * @desc This middleware used to prevent from users to go to login page after they login already
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  public function handle($request, Closure $next){
    $routeName = \Request::route()->getName();
    list($cls, $method) = explode('.', $routeName);
    if($method == 'update' || $method == 'store' || $method == 'destroy'){
      $data = \Request::all();
      $usid = Helper::getUsid($data);
      unset($data['ACCOUNT'], $data['PERMISSION'], $data['NAV'], $data['PERM'], $data['ALLPERM'], $data['ISADMIN']);
      DB::table(T::$trackUpdateStore)->insert([
        'controller' => $cls,
        'route'    => $method,
        'data'   => json_encode($data),
        'usid' =>$usid,
      ]);
    }
    return $next($request); 
  }
}