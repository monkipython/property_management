<?php
namespace App\Http\Controllers\PropertyManagement\TenantEvictionProcess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, Account, TableName AS T};
use App\Http\Models\Model; // Include the models class

class TenantEvictionProcessController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/TenantEvictionProcess/tenantEvictionProcess/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingTenant = Helper::getMapping(['tableName'=>T::$tenant]);
    $this->_mappingEvictionProcess = Helper::getMapping(['tableName'=>T::$tntEvictionProcess]);
    $this->_viewTable = T::$tntEvictionProcessView;
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
        $vData['defaultSort'] = ['prop.keyword:asc', 'unit.keyword:asc', 'tenant:desc'];
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
  public function create(Request $req) {
    $page = $this->_viewPath . 'create';
   
    $formEvictionProcess = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $req),
      'button'    =>$this->_getButton(__FUNCTION__, $req),
    ]);
    
    return view($page, [
      'data'=>[
        'formEvictionProcess' => $formEvictionProcess
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Helper::getElasticResult(Elastic::searchMatch($this->_viewTable,['match' =>['tnt_eviction_process_id' => $id]]));
    $r = !empty($r[0]['_source']) ? $r[0]['_source'] : [];
    
    $formTntEvictionProcess = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__, $req),
      'setting'   =>$this->_getSetting('update', $req, $r)
    ]);
 
    return view($page, [
      'data'=>[
        'formTntEvictionProcess' => $formTntEvictionProcess,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['tnt_eviction_process_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__, $req),
      'includeCdate'=> 0,
      'includeUsid' => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tntEvictionProcess.'|tnt_eviction_process_id'
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    $msgKey = count(array_keys($vData)) > 3 ? 'msg' : 'mainMsg';

    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData[T::$tntEvictionProcess] = ['whereData'=>['tnt_eviction_process_id'=>$id],'updateData'=>$vData];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['ep.tnt_eviction_process_id'=>[$id]]]
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
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$tntEvictionProcess],
      'edit'   => [T::$tntEvictionProcess, T::$tenant, T::$prop],
      'update' => [T::$tntEvictionProcess],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'create' => ['attorney'],
      'edit'   => ['tnt_eviction_process_id','group1', 'prop', 'unit', 'tenant', 'result', 'attorney', 'isFileuploadComplete', 'process_status', 'tnt_name', 'street', 'city', 'state', 'zip', 'base_rent','dep_held1','move_in_date','move_out_date','spec_code','usid']
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['tenantEvictionProcessupdate']) ? $orderField['edit'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $disabled = isset($perm['tenantEvictionProcessupdate']) ? [] : ['disabled'=>1];

    $setting = [
      'create' => [
        'field' => [
          'attorney' => ['type'=>'option', 'option'=>$this->_mappingEvictionProcess['attorney']],
        ]
      ],
      'update' => [
        'field' => [
          'tnt_eviction_process_id' => $disabled + ['type' =>'hidden'],
          'group1'         => ['disabled'=>1],
          'prop'           => ['disabled'=>1],
          'unit'           => ['disabled'=>1],
          'tenant'         => ['disabled'=>1],
          'result'         => $disabled + ['type'=>'textarea'],
          'attorney'       => $disabled + ['type'=>'option', 'option'=>$this->_mappingEvictionProcess['attorney']],
          'isFileuploadComplete' => ['label'=>'Is Fileupload Complete', 'type'=>'option', 'option'=>$this->_mappingEvictionProcess['isFileuploadComplete']],
          'process_status' => ['disabled'=>1],
          'tnt_name'       => ['disabled'=>1],
          'street'         => ['disabled'=>1, 'label'=>'Forward Address'],
          'city'           => ['disabled'=>1, 'label'=>'Forward City'],
          'state'          => ['disabled'=>1, 'label'=>'Forward State'],
          'zip'            => ['disabled'=>1, 'label'=>'Forward Zip'],
          'base_rent'      => ['disabled'=>1],
          'dep_held1'      => ['disabled'=>1],
          'move_in_date'   => ['disabled'=>1],
          'move_out_date'  => ['disabled'=>1],
          'spec_code'      => ['disabled'=>1],
          'usid'           => ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>1]
        ],
        'rule' => [
          'attorney' => 'nullable'
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
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'create' => isset($perm['tenantEvictionProcesscreate']) ? ['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']] : [],
      'edit'   => isset($perm['tenantEvictionProcessupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $qData, $req){
    $perm = Helper::getPermission($req);
    $rows = [];
    $iconList = ['prop','unit', 'ledgercard', 'tenantstatement', 'ledgercardpdf'];
    
    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source']; 
      $actionData = $this->_getActionIcon($perm, $source);
      $source['num'] = $vData['offset'] + $i + 1;
      $source['action'] = implode(' | ', $actionData['icon']);
      $source['linkIcon']      = Html::getLinkIcon($source,$iconList);
      $source['base_rent']     = Format::usMoney($source['base_rent']);
      $source['dep_held1']     = Format::usMoney($source['dep_held1']);
      $source['move_in_date']  = Format::usDate($source['move_in_date']);
      $source['move_out_date'] = Format::usDate($source['move_out_date']);
      $source['udate']         = Format::usDate($source['udate']);
      $source['spec_code']     = $this->_mappingTenant['spec_code'][$source['spec_code']];
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
    if(isset($perm['tenantEvictionProcessExport'])) {
      $reportList['csv'] = 'Export to CSV';
    }
    if(isset($perm['tenantEvictionProcessReportindexEvictionReport'])){
      $reportList['EvictionReport'] = 'Eviction Report';
    }
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>150];
    $data[] = ['field'=>'group1', 'title'=>'Grp','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'unit', 'title'=>'Unit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'tenant', 'title'=>'Tnt','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'tnt_name', 'title'=>'Tenant Name','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250];
    $data[] = ['field'=>'process_status', 'title'=>'Status','sortable'=> true, 'filterControl'=> 'select','filterData'=> 'url:/filter/process_status:' . $this->_viewTable, 'width'=> 50];
    $data[] = ['field'=>'isFileuploadComplete', 'title'=>'Upload Done','sortable'=> true, 'filterControl'=> 'select','editable'=>['type'=>'select','source'=>$this->_mappingEvictionProcess['isFileuploadComplete']],'filterData'=> 'url:/filter/isFileuploadComplete:' . $this->_viewTable, 'width'=> 50];
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 225];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 150];
    $data[] = ['field'=>'state', 'title'=>'State','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'zip', 'title'=>'Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'attorney', 'title'=>'Attorney','sortable'=> true, 'filterControl'=> 'select','editable'=>['type'=>'select','source'=>$this->_mappingEvictionProcess['attorney']], 'filterData'=> 'url:/filter/attorney:' . $this->_viewTable, 'width'=> 150];
    $data[] = ['field'=>'base_rent', 'title'=>'Base Rent','sortable'=> true, 'filterControl'=> 'input','width'=> 50];
    $data[] = ['field'=>'dep_held1', 'title'=>'Deposit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50]; 
    $data[] = ['field'=>'move_in_date', 'title'=>'MoveIn', 'sortable'=> true, 'filterControl'=> 'input', 'filterControlPlaceholder'=>'yyyy-mm-dd','width'=> 50];
    $data[] = ['field'=>'move_out_date', 'title'=>'MoveOut','sortable'=> true, 'filterControl'=> 'input', 'filterControlPlaceholder'=>'yyyy-mm-dd','width'=> 50];
    $data[] = ['field'=>'spec_code', 'title'=>'Spec','sortable'=> true, 'filterControl'=> 'input']; 
  
    return ['columns'=>$data, 'reportList'=>$reportList]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update' => Html::sucMsg('Successfully Update.')
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionIcon($perm, $r = []){
    $actionData = [];
    if(isset($perm['tenantEvictionProcessedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="View Tenant Eviction Process Information"></i></a>';
    }
    if(isset($perm['tenantEvictionEventedit'])){
      $actionData[] = '<a class="evictionEvent" href="javascript:void(0)"><i class="fa fa-gavel text-aqua pointer tip" title="View Eviction Event"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
}