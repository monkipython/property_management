<?php
namespace App\Http\Controllers\AccountPayable\Vendors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, GridData, HelperMysql, Upload, Account, TableName AS T};
use App\Http\Models\Model; // Include the models class

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `classController`, `method`, `programDescription`, `active`) VALUES ('0', 'Account Payable', 'fa fa-fw fa-credit-card', 'vendors', 'Vendors', 'fa fa-fw fa-cog', 'Account Payable', 'vendors', 'destroy', 'To Delete Vendor', '1');
*/

class VendorsController extends Controller{
  private $_viewPath  = 'app/AccountPayable/Vendors/vendors/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingVendor = Helper::getMapping(['tableName'=>T::$vendor]);
    $this->_mappingInfo   = Helper::getMapping(['tableName'=>T::$applicationInfo]);
    $this->_viewTable = T::$vendorView;
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
   
    $formVendor = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__, $req), 
      'orderField'=>$this->_getOrderField('createVendor', $req), 
      'setting'   =>$this->_getSetting('createVendor', $req)
    ]);
    
    return view($page, [
      'data'=>[
        'formVendor'   => $formVendor,
        'upload'       => Upload::getHtml()
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
          'vendor|vendid'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $vData['usid'] = Helper::getUsid($req);
    if(!empty($vData['uuid'])) {
      $uuid = explode(',', (rtrim($vData['uuid'], ',')));
    }
    unset($vData['uuid']);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $updateDataSet = $response = $success = $elastic = [];
    try{
      $success += Model::insert([T::$vendor=>$vData]);
      $vendorId = $success['insert:' . T::$vendor][0];
      if(!empty($uuid)) {
        foreach($uuid as $v){
          $updateDataSet[T::$fileUpload][] = ['whereData'=>['uuid'=>$v], 'updateData'=>['foreign_id'=>$vendorId]];
        }
        $success += Model::update($updateDataSet);
      }
      $elastic = [
        'insert'=>[$this->_viewTable=>['vendor_id'=>[$vendorId]]]
      ];
      $response['vendorId'] = $vendorId;
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
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['vendor_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    $r['uuid'] = '';
    $vendorFiles = [];
    $fileUpload = !empty($r['fileUpload']) ? $r['fileUpload'] : [];
      
    foreach($fileUpload as $v){
      if($v['type'] == 'vendors'){
        $vendorFiles[] = $v;
        $r['uuid'] .= $v['uuid'] . ',';
      }
    }
    $fileUploadList = Upload::getViewlistFile($vendorFiles, '/uploadVendors');
    
    $formVendor = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'button'    =>$this->_getButton(__FUNCTION__, $req), 
      'orderField'=>$this->_getOrderField('editVendor', $req), 
      'setting'   =>$this->_getSetting('updateVendor', $req, $r)
    ]);
    
    return view($page, [
      'data'=>[
        'formVendor'     => $formVendor,
        'upload'         => Upload::getHtml(),
        'fileUploadList' => $fileUploadList
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['vendor_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate'=> 0,
      'validateDatabase'=>[
        'mustExist' =>[
          'vendor|vendor_id',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $vData['usid'] = Helper::getUsid($req);
    
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$vendor=>['whereData'=>['vendor_id'=>$id],'updateData'=>$vData],
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['vendor_id'=>[$id]]]
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
  public function destroy($id,Request $req){
    $req->merge(['vendor_id'=>$id]);
    $valid  = V::startValidate([
      'rawReq'           => $req->all(),
      'tablez'           => $this->_getTable(__FUNCTION__),
      'orderField'       => $this->_getOrderField(__FUNCTION__,$req),
      'validateDatabase' => [
        'mustExist'      => [
          T::$vendor . '|vendor_id',
        ]
      ],
    ]);
    
    $vData  = $valid['dataNonArr'];
    
    ############### DATABASE SECTION ######################
    $success = $elastic = $response = [];
    try {
      $success[T::$vendor]          = HelperMysql::deleteTableData(T::$vendor,Model::buildWhere(['vendor_id'=>$vData['vendor_id']]));
      $elastic                      = ['delete'=>[T::$vendorView=>['vendor_id'=>$vData['vendor_id']]]];
      
      Model::commit([
        'success'   => $success,
        'elastic'   => $elastic,
      ]);
      $response['mainMsg']          = $this->_getSuccessMsg(__FUNCTION__);
    } catch(\Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$vendor, T::$fileUpload],
      'update' => [T::$vendor, T::$fileUpload],
      'destroy'=> [T::$vendor],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $orderField = [
      'createVendor' => ['uuid', 'vendid', 'name', 'vendor_type', 'flg_1099', 'line2', 'street', 'city', 'state', 'zip', 'name_key', 'phone', 'fax', 'e_mail', 'web', 'fed_id', 'gl_acct'],
      'editVendor'   => ['vendor_id', 'vendid', 'name', 'vendor_type', 'flg_1099', 'line2', 'street', 'city', 'state', 'zip', 'name_key', 'phone', 'fax', 'e_mail', 'web', 'fed_id', 'gl_acct', 'usid'],
      'destroy'      => ['vendor_id'],
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['vendorsedit']) ? $orderField['editVendor'] : [];
    $orderField['store']  = isset($perm['vendorscreate']) ? $orderField['createVendor'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req = [], $default = []){
    $perm = Helper::getPermission($req);
    $disabled = isset($perm['vendorsupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'createVendor' => [
        'field' => [
          'uuid'        => ['type'=>'hidden'],
          'vendid'      => ['label'=>'Vendor Id'],
          'name'        => ['label'=>'Vendor Name'],
          'vendor_type' => ['type'=>'option', 'option'=>$this->_mappingVendor['vendor_type']],
          'line2'       => ['req'=>0],
          'fax'         => ['req'=>0],
          'web'         => ['req'=>0],
          'zip'         => ['label'=>'Zip Code'],
          'name_key'    => ['label'=>'Contact Person'],
          'state'       => ['type'=>'option', 'option'=> $this->_mappingInfo['states'], 'value'=>'CA'],
          'e_mail'      => ['label'=>'Email', 'req'=>0],
          'fed_id'      => ['label'=>'Federal ID', 'req'=>0],
          'flg_1099'    => ['label'=>'If 1099?', 'type'=>'option', 'option'=>$this->_mappingVendor['flg_1099']],
          'gl_acct'     => ['label'=>'Default Gl Acct', 'req'=>0]
        ],
        'rule'=>[
          'uuid'     => 'nullable',
          'line2'    => 'nullable',
          'fax'      => 'nullable',
          'e_mail'   => 'nullable',
          'web'      => 'nullable',
          'fed_id'   => 'nullable',
          'gl_acct'  => 'nullable'
        ]
      ],
      'updateVendor' => [
        'field' => [
          'vendor_id'   => $disabled + ['type' =>'hidden'],
          'vendid'      => $disabled + ['label'=>'Vendor Id', 'readonly'=>1],
          'name'        => $disabled + ['label'=>'Vendor Name'],
          'vendor_type' => $disabled + ['type'=>'option', 'option'=>$this->_mappingVendor['vendor_type']],
          'line2'       => $disabled + ['req'=>0], 
          'street'      => $disabled, 
          'city'        => $disabled,
          'zip'         => $disabled + ['label'=>'Zip Code'],
          'name_key'    => $disabled + ['label'=>'Contact Person'],
          'phone'       => $disabled,
          'fax'         => $disabled + ['req'=>0],
          'state'       => $disabled + ['type'=>'option', 'option'=> $this->_mappingInfo['states'], 'value'=>'CA'],
          'e_mail'      => $disabled + ['label'=>'Email', 'req'=>0],
          'web'         => $disabled + ['req'=>0],
          'fed_id'      => $disabled + ['label'=>'Federal ID', 'req'=>0],
          'flg_1099'    => $disabled + ['label'=>'If 1099?', 'type'=>'option', 'option'=>$this->_mappingVendor['flg_1099']],
          'name_1099'   => $disabled, 
          'gl_acct'     => $disabled + ['label'=>'Default Gl Acct', 'req'=>0],
          'usid'        => $disabled + ['readonly'=>1]
        ],
        'rule'=>[
          'uuid'     => 'nullable',
          'line2'    => 'nullable',
          'fax'      => 'nullable',
          'e_mail'   => 'nullable',
          'web'      => 'nullable',
          'fed_id'   => 'nullable',
          'gl_acct'  => 'nullable'
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['vendorsupdate']) ? $setting['updateVendor'] : [];
    $setting['store']  = isset($perm['vendorscreate']) ? $setting['createVendor'] : [];
    
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
      'edit'  =>isset($perm['vendorsupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
      'create'=>['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']]
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
      $source['num']    = $vData['offset'] + $i + 1;
      $source['action'] = implode(' | ', $actionData['icon']);
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);
    $actionData = $this->_getActionIcon($perm);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = [];
    
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['vendorscreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      return $button;
    };
    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if(isset($perm['vendorsupdate'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };
    $vendorEditable = isset($perm['vendorsupdate']) ? ['editable'=> ['type'=> 'text']] : [];
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'vendid', 'title'=>'Vendor Code','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'name', 'title'=>'Vendor Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250] + $vendorEditable;
    $data[] = $_getSelectColumn($perm, 'vendor_type', 'Vendor Type', 100, $this->_mappingVendor['vendor_type']);
    $data[] = ['field'=>'line2', 'title'=>'Line 2','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $vendorEditable;
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250] + $vendorEditable;
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200] + $vendorEditable;
    $data[] = $_getSelectColumn($perm, 'state', 'State', 30, $this->_mappingInfo['states']);
    $data[] = ['field'=>'zip', 'title'=>'Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 75] + $vendorEditable;
    $data[] = ['field'=>'fed_id', 'title'=>'Fed Tax Id','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $vendorEditable;
    $data[] = ['field'=>'phone', 'title'=>'Phone','sortable'=> true, 'filterControl'=> 'input'] + $vendorEditable;

    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm){
    $actionData = [];
    if(isset($perm['vendorsedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Vendor Information"></i></a>';
    }
    if(isset($perm['vendorsdestroy'])){
      $actionData[] = '<a class="delete" href="javascript:void(0)" title="Delete"><i class="fa fa-trash-o text-red pointer tip" title="Edit Vendor Information"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
}