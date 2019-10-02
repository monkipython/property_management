<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\TenantRemark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper,Format, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class

class TenantRemarkController extends Controller {
  private $_viewPath  = 'app/PropertyManagement/Tenant/remark/';
  private $_viewTable = '';
  private $_mapping   = [];
  
  public function __construct(Request $req){
    $this->_viewTable = T::$tenantView;
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$remarkTnt]);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page  = $this->_viewPath . 'create';
    $valid = V::startValidate([
      'rawReq'           => $req->all(),
      'tablez'           => $this->_getTable('createValidate'),
      'setting'          => $this->_getSetting('createValidate'),
      'includeCdate'     => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$tenant . '|tenant_id',
        ]
      ]
    ]);
    $vData = $valid['data'];
    $id    = $vData['tenant_id'];
    $r     = Helper::getElasticResult(Elastic::searchMatch($this->_viewTable,['match'=>['tenant_id'=>$id]]),1)['_source'];
    $remarkForm = Form::generateForm([
      'tablez'      => $this->_getTable(__FUNCTION__),
      'orderField'  => $this->_getOrderField(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__,$r,$req),
      'button'      => $this->_getButton(__FUNCTION__,$req)
    ]);
    $html = !empty($vData['fromGrid']) && $vData['fromGrid'] != 'false' ? view($page,
        [
          'data'=>[
            'remarkList'=>$this->_getRemarkList($r),
            'form'=>$remarkForm
        ]])->render() : $remarkForm;

    return [
      'html' => $html,
      'submitMethod' => 'POST'
    ];
  }
