<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, TableName AS T, Account};
use App\Http\Models\Model; // Include the models class

class ProfileController extends Controller{
  private $_viewPath = 'app/Account/profile/';
  private $_samePassword = 'thesamepasswordOld123';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $account = $req['ACCOUNT'];
    $account['password'] = $account['confirmPassword'] = $this->_samePassword;
    $page = $this->_viewPath . 'index';
    $form    = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'button'    =>self::_getButton(__FUNCTION__), 
      'orderField'=>self::_getOrderField(__FUNCTION__), 
      'setting'   =>self::_getSetting(__FUNCTION__, $account),
      'copyField' =>self::_getCopyField(__FUNCTION__),
    ]);
    
    $data = [
      'name'      => title_case($account['firstname'] . ' ' . $account['lastname']),
      'occupation'=>$account['occupation'],
      'education' =>$account['education'],
      'location'  =>$account['location'],
      'note'      =>$account['note'],
      'form'      =>$form,
      'skill'     =>Account::getHtmlSkill($account['skill']),
      'account'   =>Account::getHtmlInfo($account),
      'nav'       =>$req['NAV'],
      'account'   =>Account::getHtmlInfo($req['ACCOUNT'])
    ];
    return view($page, ['data'=>$data]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $valid = V::startValidate([
      'rawReq'          =>$req->all(),
      'tablez'          =>self::_getTable('index'), 
      'orderField'      =>self::_getOrderField('index'),
      'copyField'       =>self::_getCopyField('index'),
      'includeCdat'     =>0,
      'setting'         =>self::_getSetting('index'),
      'validateDatabase'=>[
        'mustExist'=>[
          'account|account_id',
        ]
      ]
    ]);
    
    $vData   = $valid['dataNonArr'];
    $accountId = $vData['account_id'];
    if($vData['password'] == $this->_samePassword){
      unset($vData['password']);
    } else{ 
      if($vData['password'] != $vData['confirmPassword']){
        $response['error']['confirmPassword'] = 'Your password does not match with confirm password.';
        return $response;
      }
    }
    unset($vData['confirmPassword']); // Regardless we don't need confirmPassword
    
    if(!empty($vData['password'])){
      $vData['password'] = bcrypt($vData['password']);
    }
    $updateData = [
      T::$account=>[
        'whereData'=>['account_id'=>$accountId], 
        'updateData'=>$vData
      ], 
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
//      $success[T::$account] = Model::update(T::$account, ['account_id'=>$accountId], $vData);
//      $success[$accountId] = $accountId;
      $success += Model::update($updateData);
      $response['id'] = $accountId;
      $response['mainMsg'] = $this->_getSuccessMsg('update');
      $elastic = [
        'insert'=>[
          T::$accountView=>['account_id'=>[$accountId]]
        ]
      ];
      
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
      'index'     =>['account'],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['account_id', 'email', 'password', 'confirmPassword', 'firstname', 'middlename', 'lastname', 'cellphone', 'phone', 'ext'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $setting = [
      'index'=>[
        'field'=>[
          'account_id'=>['type'=>'hidden'],
          'password'=>['type'=>'password'],
          'confirmPassword'=>['label'=>'Confirm Password', 'type'=>'password'],
          'firstname'=>['label'=>'First Name'],
          'middlename'=>['label'=>'Middle Name'],
          'lastname'=>['label'=>'Last Name'],
          'phone'=>['label'=>'Work Phone'],
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
  private function _getButton($fn){
    $buttton = [
      'index'=>['submit'=>['id'=>'submit', 'value'=>'Update']],
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
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getErrorMsg($name){
    $data = [];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $info = ''){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
    ];
    return $data[$name];
  }
}