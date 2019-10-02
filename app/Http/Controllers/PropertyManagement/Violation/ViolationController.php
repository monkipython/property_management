<?php
namespace App\Http\Controllers\PropertyManagement\Violation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, Account, TableName AS T};
use App\Http\Models\Model; // Include the models class

class ViolationController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Violation/violation/';
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping   = [];
  
  public function __construct(Request $req){
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$violation]);
    $this->_viewTable = T::$violationView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
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
        $qData = GridData::getQuery($vData, $this->_viewTable);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $req); 
      default:
        return view($page, ['data'=>[
          'nav'=>$req['NAV'],
          'account'=>Account::getHtmlInfo($req['ACCOUNT']), 
          'initData'=>$initData
        ]]);  
    }
  } 
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['violation_id' => $id]]);
    $r = Helper::getElasticResult($r, 1)['_source'];
    
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__, $req),
      'setting'   =>$this->_getSetting('update', $req, $r)
    ]);
    return view($page, [
      'data'=>[
        'form' => $form,
      ]
    ]);
  }
  //------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['violation_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__, $req),
//      'orderField'  => $this->_getOrderField(__FUNCTION__, $req),
      'includeCdate'=> 0,
      'includeUsid'=>1,
      'validateDatabase'=>[
        'mustExist'=>[
          'violation|violation_id',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $vData['usid'] = Helper::getUsid($req);
    $msgKey = count(array_keys($vData)) > 3 ? 'msg' : 'mainMsg';
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$violation=>['whereData'=>['violation_id'=>$id],'updateData'=>$vData],
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['v.violation_id'=>[$id]]]
      ];
      $response[$msgKey] = $this->_getSuccessMsg(__FUNCTION__);
    
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
  public function create(){
    $page = $this->_viewPath . 'create';
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__),
      'setting'   =>$this->_getSetting(__FUNCTION__),
      
    ]);
    
    return view($page, [
      'data'=>[
        'form' => $form
      ]
    ]);
  }
    //------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'     => $req->all(),
      'tablez'     => $this->_getTable('create'), 
      'orderField' => $this->_getOrderField('create', $req),
      'setting'    => $this->_getSetting('create', $req), 
      'includeUsid'=>1,
    ]);
    $vData = $valid['data'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success = Model::insert([T::$violation=>$vData]);
      $elastic = [
        'insert'=>[
          T::$violationView=>['violation_id'=>$success['insert:violation']], 
        ]
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
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$violation],
      'update' => [T::$violation]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'create' => ['prop', 'date_recieved', 'date_comply', 'priority', 'inspector_fname', 'inspector_lname','inspector_phone','agent', 'remark'],
      'edit'   => ['violation_id', 'status', 'prop', 'date_recieved', 'date_comply', 'date_complete', 'priority', 'inspector_fname', 'inspector_lname','inspector_phone', 'agent', 'remark', 'usid']
    ];
 
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['violationedit']) ? $orderField['edit'] : [];
    $orderField['store']  = isset($perm['violationcreate']) ? $orderField['create'] : [];
    
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm         = Helper::getPermission($req);
    $disabled     = isset($perm['violationupdate']) ? [] : ['disabled'=>1];
    $changeStatus = isset($perm['violationupdate']) && isset($perm['violationeditchangeStatus']) ? [] : ['disabled'=>1];
    $setting = [
      'create' => [
        'field' => [
          'prop'            => ['label'=>'Property Number', 'class'=>'autocomplete'],
          'remark'          => ['type'=>'textarea'],
          'inspector_fname' => ['label'=>'Inspector First Name'],
          'inspector_lname' => ['label'=>'Inspector Last Name'],
          'priority'        => ['type'=>'option', 'option'=>$this->_mapping['priority']],
          'status'          => ['type'=>'option', 'option'=>$this->_mapping['status']],
          'date_recieved'   => ['value'=>date('m/d/Y')],
          'date_comply'     => ['value'=>date('m/d/Y')],
        ]
      ],
      'update' => [
        'field' => [
          'violation_id'    => $disabled + ['type' =>'hidden'],
          'prop'            => $disabled + ['label'=>'Property Number', 'class'=>'autocomplete'],
          'remark'          => $disabled + ['type'=>'textarea'],
          'inspector_fname' => $disabled + ['label'=>'Inspector First Name'],
          'inspector_lname' => $disabled + ['label'=>'Inspector Last Name'],
          'inspector_phone' => $disabled,
          'priority'        => $disabled + ['type'=>'option', 'option'=>$this->_mapping['priority']],
          'status'          => $changeStatus + ['type'=>'option', 'option'=>$this->_mapping['status']],
          'date_recieved'   => $disabled,
          'date_comply'     => $disabled,
          'date_complete'   => $disabled,
          'usid'            => $disabled + ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>1]
        ]  
      ]
    ];
    
    $setting['update'] = isset($perm['violationedit']) ? $setting['update'] : [];
    $setting['store']  = isset($perm['violationcreate']) ? $setting['create'] : [];

    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['date_recieved']['value'] = Format::usDate($default['date_recieved']);
        $setting[$fn]['field']['date_comply']['value']   = Format::usDate($default['date_comply']);
        $setting[$fn]['field']['date_complete']['value'] = Format::usDate($default['date_complete']);
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit'  => isset($perm['violationupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
      'create'=> ['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']]
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $req){
    $perm = Helper::getPermission($req);
    $rows = [];
    $r = Helper::getElasticResult($r, 0, 1);
    $actionData = $this->_getActionIcon($perm);
    foreach($r['data'] as $i=>$v){
      $source = $v['_source'];
      $source['action']   = implode(' | ', $actionData['icon']);
      $source['status']   = (isset($perm['violationupdate']) && isset($perm['violationeditchangeStatus']))? $source['status']   : $this->_mapping['status'][$source['status']];
      $source['priority'] = isset($perm['violationupdate']) ? $source['priority'] : $this->_mapping['priority'][$source['priority']];
      $source['street']   = $this->_addGoogleMapLink($source["street"] .' '. $source["city"], $source["street"]);
      $source['violationFile'] = !empty($source['fileUpload']) ? Html::span('View',['class'=>'clickable']) : '';
      $source['num']      = $vData['offset'] + $i + 1;
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $actionData = $this->_getActionIcon($perm);
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = isset($perm['violationExport']) ? ['csv'=>'Export to CSV'] : [];
    
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['violationcreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      return $button;
    };
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if((isset($perm['violationupdate']) && $field !== 'status')){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      if(isset($perm['violationupdate']) && isset($perm['violationeditchangeStatus']) && $field === 'status'){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    $violationEditable = isset($perm['violationupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'cons1', 'title'=>'Owner','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'trust', 'title'=>'Trust','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'group1', 'title'=>'Group','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'prop', 'title'=>'Prop', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 25] + $violationEditable;
    $data[] = ['field'=>'prop_name', 'title'=>'Property Name', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'date_recieved', 'title'=>'D. Recieved','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $violationEditable;
    $data[] = ['field'=>'date_comply', 'title'=>'D. Comply','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $violationEditable;
    $data[] = ['field'=>'date_complete', 'title'=>'D. Complete','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $violationEditable;
    $data[] = $_getSelectColumn($perm, 'status', 'Status', 35, $this->_mapping['status']);
    $data[] = $_getSelectColumn($perm, 'priority', 'Priority', 35, $this->_mapping['priority']);
    if(isset($perm['uploadViolationindex'])){
      $data[] = ['field'=>'violationFile', 'title'=>'File','width'=> 75];
    }
    $data[] = ['field'=>'inspector_name', 'title'=>'Inspector Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $violationEditable;
    $data[] = ['field'=>'inspector_phone', 'title'=>'Inspector Phone','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $violationEditable;
    $data[] = ['field'=>'agent', 'title'=>'Agent','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $violationEditable;
    $data[] = ['field'=>'remark', 'title'=>'remark','sortable'=> true, 'filterControl'=> 'input'] + $violationEditable;
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update' =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _addGoogleMapLink($adress, $name) {
    return Html::a($name, ['href'=>'https://www.google.com/maps/search/?api=1&query=' . urlencode($adress), 'target'=>'_blank' ]);
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['violationcreate'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)"><i class="fa fa-edit text-aqua pointer tip" title="Edit Violation Information"></i></a>';
    }
    if(isset($perm['uploadViolationstore'])){
      $actionData[] = '<a class="violationUpload" href="javascript:void(0)"><i class="fa fa-upload text-aqua pointer tip" title="Upload Violation File"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
}
