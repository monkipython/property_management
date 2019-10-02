<?php
namespace App\Library;
use Illuminate\Support\Facades\Storage;
use App\Library\{Helper};
use App\Http\Models\Account\Account AS M;
class Auth{
  public static function checkAuth($request) {
	$data = 0;
	$username = $request->cookie(env('COOKIE_UID'));
	$password = $request->cookie(env('COOKIE_SID'));
	$logInfo  = 'Cookie: $username: ' . $username . ', $password: ' . $password . "\n";
	if(!empty($username) && !empty($password)){
	  $r = M::showUser($username);
	  $logInfo .= 'Database: $r[username]: ' . $r['username'] . ', $r[password]: ' . $r['password'];
	  if(!empty($r)){
		$passwordTS = strtotime($r['passwordUpdate']);
		$todayTime  = strtotime('-12 hour', strtotime(date('Y-m-d H:i:s')));

		$data = ($r['password'] == $password) ? ['username'=>$username] : 0;
	  }
	}
	self::_logAuth($logInfo);
	return $data;
  }
//--------------------------------------------------------------------------------------------------
  public static function parsePermission($permission){
	$data = [];
	$pieces = explode('|', $permission);
	foreach($pieces as $v){
	  $data[] = self::parseEachPermission($v);
	}
	return $data;
  }
//--------------------------------------------------------------------------------------------------
  public static function parseEachPermission($v){
	list($permissionId, $programId, $program, $lbl, $permission) = explode(',', $v);
	return [
	  'permissionId'=>$permissionId,
	  'programId'	  =>$programId,
	  'program'	  =>$program,
	  'lbl'		  =>$lbl,
	  'permission'  =>$permission
	];
  }
//--------------------------------------------------------------------------------------------------
  public static function getUserAccessablePage($username){
	$r	  = M::showPermission($username);
	$perm = Helper::keyFieldName(Auth::parsePermission($r['permission']), 'method', 'permission');
	foreach($perm as $page=>$v){
	  if($v){ return '/' . $page; }
	}
	return '/401';
  }
//--------------------------------------------------------------------------------------------------
  public static function getUserInfo($request){
	if($request->cookie('UID') !== null){
	  $username = \Crypt::decrypt($request->cookie('UID'));
	  $r	  = M::showPermission($username);
	  $perm = Helper::keyFieldName(self::parsePermission($r['permission']), 'method');
	  foreach($perm as $page=>$val){
		if($val['permission']){
		  $r['perm'][$page] = ['lbl'=>$val['lbl'], 'perm'=>$val['permission']];
		}
	  }
	}
	return ['user'=>isset($r) ? $r : [], 'suc'=>0];
  }
####################################################################################################
#####################################     HELPER FUNCTION      #####################################
####################################################################################################
  private static function _logAuth($content) {
	$content = "\n\n######################" . Helper::mysqlDate() . "###########################\n" . $content;
	Storage::append('account/loginError.txt', $content);
  }
//--------------------------------------------------------------------------------------------------
//  private static function _getUserInfoCookie($request){
//	$retry = 1;
//	$max   = 3;
//	while($retry){
//	  $retry++;
//	  $username = $request->cookie('UID');
//	  $password = $request->cookie('SID');
//	  
//	  if(!empty($username) && !empty($password)){
//		return ['username'=>$username, 'password'=>$password];
//	  }
//	  
//	  if($retry == $max){
//		$retry = 0;
//	  }
//	  sleep(1);
//	}
//	return ['username'=>'', 'password'=>''];
//  }
}