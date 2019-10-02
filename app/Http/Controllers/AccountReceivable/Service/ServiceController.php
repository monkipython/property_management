<?php
namespace App\Http\Controllers\AccountReceivable\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, Account, HelperMysql, TableName AS T};
use App\Http\Models\{Model,ServiceModel as M}; // Include the models class

class ServiceController extends Controller{
  private $_viewPath  = 'app/AccountReceivable/Service/service/';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_indexMain = T::$serviceView . '/' . T::$serviceView . '/_search?';
    $this->_mappingService = Helper::getMapping(['tableName'=>T::$service]);
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . 'index';
    $initData = $this->_getColumnButtonReportList($req);
    switch ($op){
      case 'column':
        return $initData;
      case 'show':
        $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
        $qData = GridData::getQuery($vData, T::$serviceView);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData, $req); 
      default:
        return view($page, ['data'=>[
          'nav'=>$req['NAV'],
          'account'=>Account::getHtmlInfo($req['ACCOUNT']), 
          'initData'=>$initData
        ]]);  
    }
  } 
 //------------------------------------------------------------------------------
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
   
    $formService = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $req),
      'button'    =>$this->_getButton(__FUNCTION__, $req), 
    ]);
    
    return view($page, [
      'data'=>[
        'formService' => $formService,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable('create'),
      'orderField'      => $this->_getOrderField(__FUNCTION__, $req),
      'setting'         => $this->_getSetting(__FUNCTION__, $req),
      'includeUsid'     => 1,
      'includeCdate'    => 0,
      'validateDatabase'=>[
        'mustExist' =>[
          T::$prop.'|prop',
          T::$glChart.'|gl_acct'
        ],
        'mustNotExist' => [
          T::$service.'|prop,service'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $vData['gl_acct_past'] = $vData['gl_acct_next'] = $vData['gl_acct'];
    $insertData = [];
    if($vData['prop'] == 'Z64') {
      $rProps = M::getNumberProps();
      foreach($rProps as $i => $v) {
        $source = $v['_source'];
        $vData['prop'] = $source['prop'];
        $insertData[] = $vData;
      }
      $vData['prop'] = 'Z64';
    }
    $insertData[] = $vData;
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert([T::$service=>$insertData]);
      
      $elastic = [
        'insert'=>[T::$serviceView=>['service_id'=>$success['insert:' . T::$service]]]
      ];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
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
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    if($id == 0) {
      $fn = 'destroy';
    }else {
      $fn = 'update';
    }
    $formService = Form::generateForm([
      'tablez'    =>$this->_getTable($fn), 
      'button'    =>$this->_getButton($fn, $req), 
      'orderField'=>$this->_getOrderField($fn, $req), 
      'setting'   =>$this->_getSetting('update', $req)
    ]);
    
   
    return view($page, [
      'data'=>[
        'formService' => $formService,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__, $req),
      'includeUsid' => 1,
      'includeCdate'=> 0,
      'validateDatabase'=>[
        'mustExist' =>[
          T::$service.'|prop,service',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $vData['gl_acct_past'] = $vData['gl_acct_next'] = $vData['gl_acct'];
    $rService = HelperMysql::getServiceElastic(['prop.keyword'=>$vData['prop'], 'service.keyword'=>$vData['service']], ['service_id'], 1);
    $serviceId = [$rService['service_id']];
      
    if($vData['prop'] == 'Z64') {
      $rService = M::getNumberPropsServiceId($vData['service']);
      $serviceId = array_merge($serviceId, array_column($rService, 'service_id'));
    }
    unset($vData['prop']);
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$service=>['whereInData'=>['field'=>'service_id','data'=>$serviceId], 'updateData'=>$vData],
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[T::$serviceView=>['service_id'=>$serviceId]]
      ];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
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
  public function destroy(Request $req){
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$service.'|prop,service'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $rService = HelperMysql::getServiceElastic(['prop.keyword'=>$vData['prop'], 'service.keyword'=>$vData['service']], ['service_id'], 1);
    $serviceId = [$rService['service_id']];

    if($vData['prop'] == 'Z64') {
      $rService = M::getNumberPropsServiceId($vData['service']);
      $serviceId = array_merge($serviceId, array_column($rService, 'service_id'));
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      $success[T::$service] = DB::table(T::$service)->whereIn('service_id', $serviceId)->delete();
      $commit['success'] = $success;
      $commit['elastic']['delete'][T::$serviceView] = ['service_id'=>$serviceId];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit($commit);
    } catch (\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$service],
      'update' => [T::$service],
      'destroy'=> [T::$service]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $orderField = [
      'create' => ['prop', 'service', 'gl_acct', 'remark'],
      'update' => ['prop', 'service', 'gl_acct', 'remark'],
      'destroy'=> ['prop', 'service']
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['serviceedit']) ? $orderField['update'] : [];
    $orderField['store']  = isset($perm['servicecreate']) ? $orderField['create'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req = [], $default = []){
    $perm = Helper::getPermission($req);
    
    $rProps = M::getPropsExeceptNumbers();
    $rProps = Helper::keyFieldName($rProps, 'key', 'key');
    $rProps[' '] = 'Select';
    $rService = [];
    if($fn == 'update' || $fn == 'destroy') {
      $rService = M::getServices();
      $rService = Helper::keyFieldName($rService, 'key', 'key');
      $rService[' '] = 'Select';
    }
    
    $setting = [
      'create' => [
        'field' => [
          'prop'     => ['type'=>'option', 'option'=>$rProps, 'value'=>'Z64'],
          'gl_acct'  => ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
        ]
      ],
      'update' => [
        'field' => [
          'prop'     => ['type'=>'option', 'option'=>$rProps, 'value'=>'Z64'],
          'service'  => ['type'=>'option', 'option'=>$rService, 'value'=>' '],
          'gl_acct'  => ['label'=>'GL Account', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['serviceupdate']) ? $setting['update'] : [];
    $setting['store']  = isset($perm['servicecreate']) ? $setting['create'] : [];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'update'  => isset($perm['serviceupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
      'create'  => ['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']],
      'destroy' => ['submit'=>['id'=>'submit', 'value'=>'Delete', 'class'=>'col-sm-12']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm = Helper::getPermission($req);
    $rows = [];
   
    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source']; 
      $source['num'] = $vData['offset'] + $i + 1;
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = [];
    if(isset($perm['serviceReport'])){
      $reportList['ServiceReport'] = 'Service Report';
      $reportList['csv']           = 'Export to CSV';
    }

    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['servicecreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      if(isset($perm['serviceedit'])){
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-edit']) . ' Update', ['id'=> 'update', 'class'=>'btn btn-info']) . ' ';
      }
      if(isset($perm['servicedestroy'])){
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-trash']) . ' Delete', ['id'=> 'delete', 'class'=>'btn btn-danger']) . ' ';
      }
      return $button;
    };
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'service', 'title'=>'Service','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'gl_acct', 'title'=>'GL Account','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'remark', 'title'=>'Remark', 'sortable'=> true, 'filterControl'=> 'input'];

    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update' =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
      'destroy'=>Html::sucMsg('Successfully Deleted.'),
    ];
    return $data[$name];
  }
}