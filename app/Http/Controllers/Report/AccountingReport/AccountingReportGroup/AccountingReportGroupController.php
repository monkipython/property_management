<?php
namespace App\Http\Controllers\Report\AccountingReport\AccountingReportGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, TableName AS T};
use App\Http\Models\{Model, ReportModel AS M}; // Include the models class

class AccountingReportGroupController extends Controller{
  private $_viewPath  = 'app/Report/accountingReportGroup/';

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
    $default = $valid['dataNonArr'];
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable('store'), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, [], $default),
      'button'    =>$this->_getButton(__FUNCTION__, $req),
    ]);
    
    return view($page, [
      'data'=>[
        'form' => $form,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req) {
    $page = $this->_viewPath . 'edit';
    
    $rGroup = M::getReportGroup(Model::buildWhere(['report_group_id'=>$id]), 1);
  
    $form = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, [], $rGroup),
      'button'    =>$this->_getButton(__FUNCTION__, $req),
    ]);
   
    return view($page, [
      'data'=>[
        'form' => $form,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['report_group_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting('edit', $req),
      'orderField'  => $this->_getOrderField('edit', $req),
      'includeUsid' => 1,
      'includeCdate'=> 0,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$reportGroup.'|report_group_id',
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    $updateData = [
      T::$reportGroup=>['whereData'=>['report_group_id'=>$id],'updateData'=>$vData],
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
    $valid = V::startValidate([
      'rawReq'     => $req->all(),
      'tablez'     => $this->_getTable(__FUNCTION__), 
      'orderField' => $this->_getOrderField('create', $req),
      'setting'    => $this->_getSetting('create', $req), 
      'includeUsid'=>1,
    ]);
    $vData = $valid['data'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success = Model::insert([T::$reportGroup=>$vData]);
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
        'report_group_id' => $id,
      ],
      'tablez' => $this->_getTable(__FUNCTION__),
      'includeCdate'=> 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$reportGroup.'|report_group_id',
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    $rList  = M::getReportList([$vData['report_group_id']]);
    $listId = array_column($rList, 'report_list_id');
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    try{
      $success[T::$reportGroup] = M::deleteTableData(T::$reportGroup,Model::buildWhere(['report_group_id'=>$vData['report_group_id']]));
      if(!empty($listId)) {
        $success[T::$reportList]  = M::deleteWhereInTableData(T::$reportList, 'report_list_id', $listId);
      }
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
      'store'  => [T::$reportGroup],
      'update' => [T::$reportGroup],
      'destroy'=> [T::$reportGroup]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'edit'   => ['report_group_id', 'name_group', 'display_as', 'usid'],
      'create' => ['report_name_id', 'name_group', 'display_as']
    ];
 
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $vData=[], $default=[]){
   
    $setting = [
      'create' => [
        'field' => [
          'report_name_id' => ['type'=>'hidden'],
          'name_group'     => ['label'=>'Group Name'],
          'display_as'     => ['label'=>'Display','type'=>'option','option'=>[1=>'Positive',-1=>'Negative']]
        ]
      ],
      'edit'=>[
        'field' => [
          'report_group_id' => ['type'=>'hidden'],
          'name_group'      => ['label'=>'Group Name'],
          'display_as'      => ['label'=>'Display','type'=>'option','option'=>[1=>'Positive',-1=>'Negative']],
          'usid'            => ['readonly'=>1],
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
}