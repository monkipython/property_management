<?php
namespace App\Http\Middleware;
use Illuminate\Support\Facades\DB;
use App\Library\{TableName AS T, Helper, Html};
use Closure;

class CheckPermissionMiddleware{
  /**
   * @desc This middleware used to prevent from users to go to login page after they login already
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  private $_page  = '/creditCheck';
  private $_map   = ['create'=>'store', 'store'=>'create'];
//  private $_map   = ['edit'=>'update', 'update'=>'edit', 'create'=>'store', 'store'=>'create'];
  public function handle($request, Closure $next){

    $perm= Helper::keyFieldName($request['PERMISSION'], ['classController','method','op']);
    $perm = $request['ALLPERM'];
    unset($perm['']);

    $routeName = \Request::route()->getName();
    list($cls, $method) = explode('.', $routeName);
    $op = !empty($request['op']) ? $request['op'] : '';
    
//    $routeAllMethod = $cls . 'allall';
//    $routeAllOp     = $cls  . $method . 'all';
//    $routeAllOpMap  = isset($this->_map[$method]) ? $cls  . $this->_map[$method] . 'all' : '';
//    $route1         = $cls . $method . $op;
//    $route2         = $cls . (isset($this->_map[$method]) ? $this->_map[$method] : $method) . $op;
//    dd($cls, $method, $routeAllOp,$route1,$route2, $routeAllOpMap, $permission);
//    if( $routeName == 'logout.index' ||
//        !empty($permission[$routeAllMethod]['permission']) || 
//        !empty($permission[$routeAllOp]['permission']) || 
//        !empty($permission[$routeAllOpMap]['permission']) || 
//        !empty($permission[$route1]['permission']) || 
//        !empty($permission[$route2]['permission'])){
//      return $next($request);
//    } 
//    else if(!isset($permission[$cls])){
//       return $next($request);
//    } 
//    else{
//      if($request->ajax()) {
//        // Will change soon
//        return response('Access denied', 403); // This is ajax request
//      } else{
//        return response('Access denied', 403); // This is ajax request
//      }
//    }
    $methodMap = isset($this->_map[$method]) ? $this->_map[$method] : $method;
    $route = [
      $cls, 
      $cls . $method, 
      $cls . $method . $op, 
      $cls . $methodMap, 
      $cls . $methodMap . $op, 
      'logout.index'
    ];
//    dd($perm, $route);
//    dd($route);
    foreach($route as $eachRoute){
      if(!empty($perm[$eachRoute]['permission'])){
        return $next($request); 
      }
    }
    if($request->ajax()) {
      // Will change soon
      return response('Access denied', 403);
//      response('Access denied', 403); // This is ajax request
    } else{
      return abort(403); // This is ajax request
    }
//    
////    dd($perm, $route1, $route2 , $route3, $route4, $route5);
//    $route1 = $cls;
//    $route2 = $cls . $method;
//    $route3 = $cls . $method . $op;
//    $route4 = $cls . $methodMap;
//    $route5 = $cls . $methodMap . $op;
////    dd($perm, $route1, $route2 , $route3, $route4, $route5);
//    if(
//        !empty($perm[$route1]['permission']) || 
//        !empty($perm[$route2]['permission']) ||
//        !empty($perm[$route3]['permission']) ||
//        !empty($perm[$route4]['permission']) ||
//        !empty($perm[$route5]['permission'])
//    ){ 
//      return $next($request);
//    } else if($routeName == 'logout.index'){
//       return $next($request);
//    } else{
//      if($request->ajax()) {
//        // Will change soon
//        return response('Access denied', 403); // This is ajax request
//      } else{
//        return response('Access denied', 403); // This is ajax request
//      }
//    }
//    return $next($request);
    
    
    
    
//    $username = $request->cookie(env('COOKIE_UID'));
//    $password = $request->cookie(env('COOKIE_SID'));
//    $r = DB::table(T::$account)->where([['email', '=', $username]])->first();
//    if(!empty($r) && !empty($password) && $password == $r['password']){
//      return redirect($this->_page);
//    } else{
//      return $next($request);
//    }
    
    
    
    
    
//    
//    $cntPage  = Helper::getCurrentPage($request);
//    $auth	  = Auth::checkAuth($request);
//    if($auth){
//      $username = $auth['username'];
//      $r	= M::showPermission($username);
//      $perm = Helper::keyFieldName(Auth::parsePermission($r['permission']), 'program', 'permission');
//      if(!empty($perm[$cntPage]) || $cntPage == 'logout'){
//      return $response;
//      }
//      else{
//      $firstPerm = key($perm);
//      if ($cntPage == '' && $firstPerm != ''){ // if firstPerm was blank, there would be an inf loop. Thus, this must be checked.
//        return redirect('/' . $firstPerm);
//      }
//      if($request->ajax()) { 
//        return response('Access denied', 403); // This is ajax request
//      } else { 
//        // Adding nav bar to 403 page
//        // Processing to get into format required by template
//        // Using existing $perm will throw error. Therefore, we must process perms once again.
//        $r['perm'] = Auth::parsePermission($r['permission']);
//        return response()->view('errors.403',  ['data'=>['user'=>$r]], 403); //Stopping dead end
//      } 
//      }
//    }
//    else{ // If everything else, redirect to login page
//      Helper::clearAccessCookie();
//      if($request->ajax()){ 
//      return response('Access denied', 403); // This is ajax request
//      } else{ 
//      return redirect($this->_page); 
//      }
  }
//------------------------------------------------------------------------------
//  private function _getPermission($request){
//    $perm= Helper::keyFieldName($request['PERMISSION'], ['classController','method','op']);
//    foreach($perm as $val){
//      $subController = explode(',', $val['subController']);
//      foreach($subController as $v){
//        $perm[$v]['permission'] = 1;
//      }
//    }
//    return $perm;
//  }
}