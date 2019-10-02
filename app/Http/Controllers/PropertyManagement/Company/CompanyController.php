<?php
namespace App\Http\Controllers\PropertyManagement\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, Account, TableName AS T};
use App\Http\Models\Model; // Include the models class

class CompanyController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Company/company/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingInfo = Helper::getMapping(['tableName'=>T::$applicationInfo]);
    $this->_viewTable   = T::$companyView;
    $this->_indexMain   = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
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

    $formCompany = Form::generateForm([
      'tablez'     => $this->_getTable(__FUNCTION__), 
      'orderField' => $this->_getOrderField('createCompany'), 
      'button'     => $this->_getButton(__FUNCTION__, $req),
      'setting'    => $this->_getSetting('createCompany', $req)
    ]);
    return view($page, [
      'data'=>[
        'formCompany' => $formCompany,
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
      'includeCdate'    => 0,
      'validateDatabase'=>[
        'mustNotExist'=>[
          'company|company_code'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $vData['usid'] = Helper::getUsid($req);

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert([T::$company=>$vData]);
      $companyId = $success['insert:' . T::$company][0];

      $elastic = [
        'insert'=>[$this->_viewTable=>['company_id'=>[$companyId]]]
      ];
      $response['companyId'] = $companyId;
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
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['company_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    
    $formCompany = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editCompany'), 
      'button'    =>$this->_getButton(__FUNCTION__, $req),
      'setting'   =>$this->_getSetting('updateCompany', $req, $r)
    ]);
   
    return view($page, [
      'data'=>[
        'formCompany' => $formCompany,
      ]
    ]);
  }
  //------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['company_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__, $req),
  //    'orderField'  => $this->_getOrderField(__FUNCTION__, $req->all()),
      'includeCdate'=> 0,
      'validateDatabase'=>[
        'mustExist'=>[
          'company|company_id'
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    $msgKey = count(array_keys($vData)) > 2 ? 'msg' : 'mainMsg';
    $vData['usid'] = Helper::getUsid($req);

    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData[T::$company] = ['whereData'=>['company_id'=>$id],'updateData'=>$vData];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['company_id'=>[$id]]]
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
  public function destroy($id){
    $valid = V::startValidate([
      'rawReq' => [
        'company_id' => $id,
      ],
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          'company|company_id',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      $success[T::$company] = DB::table(T::$company)->where(Model::buildWhere(['company_id'=>$vData['company_id']]))->delete();
      $commit['success'] = $success;
      $commit['elastic'] = [
        'delete'=>[$this->_viewTable => ['company_id'=>$vData['company_id']]]
      ];
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
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
      'create'  => [T::$company],
      'update'  => [T::$company],
      'destroy' => [T::$company]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'createCompany' => ['company_name', 'company_code', 'street', 'city', 'state', 'zip', 'mailing_street', 'mailing_city', 'mailing_state', 'mailing_zip', 'phone', 'e_mail', 'fax', 'start_date'], 
      'editCompany'   => ['company_id', 'company_name', 'company_code', 'street', 'city', 'state', 'zip', 'mailing_street', 'mailing_city', 'mailing_state', 'mailing_zip', 'phone', 'e_mail', 'fax', 'start_date', 'usid']
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['companyedit']) ? $orderField['editCompany'] : [];
    $orderField['store']  = isset($perm['companycreate']) ? $orderField['createCompany'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $disabled = isset($perm['companyupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'createCompany' => [
        'field' => [
          'state'           => ['type' =>'option', 'option'=>$this->_mappingInfo['states'], 'value'=>'CA'],
          'mailing_state'   => ['type' =>'option', 'option'=>$this->_mappingInfo['states'], 'value'=>'CA'],
          'start_date'      => ['value'=>Helper::usDate()]
        ]
      ],
      'updateCompany' => [
        'field' => [
          'company_id'      => $disabled + ['type' =>'hidden'],
          'company_name'    => $disabled,
          'company_code'    => $disabled,
          'street'          => $disabled,
          'city'            => $disabled,
          'state'           => $disabled + ['type' =>'option', 'option'=>$this->_mappingInfo['states']],
          'zip'             => $disabled,
          'mailing_street'  => $disabled,
          'mailing_city'    => $disabled,
          'mailing_state'   => $disabled + ['type' =>'option', 'option'=>$this->_mappingInfo['states']],
          'mailing_zip'     => $disabled,
          'phone'           => $disabled,
          'e_mail'          => $disabled,
          'fax'             => $disabled,
          'start_date'      => $disabled,
          'usid'            => $disabled + ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>1]
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['companyedit']) ? $setting['updateCompany'] : [];
    $setting['store'] = isset($perm['companycreate']) ? $setting['createCompany'] : [];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['start_date']['value'] = Format::usDate($default['start_date']);
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit'   => isset($perm['companyupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
      'create' => ['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']]
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm = Helper::getPermission($req);
    $rows = [];
    $actionData = $this->_getActionIcon($perm);

    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source']; 
      $source['num'] = $vData['offset'] + $i + 1;
      $source['action'] = implode(' | ', $actionData['icon']);
      $source['street'] = $this->_addGoogleMapLink($source["street"] .' '. $source["city"] .' '. $source["zip"], $source["street"]);
      $source['mailing_street'] = $this->_addGoogleMapLink($source["mailing_street"] .' '. $source["mailing_city"] .' '. $source["mailing_zip"], $source["mailing_street"]);
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $actionData = $this->_getActionIcon($perm);
 
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['companycreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      return $button;
    };
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $source, $width = 0){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true];
      if($width) {
        $data['width'] = $width;
      }
      if(isset($perm['companyupdate'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    
    $companyEditable = isset($perm['companyupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'company_name', 'title'=>'Company Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300] + $companyEditable;
    $data[] = ['field'=>'company_code', 'title'=>'Company Code','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $companyEditable;
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $companyEditable;
    $data[] = ['field'=>'zip', 'title'=>'Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 100] + $companyEditable;
    $data[] = $_getSelectColumn($perm,'state','State',$this->_mappingInfo['states'],60);
    $data[] = ['field'=>'mailing_street', 'title'=>'Mailing Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'mailing_city', 'title'=>'Mailing City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $companyEditable;
    $data[] = ['field'=>'mailing_zip', 'title'=>'Mailing Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 100] + $companyEditable;
    $data[] = $_getSelectColumn($perm,'mailing_state','Mailing State',$this->_mappingInfo['states'], 60);
    $data[] = ['field'=>'phone', 'title'=>'Phone','sortable'=> true, 'filterControl'=> 'input', 'width'=> 150] + $companyEditable;
    $data[] = ['field'=>'e_mail', 'title'=>'Email','sortable'=> true, 'filterControl'=> 'input'] + $companyEditable;
  
    return ['columns'=>$data, 'button'=>$_getButtons($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _addGoogleMapLink($adress, $name) {
    return '<a href="https://www.google.com/maps/search/?api=1&query=' . urlencode($adress) . '" target="_blank" >'. $name .'</a>';
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['companyedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Company Information"></i></a>';
    }
    if(isset($perm['companydestroy'])){
      $actionData[] = '<a class="delete" href="javascript:void(0)" title="Delete Unit"><i class="fa fa-trash-o text-red pointer tip" title="Delete this Company"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
}