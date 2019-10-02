<?php
namespace App\Library;
use App\Library\RuleField;

class Form{
  /**
   * @desc 
   * @param type $tablez
   * @param type $button
   * @param type $sltField
   * @param type $setting
   * @param type $isKeyArray
   * @return type
   */
//  public static function generateForm($tablez, $button = [], $sltField = [], $setting = [], $isKeyArray = 0){
  public static function generateForm($data){
    $tablez     = $data['tablez'];
    $button     = !empty($data['button']) ? $data['button'] : [];
    $orderField = !empty($data['orderField']) ? $data['orderField'] : [];
    $setting    = !empty($data['setting']) ? $data['setting'] : [];
    $copyField  = !empty($data['copyField']) ? $data['copyField'] : [];
    $isKeyArray = !empty($data['isKeyArray']) ? $data['isKeyArray'] : 0;
    
    $formHtml = '';
    $formId   = $tablez[0];
    $ruleField = RuleField::generateRuleField($data)['field'];
//    $copyField
    $form      = Form::generateField($ruleField);
    foreach($orderField as $fl){
      $formHtml .= $form[$fl];
    }
    $formHtml = Html::div($formHtml, ['class'=>'box-body']);
    $button   = self::_button($button);
    
    return $formHtml . $button;
  }
//------------------------------------------------------------------------------
  public static function generateField($data){
    $field = [];
    foreach($data as $k=>$v){
      $field[$k] = self::getField($v);   
    }
    return $field;
  }
//--------------------------------------------------------------------------------------------------
  public static function getField($params){
	$params['class'] = (!empty($params['class'])) ? $params['class'] : 'fm form-control';
	$params['label'] = (!empty($params['label'])) ? $params['label'] : $params['id'];
  $params['wrap']  = (!empty($params['wrap']))  ? $params['wrap']  : ['param'=>['class'=>'form-group row'],'innerParam'=>['class'=>'col','style'=>'padding-left:0;']];
    
    switch ($params['type']){
      case 'textarea': 
        return self::_textarea($params);
      case 'option': 
        return self::_option($params); 
      case 'submit': 
        return self::_submit($params); 
      default:
        return self::_input($params);
    }
  }
####################################################################################################
#####################################     HELPER FUNCTION      #####################################
####################################################################################################
  private static function _button($button){
    $html = '';
    foreach($button as $param){
      $value = $param['value'];
      unset($param['value']);
      $param['class'] = isset($param['class']) ? 'btn btn-info pull-right btn-sm ' . $param['class'] : 'btn btn-info pull-right btn-sm';
      $param['type']  = 'submit';
      $html .= Html::input($value, $param);
    }
    return !empty($html) ? Html::div($html, ['class'=>'box-footer']) : '';
  }
//------------------------------------------------------------------------------
  private static function _input($params){
    $params['class'] = (empty($params['class']) ? '' : $params['class']) . ' form-control input-sm';
    $includeLabel    = (isset($params['includeLabel']) && empty($params['includeLabel'])) ? 0 : 1;
    $label           = !$includeLabel ? '' : self::_getLabel(['class'=>'col-sm-4 control-label'] + $params);
    $input           = '<input type="' . $params['type'] . '"' . self::_listParams($params) . ' />';
    $formField       = Html::div($input, ['class'=>'col-sm-8']);
    
    return ($params['type'] == 'hidden' || !$includeLabel) ? $input : Html::div($label . $formField, ['class'=>'form-group']);
  }
//--------------------------------------------------------------------------------------------------  
  private static function _option($params) {
    $getOption = function ($params, $option){
      $opt = '';
      foreach ($option as $k => $v) {
        $slt  = (isset($params['value']) && $params['value'] == $k) ? ' selected' : '';
        $opt .= '<option value="' . $k . '" ' . $slt . '>' . $v . '</option>';
      }
      return $opt;
    };
    
    $params['class'] = (empty($params['class']) ? '' : $params['class'])  . ' form-control';
    $includeLabel    = (isset($params['includeLabel']) && empty($params['includeLabel'])) ? 0 : 1;
    $label           = !$includeLabel ? '' : self::_getLabel(['class'=>'col-sm-4 control-label'] + $params);
    $select          = '<select'.self::_listParams($params) . '>' . $getOption($params, $params['option']) . '</select>';
    $formField       = Html::div($select, ['class'=>'col-sm-8']);
    return ($includeLabel) ? Html::div($label . $formField, ['class'=>'form-group']) : $select;
  }
#above is done section  

  
  
  
//--------------------------------------------------------------------------------------------------  
  private static function _textarea($params) {
    $val = '';
    if(isset($params['value'])){
      $val = $params['value'];
      unset($params['value']);
    }
    $el = self::_getWrap($params);
    $params['class'] = (empty($params['class']) ? '' : $params['class'])  . ' form-control';
    $textArea = '<textarea '. self::_listParams($params) .'>'.$val.'</textarea>';
    return Html::$el(self::_getLabel($params) . Html::div($textArea, ['class'=>'col-sm-8']), ['class'=>'form-group']);
  }
//--------------------------------------------------------------------------------------------------
  private static function _listParams($params){
    $cls = (!empty($params['readonly'])) ? ' readonly' : '';
    $cls .= (!empty($params['disabled'])) ? ' disabled' : '';
    $cls .= ' type_' . $params['type'];
    $str = (isset($params['class'])) ? 'class="'. $params['class'] . $cls .'"' : '';
    $str .= ' name="'. $params['id'] .'" ';
    
    if(isset($params['value'])){
      $params['value'] .= (isset($params['format'])) ? self::_formatValue($params['value']) : '';
    }
    else{
      $params['value'] = '';
    }
    foreach($params as $k=>$v){
      switch ($k) {
        // Exclude these fields
        case 'req':
        case 'label':
        case 'showLabel':
        case 'class':
        case 'type':
        case 'format':
        case 'option':
        case 'optgroup':
        case 'wrap':
          break;
        case 'disabled':
        case 'readonly':
          $str .= !empty($v) ? $k . '="' . $v . '" ' : '';
          break;
        default:
          $str .= $k . '="' . $v . '" ';
      }
    }
//    echo $str. "\n";
    return (empty($str)) ? '' : ' ' . rtrim($str);
  }
//--------------------------------------------------------------------------------------------------  
  private static function _getLabel($params) {
    $reqStr = !empty($params['req'])  ? Html::span('*', ['class'=>'text-red']): '';
    $hint   = !empty($params['hint']) ? Html::span('', ['class'=>'fa fa-fw fa-question-circle text-aqua hint', 'data-toggle'=>'tooltip', 'title'=>$params['hint']]) : '';
    return (!isset($params['showLabel']) || $params['showLabel']) ? '<label class="col-sm-4 control-label" for="' . $params['id'] . '">' . $hint . $params['label'] . $reqStr . '</label>' : '';
  }
//--------------------------------------------------------------------------------------------------  
  private static function _formatValue($format, $v){
    switch ($format){
      case 'date':
        return ($v == '0000-00-00' || empty($v)) ? '' : Format::us_format_date($v);
      case 'money':
        return Format::us_money_format($v);
      case 'percent':
        return Format::number($v, 2, '.', '');
      default:
        return $v;
    }
  }
//--------------------------------------------------------------------------------------------------
  private static function _getWrap($params){
    return !empty($params['wrap']['wrap']) ? $params['wrap']['wrap'] : 'div';
  }
//--------------------------------------------------------------------------------------------------
  private static function _getWrapParam($params){
    return !empty($params['wrap']['param']) ? $params['wrap']['param'] : [];
  }
//--------------------------------------------------------------------------------------------------
  private static function _getInnerWrapParams($params){
    return !empty($params['wrap']['innerParam']) ? $params['wrap']['innerParam'] : [];
  }
//--------------------------------------------------------------------------------------------------
  private static function _getWrapTextAfter($params){
    return !empty($params['wrap']['textAfter']) ? $params['wrap']['textAfter'] : '';
  }
//--------------------------------------------------------------------------------------------------
  private static function _getWrapTextBefore($params){
    return !empty($params['wrap']['textBefore']) ? $params['wrap']['textBefore'] : '';
  }
}
