<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Mail, Html, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\AccountModel AS M; // Include the models class
use \utilphp\util;

class PasswordResetController extends Controller{
  private $_viewPath = 'app/Account/passwordReset/';
  private $_uid      = '';
  private $_sid      = '';
  
  public function __construct(Request $req){
    $this->_uid      = env('COOKIE_UID');
    $this->_sid      = env('COOKIE_SID');
  }
//------------------------------------------------------------------------------
  // Is used when change the drop drow during the permission change
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
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $response = [];
    $valid = V::startValidate([
      'rawReq'       =>$req->all(),
      'tablez'       =>self::_getTable(__FUNCTION__), 
      'orderField'   =>self::_getOrderField('editValidate'),
      'includeCdate' =>0,
      'copyField'    =>self::_getCopyField(__FUNCTION__),
      'setting'      =>self::_getSetting(__FUNCTION__),
    ]);
    
    $vData = $valid['dataNonArr'];
    $vData['active'] = 1;
    $r = M::getPasswordReset($vData, 1);
    $to = strtotime(date('Y-m-d H:i:s'));
    $from = strtotime($r['cdate']);
    $timeDiff = util::human_time_diff($from, $to, false, '');
    list($time, $strTime) = explode(' ', $timeDiff);
    
    if((preg_match('/minute/', $strTime) && $time < 20) || (preg_match('/second/', $strTime) && $time < 59)){
      $form = Form::generateForm([
        'tablez'    =>self::_getTable(__FUNCTION__), 
        'button'    =>self::_getButton(__FUNCTION__), 
        'orderField'=>self::_getOrderField('editForm'), 
        'copyField' =>self::_getCopyField(__FUNCTION__),
        'setting'   =>self::_getSetting(__FUNCTION__, $vData)
      ]);
      $response = ['form'=>$form, 'msg'=>'']; 
    } else{
      $response = ['form'=>'', 'msg'=>$this->_getErrorMsg('edit')]; // Token is expired
    }
    return view($page, [ 'data'=>$response ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $valid = V::startValidate([
      'rawReq'       =>$req->all(),
      'tablez'       =>self::_getTable('edit'), 
      'orderField'   =>self::_getOrderField('editForm'),
      'includeCdate' =>0,
      'copyField'    =>self::_getCopyField('edit'),
      'setting'      =>self::_getSetting('edit'),
    ]);
    $vData = $valid['dataNonArr'];
    $r = M::getPasswordReset(['token'=>$vData['token']], 1);
    $rAccount = M::getAccount(['email'=>$r['email']], 1);
    if(empty($r) || empty($rAccount)){
      return ['error'=>['msg'=>$this->_getErrorMsg('missingToken')]];
    } else if($vData['password'] != $vData['confirmPassword']){
      return ['error'=>['msg'=>$this->_getErrorMsg('misMatchPassword')]];
    }
    unset($vData['confirmPassword']);
    
    
    $updateData = [
      T::$account=>[
        'whereData'=>['account_id'=>$rAccount['account_id']], 
        'updateData'=>['password'=>bcrypt($vData['password'])]
      ], 
      T::$accountPasswordReset=>[
        'whereData'=>['accountPasswordReset_id'=>$r['accountPasswordReset_id']], 
        'updateData'=>['active'=>0]
      ]
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      # SECCESS SECTION 
//      $success[T::$account] = Model::update(
//        T::$account, 
//        ['account_id'=>$rAccount['account_id']], 
//        [['password'=>bcrypt($vData['password']), 'account_id'=>$rAccount['account_id']]]
//      );
//      $success[T::$accountPasswordReset] = Model::update(
//        T::$accountPasswordReset, 
//        ['accountPasswordReset_id'=>$r['accountPasswordReset_id']], 
//        [['active'=>0, 'accountPasswordReset_id'=>$r['accountPasswordReset_id']]]
//      );
      $success += Model::update($updateData);

      # RESPONSE SECTION
      $response['mainMsg'] = $this->_getSuccessMsg('update');
      
      Model::commit([
        'success' =>$success,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $response = ['error'=>['msg'=>self::_getErrorMsg('emailNotExist')]];
    $valid = V::startValidate([
      'rawReq'          =>$req->all(),
      'tablez'          =>self::_getTable('index'), 
      'orderField'      =>self::_getOrderField('index'),
      'setting'         =>self::_getSetting('index'),
      'validateDatabase'=>[
        'mustExist'=>[
          'account|email',
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    $r = DB::table(T::$account)->where(Model::buildWhere(['email'=>$vData['email'], 'active'=>1]))->first();
    if(!empty($r)){
      $token = bcrypt($vData['email']);
      $href = env('APP_URL') . '/passwordReset/reset/edit?token=' . urlencode($token);
      if(DB::table(T::$accountPasswordReset)->insert(['token'=>$token, 'email'=>$vData['email']])){
        Mail::send([
          'to'=>$vData['email'], 
          'from'=>'ryan@pamamgt.com,admin@pamamgt.com',
          'subject' =>'Recover Password On Dataworkers',
          'msg'=>$this->_getRecoverMsg($href)
        ]);
        $response = ['msg'=>$this->_getSuccessMsg('store')];
      }
    } else{
      $response = ['msg'=>$this->_getErrorMsg('store')];
    }
    return $response;
  } 
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'index' =>[T::$account],
      'edit'  =>[T::$account, T::$accountPasswordReset]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['email'],
      'editValidate' =>['token'],
      'editForm' =>['token', 'password', 'confirmPassword'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $setting = [
      'index'=>[
        'rule'=>[
          'email'   =>'required|string',
        ]
      ],
      'edit'=>[
        'field'=>[
          'token'=>['type'=>'hidden'],
          'password'=>['type'=>'New Password', 'type'=>'password'],
          'confirmPassword'=>['label'=>'Confirm New Password', 'type'=>'password'],
        ],
        'rule'=>[
          'password'=>'required|string|between:8,255',
        ]
      ]
    ];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getCopyField($fn){
    $copyField = [
      'edit'=>['password'=>'confirmPassword'],
    ];
    return $copyField[$fn];
  }  
//------------------------------------------------------------------------------
  private function _getButton($fn){
    $buttton = [
      'index'=>['submit'=>['id'=>'submit', 'value'=>'Reset']],
      'edit'=>['submit'=>['id'=>'submit', 'value'=>'Reset']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################     HELPER FUNCTION   ###############################  
################################################################################
  private function _getErrorMsg($name){
    $data = [
      'emailNotExist'   =>Html::errMsg('The email does not exist.'),
      'missingToken'    =>Html::mysqlError(),
      'misMatchPassword'=>Html::errMsg('Your password does not match with confirm password.'), 
      'edit'            =>Html::errMsg('The link is expired. Please reset it again.'),
      'store'           =>Html::errMsg('The email does not exist.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'=>Html::sucMsg('Your password is successfully reset. ' . Html::a('Login' , ['href'=>'/login']) ),
      'store' =>Html::sucMsg('An email with instruction is sent to your register email. Please follow instruction in the email to reset your password.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getRecoverMsg($href){
    return 'Recover Password on Dataworkers' . Html::br(2) . 
      'Please click on this ' . Html::a('link', ['href'=>$href , 'target'=>'_blank']) . ' to recover your password.' . Html::br(2).
      'This message will never be sent to any other email address than the one you registered in your account. Please rest assured that your personal information is secure with us.';
  }
}