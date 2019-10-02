<?php
namespace App\Http\Controllers\Report\AccountingReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, Account, TableName AS T, Helper, Elastic};
use App\Http\Models\{Model, ReportModel AS M}; // Include the models class

class AccountingReportController extends Controller{
  private $_viewPath = 'app/Report/accountingReport/';
  private $_mapping  = [];
  private static $_instance;
  
  public function __construct(){
    $this->_mapping = Helper::getMapping(['tableName'=>T::$prop]);
  }
  
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $page   = $this->_viewPath . 'index';
    $perm   = Helper::getPermission($req);
    $fields = [
      'report'=>['id'=>'report','label'=>'Report','type'=>'option','option'=>[''=>''], 'req'=>1],
    ];

    $button1  = $button2 = $buttonContainer = '';
    $button1 .= Html::div(Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New Report', ['id'=> 'createReport', 'class'=>'btn btn-success col-xs-12']) . ' ', ['class'=>'col-xs-6']);
    $button1 .= Html::div(Html::button(Html::i('', ['class'=>'fa fa-fw fa-trash']) . ' Delete Report',['id'=>'destroyReport','class'=>'btn btn-danger col-xs-12 tip', 'title'=>'Delete Report']) . ' ', ['class'=>'col-xs-6']);
    
    $button2 .= Html::div(Html::button(Html::i('', ['class'=>'fa fa-fw fa-group']) . ' New Group', ['id'=> 'createGroup', 'class'=>'btn btn-primary col-xs-12']) . ' ', ['class'=>'col-xs-6']);
    $button2 .= Html::div(Html::button(Html::i('', ['class'=>'fa fa-fw fa-list']) . ' New List', ['id'=> 'createList', 'class'=>'btn btn-warning col-xs-12']) . ' ', ['class'=>'col-xs-6']);
    $buttonDiv = Html::div(Html::div($button1, ['class'=>'row margin-bottom']) . Html::div($button2, ['class'=>'row margin-bottom']), ['id'=>'buttonContainer']);
    $buttonContainer .= isset($perm['accountingReportmodify']) ? $buttonDiv : '';

    $accordionData['Report Form']    = Html::div('', ['id'=>'submitForm']); 
    $accordionData['Report Creator'] = Html::div('', ['id'=>'sortableForm', 'class'=>'list-group']);    
    $accordion = Html::buildAccordion($accordionData, 'accordion', ['class'=>'panel box']);
    return view($page, ['data'=>[
      'reportHeader' => 'SELECT REPORT', 
      'reportForm'   => Form::getField($fields['report']) . $buttonContainer,
      'nav'          => $req['NAV'],
      'account'      => Account::getHtmlInfo($req['ACCOUNT']),
      'dropdownData' => $this->_getReportListJson(),
      'accordion'    => $accordion
    ]]);
  }
//------------------------------------------------------------------------------
  public function create() {
    $page = $this->_viewPath . 'create';
    $form = Form::generateForm([
      'tablez'    =>[T::$reportName], 
      'orderField'=>['report_name'], 
      'button'    =>['submit'=>['id'=>'submit', 'value'=>'Submit', 'class'=>'col-sm-12']],
    ]);
    return view($page, [
      'data'=>[
        'form' => $form,
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req) {
    $req->merge(['report_name_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => [T::$reportName],
      'includeCdate'=>0,
      'isPopupMsgError' =>1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$reportName.'|report_name_id'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $perm  = Helper::getPermission($req);
    $sortablePerm = isset($perm['accountingReportmodify']) ? 'nested-sortable' : '';
    $reportGroup   = M::getReportGroup(['report_name_id'=>$vData['report_name_id']]);
    $reportGroupId = array_column($reportGroup, 'report_group_id');
    $reportList    = M::getReportList($reportGroupId);
    $sortable = '';
    foreach($reportGroup as $group) {
      $nestedSortable = '';
      foreach($reportList as $list) {
        if($group['report_group_id'] == $list['report_group_id']) {
          $editList  = Html::a(Html::i('', ['class'=>'fa fa-edit text-aqua pointer tip', 'title'=>'Edit List']), ['class'=>'listEdit', 'data-key'=>$list['report_list_id']]);
          $trashList = Html::a(Html::i('', ['class'=>'fa fa-trash-o text-red pointer tip', 'title'=>'Remove List']), ['class'=>'listRemove', 'data-key'=>$list['report_list_id']]);
          $iconsList = isset($perm['accountingReportmodify']) ? Html::div($editList . ' | ' . $trashList, ['class'=>'listIcon']) : '';
          $title = Html::div($list['name_list'], ['class'=>'listTitle']);
          $nestedSortable .= Html::div($iconsList . $title, ['class'=>'nested-2 list text-hover-list', 'data-order'=>$list['order'], 'data-id'=>$list['report_list_id']]);
        }
      }
      $editGroup  = Html::a(Html::i('', ['class'=>'fa fa-edit text-aqua pointer tip', 'title'=>'Edit Group']), ['class'=>'groupEdit', 'data-key'=>$group['report_group_id']]);
      $trashGroup = Html::a(Html::i('', ['class'=>'fa fa-trash-o text-red pointer tip', 'title'=>'Remove Group']), ['class'=>'groupRemove', 'data-key'=>$group['report_group_id']]);
      $iconsGroup = isset($perm['accountingReportmodify']) ? Html::div($editGroup . ' | ' . $trashGroup, ['class'=>'groupIcon']) : '';
      $sortable .= Html::div(Html::b($group['name_group']) . $iconsGroup . Html::div($nestedSortable, ['class'=>'list-group group '. $sortablePerm]),['class'=>'list-group-item nested-1 text-hover', 'data-order'=>$group['order'], 'data-id'=>$group['report_group_id']]);
    }
   
    return [
      'sortable'     => $sortable,
      'sortablePerm' => $sortablePerm,
      'submitForm'   => self::getReportForm()
    ];
  }
//------------------------------------------------------------------------------
  public function store(Request $req) {
    $valid = V::startValidate([
      'rawReq'     => $req->all(),
      'tablez'     => [T::$reportName], 
      'orderField' => ['report_name'],
      'includeUsid'=>1,
    ]);
    $vData = $valid['data'];

    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $response = $success = $elastic = [];
    try{
      $success = Model::insert([T::$reportName=>$vData]);
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      $response['dropdownData'] = $this->_getReportListJson();
      $response['reportNameId'] = $success['insert:'.T::$reportName];
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
        'report_name_id' => $id,
      ],
      'tablez' => [T::$reportName],
      'includeCdate'=> 0,
      'isPopupMsgError' =>1,
      'validateDatabase' => [
        'mustExist' => [
          T::$reportName.'|report_name_id',
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    $rGroup = M::getReportGroup(Model::buildWhere(['report_name_id'=>$vData['report_name_id']]));
    $groupId = array_column($rGroup, 'report_group_id');
 
    if(!empty($groupId)) {
      $rList  = M::getReportList($groupId);
      $listId = array_column($rList, 'report_list_id');
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    try{
      $success[T::$reportName] = M::deleteTableData(T::$reportName,Model::buildWhere(['report_name_id'=>$vData['report_name_id']]));
      if(!empty($groupId)) {
        $success[T::$reportGroup] = M::deleteWhereInTableData(T::$reportGroup, 'report_group_id', $groupId);
      }
      if(!empty($listId)) {
        $success[T::$reportList] = M::deleteWhereInTableData(T::$reportList, 'report_list_id', $listId);
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
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'store'   =>Html::sucMsg('Successfully Created.'),
      'destroy' =>Html::sucMsg('Successfully Deleted.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getReportListJson() {
    $rReport = M::getReportName();
    foreach($rReport as $k => $report) {
      $rReport[$k]['id'] = $report['report_name_id'];
      $rReport[$k]['text'] = $report['report_name'];
      unset($rReport[$k]['report_name_id'], $rReport[$k]['report_name']);
    }
    return json_encode($rReport);
  }
//------------------------------------------------------------------------------
  private static function _getPropGroup() {
    $r = [];
    $search = Helper::getElasticResult(Elastic::searchQuery([
      'index'   => T::$groupView,
      '_source' => ['prop'],
      'sort'    => ['prop.keyword']
    ]));
    foreach($search as $i=>$val){
      $r[$val['_source']['prop']] = $val['_source']['prop']; 
    }
    return $r;
  }
//------------------------------------------------------------------------------
  public static function getGlSum($groupBy, $prop, $glList, $date, $acctType = []) {
    $bool = [];
    $terms      = is_array($prop) ? 'terms' : 'term';
    $isMustNot  = preg_match('/^!/', $glList);
    $glAcct     = $isMustNot ? str_replace('!', '', $glList) : $glList;
    $glExploded = !empty($glAcct) ? Helper::explodeGlAcct($glAcct) : [];
    $should     = $isMustNot ? $acctType : array_merge($glExploded,$acctType);
    $bool['bool']['should'] = $should;
    if($isMustNot) {
      $bool['bool']['must_not'] = $glExploded;
    }
    return Helper::getElasticAggResult(Elastic::searchQuery([
      'size' => 0,
      'index'=> T::$glTransView,
      'query'    => [
        'raw' => [
          'must'  => [
            [ 
              $terms =>Helper::getPropMustQuery(['prop'=>$prop], [], 0)
            ],
            $bool,
            [
              'range'=>['date1'=>['gte'=>$date['date1'],'lte'=>$date['todate1']]],
            ]
          ]
        ]  
      ],
      'aggs'       => [
        'by_'.$groupBy  => [
          'terms'     => [
            'field'   => $groupBy.'.keyword',
            'size'    => 10000
          ],
          'aggs'      => [
            'total_amount'  => [
              'sum'   => [
                'field' => 'amount',
              ]
            ]
          ]
        ]
      ]
    ]), 'by_'.$groupBy);
  }

//------------------------------------------------------------------------------
  public static function getPropInformations($propList) {
    $r = [];
    $search = Helper::getElasticResult(Elastic::searchQuery([
      'index'=>T::$propView,
      'query'=>[
        'must'=>['prop'=>$propList],
      ],
      '_source'=>['prop','prop_class','street','city','state','zip','po_value','start_date','number_of_units'],
      'sort'=>['prop.keyword']
    ]));
    foreach($search as $i=>$val){
      $r[$val['_source']['prop']] = $val['_source']; 
    }
    return $r;
  }
//------------------------------------------------------------------------------
  public static function getReportForm($sum = 0) {
    $propGroup  = self::_getPropGroup();  
    $groupBy    = ['prop'=>'Prop', 'consolidate'=>'Consolidate Sum'];
    $formFields = [
      'dateRange' => ['id'=>'dateRange','name'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange','value'=>date('01/01/Y').' - '.Helper::usDate(), 'req'=>1],
      'prop'      => ['id'=>'prop','name'=>'prop','label'=>'Prop','type'=>'textarea','value'=>'0001-9999'],
      'group1'    => ['id'=>'group1','name'=>'group1','label'=>'Group','type'=>'option', 'option'=>[''=>'Select Group'] + $propGroup,'req'=>0],
      'city'      => ['id'=>'city','name'=>'city','label'=>'City','type'=>'textarea', 'placeHolder'=>'Ex. Alameda-Fresno,Torrance'],
      'cons1'     => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'****83-**83,Z64'],
      'trust'     => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'****83-**83,*ZA67'],
      'prop_type' => ['id'=>'prop_type','name'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + self::getInstance()->_mapping['prop_type']],
    ];
    if($sum) {
      $formFields['groupBy'] = ['id'=>'groupBy','name'=>'groupBy','label'=>'Group By','type'=>'option','option'=>$groupBy,'req'=>1];
    }
    return implode('',Form::generateField($formFields));
  }
//------------------------------------------------------------------------------
  public static function getGlSumAmount($array) {
    $sum = 0;
    foreach($array as $value) {
      $sum += $value['total_amount']['value'];
    }
    return $sum;
  }
//------------------------------------------------------------------------------
  public static function getAccTypeTermQuery($accTypeString) {
    $acctTypeArray = [];
    $acctTypeList = explode(',', $accTypeString);
    foreach($acctTypeList as $type){
      $acctTypeArray[] = ['term'=>[ 'acct_type.keyword'=>$type]];
    }
    return $acctTypeArray;
  }
//------------------------------------------------------------------------------
  public static function getProps($vData) {
    $propList = Helper::explodeField($vData,['prop','trust', 'group1', 'city','prop_type'])['prop'];
    $rProps   = self::getPropInformations($propList);
    return array_keys($rProps);
  }
}
