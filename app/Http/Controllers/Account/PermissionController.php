<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, Helper, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\AccountModel AS M; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class

class PermissionController extends Controller{
  private $_viewPath   = 'app/Account/permission/';
  private $_location   = [];
  private $_viewTable  = '';
  private $_indexMain  = '';
  
  public function __construct(Request $req){
//    $this->_location = File::getLocation(__CLASS__);
//    $this->_emailList = Mail::emailList(__CLASS__);
    
    $this->_viewTable       = T::$accountView;
    $this->_indexMain       = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => self::_getTable(__FUNCTION__),
      'orderField'  => self::_getOrderField(__FUNCTION__),
      'setting'     => self::_getSetting(__FUNCTION__)
    ]);
    $vData = $valid['dataNonArr'];
    $accountRoleId = $vData['accountRole_id'];
    $rAccountRole = M::getAccountRole(Model::buildWhere(['accountRole_id'=>$accountRoleId]), 1);
    return ['rolePermission'=> json_decode($rAccountRole['rolePermission'], true)];
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => self::_getTable(__FUNCTION__),
      'orderField'  => self::_getOrderField(__FUNCTION__),
      'includeCdate'=>0,
      'setting'     => self::_getSetting(__FUNCTION__)
    ]);
    $permission = [];
    $vData = $valid['dataNonArr'];
    if(!empty($vData['rolePermission'])){
      $rolePermissionPieces = explode('_',$vData['rolePermission']);
      foreach($rolePermissionPieces as $v){
        $p = explode('-', $v);
        $permission[$p[0]] = $p[1];
      }
    }
    
//    dd($permission);
    $accountRoleId  = $vData['accountRole_id'];
    $rProgram       = M::getProgram(Model::buildWhere(['module'=>$id]));
    $rPermission    = Helper::keyFieldName(M::getPermission(Model::buildWhere(['ap.account_id'=>$vData['account_id']])), 'accountProgram_id', 'permission');
    $rAccountRole   = M::getAccount(Model::buildWhere(['a.account_id'=>$vData['account_id']]));
    
    if(empty($accountRoleId)){
      if(!empty($rAccountRole[0]['rolePermission'])){
        $rolePermission = json_decode($rAccountRole[0]['rolePermission'], true);
        foreach($rolePermission as $accountProgramId=>$v){
          $permission[$accountProgramId] = !isset($permission[$accountProgramId]) ? $v : $permission[$accountProgramId];
        }
      } else{
        // Override all the permission in the database with the current select from the front end
        foreach($rPermission as $accountProgramId=>$v){
          $v = $v ? true : false;
          $permission[$accountProgramId] = !isset($permission[$accountProgramId]) ? $v : $permission[$accountProgramId];
        }
      }
    } else{
    }
    
    $program = [];
    $tableHtml = '';
    # SORT MULTIPLE ARRAY
    $rProgram = array_values(array_sort($rProgram, function ($value) {
      return $value['section'];
    }));

    foreach($rProgram as $v){
      $program[$v['section']][] = $v;
    }
    
    foreach($program  as $section=>$val){
      $section = Html::span('', ['class'=>$val[0]['sectionIcon']]) . ' ' . Html::u(strtoupper($section . ' section'));
      $tableData = [
        ['input'=>['val'=>$section, 'param'=>['colspan'=>2]]]
      ];
      
      # SORT MULTIPLE ARRAY
      $val = array_values(array_sort($val, function ($value) {
        return $value['programDescription'];
      }));

      foreach($val as $i=>$v){
        $check = !empty($permission[$v['accountProgram_id']]) ? ['checked'=>'checked'] : [];
        $tableData[] = [
          'input'=>[
            'val'=>Html::input('', $check + ['type'=>'checkbox', 'class'=>'checkboxToggle', 'data-id'=>$v['accountProgram_id']]),
            'param'=>['width'=>'30']
          ],
          'programDescription'=>['val'=>$v['programDescription']]
        ];
      }
      $tableHtml .= Html::buildTable([
        'data'=>$tableData, 
        'tableParam'=>['class'=>'table table-hover'], 
        'isHeader'=>0, 
        'isOrderList'=>0,
        'isAlterColor'=>0,
      ]);
    }
    return $tableHtml;
    
