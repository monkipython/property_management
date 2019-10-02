<?php
namespace App\Http\Middleware;
use Illuminate\Support\Facades\DB;
use App\Library\{TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\AccountModel AS M; // Include the models class
use Closure;

class AlreadyLoginMiddleware{
  /**
   * @desc This middleware used to prevent from users to go to login page after they login already
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  private $_page  = '/creditCheck';
  public function handle($request, Closure $next){
    $username = $request->cookie(env('COOKIE_UID'));
    $password = $request->cookie(env('COOKIE_SID'));
    $r = M::getAccount(Model::buildWhere(['email'=>$username]), 1);
    
    if(!empty($r) && !empty($password) && $password == $r['password']){
      return redirect($this->_page);
    } else{
      return $next($request);
    }
  }
}