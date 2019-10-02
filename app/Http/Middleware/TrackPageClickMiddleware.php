<?php
namespace App\Http\Middleware;
use Illuminate\Support\Facades\DB;
use App\Library\{TableName AS T, Helper, Html};
use Closure;

class TrackPageClickMiddleware{
  /**
   * @desc This middleware used to prevent from users to go to login page after they login already
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  private $_page  = '/creditCheck';
  private $_map   = ['edit'=>'update', 'update'=>'edit', 'create'=>'store', 'store'=>'create'];
  public function handle($request, Closure $next){
    $routeName = \Request::route()->getName();
    DB::table(T::$trackPageClick)->insert([
      'page'  => $routeName,
      'cdate' => Helper::mysqlDate(),
      'ip'    => $request->ip(),
      'usid'  => $request['ACCOUNT']['email']
    ]);
    return $next($request); 
  }
}