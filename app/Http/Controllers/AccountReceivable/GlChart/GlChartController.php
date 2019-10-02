<?php
namespace App\Http\Controllers\AccountReceivable\GlChart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, GridData, Account, HelperMysql, TableName AS T};
use App\Http\Models\{Model,GlChartModel as M}; // Include the models class

class GlChartController extends Controller{
  private $_viewPath  = 'app/AccountReceivable/GlChart/glChart/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingGlchart = Helper::getMapping(['tableName'=>T::$glChart]);
    $this->_viewTable = T::$glChartView;
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
       // $vData['defaultFilter'] = ['prop'=>'(NOT [0001 TO 9999])', 'acct_type'=>'A,C,E,I,L'];
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
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
   
    $formGlChart = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'button'    =>$this->_getButton(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $req)
    ]);
    
    return view($page, [
      'data'=>[
        'formGlChart'   => $formGlChart
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
          T::$prop.'|prop'
        ],
        'mustNotExist' => [
           T::$glChart.'|prop,gl_acct'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $insertData = [];
    if($vData['prop'] == 'Z64') {
      $rProps = M::getNumberProps();
      foreach($rProps as $i => $v) {
        $source = $v['_source'];
        $insertData[] = [
            'prop'      => $source['prop'],
            'gl_acct'   => $vData['gl_acct'],
            'title'     => $vData['title'],
            'acct_type' => $vData['acct_type'],
            'no_post'   => $vData['no_post'],
            'type1099'  => $vData['type1099'],
            'remarks'   => $vData['remarks'],
            'usid'      => $vData['usid']
        ];
      }
    }
    $insertData[] = $vData;
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success += Model::insert([T::$glChart=>$insertData]);
      
      $elastic = [
        'insert'=>[$this->_viewTable=>['gl_chart_id'=>$success['insert:' . T::$glChart]]]
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
    $formGlChart = Form::generateForm([
      'tablez'    =>$this->_getTable($fn), 
      'button'    =>$this->_getButton($fn, $req), 
      'orderField'=>$this->_getOrderField($fn, $req), 
      'setting'   =>$this->_getSetting($fn, $req)
    ]);

    return view($page, [
      'data'=>[
        'formGlChart' => $formGlChart
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
          T::$glChart.'|prop,gl_acct',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];

    $rGlChart = HelperMysql::getGlChart(['prop.keyword'=>$vData['prop'], 'gl_acct.keyword'=>$vData['gl_acct']], ['gl_chart_id'], [], 1);
    $glChartId = [$rGlChart['gl_chart_id']];
      
    if($vData['prop'] == 'Z64') {
      $rGlChart = M::getNumberPropsGlChartId($vData['gl_acct']);
      $glChartId = array_merge($glChartId, array_column($rGlChart, 'gl_chart_id'));
    }
    unset($vData['prop']);
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$glChart=>['whereInData'=>['field'=>'gl_chart_id','data'=>$glChartId], 'updateData'=>$vData],
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['gl_chart_id'=>$glChartId]]
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
          T::$glChart.'|prop,gl_acct'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $rGlChart = HelperMysql::getGlChart(['prop.keyword'=>$vData['prop'], 'gl_acct.keyword'=>$vData['gl_acct']], ['gl_chart_id'], [], 1);
    $glChartId = [$rGlChart['gl_chart_id']];
      
    if($vData['prop'] == 'Z64') {
      $rGlChart = M::getNumberPropsGlChartId($vData['gl_acct']);
      $glChartId = array_merge($glChartId, array_column($rGlChart, 'gl_chart_id'));
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      $success[T::$glChart] = DB::table(T::$glChart)->whereIn('gl_chart_id', $glChartId)->delete();
      $commit['success'] = $success;
      $commit['elastic']['delete'][T::$glChartView] = ['gl_chart_id'=>$glChartId];
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
      'create' => [T::$glChart],
      'update' => [T::$glChart],
      'destroy'=> [T::$glChart]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req=[]){
    $perm = Helper::getPermission($req);
    $orderField = [
      'create'  => ['prop', 'gl_acct', 'title', 'acct_type', 'no_post', 'type1099', 'remarks'],
      'update'  => ['prop', 'gl_acct', 'title', 'acct_type', 'no_post', 'type1099', 'remarks'],
      'destroy' => ['prop', 'gl_acct']
    ];
    
    # IF USER DOES NOT HAVE PERMISSION, RETURN EMPTY ARRAY
    $orderField['update'] = isset($perm['glchartedit']) ? $orderField['update'] : [];
    $orderField['store']  = isset($perm['glchartcreate']) ? $orderField['create'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req=[], $default = []){
    $perm = Helper::getPermission($req);
    $disabled = isset($perm['glchartupdate']) ? [] : ['disabled'=>1];
    $rProps = M::getPropsExeceptNumbers();
    $rProps = Helper::keyFieldName($rProps, 'key', 'key');
    $rProps[''] = 'Select';
    $rGlAcct = [];
    if($fn == 'update' || $fn == 'destroy') {
      $rGlAcct = M::getGlAccts();
      $rGlAcct = Helper::keyFieldName($rGlAcct, 'key', 'key');
      $rGlAcct[''] = 'Select';
    }
       
    $setting = [
      'create' => [
        'field' => [
          'prop'        => ['type'=>'option', 'option'=>$rProps, 'value'=>'Z64'],
          'gl_acct'     => ['label'=>'GL Account'],
          'acct_type'   => ['type'=>'option', 'option'=>$this->_mappingGlchart['acct_type']],
          'type1099'    => ['label'=>'Type 1099', 'type'=>'option', 'option'=>$this->_mappingGlchart['type1099']],
          'no_post'     => ['req'=>0, 'type'=>'option', 'option'=>$this->_mappingGlchart['no_post']],
          'remarks'     => ['req'=>0],
        ],
        'rule' => [
          'no_post' =>'nullable|string',
          'remarks' =>'nullable|string'
        ]
      ],
      'update' => [
        'field' => [
          'prop'        => ['type'=>'option', 'option'=>$rProps, 'value'=>'Z64'],
          'gl_acct'     => ['label'=>'GL Account', 'type'=>'option', 'option'=>$rGlAcct, 'value'=>' '],
          'acct_type'   => ['type'=>'option', 'option'=>$this->_mappingGlchart['acct_type']],
          'type1099'    => ['label'=>'Type 1099', 'type'=>'option', 'option'=>$this->_mappingGlchart['type1099']],
          'no_post'     => ['req'=>0, 'type'=>'option', 'option'=>$this->_mappingGlchart['no_post']],
          'remarks'     => ['req'=>0],
        ],
        'rule' => [
          'no_post' =>'nullable|string',
          'remarks' =>'nullable|string'
        ]
      ],
      'destroy'  => [
        'field' => [
          'prop'    => ['type'=>'option', 'option'=>$rProps, 'value'=>'Z64'],
          'gl_acct' => ['label'=>'GL Account', 'type'=>'option', 'option'=>$rGlAcct, 'value'=>' '],
        ]
      ]
    ];
    
    $setting['update'] = isset($perm['glchartupdate']) ? $setting['update'] : [];
    $setting['store']  = isset($perm['glchartcreate']) ? $setting['create'] : [];
    
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
      'update' =>isset($perm['glchartupdate']) ? ['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']] : [],
      'create' => ['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']],
      'destroy'=> ['submit'=>['id'=>'submit', 'value'=>'Delete', 'class'=>'col-sm-12']],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r, $vData, $req){
    $perm = Helper::getPermission($req);
    $rows = [];
 
    foreach($r['hits']['hits'] as $i=>$v){
      $source = $v['_source']; 
      $source['num']  = $vData['offset'] + $i + 1;
      $rows[] = $source;
    }
    return ['rows'=>$rows, 'total'=>$r['hits']['total']];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm = Helper::getPermission($req);

    ### REPORT AND EXPORT LIST SECTION ###
    $reportList = [];
    if(isset($perm['glChartReport'])){
      $reportList['GlChartReport'] = 'GL Account Report';
      $reportList['csv']           = 'Export to CSV';
    }

    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['glchartcreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn btn-success']) . ' ';
      }
      if(isset($perm['glchartedit'])){
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-edit']) . ' Update', ['id'=> 'update', 'class'=>'btn btn-info']) . ' ';
      }
      if(isset($perm['glchartdestroy'])){
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-trash']) . ' Delete', ['id'=> 'delete', 'class'=>'btn btn-danger']) . ' ';
      }
      return $button;
    };
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 50];
    $data[] = ['field'=>'prop', 'title'=>'Prop','sortable'=> true, 'filterControl'=> 'input', 'width'=> 125];
    $data[] = ['field'=>'gl_acct', 'title'=>'GL Account','sortable'=> true, 'filterControl'=> 'input', 'width'=> 125];
    $data[] = ['field'=>'title', 'title'=>'Title','sortable'=> true, 'filterControl'=> 'input', 'width'=> 350];
    $data[] = ['field'=>'acct_type', 'title'=>'Account Type','sortable'=> true, 'filterControl'=> 'select','filterData'=> 'url:/filter/acct_type:' . T::$glChartView, 'width'=> 125];
    $data[] = ['field'=>'no_post', 'title'=>'No Post','sortable'=> true, 'filterControl'=> 'select','filterData'=> 'url:/filter/no_post:' . T::$glChartView, 'width'=> 50];
    $data[] = ['field'=>'type1099', 'title'=>'Type 1099','sortable'=> true, 'filterControl'=> 'select','filterData'=> 'url:/filter/type1099:' . T::$glChartView, 'width'=> 50];
    $data[] = ['field'=>'remarks', 'title'=>'Remarks', 'sortable'=> true, 'filterControl'=> 'input'];
   
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'update' =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
      'destroy'=>Html::sucMsg('Successfully Deleted.')
    ];
    return $data[$name];
  }
}