<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use \Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, TableName AS T};
use App\Http\Models\Model; // Include the models class

class LoginController extends Controller{
  private $_viewPath = 'app/Account/login/';
  private $_uid      = '';
  private $_sid      = '';
  
  public function __construct(Request $req){
    $this->_uid      = env('COOKIE_UID');
    $this->_sid      = env('COOKIE_SID');
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $page = $this->_viewPath . 'index';
    $form = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'button'    =>self::_getButton(__FUNCTION__), 
      'orderField'=>self::_getOrderField(__FUNCTION__), 
      'setting'   =>self::_getSetting(__FUNCTION__),
    ]);
    return view($page, ['data'=>['form'=>$form]]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $response         = ['error'=>['msg'=>self::_errorMsg('cannotLogin')]];
    $responseAttempt  = ['error'=>['msg'=>self::_errorMsg('maxAttempt')]];
    $responseNotExist = ['error'=>['msg'=>self::_errorMsg('accountNotExist')]];
    $maxAttempt       = 4;
    $redirectLink     = ['/creditCheck'];
    
    $valid = V::startValidate([
      'rawReq'          =>$req->all(),
      'tablez'          =>self::_getTable('index'), 
      'orderField'      =>self::_getOrderField('index'),
      'setting'         =>self::_getSetting('index'),
    ]);
    $vData   = $valid['dataNonArr'];
    $r = DB::table(T::$account)->where(Model::buildWhere(['email'=>$vData['email'], 'active'=>1]))->first();
    
    if(!empty($r) && !$r['isLocked']){
      if(\Hash::check($vData['password'], $r['password'])){
        # COOKIE HAS TO SET BEFORE ANYTHING ELSE 
        Cookie::queue($this->_uid, $r['email']);
        Cookie::queue($this->_sid, $r['password']);
        
        $r = DB::table(T::$account)->where(Model::buildWhere(['account_id'=>$r['account_id']]))->update(['loginAttempt'=>0]);
        
        $path = parse_url(url()->previous(), PHP_URL_QUERY);
        return response(['link'=>$path ? urldecode($path) : $redirectLink]);
      } else if(!empty($r['account_id'])){
        $updateDataSet = [
          T::$account=>[
            'whereData'=>['account_id'=>$r['account_id']],
            'updateData'=>[ 'loginAttempt'=>$r['loginAttempt'] + 1,'isLocked'=> ($r['loginAttempt'] >= $maxAttempt) ? 1 : 0]
          ]
        ];
        DB::beginTransaction();
        try{
          $success = Model::update($updateDataSet);
          $elastic = [
            'insert'=>[T::$accountView=>['account_id'=>[$success['update:'. T::$account][0]]]]
          ];
          Model::commit([
            'success' =>$success,
            'elastic' =>$elastic,
          ]);
        } catch(\Exception $e){
          $response['error']['mainMsg'] = Model::rollback($e);
        }
        return response($response);
      } 
    } else{
      return response(empty($r) ? $responseNotExist : $responseAttempt);
    }
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'index'     =>['account'],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['email', 'password'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn){
    $setting = [
      'index'=>[
        'field'=>[
          'password'=>['type'=>'password']
        ],
        'rule'=>[
          'email'   =>'required|string',
          'password'=>'required|string'
        ]
      ],
    ];
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn){
    $buttton = [
      'index'=>['submit'=>['id'=>'submit', 'value'=>'Submit']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################     HELPER FUNCTION   ###############################  
################################################################################
  private function _errorMsg($name){
    $data = [
      'cannotLogin' =>Html::errMsg('Email and Password does not match.'),
      'maxAttempt'  =>Html::errMsg('You attempt to login too many times. Your account is locked. Please contact administrator to unlock your account.'),
      'accountNotExist'  =>Html::errMsg('Your account does not exist.'),
    ];
    return $data[$name];
  }
}