//    foreach($rProgram as $i=>$v){
//      $check = !empty($permission[$v['accountProgram_id']]) ? ['checked'=>'checked'] : [];
//      $tableData[] = [
//        'input'=>[
//          'val'=>Html::input('', $check + ['type'=>'checkbox', 'class'=>'checkboxToggle', 'data-id'=>$v['accountProgram_id']]),
//          'param'=>['width'=>'30']
//        ],
//        'programDescription'=>['val'=>$v['programDescription']]
//      ];
//    }
//    
//    return Html::createTable($tableData, ['class'=>'table table-hover table-striped'], 0);
  }
//------------------------------------------------------------------------------
  public function edit($id){
    $program = [];
    $page    = $this->_viewPath . __FUNCTION__;
    $rProgram      = M::getProgram();
    $rAccount      = M::getAccount([['a.account_id', '=', $id]]);
    $rPermission   = Helper::keyFieldName(M::getPermission(Model::buildWhere(['ap.account_id'=>$id])), 'accountProgram_id', 'permission');
    $accountRoleId = !empty($rAccount[0]['accountRole_id']) ? $rAccount[0]['accountRole_id'] : 0;
    foreach($rProgram as $v){
      $program[$v['category']][$v['module']] = $v;
    }
    foreach($rPermission as $i=>$v){
      $rPermission[$i] = $v ? true : false;
    }
    $form = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'orderField'=>self::_getOrderField(__FUNCTION__), 
      'setting'   =>self::_getSetting(__FUNCTION__, ['accountRole_id'=>$accountRoleId, 'account_id'=>$id]),
    ]);
