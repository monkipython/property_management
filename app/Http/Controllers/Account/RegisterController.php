<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, TableName AS T, Helper};
use App\Http\Models\Model; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class

class RegisterController extends Controller{
  private $_viewPath = 'app/Account/register/';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $page = $this->_viewPath . __FUNCTION__;
    $form = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'button'    =>self::_getButton(__FUNCTION__), 
      'orderField'=>self::_getOrderField(__FUNCTION__), 
      'setting'   =>self::_getSetting(__FUNCTION__),
      'copyField' =>self::_getCopyField(__FUNCTION__),
    ]);
    return view($page, ['data'=>['form'=>$form]]);
  }
//------------------------------------------------------------------------------
  #NOTE: this is used for admin only to call the ajax and create user for new user
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
    $form = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'button'    =>self::_getButton(__FUNCTION__), 
      'orderField'=>self::_getOrderField(__FUNCTION__), 
      'setting'   =>self::_getSetting(__FUNCTION__),
      'copyField' =>self::_getCopyField(__FUNCTION__),
    ]);
    return view($page, ['data'=>['form'=>$form]]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          =>$req->all(),
      'tablez'          =>self::_getTable('index'), 
      'orderField'      =>self::_getOrderField('index'),
      'copyField'       =>self::_getCopyField('index'),
      'setting'         =>self::_getSetting('index'),
      'validateDatabase'=>[
        'mustNotExist'=>[
          'account|email',
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    if($vData['password'] != $vData['confirmPassword']){
      $response['error']['confirmPassword'] = 'Your password does not match with confirm password.';
      return $response;
    }
    unset($vData['confirmPassword']);
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $vData['password'] = bcrypt($vData['password']);
      $insertDataSet = [T::$account=>$vData];
      $success += Model::insert($insertDataSet);
      # RESPONSE SECTION
      $accountId = $success['insert:account'][0];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      
      # ELASTIC SEARCH SECTION
      $elastic =['insert'=>[T::$accountView=>['account_id'=>[$accountId]]]];
      
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }  
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'index'     =>['account', 'accountRole'],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['email', 'password', 'confirmPassword', 'firstname', 'middlename', 'lastname', 'cellphone', 'phone', 'ext', 'office'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn){
    $optionRole = OptionFilter::getInstance()->getOptionFilterDB(T::$accountRoleView, 'role', 'accountRole_id', 'role');
    $optionRole[0] = 'Customize';
    $office = Helper::getMapping(['tableName'=>'account'])['office'];
    ksort($office);
    
    $setting = [
      'index'=>[
        'field'=>[
          'password'=>['type'=>'password'],
          'confirmPassword'=>['label'=>'Confirm Password', 'type'=>'password'],
          'firstname'=>['label'=>'First Name'],
          'middlename'=>['label'=>'Middle Name'],
          'lastname'=>['label'=>'Last Name'],
          'phone'=>['label'=>'Work Phone'],
          'office'=>['label'=>'Work Office', 'type'=>'option', 'option'=>$office],
        ],
        'rule'=>[
          'password'=>'required|string|between:8,255',
          'accountRole_id'=>'required|integer|between:0,1000'
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
//------------------------------------------------------------------------------
  private function _getCopyField($fn){
    $copyField = [
      'index'=>['password'=>'confirmPassword'],
    ];
    return $copyField[$fn];
  }
#######################################
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getErrorMsg($name){
    $data = [];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store'=>Html::sucMsg('Welcome to Dataworkers.<br>' . Html::a('Click here to login.', ['href'=>'/login']))
    ];
    return $data[$name];
  }
}