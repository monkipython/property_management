<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper};
use App\Http\Models\Model; // Include the models class

class AccountController extends Controller{
  private $_viewPath  = 'app/Account/account/';
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping   = [];
  public function __construct(Request $req){
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$account]);
    $this->_viewTable = T::$accountView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $op   = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . 'index';
    $initData = $this->_getColumnButtonReportList($req);
    switch ($op){
      case 'column':
        return $initData;
      case 'show':
        $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
        $vData['defaultFilter'] = ['active'=>1];
        $qData = GridData::getQuery($vData, $this->_viewTable);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData); 
      default:
        return view($page, ['data'=>[
          'nav'=>$req['NAV'],
          'account'=>Account::getHtmlInfo($req['ACCOUNT']),
          'initData'=>$initData
        ]]);
    }
  }
//------------------------------------------------------------------------------
  public function edit($id){
    $form = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'button'    =>self::_getButton(__FUNCTION__), 
      'orderField'=>self::_getOrderField(__FUNCTION__), 
      'setting'   =>self::_getSetting(__FUNCTION__),
      'copyField' =>self::_getCopyField(__FUNCTION__),
    ]);
    return view($this->_viewPath . 'register', ['data'=>['form'=>$form]]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['account_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => self::_getTable('edit'),
      'includeCdate'=>0,
      'validateDatabase'=>[
        'mustExist'=>[
          'account|account_id',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $key   = key($vData);
    if($key == 'isLocked'){
      $vData['loginAttempt'] = $vData[$key] ? 100 : 0;
    }
    $updateData = [
      T::$account=>[
        'whereData'=>['account_id'=>$id],
        'updateData'=>$vData,
      ]
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[T::$accountView=>['account_id'=>[$id]]]
      ];
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
 public function destroy($id){
  $updateData = [
    T::$account=>[
      'whereData'=>['account_id'=>$id], 
      'updateData'=>['active'=>0]
    ], 
  ];
  ############### DATABASE SECTION ######################
  DB::beginTransaction();
  $success = $response = $elastic = [];
  try{
    $success += Model::update($updateData);
    $elastic = [
      'insert'=>[
        T::$accountView=>['account_id'=>[$id]]
      ]
    ];
    $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);

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
      'create' =>['account'],
      'show'   =>['account'],
      'edit'   =>['account', 'accountRole'],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'create' =>['role', 'permission'],
      'show'   =>['permission'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $setting = [
      'create'=>[
        'field'=>[
          'permission' =>['type'=>'hidden'],
          'role'       =>['label'=>'New Role'],
        ]
      ],
      'show'=>[
        'rule'=>[
          'permission' =>'required|string|between:0,65000',
        ]
      ],
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
      'create'=>['submit'=>['id'=>'submit', 'value'=>'Submit']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData){
    $rows = [];
    $actionData = [
      '<a class="permission" href="javascript:void(0)"><i class="fa fa-lock text-aqua pointer tip" title="Set Up Permission"></i></a>',
      '<a class="delete" href="javascript:void(0)"><i class="fa fa-trash-o text-red pointer tip" title="Delete This Account"></i></a>'
    ];
      
    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source']; 
      $id     =  $source['account_id'];
      $source['num'] = $vData['offset'] + $i + 1;
      $source['action'] = implode(' | ', $actionData);
      $source['permission'] = 'Permission';
      $source['office']     = title_case($source['office']);
      $source['firstname']  = title_case($source['firstname']);
      $source['middlename'] = title_case($source['middlename']);
      $source['lastname']   = title_case($source['lastname']);
      $source['role']       = title_case($source['role']);
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }  
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req = []){
    ### BUTTON SECTION ###
    $_getCreateButton = function(){
      $button =  Html::span(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=>'new', 'class'=>'btn btn-success']);
      return Html::a($button, ['href'=>'/register', 'style'=>'color:#fff;']);
    };
    
    $data = [
      ['field'=>'num', 'title'=>'#', 'width'=> 40],
      ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> 50],
      ['field'=>'email',  'title'=>'Email', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Email','editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 200],
      ['field'=>'role', 'title'=>'Role', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Role', 'sortable'=> true, 'width'=> 75],
      ['field'=>'firstname', 'title'=>'First Name', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'First Name', 'editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 75],
      ['field'=>'middlename', 'title'=>'Middle Name', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Middle Name', 'editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 75],
      ['field'=>'lastname',  'title'=>'Last Name', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Last Name', 'editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 75],
      ['field'=>'phone',  'title'=>'Work Phone', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Work Phone', 'editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 75],
      ['field'=>'ext',  'title'=>'Extension', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Extension', 'editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 75],
      ['field'=>'cellphone',  'title'=>'Cell Phone', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Extension', 'editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 75],
      ['field'=>'office',  'title'=>'Office', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'office', 'editable'=> ['type'=> 'select', 'source'=>$this->_mapping['office']], 'sortable'=> true, 'width'=> 150],
      ['field'=>'education',  'title'=>'Education', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Edication', 'editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 75],
      ['field'=>'isLocked','title'=>'Locked?','filterControl'=>'input','filterControlPlaceholder'=>'Locked', 'editable'=> ['type'=> 'select', 'source'=>Helper::convertGridSelect($this->_mapping['isLocked'])],'sortable'=> true, 'width'=> 50],
      ['field'=>'ownGroup',  'title'=>'Group Own', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Last Name', 'editable'=> ['type'=> 'textarea'], 'sortable'=> true],
      ['field'=>'accessGroup',  'title'=>'Group Access', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Last Name', 'editable'=> ['type'=> 'textarea'], 'sortable'=> true],
    ];
    return ['columns'=>$data,  'button'=>$_getCreateButton()]; 
    
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getErrorMsg($name){
    $data = [
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $info = ''){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'destroy' =>Html::sucMsg('Successfully Delete The Account.'),
    ];
    return $data[$name];
  }
}