<?php
namespace App\Http\Controllers\PropertyManagement\Section8;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, Format, GridData, Account, TableName AS T};
use App\Http\Models\{Model,Section8Model as M}; // Include the models class

class Section8Controller extends Controller {
  private $_viewPath  = 'app/PropertyManagement/Section8/section8/';
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping   = [];
  
  public function __construct(Request $req){
    $this->_viewTable    = T::$section8View; 
    $this->_indexMain    = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_mapping      = Helper::getMapping(['tableName'=>T::$section8]);
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $op        = isset($req['op']) ? $req['op'] : 'index';
    $page      = $this->_viewPath  . 'index';
    $initData  = $this->_getColumnButtonReportList($req);
    switch($op){
      case 'column':
        return $initData;
      case 'show':
        $vData                 = V::startValidate(['rawReq'=>$req->all(),'rule'=>GridData::getRule()])['data'];
        $vData['defaultSort']  = ['group1.keyword:asc','prop.keyword:asc','unit.keyword:asc','tenant:desc'];
        $qData                 = GridData::getQuery($vData,$this->_viewTable);
        $r                     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r,$vData,$req);
      default:
        return view($page,[
          'data' => [
            'nav'     => $req['NAV'],
            'account' => Account::getHtmlInfo($req['ACCOUNT']),
            'initData'=> $initData,
          ]
        ]);
    }
  }
