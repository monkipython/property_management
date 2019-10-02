<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use \Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\Controller;

class LogoutController extends Controller{
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $uid = env('COOKIE_UID');
    $sid = env('COOKIE_SID');

    \Cookie::queue(\Cookie::forget($uid));
    \Cookie::queue(\Cookie::forget($sid));
    return redirect('/login');
  }
}