//------------------------------------------------------------------------------  
  public function edit($id, Request $req){
    $page       = $this->_viewPath . 'edit';
    $valid      = V::startValidate([
      'rawReq'           => $req->all(),
      'tablez'           => $this->_getTable('editValidate'),
      'setting'          => $this->_getSetting('editValidate'),
      'includeCdate'     => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$remarkTnt . '|remark_tnt_id'
        ]
      ]
    ]);
    $vData    = $valid['data'];
    $remarkId = $vData['remark_tnt_id'];
    $r        = Helper::getElasticResult(Elastic::searchMatch($this->_viewTable,['match'=>['tenant_id'=>$id]]),1);
    $r        = !empty($r) ? $r['_source'] : [];

    $remarkForm = Form::generateForm([
      'tablez'     => $this->_getTable(__FUNCTION__),
      'orderField' => $this->_getOrderField(__FUNCTION__),
      'setting'    => $this->_getSetting(__FUNCTION__,$r,$req,$remarkId),
      'button'     => $this->_getButton(__FUNCTION__,$req)
    ]);
    
    $html =  !empty($vData['fromGrid']) && $vData['fromGrid'] != 'false' ? view($page,['data'=>[
      'remarkList' => $this->_getRemarkList($r),
      'form'       => $remarkForm
    ]])->render() : $remarkForm;
    return [
      'html' => $html,
      'submitMethod' => 'PUT'
    ];
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $valid = V::startValidate([
      'rawReq'           => $req->all(),
      'tablez'           => $this->_getTable(__FUNCTION__),
      'orderField'       => $this->_getOrderField(__FUNCTION__),
      'setting'          => $this->_getSetting('edit'),
      'includeCdate'     => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$tenant . '|prop,unit,tenant',
          T::$remarkTnt . '|remark_tnt_id',
        ]
      ]
    ]);
    $vData = $valid['data'];
    $updateData = [
      T::$remarkTnt => [
        'whereData' => [
          'remark_tnt_id' => $vData['remark_tnt_id']
        ],
        'updateData'=>$vData,
      ]
    ];
    ############### DATABASE SECTION ######################
    $response = $success = $elastic = [];
    DB::beginTransaction();
    try {
      $success += Model::update($updateData);
      $elastic = [
        'insert' => [$this->_viewTable => ['t.tenant_id' => [$id]]],
      ];
      
      Model::commit([
        'success' => $success,
        'elastic' => $elastic,
      ]);
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      $response['remarkList'] = $this->_getRemarkList($vData);
    } catch (Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'           => $req->all(),
      'tablez'           => $this->_getTable(__FUNCTION__),
      'orderField'       => $this->_getOrderField(__FUNCTION__),
      'setting'          => $this->_getSetting(__FUNCTION__),
      'includeCdate'     => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$tenant . '|prop,unit,tenant',
        ],
      ]
    ]);

    $vData        = $valid['data'];
    $tenant       = M::getTenant(Model::buildWhere(Helper::selectData(['prop','unit','tenant'],$vData)),['tenant_id'],1);
    $insertData   = [T::$remarkTnt => $vData];
    $r            = Helper::getElasticResult(Elastic::searchMatch($this->_viewTable,['match'=>['tenant_id'=>$tenant['tenant_id']]]),1)['_source'];
    $remarkForm = Form::generateForm([
      'tablez'      => $this->_getTable(__FUNCTION__),
      'orderField'  => $this->_getOrderField('create'),
      'setting'     => $this->_getSetting('create', $r, $req),
      'button'      => $this->_getButton('create', $req)
    ]);
    ############### DATABASE SECTION ######################
    $response     = $elastic = $success = [];
    try {
      $success   += Model::insert($insertData);
      $elastic    = [
        'insert' => [$this->_viewTable => ['t.tenant_id' => [$tenant['tenant_id']]]],
      ];

      Model::commit([
        'success'   => $success,
        'elastic'   => $elastic
      ]);
      $response['msg']        = $this->_getSuccessMsg(__FUNCTION__);
      $response['remarkList'] = $this->_getRemarkList($vData);
      $response['form']       = $remarkForm;
    } catch (Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req) {
    $valid = V::startValidate([
      'rawReq' => [
        'remark_tnt_id' => $id,
      ],
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          T::$remarkTnt . '|remark_tnt_id',
        ]
      ]
    ]);
    
    $vData = $valid['dataNonArr'];
    $tenantRemark = M::getRemark(Model::buildWhere(['remark_tnt_id' => $vData['remark_tnt_id']]), ['prop', 'unit', 'tenant']);
    $tenantId     = M::getTenant(Model::buildWhere(['prop'=>$tenantRemark['prop'], 'unit'=>$tenantRemark['unit'], 'tenant'=>$tenantRemark['tenant']]), 'tenant_id')['tenant_id'];
    $r            = Helper::getElasticResult(Elastic::searchMatch($this->_viewTable,['match'=>['tenant_id'=>$tenantId]]),1)['_source'];
    $remarkForm = Form::generateForm([
      'tablez'      => $this->_getTable(__FUNCTION__),
      'orderField'  => $this->_getOrderField('create'),
      'setting'     => $this->_getSetting('create', $r, $req),
      'button'      => $this->_getButton('create', $req)
    ]);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      $success[T::$remarkTnt] = DB::table(T::$remarkTnt)->where(Model::buildWhere(['remark_tnt_id'=>$vData['remark_tnt_id']]))->delete();
      $elastic = [
        'insert'=>[$this->_viewTable=>['t.tenant_id'=>[$tenantId]]]
      ];
      Model::commit([
        'success'   => $success,
        'elastic'   => $elastic
      ]);
      $response = [
        'msg'        => $this->_getSuccessMsg(__FUNCTION__),
        'remarkList' => $this->_getRemarkList($tenantRemark),
        'form'       => $remarkForm
      ];
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
      'createValidate' => [T::$tenant],
      'editValidate'   => [T::$remarkTnt,T::$tenant],
      'create'         => [T::$remarkTnt],
      'edit'           => [T::$remarkTnt],
      'update'         => [T::$tenant,T::$remarkTnt],
      'store'          => [T::$tenant,T::$remarkTnt],
      'destroy'        => [T::$remarkTnt]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField = [
      'create'=> ['prop','unit','tenant','remark_code','date1','amount','remarks'],
      'edit'  => ['prop','unit','tenant','remark_tnt_id','remark_code','date1','amount','remarks'],
      'update'=> ['prop','unit','tenant','remark_tnt_id','remark_code','date1','amount','remarks'],
      'store' => ['prop','unit','tenant','remark_code','date1','amount','remarks'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$default=[],$req=[],$id=''){
    $perm          = Helper::getPermission($req);
    $disabled      = isset($perm['tenantRemarkupdate']) ? [] : ['disabled'=>1];

    $setting = [
      'createValidate'  => [
        'rule' => [
          'fromGrid' => 'nullable|string'
        ]
      ],
      'editValidate'    => [
        'rule' => [
          'fromGrid' => 'nullable|string'
        ]
      ],
      'create' => [
        'field' => [
          'prop'        => ['type'=>'hidden'],
          'unit'        => ['type'=>'hidden'],
          'tenant'      => ['type'=>'hidden'],          
          'remark_code' => $disabled + ['label'=>'Remark Code','type'=>'option','option'=>$this->_mapping['remark_code'],'value'=>'EVI'],
          'remark_type' => $disabled + ['label'=>'Remark Type'],
          'date1'       => $disabled + ['label'=>'Date','value'=>date('m/d/Y')],
          'amount'      => $disabled + ['label'=>'Amount','value'=>'0'],
          'remarks'     => $disabled + ['label'=>'Remarks','type'=>'textarea','rows'=>10],
        ]
      ],
      'edit'   => [
        'field' => [
          'prop'        => ['type'=>'hidden'],
          'unit'        => ['type'=>'hidden'],
          'tenant'      => ['type'=>'hidden'],
          'remark_tnt_id'   => ['type'=>'hidden'],
          'remark_code' => $disabled + ['label'=>'Remark Code','type'=>'option','option'=>$this->_mapping['remark_code'],'value'=>'EVI'],
          'remark_type' => $disabled + ['label'=>'Remark Type'],
          'date1'       => $disabled + ['label'=>'Date','value'=>date('m/d/Y')],
          'amount'      => $disabled + ['label'=>'Amount','value'=>'0'],
          'remarks'     => $disabled + ['label'=>'Remarks','type'=>'textarea','rows'=>10],
        ]
      ],
      'store'   => [
        'rule' => [
          'remark_tnt_id' => 'nullable',
        ]
      ]
    ];

    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] =  $v;
        if($k === T::$remarkTnt){
          foreach($v as $i => $item){
            if(!empty($id) && $item['remark_tnt_id'] == $id){
              foreach($item as $key => $val){
                $setting[$fn]['field'][$key]['value'] = $val;
                $setting[$fn]['field']['date1']['value'] = Format::usDate($item['date1']);
              }
            }
          }
        }
      }
    }
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $perm   = Helper::getPermission($req);
    $btn    = [];

    $btn['edit']     = isset($perm['tenantRemarkupdate']) ? ['submit'=>['id'=>'submit','value'=>'Update Tenant Remark','class'=>'pull-left col-sm-6'], 'button'=>['id'=>'delete','value'=>'Delete Tenant Remark','class'=>'btn-danger col-sm-5']] : '';
    $btn['create']   = isset($perm['tenantRemarkupdate']) ? ['submit'=>['id'=>'submit','value'=>'Create Tenant Remark','class'=>'col-sm-12']] : '';
    return $btn[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getSuccessMsg($fn,$vData=[]){
    $data = [
      'store'  => Html::sucMsg('Tenant Remark Successfully Created'),
      'update' => Html::sucMsg('Tenant Remark Successfully Updated'),
      'destroy' =>Html::sucMsg('Successfully Deleted Tenant Remark'),
    ];
    return $data[$fn];
  }
//------------------------------------------------------------------------------
  private function _getRemarkList($r=[]){
    $links       = [];
    $remarks     = !empty($r) ? M::getRemark(Model::buildWhere(Helper::selectData(['prop','unit','tenant'],$r)),['remark_tnt_id','date1','remark_code'],0,'date1') : [];
    $remarkList  = array_merge([['remark_tnt_id'=>'','date1'=>'','remark_code'=>'']],$remarks);

    foreach($remarkList as $k => $v){
      $remarkCode  = !empty($this->_mapping['remark_code'][$v['remark_code']]) ? $this->_mapping['remark_code'][$v['remark_code']] : $v['remark_code'];
      $remarkTitle = !empty($v['remark_tnt_id']) ? Format::usDate($v['date1']) . ' : ' . $remarkCode : 'Create New Remark';
      $links[] = Html::a($remarkTitle,['id'=>$v['remark_tnt_id'],'href'=>'#']);
    }
    
    return Html::liAll($links,['id'=>'remarkList','class'=>'nav nav-pills nav-stacked','style'=>'overflow-y:auto;vertical-align:middle;'],['class'=>'nav-item']);
  }
}