<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{Elastic, Form, Html, Helper, V, HelperMysql, TableName AS T};
use App\Http\Models\{Model,MortgageModel AS M}; // Include the models class
use Illuminate\Support\Facades\DB;

class TenantLoginController extends Controller {
  private $_viewPath      = 'app/Account/tenantLogin/';
  private $_redirectRoute = '/signAgreement/';
  private $_uid           = '';
  private $_sid           = '';
  
  public function __construct(){
    $this->_uid      = env('COOKIE_UID');
    $this->_sid      = env('COOKIE_SID');
  }  

//------------------------------------------------------------------------------
  public function index(Request $req){
    $page  = $this->_viewPath . 'index';
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__),
    ]);
    return view($page, ['data'=>['form'=>$form]]);
  }
//------------------------------------------------------------------------------
  public function update($id,Request $req){    
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      'orderField'   => $this->_getOrderField(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__),
      'includeCdate' => 0,
    ]);
    
    $vData = $valid['data'];
    $r     = Helper::getValue('_source',Helper::getElasticResult(Elastic::searchQuery([
      'index'       => T::$creditCheckView,
      '_source'     => ['application_id',T::$application . '.social_security',T::$application . '.fname',T::$application .'.lname'],
      'query'       => [
        'raw'       => [
          'must'  => [
            [
              'exists' => ['field'=>T::$application],
            ],
            [
              'match'  => [T::$application . '.fname'           => strtolower($vData['fname'])]
            ],
            [
              'match'  => [T::$application . '.lname'           => strtolower($vData['lname'])]
            ],
            [
              'regexp' => [T::$application . '.social_security' => '(.+)*' . substr($vData['social_security'],-4)]
            ],
            [
              'term'   => ['application_status.keyword'=>'Approved'],
            ]
          ]
        ]
      ]
    ]),1));
    
    $redirect = action('CreditCheck\Agreement\SignAgreementController@edit',Helper::getValue('application_id',$r,0)) . '?' . http_build_query($vData) ;
    if(!empty($r)){
      return response(['link'=>$redirect]);
    } else {
      return response(['error'=>['mainMsg'=>$this->_errorMsg('accountNotExist')]]);
    }
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################ 
  private function _getTable($fn){
    $tablez = [
      'index'     =>[T::$application,T::$applicationInfo],
      'update'    =>[T::$application,T::$applicationInfo],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['social_security','fname','lname'],
      'update'=>['social_security','fname','lname']
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn){
    $setting = [
      'index'=>[
        'field'=>[
          'social_security'   =>['label'=>'Last 4 Digits of SSN','placeholder'=>'Last 4 Digits of SSN','type'=>'password'],
          'fname'             =>['label'=>'First Name','placeholder'=>'First Name'],
          'lname'             =>['label'=>'Last Name','placeholder'=>'Last Name'],
          'password'          =>['type'=>'password']
        ],
        'rule'=>[
          'social_security' => 'required|string|between:4,4',
          'fname'           => 'required|string',
          'lname'           => 'required|string',
        ]
      ],
    ];
    $setting['update'] = $setting['index'];
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn){
    $buttton = [
      'index'=>['submit'=>['id'=>'submit', 'value'=>'Find Application']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################     HELPER FUNCTION   ###############################  
################################################################################
  private function _errorMsg($name){
    $data = [
      'cannotLogin'      =>Html::errMsg('SSN and Name(s) do not match'),
      'accountNotExist'  =>Html::errMsg('Your Rental Application does not exist or is not approved.'),
    ];
    return $data[$name];
  }
}
