<?php
namespace App\Http\Controllers\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\AccountModel AS M; // Include the models class

class RoleController extends Controller{
  private $_viewPath        = 'app/Account/role/';
  private $_viewTable       = '';
  private $_indexMain       = '';
  
  public function __construct(Request $req){
    $this->_viewTable = T::$accountRoleView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $op   = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . __FUNCTION__;
    switch ($op){
      case 'column':
        return $this->_getColumn();
      case 'show':
        $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
        $vData['defaultFilter'] = ['active'=>1];
        $qData = GridData::getQuery($vData, $this->_viewTable);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData); 
      default:
        return view($page, ['data'=>[
          'nav'=>$req['NAV'],
          'account'=>Account::getHtmlInfo($req['ACCOUNT'])
        ]]);
    }
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => self::_getTable(__FUNCTION__),
      'selectField' => self::_getOrderField(__FUNCTION__),
      'includeCdate'=>0,
      'setting'     => self::_getSetting(__FUNCTION__)
    ]);
    $vData = $valid['dataNonArr'];
    $permission     = json_decode($vData['rolePermission'], true);
    $rProgram       = M::getProgram(Model::buildWhere(['module'=>$id]));
    
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
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $program = [];
    $page    = $this->_viewPath . __FUNCTION__;
    $rProgram      = M::getProgram();
    $rAccountRole  = M::getAccountRole(Model::buildWhere(['accountRole_id' => $id]), 1);
    $rPermission   = !empty($rAccountRole['rolePermission']) ? json_decode($rAccountRole['rolePermission'], true) : [];
    foreach($rProgram as $v){
      $program[$v['category']][$v['module']] = $v;
    }
    foreach($rPermission as $i=>$v){
      $rPermission[$i] = $v ? true : false;
    }
    $form = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'orderField'=>self::_getOrderField(__FUNCTION__), 
      'setting'   =>self::_getSetting(__FUNCTION__, ['accountRole_id'=>$rAccountRole['accountRole_id'], 'role'=>$rAccountRole['role']]),
    ]);
    return [
      'rolePermission'=> !empty($rPermission) ? $rPermission : '', // We use '' because in js it will convert to [null] if we use []
      'html'=>view($page, ['data'=>[
        'permissionList'=>self::_getPermissionList($program),
        'permissionTable'=>self::_getPermission(),
        'form'=>$form
      ]])->render()
    ];
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['accountRole_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => self::_getTable('edit'),
      'includeCdate'=>0,
      'validateDatabase'=>[
        'mustExist'=>[
          'accountRole|accountRole_id',
        ]
      ]
    ]);
    
    $r = M::getPermissionByAccountId(Model::buildWhere(['accountRole_id'=>$id]));
    $rAcctId = array_values(Helper::keyFieldName($r, 'account_id', 'account_id'));
    $rAccountProgramId = Helper::keyFieldName($r, 'accountProgram_id', 'permission');
    $vData = $valid['dataNonArr'];
    $permission = json_decode($vData['rolePermission'],true);
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $updateDataSet = $insertData = $success = $response = [];
    try{
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      if(!empty($permission)){// Nothing to change
        DB::table(T::$accountPermission)->whereIn('account_id', $rAcctId)->update(['permission'=>0]);
        
        foreach($rAcctId as $accountId){
          foreach($permission as $accountProgramId=>$v){
            if(isset($rAccountProgramId[$accountProgramId])){
              $updateDataSet[T::$accountPermission][] = [
                'whereData'=>['account_id'=>$accountId, 'accountProgram_id'=>$accountProgramId],
                'updateData'=>['permission'=>$v]
              ];
            } else{
              $insertData[T::$accountPermission][] = ['account_id'=>$accountId, 'accountProgram_id'=>$accountProgramId,'permission'=>1];
            }
          }
        }

        if(!empty($insertData)){
          $success += Model::Insert($insertData); 
        }
      } else{
        unset($vData['rolePermission']);
      }
      
      $updateDataSet[T::$accountRole] = [
        'whereData'=>['accountRole_id'=>$id],
        'updateData'=>$vData
      ];
      $success += Model::update($updateDataSet);
      
      $elastic = ['insert'=>[T::$accountRoleView=>['accountRole_id'=>[$id]]]];
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);

    } catch(\Exception $e){
      $response['error']['msg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $program = [];
    $page    = $this->_viewPath . __FUNCTION__;
    $rProgram      = M::getProgram();
    
    foreach($rProgram as $v){
      $program[$v['category']][$v['module']] = $v;
    }
    $form = Form::generateForm([
      'tablez'    =>self::_getTable(__FUNCTION__), 
      'orderField'=>self::_getOrderField(__FUNCTION__), 
      'setting'   =>self::_getSetting(__FUNCTION__),
    ]);
    return view($page, ['data'=>[
      'permissionList'=>self::_getPermissionList($program),
      'permissionTable'=>self::_getPermission(),
      'form'=>$form
    ]])->render();
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => self::_getTable('create'),
      'selectField'     => self::_getOrderField('create'),
      'validateDatabase'=>[
        'mustNotExist'=>[
          'accountRole|role',
        ]
      ]
    ]);
    $vData   = $valid['dataNonArr'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $insetDataSet = [T::$accountRole=>$vData];
      $success += Model::insert($insetDataSet);
      $accountRoleId = $success['insert:' . T::$accountRole];
      $elastic = [
        'insert'=>[$this->_viewTable=>['accountRole_id'=>$accountRoleId]]
      ];
      
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['msg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function destroy($id){
    $updateData = [
      T::$accountRole=>[
        'whereData'=>['accountRole_id'=>$id], 
        'updateData'=>['active'=>0]
      ], 
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
//      $success[T::$accountRole] = Model::update(T::$accountRole, ['accountRole_id'=>$id], [['active'=>0, 'accountRole_id'=>$id]]);
      $success += Model::update($updateData);
      $elastic = ['insert'=>[T::$accountRoleView=>['a.accountRole_id'=>[$id]]]];
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
      'create' =>['account', 'accountRole'],
      'show'   =>['accountRole'],
      'edit'   =>['accountRole'],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'edit'   =>['role', 'accountRole_id', 'rolePermission'],
      'create' =>['role', 'rolePermission'],
      'show'   =>['rolePermission'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $setting = [
      'create'=>[
        'field'=>[
          'rolePermission' =>['type'=>'hidden'],
          'role'       =>['label'=>'New Role'],
        ]
      ],
      'edit'=>[
        'field'=>[
          'accountRole_id' =>['type'=>'hidden'],
          'role'           =>['label'=>'Role'],
          'rolePermission' =>['type'=>'hidden'],
        ]
      ],
      'show'=>[
        'rule'=>[
          'rolePermission' =>'required|string|between:0,65000',
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
    $buttton = [];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData){
    $rows = [];
    $actionData = [
      '<a class="edit" href="javascript:void(0)"><i class="fa fa-edit text-aqua pointer tip" title="Edit Role"></i></a>',
      '<a class="delete" href="javascript:void(0)"><i class="fa fa-trash-o text-red pointer tip"  title="Delete Role"></i></a>'
    ];
    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source']; 
      $id     =  $source['accountRole_id'];
      $source['num'] = $vData['offset'] + $i + 1;
      $source['action'] = implode(' | ', $actionData);
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumn(){
    return [
      ['field'=>'num', 'title'=>'#', 'width'=> 50],
      ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> 50],
      ['field'=>'role', 'title'=>'Role', 'filterControl'=> 'input', 'filterControlPlaceholder'=>'Prop', 'editable'=> ['type'=> 'text'], 'sortable'=> true, 'width'=> 300],
      ['field'=>'cdate', 'title'=>'Create','filterControl'=> 'input','sortable'=> true, 'width'=> 300],
      ['field'=>'udate', 'title'=>'Update','filterControl'=> 'input','sortable'=> true]

    ];
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
      'destroy' =>Html::sucMsg('Successfully Delete The Role.'),
      'store'   =>Html::sucMsg('Successfully Create The New Role.'),
    ];
    return $data[$name];
  }  
//------------------------------------------------------------------------------
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
}