//------------------------------------------------------------------------------
  public function edit($id,Request $req){
    $page     = $this->_viewPath . 'edit';
    $r        = Elastic::searchMatch($this->_viewTable,['match'=>['section_8_id'=>$id]]);
    $r        = Helper::getElasticResult($r,1)['_source'];

    $form     = Form::generateForm([
      'tablez'       => $this->_getTable(__FUNCTION__),
      'orderField'   => $this->_getOrderField(__FUNCTION__),
      'button'       => $this->_getButton(__FUNCTION__,$req),
      'setting'      => $this->_getSetting(__FUNCTION__,$req,$r)
    ]);

    return view($page,[
      'data'  => [
        'form'  => $form,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function update($id,Request $req){
    $req->merge(['section_8_id'=>$id]);
    $valid   = V::startValidate([
      'rawReq'         => $req->all(),
      'tablez'         => $this->_getTable(__FUNCTION__),
      'setting'        => $this->_getSetting(__FUNCTION__,$req),
      'includeCdate'   => 0,
      'includeUsid'    => 1,
      'validateDatabase' => [
        'mustExist'  => [
          T::$section8 . '|section_8_id',
        ]
      ]
    ]);

    $vData       = $valid['dataNonArr'];
    $msgKey      = count(array_keys($vData)) > 3 ? 'msg' : 'mainMsg';
    $updateData  = [
      T::$section8  => ['whereData'=>['section_8_id'=>$id],'updateData'=>$vData],
    ];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    try {
      $success   += Model::update($updateData);
      $elastic    = [
        'insert'  => [$this->_viewTable=>['s.section_8_id'=>[$id]]],
      ];
      $response[$msgKey]  = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success'  => $success,
        'elastic'  => $elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page     = $this->_viewPath . 'create';
    $form     = Form::generateForm([
      'tablez'       => $this->_getTable(__FUNCTION__),
      'orderField'   => $this->_getOrderField(__FUNCTION__),
      'button'       => $this->_getButton(__FUNCTION__,$req),
      'setting'      => $this->_getSetting(__FUNCTION__),
    ]);

    return view($page,[
      'data' => [
        'form' => $form,
      ],
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid    = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'orderField'  => $this->_getOrderField(__FUNCTION__,$req),
      'setting'     => $this->_getSetting(__FUNCTION__,$req),
      'includeUsid' => 1,
      'validateDatabase' => [
        'mustExist' => [
          T::$tenant . '|prop,unit,tenant',
        ],
      ],
    ]);

    $vData    = $valid['data'];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try {
      $success += Model::insert([T::$section8 => $vData]);
      $elastic  = [
        'insert'  => [
          $this->_viewTable => ['section_8_id'=>$success['insert:' . T::$section8]],
        ],
      ];
      $response['msg']   = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success'  => $success,
        'elastic'  => $elastic,
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg']  = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getTable($fn){
    $tablez = [
      'edit'   => [T::$section8],
      'create' => [T::$section8],
      'store'  => [T::$section8],
      'update' => [T::$section8],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $perm       = Helper::getPermission($req);
    $orderField = [
      'edit'   => ['section_8_id','prop','unit','tenant','first_inspection_date','status','second_inspection_date','status2','third_inspection_date','status3','remarks','usid'],
      'create' => ['prop','unit','tenant','first_inspection_date','status','second_inspection_date','status2','third_inspection_date','status3','remarks'],
    ];

    $orderField['store']   = isset($perm['section8create']) ? $orderField['create'] : [];
    $orderField['update']  = isset($perm['section8edit']) ? $orderField['edit'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[],$default=[]){
    $perm      = Helper::getPermission($req);
    $disabled  = isset($perm['section8update']) ? [] : ['disabled'=>1];
    $rUnit = !empty($default) ? Helper::keyFieldName(M::getUnit(Model::buildWhere(['prop'=>$default['prop']]), ['unit'], 0), 'unit', 'unit') : [''=>'Select Unit'];

    $setting   = [
      'create' => [
        'field'  => [
          'prop'                    => ['label'=>'Property Number','class'=>'autocomplete'],
          'unit'                    => ['type'=>'option','option'=>$rUnit],
          'tenant'                  => ['label'=>'Tenant #'],
          'first_inspection_date'   => ['label'=>'First Inspec. Date','value'=>date('m/d/Y')],
          'status'                  => ['type'=>'option','option'=>$this->_mapping['status']],
          'second_inspection_date'  => ['label'=>'Second Inspec. Date','value'=>'01/01/1000'],
          'status2'                 => ['label'=>'Status 2','type'=>'option','option'=>$this->_mapping['status2']],
          'third_inspection_date'   => ['label'=>'Third Inspec. Date','value'=>'01/01/1000'],
          'status3'                 => ['label'=>'Status 3','type'=>'option','option'=>$this->_mapping['status3']],
          'remarks'                 => ['label'=>'Remark','type'=>'textarea'],
        ],
      ],
      'edit'   => [
        'field' => [
          'section_8_id'                   => ['type'=>'hidden'],
          'prop'                           => ['type'=>'hidden'],
          'unit'                           => ['type'=>'hidden'],
          'tenant'                         => ['type'=>'hidden'],
          'first_inspection_date'          => ['label'=>'First Inspec. Date','value'=>date('m/d/Y')] + $disabled,
          'status'                         => ['type'=>'option','option'=>$this->_mapping['status']] + $disabled,
          'second_inspection_date'         => ['label'=>'Second Inspec. Date','value'=>'01/01/1000'] + $disabled,
          'status2'                        => ['label'=>'Status 2','type'=>'option','option'=>$this->_mapping['status2']] + $disabled,
          'third_inspection_date'          => ['label'=>'Third Inspec. Date','value'=>'01/01/1000'] + $disabled,
          'status3'                        => ['label'=>'Status 3','type'=>'option','option'=>$this->_mapping['status3']] + $disabled,
          'remarks'                        => ['label'=>'Remark','type'=>'textarea'] + $disabled,
          'usid'                           => ['label'=>'Last Updated By', 'req'=>0, 'readonly'=>1] + $disabled,
        ],
      ]
    ];

    $setting['update'] = isset($perm['section8edit']) ? $setting['edit'] : [];
    $setting['store']  = isset($perm['section8create']) ? $setting['create'] : [];

    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value']                    = $v;
        $setting[$fn]['field']['first_inspection_date']['value']    = Format::usDate($default['first_inspection_date']);
        $setting[$fn]['field']['second_inspection_date']['value']   = Format::usDate($default['second_inspection_date']);
        $setting[$fn]['field']['third_inspection_date']['value']    = Format::usDate($default['third_inspection_date']);
      }
    }
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $perm    = Helper::getPermission($req);
    $button  = [
      'edit'    => isset($perm['section8update']) ? ['submit'=>['id'=>'submit','value'=>'Update Inspection','class'=>'col-sm-12']] : [],
      'create'  => isset($perm['section8create']) ? ['submit'=>['id'=>'submit','value'=>'Create Inspection','class'=>'col-sm-12']] : []
    ];
    return $button[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r,$vData,$req){
    $perm       = Helper::getPermission($req);
    $rows       = [];
    $r          = Helper::getElasticResult($r,0,1);
    $actionData = $this->_getActionData($perm);
    foreach($r['data'] as $i => $v){
      $source            = $v['_source'];
      $source['num']     = $vData['offset']  + $i + 1;
      $source['action']  = $actionData['icon'];
      $source['status']  = isset($perm['section8update']) ? $source['status'] : $this->_mapping['status'][$source['status']];
      $source['status2'] = isset($perm['section8update']) ? $source['status2'] : $this->_mapping['status2'][$source['status2']];
      $source['status3'] = isset($perm['section8update']) ? $source['status3'] : $this->_mapping['status3'][$source['status3']];
      
      $source['first_inspection_date']  = $source['first_inspection_date'] !== '1000-01-01' ? $source['first_inspection_date']  : '';
      $source['second_inspection_date'] = $source['second_inspection_date'] !== '1000-01-01' ? $source['second_inspection_date']  : '';
      $source['third_inspection_date']  = $source['third_inspection_date'] !== '1000-01-01' ? $source['third_inspection_date']  : '';
      
      $source['remarks'] = !empty($source['remarks']) ? $source['remarks'] : '';
      $rows[] = $source;
    }

    return ['rows'=>$rows,'total'=>$r['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm        = Helper::getPermission($req);
    $actionData  = $this->_getActionData($perm);
    
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList  = isset($perm['section8Export']) ? ['csv'=>'Export to CSV'] : [];

    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['section8create'])){
        $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-plus-square']) . ' New',['id'=>'new','class'=>'btn btn-success']) . ' ';
      }
      return $button;
    };

    ### COLUMNS SECTION ###
    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      if(isset($perm['section8update'])){
        $data['editable'] = ['type'=>'select','source'=>$source];
      }
      return $data;
    };

    $section8Editable = isset($perm['section8update']) ? ['editable'=>['type'=>'text']] : [];
    $data[]           = ['field'=>'num','title'=>'#','width'=>25];
    if(!empty($actionData['icon'])){
      $data[] = ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> $actionData['width']];
    }
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'unit', 'title'=>'Unit','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'tenant', 'title'=>'Tnt','sortable'=> true, 'filterControl'=> 'input', 'width'=> 25];
    $data[] = ['field'=>'tnt_name','title'=>'Name','sortable'=>true,'filterControl'=>'input','width'=>250];
    $data[] = ['field'=>'group1', 'title'=>'Group', 'filterControl'=> 'input', 'sortable'=> true, 'width'=> 25];
    $data[] = ['field'=>'trust','title'=>'Trust','filterControl'=>'input','sortable'=>true,'width'=>25];
    $data[] = ['field'=>'prop_name', 'title'=>'Property Name', 'sortable'=> true, 'filterControl'=> 'input', 'width'=> 300];
    $data[] = ['field'=>'street', 'title'=>'Street','sortable'=> true, 'filterControl'=> 'input', 'width'=> 250];
    $data[] = ['field'=>'city', 'title'=>'City','sortable'=> true, 'filterControl'=> 'input', 'width'=> 200];
    $data[] = ['field'=>'first_inspection_date', 'title'=>'1st Inspec.','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $section8Editable;
    $data[] = $_getSelectColumn($perm,'status','Status',35,$this->_mapping['status']);
    $data[] = ['field'=>'second_inspection_date', 'title'=>'2nd Inspec.','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $section8Editable;
    $data[] = $_getSelectColumn($perm,'status2','Status',35,$this->_mapping['status2']);
    $data[] = ['field'=>'third_inspection_date', 'title'=>'3rd Inspec.','sortable'=> true, 'filterControl'=> 'input', 'width'=> 50] + $section8Editable;
    $data[] = $_getSelectColumn($perm,'status3','Status',35,$this->_mapping['status3']);
    $data[] = ['field'=>'remarks', 'title'=>'Remarks','sortable'=> true, 'filterControl'=> 'input'] + $section8Editable;

    return ['columns'=>$data,'reportList'=>$reportList,'button'=>$_getButtons($perm)];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getSuccessMsg($name){
    $data = [
      'update'  => Html::sucMsg('Successfully Updated Inspection'),
      'store'   => Html::sucMsg('Successfully Created Inspection'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionData($perm){
    $actionData = [];
    if(isset($perm['section8edit'])){
      $actionData[] = '<a class="edit" href="javascript:void(0)"><i class="fa fa-edit text-aqua pointer tip" title="Edit Section 8"></i></a>';
    } 

    $num = count($actionData);
    return ['icon'=>$actionData,'width'=>$num*42];
  }
}
