<?php
namespace App\Http\Controllers\PropertyManagement\TenantMoveOutProcess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, HelperMysql, GridData, Account, TableName AS T};
use App\Http\Models\Model; // Include the models class

class TenantMoveOutProcessController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/TenantMoveOutProcess/tenantMoveOutProcess/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingTntMoveOutProcess = Helper::getMapping(['tableName'=>T::$tntMoveOutProcess]);
    $this->_mappingTenant = Helper::getMapping(['tableName'=>T::$tenant]);
    $this->_viewTable = T::$tntMoveOutProcessView;
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
        $vData['defaultSort'] = ['status.keyword:asc', 'dep_held1:desc', 'prop.keyword:asc'];
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
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Helper::getElasticResult(Elastic::searchMatch($this->_viewTable,['match' =>['tnt_move_out_process_id' => $id]]));
    $r = !empty($r[0]['_source']) ? $r[0]['_source'] : [];
    
    $formTntMoveOutProcess = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__, $req),
      'setting'   =>$this->_getSetting('update', $req, $r)
    ]);
   
    $vendid = $r['prop'] . '-' . $r['unit'] . '-' . $r['tenant'];
    $vendorLink = Html::a('Click Here to Change Tenant\'s Forward Information', ['href'=>url('vendors?' . http_build_query(['vendid'=>$vendid])), 'target'=>'_black']);
    $vendorDiv  = Html::div($vendorLink, ['class'=>'text-center forwardContainer']);
    
    return view($page, [
      'data'=>[
        'formTntMoveOutProcess' => $formTntMoveOutProcess . $vendorDiv,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['tnt_move_out_process_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__, $req),
  //    'orderField'  => $this->_getOrderField(__FUNCTION__, $req->all()),
      'includeCdate'=> 0,
      'includeUsid' => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          'tnt_move_out_process|tnt_move_out_process_id'
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    $msgKey = count(array_keys($vData)) > 3 ? 'msg' : 'mainMsg';

    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData[T::$tntMoveOutProcess] = ['whereData'=>['tnt_move_out_process_id'=>$id],'updateData'=>$vData];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['tnt_move_out_process_id'=>[$id]]]
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
      'edit'   => [T::$tntMoveOutProcess, T::$vendor],
      'update' => [T::$tntMoveOutProcess],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $perm = Helper::getPermission($req);
    $orderField = [
      'edit' => ['tnt_move_out_process_id', 'prop', 'unit', 'tenant', 'status', 'isFileuploadComplete', 'phone', 'e_mail', 'street', 'city', 'state', 'zip', 'usid']
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['tenantMoveOutProcessupdate']) ? $orderField['edit'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $disabled = isset($perm['tenantMoveOutProcessupdate']) ? [] : ['disabled'=>1];
    
    $setting = [
      'update' => [
        'field' => [
          'tnt_move_out_process_id' => $disabled + ['type' =>'hidden'],
          'prop'    => $disabled + ['readonly'=>1],
          'unit'    => $disabled + ['readonly'=>1],
          'tenant'  => $disabled + ['readonly'=>1],
          'status'  => $disabled + ['label'=>'Dep Issued', 'type'=>'option', 'option'=>$this->_mappingTntMoveOutProcess['status']],
          'isFileuploadComplete' => ['label'=>'Is Fileupload Complete', 'type'=>'option', 'option'=>$this->_mappingTntMoveOutProcess['isFileuploadComplete']],
          'phone'   => ['disabled'=>1],
          'e_mail'  => ['disabled'=>1],
          'street'  => ['disabled'=>1, 'label'=>'Forward Address'],
          'city'    => ['disabled'=>1, 'label'=>'Forward City'],
          'state'   => ['disabled'=>1, 'label'=>'Forward State'],
          'zip'     => ['disabled'=>1, 'label'=>'Forward Zip'],
          'usid'    => ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>1]
        ]
      ]
    ];
    $setting['update'] = isset($perm['tenantMoveOutProcessedit']) ? $setting['update'] : [];
    
    if(!empty($default)){
      $vendid = $default['prop'] . '-' . $default['unit'] . '-' . $default['tenant'];
      $rVendor = HelperMysql::getTableData(T::$vendor, Model::buildWhere(['vendid'=>$vendid]), ['street', 'phone', 'e_mail', 'city', 'state', 'zip'], 1);
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
        $setting[$fn]['field']['street']['value'] = $rVendor['street'];
        $setting[$fn]['field']['phone']['value']  = $rVendor['phone'];
        $setting[$fn]['field']['e_mail']['value'] = $rVendor['e_mail'];
        $setting[$fn]['field']['city']['value']   = $rVendor['city'];
        $setting[$fn]['field']['state']['value']  = $rVendor['state'];
        $setting[$fn]['field']['zip']['value']    = $rVendor['zip'];
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit' => isset($perm['tenantMoveOutProcessupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
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
      $source['linkIcon']      = Html::getLinkIcon($source, $iconList);
      $source['base_rent']     = Format::usMoney($source['base_rent']);
      $source['dep_held1']     = Format::usMoney($source['dep_held1']);
      $source['move_in_date']  = Format::usDate($source['move_in_date']);
      $source['move_out_date'] = Format::usDate($source['move_out_date']);
      $source['udate']         = Format::usDate($source['udate']);
      $source['spec_code']     = $this->_mappingTenant['spec_code'][$source['spec_code']];
      $source['moveout_report'] = $source['moveout_file'] = '';
      if(!empty($source['fileUpload'])){
        foreach($source['fileUpload'] as $i=>$v){
          if($v['type'] == 'tenantMoveOutReport') {
            $source['moveout_report'] = Html::span('View',['class'=>'clickable']);
          }
          if($v['type'] == 'tenantMoveOutFile') {
            $source['moveout_file'] = Html::span('View',['class'=>'clickable']);
          }
        }
      }
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
    if(isset($perm['tenantMoveOutProcessExport'])) {
      $reportList['csv'] = 'Export to CSV';
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
    $data[] = ['field'=>'status', 'title'=>'Dep Issued','sortable'=> true, 'filterControl'=> 'select','editable'=>['type'=>'select','source'=>$this->_mappingTntMoveOutProcess['status']],'filterData'=> 'url:/filter/status:' . $this->_viewTable, 'width'=> 50];
    $data[] = ['field'=>'isFileuploadComplete', 'title'=>'Upload Done','sortable'=> true, 'filterControl'=> 'select','editable'=>['type'=>'select','source'=>$this->_mappingTntMoveOutProcess['isFileuploadComplete']],'filterData'=> 'url:/filter/isFileuploadComplete:' . $this->_viewTable, 'width'=> 50];
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 225];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 150];
    $data[] = ['field'=>'state', 'title'=>'State','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'zip', 'title'=>'Zip','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50];
    $data[] = ['field'=>'moveout_report','title'=>'Report','width'=>50];
    $data[] = ['field'=>'moveout_file','title'=>'Upload File','width'=>50];
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
    if(isset($perm['tenantMoveOutProcessedit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="View Tenant Move Out Process Information"></i></a>';
    }
    if(isset($perm['uploadMoveOutFilestore'])){
      $actionData[] = '<a class="moveOutFileUpload" href="javascript:void(0)"><i class="fa fa-upload text-aqua pointer tip" title="Upload Move Out File"></i></a>';
    }
    if(isset($perm['tenantDepositRefundedit']) &&  isset($r['status']) && $r['status'] == '0' ){
      $actionData[] = '<a class="depositRefund" href="javascript:void(0)" title="Edit"><i class="fa fa-dollar text-green pointer tip" title="Issue Deposit Refund"></i></a>';
    }else if(isset($perm['tenantDepositRefundUndostore']) &&  isset($r['status']) && $r['status'] == '1' ) {
      $actionData[] = '<a class="depositRefundUndo" href="javascript:void(0)" title="Edit"><i class="fa fa-reply text-red pointer tip" title="Undo Deposit Refund"></i></a>';
    }
    $num = count($actionData);
        
    return ['icon'=>$actionData, 'width'=>$num * 42];
  }
}