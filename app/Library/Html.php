<?php
namespace App\Library;
class Html {
  public static function sucMsg($str){
    $icon = Html::span('', ['class'=>'icon glyphicon glyphicon glyphicon-ok', 'aria-hidden'=>'true']);
    return self::div($icon . ' ' . $str, ['class'=>'alert alert-success text-center', 'role'=>'alert']);
  }
//------------------------------------------------------------------------------
  public static function errMsg($str, $extraDivClasses = ''){
    $icon = Html::span(' ', ['class'=>'icon  glyphicon glyphicon-exclamation-sign', 'aria-hidden'=>'true']);
    return self::div($icon . ' ' .  $str, ['class'=>'alert alert-danger text-center ' . $extraDivClasses, 'role'=>'alert']);
  }
//------------------------------------------------------------------------------
  public static function warnMsg($str, $extraDivClasses = ''){
    $icon = Html::span(' ', ['class'=>'icon  glyphicon glyphicon-exclamation-sign', 'aria-hidden'=>'true']);
    return self::div($icon . ' ' . $str, ['class'=>'alert alert-warning text-center ' . $extraDivClasses, 'role'=>'alert']);
  }
//------------------------------------------------------------------------------
  public static function mysqlError(){
    return self::errMsg('Please contact sean@pamamgt.com to help with this issue.');
  }
//------------------------------------------------------------------------------
  public static function input($str, $params = []){
    return '<input '. self::listParams($params) .' value="'.$str.'" />';
  }
//------------------------------------------------------------------------------
  public static function label($str, $params = []){
    return '<label '. self::listParams($params) .' >'.$str.'</label>';
  }
//------------------------------------------------------------------------------  
  public static function spanBoldMaroon($str){
    return '<span class="bold_maroon">'. $str .'</span>';
  }
//------------------------------------------------------------------------------  
  public static function spanMaroon($str){
    return '<span class="maroon">'. $str .'</span>';
  }
//------------------------------------------------------------------------------  
  public static function span($str, $params = []){
    return '<span'. self::listParams($params) .'>'.$str.'</span>';
  }
//------------------------------------------------------------------------------
  public static function p($str,$params = []){
    return '<p' . self::listParams($params) . '>' . $str . '</p>';
  }
//------------------------------------------------------------------------------
  public static function button($str, $params = []){
    return '<button'. self::listParams($params) .'>'.$str.'</button>';
  }
//------------------------------------------------------------------------------  
  public static function img($params = []){
    return '<img'. self::listParams($params) .'/>';
  }
//------------------------------------------------------------------------------  
  public static function tr($str, $params = [], $isDoubleQuote = 1){
    return '<tr'. self::listParams($params, $isDoubleQuote) .'>'.$str.'</tr>';
  }
//------------------------------------------------------------------------------  
  public static function td($str, $params = [], $isDoubleQuote = 1){
    return '<td'. self::listParams($params, $isDoubleQuote) .'>'.$str.'</td>';
  }
//------------------------------------------------------------------------------  
  public static function th($str, $params = [], $isDoubleQuote = 1){
    return '<th'. self::listParams($params, $isDoubleQuote) .'>'.$str.'</th>';
  }
//------------------------------------------------------------------------------  
  public static function table($str, $params = [], $isDoubleQuote = 1){
    return '<table'. self::listParams($params, $isDoubleQuote) .'>'.$str.'</table>';
  }
//------------------------------------------------------------------------------  
  public static function tbody($str, $params = []){
    return '<tbody'. self::listParams($params) .'>'.$str.'</tbody>';
  }
//------------------------------------------------------------------------------
  public static function buildOption($optionz, $sltVal, $params = []){
    $option = '';
    foreach($optionz as $k=>$v){
      $sltOpt    = ($k == $sltVal) ? ['selected'=>'selected'] : [];
      $option   .= Html::tag('option', $v, $sltOpt + ['value'=>$k]);
    }
    return Html::tag('select', $option, $params);
  }
//------------------------------------------------------------------------------
  public static function buildCheckbox($isTrue, $params){
    $checked = ($isTrue) ? ' checked' : '';
    return '<input type="checkbox"' . self::listParams($params) .$checked.'>';
  }
//------------------------------------------------------------------------------
  /**
   * @desc This will create a HTML table taking 2 params
   * @param {array} $data will be in the format of 
   *  array(
   *    [0] => Array(
   *      [prop] => Array (
   *        [val] => 0001,
   *        [header]=>['val'=>'Property', 'param'=>[]]
   *        [param]=> [] // it's optional
   *      )
   *      [unit] => Array(
   *        [val] => 0019,
   *        [header]=>['val'=>'Unit', 'param'=>[]],
   *        [param]=> [] // it's optional
   *      )
   *    )
   *  )
   * @param {array} $headeer_data is optional. The format is the same as $data
   * @return {string} will return html in a table format
   */
  public static function createTable($data, $tableParam = [], $haveHeader = 1, $includeOrderList = 1){
    
    $headerData = [];
    if($haveHeader){
      foreach($data as $i=>$val){
        foreach($val as $k=>$v){
          $headerData[$i][$k] = ['val'=>$v['header']['val'], 'param'=>isset($v['header']['param']) ? $v['header']['param'] : []];
        }
        break;
      }
    }
    
    $html = ($haveHeader ? self::listRow($headerData, 1) : '') . self::listRow($data,0, $includeOrderList);
    return Html::table($html, $tableParam);
  }
  /**
   * @desc This will create a HTML table taking 2 params
   * @param {array} $data will be in the format of 
      Html::buildTable([
        'data'=>[
          [
            'desc1'=>['val'=>'Tenant Information', []],   'val1'=>['val'=>'', 'param'=>[]],
            'desc2'=>['val'=>'Other Information'],    'val2'=>['val'=>''],
            'desc3'=>['val'=>'Financial Information'],'val3'=>['val'=>''],
          ],
          [
            'desc1'=>['val'=>'Account Number:'],  'val1'=>['val'=>''],
            'desc2'=>['val'=>'Move In Date:'],    'val2'=>['val'=>''],
            'desc3'=>['val'=>'Security Deposit:'],'val3'=>['val'=>''],
          ],
        ], 'isHeader'=>0, 'isOrderList'=>0
      ])
   * @param {array} $headeer_data is optional. The format is the same as $data
   * @return {string} will return html in a table format
   */
  public static function buildTable($data){
    $tableParam   = isset($data['tableParam']) ? $data['tableParam'] : [];
    $isHeader     = isset($data['isHeader']) ? $data['isHeader'] : 1;
    $isOrderList  = isset($data['isOrderList']) ? $data['isOrderList'] : 1;
    $isAlterColor = isset($data['isAlterColor']) ? $data['isAlterColor'] : 0;
    $data = $data['data'];
    $headerData = [];
    
    $_listRow = function($data, $isHeader, $isOrderList, $isAlterColor){
      $html = '';
      $isDoubleQuote = 1;
      foreach($data as $i=>$value){
        $firstColumnVal = reset($value);
        ## If the row has two headers
        if(isset($firstColumnVal[0]) && $isHeader) {
          $td  = '';
          $td2 = '';
          foreach($value as $j=>$val) {
            if($isOrderList){
              $td  .= Html::th('#',['width'=>15, 'align'=>'center'], $isDoubleQuote);
              $td2 .= Html::th('#',['width'=>15, 'align'=>'center'], $isDoubleQuote);
            }
            foreach($val as $i => $v){
              if($i == 0) {
                $td  .= ($isHeader) ? Html::th($v['val'], (isset($v['param']) ? $v['param'] : []), $isDoubleQuote) : Html::td($v['val'], (isset($v['param']) ? $v['param'] : []), $isDoubleQuote);
              }elseif($i == 1) {
                $td2 .= ($isHeader) ? Html::th($v['val'], (isset($v['param']) ? $v['param'] : []), $isDoubleQuote) : Html::td($v['val'], (isset($v['param']) ? $v['param'] : []), $isDoubleQuote);
              } 
            }
          }
          $color = [];
          $color = $isAlterColor ? $color : [];
          $html .= Html::tr($td, $color, $isDoubleQuote);   
          $html .= Html::tr($td2, $color, $isDoubleQuote); 
        }else {
          $td = '';
          if($isOrderList){
            if($isHeader){
              $td .= Html::th('#',['width'=>15, 'align'=>'center'], $isDoubleQuote);
            } else{
              $td .= Html::td($i + 1, ['width'=>15, 'align'=>'center'], $isDoubleQuote);
            }
          }
          foreach($value as $v){
            $td .= ($isHeader) ? Html::th($v['val'], (isset($v['param']) ? $v['param'] : []), $isDoubleQuote) : Html::td($v['val'], (isset($v['param']) ? $v['param'] : []), $isDoubleQuote);
          }
          $color = (++$i % 2 == 0) ? ['style'=>'background-color: #f5f5f5;'] : [];
          $color = $isAlterColor ? $color : [];
          $html .= Html::tr($td, $color, $isDoubleQuote);
        }
      }
      return $html;
    };
    
    if($isHeader){
      foreach($data as $i=>$val){
        foreach($val as $k=>$v){
          ## If the row has two headers
          if(isset($v['header'][0])) {
            foreach($v['header'] as $j=>$head) {
              $headerData[$i][$k][] = ['val'=>$v['header'][$j]['val'], 'param'=>isset($v['header'][$j]['param']) ? $v['header'][$j]['param'] : []];
            }
          }else {
            $headerData[$i][$k] = ['val'=>$v['header']['val'], 'param'=>isset($v['header']['param']) ? $v['header']['param'] : []];
          }
        }
        break;
      }
    }
    $html = ($isHeader ? $_listRow($headerData, $isHeader, $isOrderList, $isAlterColor) : '') . $_listRow($data,0, $isOrderList, $isAlterColor);
    return Html::table($html, $tableParam);
  }
//------------------------------------------------------------------------------
  public static function spanGreen($str){
     return '<span class="green">'. $str .'</span>';
  }
//------------------------------------------------------------------------------
  public static function spanBoldGreen($str){
     return '<span class="green">'. $str .'</span>';
  }
//------------------------------------------------------------------------------
  public static function a($str, $params = []) {
    return '<a'. self::listParams($params) .'>'.$str.'</a>';
  }  
//------------------------------------------------------------------------------
  public static function b($str, $params = []) {
    return '<b'. self::listParams($params) .'>'.$str.'</b>';
  }  
//------------------------------------------------------------------------------
  public static function u($str, $params = []) {
    return '<u'. self::listParams($params) .'>'.$str.'</u>';
  }  
//------------------------------------------------------------------------------
  public static function bu($str, $params = []) {
    return '<b><u'. self::listParams($params) .'>'.$str.'</u></b>';
  }  
//------------------------------------------------------------------------------
  public static function ub($str, $params = []) {
    return '<u><b'. self::listParams($params) .'>'.$str.'</b></u>';
  }  
//------------------------------------------------------------------------------
  public static function i($str, $params = []) {
    return '<i'. self::listParams($params) .'>'.$str.'</i>';
  }  
//------------------------------------------------------------------------------
  public static function h1($str, $params = []) {
    return '<h1'. self::listParams($params) .'>'.$str.'</h1>';
  }  
  public static function h2($str, $params = []) {
    return '<h2'. self::listParams($params) .'>'.$str.'</h3>';
  }  
  public static function h3($str, $params = []) {
    return '<h3'. self::listParams($params) .'>'.$str.'</h3>';
  }  
  public static function h4($str, $params = []) {
    return '<h4'. self::listParams($params) .'>'.$str.'</h4>';
  }  
  public static function h5($str, $params = []) {
    return '<h5'. self::listParams($params) .'>'.$str.'</h5>';
  }  
  public static function h6($str, $params = []) {
    return '<h6'. self::listParams($params) .'>'.$str.'</h6>';
  }  
//------------------------------------------------------------------------------
  public static function tag($tag, $str, $params = []){
    return '<'. $tag. ' '. self::listParams($params) .'>'.$str.'</'.$tag.'>';
  }
//------------------------------------------------------------------------------  
  public static function li($str, $params = []){
    return '<li'. self::listParams($params) .'>'.$str.'</li>';
  }
//------------------------------------------------------------------------------  
  public static function br($num = 1){
    $str = '';
    for($i = 0; $i < $num; $i++){
      $str .= '<br>' . "\n";
    }
    return $str;
  }
//------------------------------------------------------------------------------  
  public static function space($num = 1){
    $str = '';
    for($i = 0; $i < $num; $i++){
      $str .= '&nbsp;';
    }
    return $str;
  }
//------------------------------------------------------------------------------  
  public static function repeatChar($char, $num = 1){
    $str = '';
    for($i = 0; $i < $num; $i++){
      $str .= $char;
    }
    return $str;
  }
//------------------------------------------------------------------------------  
  public static function ul($str, $params = []){
    return '<ul'. self::listParams($params) .'>'.$str.'</ul>';
  }
//------------------------------------------------------------------------------  
  public static function ol($str, $params = []){
    return '<ol'. self::listParams($params) .'>'.$str.'</ol>';
  }
//------------------------------------------------------------------------------
  public static function liAll($arr, $ulParams = [], $liParams = []){
    $li = '';
    foreach($arr as $v){ $li .= self::li($v, $liParams); }
    return '<ul'. self::listParams($ulParams) .'>'. $li .'</ul>';
  }
//------------------------------------------------------------------------------  
  public static function loAll($arr, $ulParams = [], $liParams = []){
    $li = '';
    foreach($arr as $v){ $li .= self::li($v, $liParams); }
    return '<ol'. self::listParams($ulParams) .'>'. $li .'</ol>';
  }
//------------------------------------------------------------------------------
  public static function div($str, $params = []){
    return '<div'. self::listParams($params) .'>'. $str .'</div>';
  }
//------------------------------------------------------------------------------
  public static function form($str, $params = []){
    return '<form'. self::listParams($params) .'>'.$str.'</form>';
  }
//------------------------------------------------------------------------------
  public static function icon($iconRef, $params = []){
    return self::span('', ['class'=>$iconRef] + $params); 
  }
//------------------------------------------------------------------------------
  public static function iconLink($iconRef, $params, $iconParam = []){
    $params['class'] = isset($params['class']) ? $params['class'] . ' tip' : 'tip';
    return Html::a(self::span('', ['class'=>$iconRef] + $iconParam), $params); 
  }
  //------------------------------------------------------------------------------
  public static function getLinkIcon($r, $iconList = []){
    $icon = [
      'prop'           =>Html::iconLink('fa fa-fw fa-home', ['title'=>'Click to view Property '.Helper::getValue('prop',$r),  'href'=>url('prop?' . http_build_query(Helper::selectData(['prop'], $r))), 'target'=>'_blank']),
      'unit'           =>Html::iconLink('fa fa-fw fa-hotel', ['title'=>'Click to view Unit '.Helper::getValue('unit',$r), 'href'=>url('unit?' . http_build_query(['prop.prop'=>Helper::getValue('prop', $r), 'unit'=>Helper::getValue('unit', $r)])), 'target'=>'_blank']), 
      'tenant'         =>Html::iconLink('fa fa-fw fa-street-view', ['title'=>'Click to view Tenant '.Helper::getValue('tenant',$r), 'href'=>url('tenant?' . http_build_query(Helper::selectData(['prop', 'unit', 'tenant'], $r))), 'target'=>'_blank']), 
      'trust'          =>Html::iconLink('fa fa-fw fa-building',['title'=>'Click to view Trust ' . Helper::getValue('trust',$r),'href'=>url('trust?' . http_build_query(['prop.keyword'=>Helper::getValue('trust',$r)])),'target'=>'_blank']),
      'group1'         =>Html::iconLink('fa fa-fw fa-users',['title'=>'Click to view Group ' . Helper::getValue('group1',$r),'href'=>url('group?' . http_build_query(['prop.keyword'=>Helper::getValue('group1',$r)])),'target'=>'_blank']),
      'ledgercard'     =>Html::iconLink('fa fa-fw fa-list-alt',['title'=>'Click to view Ledger Card','href'=>url('cashRec#' . http_build_query(['prop'=>Helper::getValue('prop',$r),'unit'=>Helper::getValue('unit',$r), 'tenant'=>Helper::getValue('tenant',$r)])),'target'=>'_blank']),
      'tenantstatement'=>Html::iconLink('fa fa-fw  fa-file-pdf-o',['class'=>'iconLinkClick', 'title'=>'Click to download tenant statement','href'=>url('ledgerCardExport?' . http_build_query(['sort'=>'prop', 'order'=>'asc', 'op'=>'tenantStatement', 'type'=>'ledgerCard','dateRange'=>date('m/01/Y') . ' - ' . date('m/t/Y') , 'prop'=>Helper::getValue('prop',$r),'unit'=>Helper::getValue('unit',$r), 'tenant'=>Helper::getValue('tenant',$r)])),'target'=>'_blank']),
      'ledgercardpdf'  =>Html::iconLink('fa fa-fw fa-download',['class'=>'iconLinkClick', 'title'=>'Click to download tenant ledger card','href'=>url('ledgerCardExport?' . http_build_query(['sort'=>'prop', 'order'=>'asc', 'op'=>'ledgerCard', 'type'=>'ledgerCard','dateRange'=>date('m/01/Y') . ' - ' . date('m/t/Y') , 'prop'=>Helper::getValue('prop',$r),'unit'=>Helper::getValue('unit',$r), 'tenant'=>Helper::getValue('tenant',$r)])),'target'=>'_blank']),
      'checkCopypdf'   =>Html::iconLink('fa fa-fw fa-file-pdf-o',['class'=>'iconLinkClick checkCopyLink','href'=>'#','title'=>'Click to View Check Copy','data-vendor-payment-id'=>Helper::getValue('vendor_payment_id',$r,0)]),
    ];
    
    return implode('&nbsp;', (empty($iconList) ? $icon : Helper::selectData($iconList, $icon)));
  }
  //------------------------------------------------------------------------------
  public static function getReportIcon($r, $iconList = []){
    $icon = [
      'rentRoll'      =>Html::iconLink('fa fa-fw fa-registered', ['title'=>'Click to view Rent Roll Report',  'href'=>url('report#report=rentRollReport&' . http_build_query(['prop'=>Helper::getValue('prop', $r)])), 'target'=>'_blank']),
      'tenantStatus'  =>Html::iconLink('fa fa-fw fa-tumblr', ['title'=>'Click to view Tenant Status Report', 'href'=>url('report#report=tenantStatusReport&' . http_build_query(['prop'=>Helper::getValue('prop', $r)])), 'target'=>'_blank']), 
      'vacancy'       =>Html::iconLink('fa fa-fw fa-vimeo', ['title'=>'Click to view Vacancy Report', 'href'=>url('report#report=vacancyReport&' . http_build_query(['prop'=>Helper::getValue('prop', $r)])), 'target'=>'_blank']) 
    ];
    
    return implode('&nbsp;', (empty($iconList) ? $icon : Helper::selectData($iconList, $icon)));
  }
/**
 * @desc listing all the property of the html
 * @public
 * @param {array} params of the html property 
 * @return {string} return the list of all the property of the html element
 */
  public static function listParams($params, $isDoubleQuote = 1){
    $str = '';
    foreach($params as $k=>$v){ 
      $str .= $isDoubleQuote ? $k . '="' . $v . '" ' : $k . "='" . $v . "' "; 
    }
    return (empty($str)) ? '' : ' ' . $str;
  }
/**
 * @desc listing all the row for the table only
 * @param {array} 
 */
  private static function listRow($data, $isHeader = 0, $includeOrderList = 1){
    $html = '';
    $isDoubleQuote = 1;
    foreach($data as $i=>$val){
      $td = '';
      if($isHeader){
        $td .= Html::th('#',['width'=>15, 'align'=>'center'], $isDoubleQuote);
      } else{
        if($includeOrderList){
          $td .= Html::td(++$i, ['width'=>15, 'align'=>'center'], $isDoubleQuote);
        }
      }
      foreach($val as $v){
        $td .= ($isHeader) ? Html::th($v['val'], (isset($v['param']) ? $v['param'] : []), $isDoubleQuote) : Html::td($v['val'], (isset($v['param']) ? $v['param'] : []), $isDoubleQuote);
      }
      $html .= Html::tr($td, (isset($val['param']) ? $val['param'] : []), $isDoubleQuote);
    }
    return $html;
  }
/**
 * @desc to generate bootstrap tab 
 * @param $data 
 *  1. the key in the element will be used as tab header which is li 
 *  2. the value in the element will be used as tab body which is div 
 *  [
 *    ["jonathan consumer"] => 'Div inner tab'
 *    ["Li data"] => 'Div inner tab 3'
 *  ]
 */
  public static function buildTab($data, $params = []){
    $tabUl = $tabDiv = '';
    $num = 0;
    $tabClass = !empty($params['tabClass']) ? ' ' . $params['tabClass'] : ' pull-right';
    
    foreach($data as $li=>$div){
      $active = ($num == 0) ? 'active' : '';
      $tab    = 'tab_' . $num++;
      
      $a = Html::a($li, ['href'=>'#'.$tab, 'data-toggle'=>'tab', 'class'=>'tabClass', 'data-key'=>$li]);
      $tabUl  .= Html::li($a, ['class'=>$active]);
      $tabDiv .= Html::div($div, ['class'=>'tab-pane ' . $active, 'id'=>$tab]);
    }
    $tabUl  = self::ul($tabUl, ['class'=>'nav nav-tabs']);
    $tabDiv = self::div($tabDiv, ['class'=>'tab-content']);
    
    return Html::div($tabUl . $tabDiv, ['class'=>'nav-tabs-custom']);
  }
/**
 * @desc to generate Accordion
 * @param
 */
  public static function buildAccordion($data, $id = 'accordion', $headerParam = ['class'=>'panel box box-primary'], $closeFirst = 0){ 
    $eachAccordion = '';
    $num = 0;
    foreach($data as $k=>$v){
      $openFirst = $num == 0 && $closeFirst == 0 ? ' in' : '';
      $accordionId = 'collapse'. $id . $num++;
      
      
      $a = Html::a($k, ['data-toggle'=>'collapse', 'data-parent'=>'#' . $id, 'href'=>'#' . $accordionId]);
      $h4 = Html::tag('h4', $a, ['class'=>'box-title']);
      $header = Html::div($h4, ['class'=>'box-header with-border']);
      
      $div = Html::div($v, ['class'=>'box-body']);
      $body = Html::div($div, ['id'=>$accordionId, 'class'=>'panel-collapse collapse' . $openFirst]);
      
      $eachAccordion .= Html::div($header . $body, $headerParam);
    }        
    return Html::div($eachAccordion, ['class'=>'box-group', 'id'=>$id]);
  }
//------------------------------------------------------------------------------
  /**
   * @desc build the ul with li in it
   * @param type $data
   *  [
   *    0=>[
   *      value=>'some'
   *      param=>['class'=>'test']
   *    ]
   *  ]
   * @param type $ulParams
   *   [
   *     class=>'test
   *   ]
   * @return type
   */
  public static function buildUl($data, $ulParams = []){
    $li = '';
    foreach($data as $v){ 
      $li .= self::li($v['value'], !empty($v['param']) ? $v['param'] : [] ); 
    }
    return '<ul'. self::listParams($ulParams) .'>'. $li .'</ul>';
  }
}
