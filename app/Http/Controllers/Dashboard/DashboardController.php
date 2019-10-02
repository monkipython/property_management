<?php
namespace App\Http\Controllers\Dashboard;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Account, TableName AS T, Helper};

class DashboardController extends Controller {
  private $_viewPath    = 'app/Dashboard/dashboard/';
  private $_timerSelect = [''=>'Do Not Refresh','5'=>'5 Minutes','10'=>'10 Minutes','15'=>'15 Minutes','20'=>'25 Minutes','30'=>'30 Minutes'];
  private $_classRefs   = [
    'vacancyReport'      => 'App\Http\Controllers\Report\VacancyReport\VacancyReportController',
    //'supervisorReport'   => 'App\Http\Controllers\Report\SupervisorReport\SupervisorReportController',
  ];
  private $_checkList   = [
    'vacancyReport'    => ['title'=>'Vacancy Histogram'], 
    //'supervisorReport' => ['title'=>'Supervisor Group Chart'],
  ];

  public function __construct(){

  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $checkList  = $this->_renderCheckList($this->_checkList);
    $page       = $this->_viewPath . 'index';
    $chartHtml  = '';
    foreach($this->_checkList as $k => $v){
      $chartHtml .= $this->_createChartBox($k,$v);
    }

    return view($page,[
      'data' => [
        'nav'      => $req['NAV'],
        'account'  => Account::getHtmlInfo($req['ACCOUNT']),
        'dashboard'     => [
          'timerOptions' => implode('',Form::generateField(['refreshRate'=>['name'=>'refreshRate','id'=>'refreshRate','label'=>'Refresh Rate','type'=>'option','option'=>$this->_timerSelect,'value'=>'5']])),
          'checkList'    => $checkList,
          'charts'       => $chartHtml
        ]
      ]
    ]);
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _renderCheckList($options){
    $html = '';
    
    foreach($options as $k => $v){
      $types = property_exists($this->_classRefs[$k]::getInstance(),'typeOption') ? $this->_classRefs[$k]::getInstance()->typeOption : [];
      $html .= Html::input($k,['class'=>'form-check-input chart-checkbox','id'=>$k,'type'=>'checkbox','checked'=>'checked'] + (!empty($types) ? ['data-types'=>implode(',',array_keys($types))] : []));
      $html .= Html::tag('label',$v['title'],['class'=>'form-check-label','for'=>$k]);
      $html .= '&nbsp;&nbsp;&nbsp;';
    }

    $html = Html::div($html,['class'=>'form-check form-check-inline','style'=>'visibility:hidden;']);
    return $html;
  }
//------------------------------------------------------------------------------
  private function _createChartBox($key,$params){
    $tableList    = $this->_createTableList($key,$params);
    $canvasList   = $this->_createCanvasBox($key,$params);
    $canvasTab    = Html::div($canvasList,['class'=>'tab-content no-padding']);
    $chartHtml    = Html::div($tableList . $canvasTab,['class'=>'nav-tabs-custom','style'=>'cursor:move;']);
    return $chartHtml;
  }
//------------------------------------------------------------------------------
  private function _createTableList($key,$v){
    $typeList = property_exists($this->_classRefs[$key]::getInstance(),'typeOption') ? $this->_classRefs[$key]::getInstance()->typeOption : [];
    $firstLi  = true;
    $list     = [];
    
    foreach($typeList as $k=>$val){
      $liHtml  = Html::li(Html::a(!empty($val) ? 'By ' . $val : title_case($v['title']),['href'=>'#' .$key . $k,'data-toggle'=>'tab','aria-expanded'=>'true']),$firstLi ? ['class'=>'active']: []);
      $firstLi = false;
      $list[]  = $liHtml;
    }
    
    return Html::ul(implode('',$list),['class'=>'nav nav-tabs pull-left ui-sortable-handle','id'=>$key . 'tabList']);
  }
//------------------------------------------------------------------------------
  private function _createCanvasBox($key,$v){
    $types    = property_exists($this->_classRefs[$key]::getInstance(),'typeOption') ? $this->_classRefs[$key]::getInstance()->typeOption : [''=>''];
    $html     = '';
    $page     = $this->_viewPath . 'canvasTemplate';
    $firstTab = true;
    foreach($types as $k=>$val){
      $params = [
        'tabId'     => $key . $k,
        'tabClass'  => $firstTab ? 'chart tab-pane active' : 'chart tab-pane',
        'plotTitle' => $v['title'] . (!empty($val) ? ' by ' . $val: ''),
        'canvas'    => Html::div(Html::tag('canvas','',['id'=>$key . 'Chart' . $k]),['id'=>$key . 'Parent' . $k,'class'=>'chart','data-form'=>$key . 'Form' . $k]),
        'formId'    => $key . 'Form' . $k,
        'form'      => $this->_classRefs[$key]::getInstance()->getForm(!empty($val) ? ['type'=>$k] : [])['html']
      ];
      
      $html  .= view($page,[
        'data' => $params
      ])->render();
      $firstTab = false;
    }
    
    return $html;
  }
}