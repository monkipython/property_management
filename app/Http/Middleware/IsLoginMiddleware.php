<?php
namespace App\Http\Middleware;
use \App\Http\Models\AccountModel AS M; // Include the models class
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;
use App\Library\{TableName AS T, Html, Helper};
use Closure;
class IsLoginMiddleware{
  private $_page = '/login';
  public function handle($request, Closure $next){
    $username = $request->cookie(env('COOKIE_UID'));
    $password = $request->cookie(env('COOKIE_SID'));
    $r = M::getAccount(Model::buildWhere(['email'=>$username]), 1);
    if(!empty($r) && !empty($password) && $password == $r['password']){
      $rNav = M::getPermission(Model::buildWhere(['account_id'=>$r['account_id'], 'permission'=>1]));
      $rPermission = M::getPermission(Model::buildWhere(['account_id'=>$r['account_id'], 'permission'=>1]));
      $request->merge([
        // accountRole_id 4 is an admin. we don't want to use Administrator because we are afraid that user will change the name
        'ISADMIN'    =>($r['accountRole_id'] == 4) ? 1 : 0,  
        'ACCOUNT'    =>$r, 
        'PERMISSION' =>$rPermission, 
        'ALLPERM'    =>$this->_getAllPermission($rPermission),
        'PERM'       =>$this->_getPermission($request, $rPermission), 
        'NAV'        =>$this->_getNav($request, $rNav)]
      );
      return $next($request);
    } else{
      if($request->ajax()){ 
        return response(['error'=>['msg'=>'Do not have permission to access this area.']]); // This is ajax request
      } else{
        $savedPath = urlencode($request->getRequestUri());
        return redirect($this->_page . '?' . $savedPath);
      }
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getPermission($request, $rPermission){
    $permission = [];
    
    $routeName = \Request::route()->getName();
    list($cls, $method) = explode('.', $routeName);
    
    foreach($rPermission as $v){
      $permission[$v['classController']][] = $v;
    }
//    dd($permission);
//    dd(Helper::keyFieldName($permission[$cls], 'method'));
    return ($cls != 'logout' && isset($permission[$cls])) ? Helper::keyFieldName($permission[$cls], 'method') : []; 
  }
//------------------------------------------------------------------------------
  private function _getAllPermission($rPermission){
    $perm= Helper::keyFieldName($rPermission, ['classController','method','op']);
    foreach($perm as $val){
      $subController = explode(',', $val['subController']);
      foreach($subController as $v){
        $perm[$v]['permission'] = 1;
      }
    }
    unset($perm['']);
    return $perm;
  }
//------------------------------------------------------------------------------  
  private function _getNav($request, $rPermission){
    $nav = '';
    $num = 0;
    $url = $request->path();
    $program = [];
    foreach($rPermission as $v){
      $program[$v['category']][$v['module']] = $v;
    }
    foreach($program as $category=>$val){
      $active = isset($val[$url]) ? ' menu-open active' : '';
      $icon = reset($val)['categoryIcon'];
      $i = Html::i('', ['class'=>$icon]) . Html::span($category);
      
      $span = Html::span(
        Html::i('', ['class'=>'fa fa-angle-left pull-right']),
        ['class'=>'pull-right-container']
      );
      $header = Html::a($i .$span , ['href'=>'#']);
      $ulData = [];
      
      # SORT MULTIPLE ARRAY
      $val = array_values(array_sort($val, function ($value) {
        return $value['module'];
      }));

      foreach($val as $v){
        $i = Html::i('', ['class'=>'fa fa-circle-o']) . ' ' . $v['moduleDescription'];
        $liValue = Html::a($i, ['href'=>'/' . $v['module'] ]);
        $liData = ['value'=>$liValue];
        $liData['param'] = $v['module'] == $url ? ['class'=>$active] : [];
        $ulData[] = $liData;
      }
      $body = Html::buildUl($ulData, ['class'=>'treeview-menu']);
      $nav .= Html::li($header . $body, ['class'=>'treeview' . $active]);
    }
    return $nav;
  }
}