//    dd($form);
    return [
      'rolePermission'=> !empty($rPermission) ? $rPermission : '', // We use '' because in js it will convert to [null] if we use []
      'html'=>view($page, ['data'=>[
        'permissionList' =>self::_getPermissionList($program),
        'permissionTable'=>self::_getPermission(),
        'form'=>$form
      ]])->render()
    ];
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $insertDataSet = $updateDataSet = [];
    $_getAccPermissionData = function($permission, $vData){
      $insertData = $updateData = [];
      if(!empty($permission)){
        foreach($permission as $accountProgramId=>$perm){
          $accountId = $vData['account_id'];
          $r = M::getAccountPermission(Model::buildWhere(['ap.account_id'=>$accountId, 'ap.accountProgram_id'=>$accountProgramId]), 1);
          if(!empty($r)){
            $updateData[$r['accountPermission_id']] = ['permission'=>$perm, 'accountPermission_id'=>$r['accountPermission_id']];
          } else{
            $insertData[] = [
              'account_id'=>$vData['account_id'],
              'accountProgram_id'=>$accountProgramId,
              'permission'=>$perm,
              'cdate'=>$vData['cdate'],
            ];
          }
        }
      }
      return ['insert'=>$insertData, 'update'=>$updateData];
    };

    # VALIDATE DATA
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => self::_getTable(__FUNCTION__),
      'setting'     => self::_getSetting(__FUNCTION__),
      'validateDatabase'=>[
        'mustExist'=>[
          'account|account_id',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $accountRoleId = $vData['accountRole_id'];
    $accountId = $vData['account_id'];
    
    # BUILD INSERT DATA
    if(empty($vData['accountRole_id'])){
      $permission = json_decode($vData['rolePermission'], true);
    } else {
      $rRole = M::getAccountRole([['accountRole_id', '=', $accountRoleId]], 1);
      $permission = json_decode($rRole['rolePermission'], true);
    }
    # BULID UPDATE DATA
    $dataset = $_getAccPermissionData($permission, $vData);
    $insertAccPermission = $dataset['insert'];
    $updateAccPermission = $dataset['update'];
    $updateAcc = ['accountRole_id'=>$accountRoleId, 'account_id'=>$accountId];

    // if there is any record, Set all the permission to false first before doing anything else
    $updateDataSet[T::$account] = ['whereData'=>['account_id'=>$accountId], 'updateData'=>$updateAcc];
    if(!empty(M::getPermission(Model::buildWhere(['account_id'=>$accountId])))){
      $updateDataSet[T::$accountPermission][] = [
        'whereData'=>['account_id'=>$accountId],
        'updateData'=>['permission'=>0]
      ]; 
    } 
    if(!empty($insertAccPermission)){
      $insertDataSet[T::$accountPermission] = $insertAccPermission;
    }
    if(!empty($updateAccPermission)){
      foreach($updateAccPermission as $id=>$v){
        $updateDataSet[T::$accountPermission][] = [
          'whereData'=>['accountPermission_id'=>$id],
          'updateData'=>$v
        ]; 
      }
    }
//    dd('sdfh');
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateDataSet);
      if(!empty($insertDataSet)){
        $success += Model::insert($insertDataSet);
      }
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      $elastic = [
        'insert'=>[T::$accountView=>['a.account_id'=>[$accountId]]]
      ];
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    sleep(1);
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'index'  =>['accountRole'],
      'show'   =>['account', 'accountRole'],
      'edit'   =>['account', 'accountRole'],
      'update' =>['account', 'accountRole', 'accountPermission'],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['accountRole_id'],
      'show'  =>['rolePermission', 'account_id', 'accountRole_id'],
      'edit'  =>['accountRole_id', 'rolePermission', 'account_id']
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $optionRole = OptionFilter::getInstance()->getOptionFilterDB(T::$accountRoleView, 'accountRole_id', 'accountRole_id', 'role');
    $optionRole[0] = 'Customize';
    $setting = [
      'index'=>[
        'rule'=>[
          'accountRole_id'=>'required|integer'
        ]
      ],
      'show'=>[
        'rule'=>[
          'rolePermission' =>'nullable|string|between:0,65000',
          'accountRole_id' =>'required|integer|between:0,65000'
        ]
      ],
      'edit'=>[
        'field'=>[
          'accountRole_id'=>['label'=>'Assign Role', 'type'=>'option', 'option'=>$optionRole],
          'rolePermission'=>['type'=>'hidden'],
          'account_id'=>['type'=>'hidden'],
        ]
      ],
      'update'=>[
        'rule'=>[
          'accountRole_id' =>'required|string|between:0,65000',
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
      'edit'=>['submit'=>['id'=>'submit', 'value'=>'Update']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData){
    $rows = [];
    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source']; 
      $id     =  $source['account_id'];
      $source['num'] = $vData['offset'] + $i + 1;
      $source['rolePermission'] = 'Permission';
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getPermissionList($program){
    $html = '';
    foreach($program as $category=>$val){
      $categoryIcon = reset($val)['categoryIcon'];
      
      $i  = Html::i('', ['class'=>$categoryIcon]);
      $h3 = Html::h3($i . ' ' . $category, ['class'=>'box-title text-light-blue']);
      $i  = Html::i('', ['class'=>'fa fa-plus']);
      $button = Html::button($i, ['type'=>'button', 'class'=>'btn btn-box-tool', 'data-widget'=>'collapse']);
      $div    = Html::div($button, ['class'=>'box-tools']);
      $header = Html::div($h3 . $div, ['class'=>'box-header with-border']);
      
      foreach($val as $module=>$v){
        $moduleDescription = $v['moduleDescription'];
        $i = Html::i('', ['class'=>$v['moduleIcon']]) . ' ';
        $val[$module] = ['value'=>Html::a($i . $moduleDescription), 'param'=>['data-id'=>$module, 'class'=>'pointer eachPage']];
      }
      
      $ul     = Html::buildUl($val, ['class'=>'nav nav-pills nav-stacked']);
      $body   = Html::div($ul, ['class'=>'box-body no-padding']);
      
      $html .= Html::div($header . $body , ['class'=>'box box-solid no-margin']);
    }
    return $html;
  }
//------------------------------------------------------------------------------
  private function _getPermission(){
    Html::div('', ['class'=>'table-responsive mailbox-messages']);
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name, $info = ''){
    $data = [
      'update'     =>Html::sucMsg('Successfully Update.'),
    ];
    return $data[$name];
  }  
}