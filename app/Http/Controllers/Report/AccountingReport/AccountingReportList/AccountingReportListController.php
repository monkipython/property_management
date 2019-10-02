<?php
namespace App\Http\Controllers\Report\AccountingReport\AccountingReportList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, TableName AS T, Helper};
use App\Http\Models\{Model, ReportModel AS M}; // Include the models class

class AccountingReportListController extends Controller{
  private $_viewPath  = 'app/Report/accountingReportList/';

//------------------------------------------------------------------------------
  public function create(Request $req) {
    $page = $this->_viewPath . 'create';
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => [T::$reportName],
      'includeCdate'=>0,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$reportName.'|report_name_id'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable('store'), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $vData),
      'button'    =>$this->_getButton(__FUNCTION__, $req),
    ]);
    $glChartMapping   = Helper::getMapping(['tableName'=>T::$glChart]);
    $accTypeData = $this->_mappingToSelect2($glChartMapping['acct_type']);
    return view($page, [
      'data'=>[
        'form' => $form,
        'accTypeData' => json_encode($accTypeData)
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req) {
    $page = $this->_viewPath . 'edit';
    
    $rList = M::getTableData(T::$reportList, Model::buildWhere(['report_list_id'=>$id]), '*', 1);
    
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, [], $rList),
      'button'    =>$this->_getButton(__FUNCTION__, $req),
    ]);
   
    $glChartMapping   = Helper::getMapping(['tableName'=>T::$glChart]);
    $accTypeData = $this->_mappingToSelect2($glChartMapping['acct_type'], $rList);
    return view($page, [
      'data'=>[
        'form'        => $form,
        'accTypeData' => json_encode($accTypeData)
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['report_list_id'=>$id]);
    $req->merge(['acct_type_list'=> explode(',', $req['acct_type_list'])]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting('edit', $req),
      'orderField'  => $this->_getOrderField('edit', $req),
      'includeUsid' => 1,
      'includeCdate'=> 0,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$reportList.'|report_list_id',
        ]
      ]
    ]);
    $vData    = $valid['dataNonArr'];
    $vDataArr = $valid['dataArr'];
    $vData['acct_type_list'] = $this->_arrayToCommaString($vDataArr);
    $updateData = [
      T::$reportList=>['whereData'=>['report_list_id'=>$id],'updateData'=>$vData],
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);

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
  public function store(Request $req){
    $req->merge(['acct_type_list'=> explode(',', $req['acct_type_list'])]);
    $valid = V::startValidate([
      'rawReq'     => $req->all(),
      'tablez'     => $this->_getTable(__FUNCTION__), 
      'orderField' => $this->_getOrderField('create', $req),
      'setting'    => $this->_getSetting('create', $req), 
      'includeUsid'=>1,
    ]);
    $vData    = $valid['data'];
    $vDataArr = $valid['dataArr'];
    $vData['acct_type_list'] = $this->_arrayToCommaString($vDataArr);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success = Model::insert([T::$reportList=>$vData]);
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
  public function destroy($id){
    $valid = V::startValidate([
      'rawReq' => [
        'report_list_id' => $id,
      ],
      'tablez' => $this->_getTable(__FUNCTION__),
      'includeCdate'=> 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$reportList.'|report_list_id',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    try{
      $success[T::$reportList] = M::deleteTableData(T::$reportList,Model::buildWhere(['report_list_id'=>$vData['report_list_id']]));
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
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
      'store'  => [T::$reportList],
      'update' => [T::$reportList],
      'destroy'=> [T::$reportList]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'edit'   => ['report_list_id', 'name_list', 'gl_list', 'acct_type_list', 'usid'],
      'create' => ['report_group_id', 'name_list', 'gl_list', 'acct_type_list']
    ];
 
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $vData=[], $default=[]){
    $rGroup = !empty($vData) ? Helper::keyFieldName(M::getReportGroup(Model::buildWhere(['report_name_id'=>$vData['report_name_id']])), 'report_group_id', 'name_group') : [];

    $setting = [
      'create' => [
        'field' => [
          'report_group_id'=> ['label'=>'Report Group', 'type'=>'option', 'option'=>$rGroup],
          'name_list'      => ['label'=>'List Name'],
          'gl_list'        => ['type'=>'textarea', 'value'=>0],
          'acct_type_list' => ['type'=>'option', 'option'=>[], 'multiple'=>'multiple', 'name'=>'acct_type_list[]'],
        ]
      ],
      'edit'=>[
        'field' => [
          'report_list_id' => ['type'=>'hidden'],
          'name_list'      => ['label'=>'List Name'],
          'gl_list'        => ['type'=>'textarea'],
          'acct_type_list' => ['type'=>'option', 'option'=>[], 'multiple'=>'multiple', 'name'=>'acct_type_list[]'],
          'usid'           => ['readonly'=>1],
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
  private function _getButton($fn, $req=[]){
    $buttton = [
      'edit'  => ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']],
      'create'=> ['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']]
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Updated.'),
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _mappingToSelect2($array, $data = []) {
    $select2Format = [];
    $selectedAcctType = !empty($data) ? explode(',', $data['acct_type_list']) : [];
    foreach($array as $field => $val) {
      $select2Format[]= [
        'id'   => $field,
        'text' => $val,
        'selected' => in_array($field, $selectedAcctType) ? true : false
      ];
    }
    return $select2Format;
  }
//------------------------------------------------------------------------------
  private function _arrayToCommaString($vDataArr) {
    $acctTypeList = '';
    foreach($vDataArr as $i => $value) {
      $acctTypeList .= $i == 0 ? $value['acct_type_list'] : ','.$value['acct_type_list'];
    }
    return $acctTypeList;
  }
}
