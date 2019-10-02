<?php 
namespace App\Library;
use Illuminate\Support\Facades\DB;

class RuleField extends DB{
/**
 * @Desc Generate rule to validate the data and field for generating form
 * @param {array} $tablez is database table name
 *  ['application','application_info']
 * @param {array} $setting is used to override the exist default
 *  [
 *    'prop'=>['hint'=>'welkr', 'id'=>'prop[0]']
 *  ]
 * @return {array}
 *   array:2 [
 *    "rule" => array:2 [
 *      "prop" => "required|string|1,255"
 *      "unit" => "required|string|1,255"
 *     ]
 *     "field" => array:2 [
 *       "prop" => array:8 [
 *         "id" => "prop"
 *         "class" => ""
 *         "type" => "text"
 *         "placeholder" => "Prop"
 *         "label" => "Prop"
 *         "default" => null
 *         "req" => 1
 *         "hint" => "welkr"
 *       ]
 *       "unit" => array:7 [
 *         "id" => "unit"
 *         "class" => ""
 *         "type" => "text"
 *         "placeholder" => "Unit"
 *         "label" => "Unit"
 *         "default" => null
 *         "req" => 1
 *       ]
 *     ]
 *   ]
 */
//  public static function generateRuleField($tablez, $setting, $selectedField, $isKeyArray = 0, $copyField = []){
  public static function generateRuleField($data){
    $tablez     = $data['tablez'];
    $orderField = !empty($data['orderField']) ? $data['orderField'] : [];
    $setting    = !empty($data['setting']) ? $data['setting'] : [];
    $copyField  = !empty($data['copyField']) ? $data['copyField'] : [];
    $isKeyArray = !empty($data['isKeyArray']) ? $data['isKeyArray'] : 0;
    $isKeyArray = !empty($data['isKeyArray']) ? $data['isKeyArray'] : 0;
    
    $data = $response = [];
    ############### START TO GO THROW EACH TABLE ############
    foreach($tablez as $tbl){
      $col = DB::select('SHOW COLUMNS FROM ' . $tbl);
      foreach($col as $v){
        $typeData = self::_splitType($v['Type']);
        $field = $v['Field'];
        $null  = $v['Null'];
        $additionCls = [
          'date'=>'date',
          'decimal'=>'decimal',
          'datetime'=>'datetime',
          'timestamp'=>'datetime',
          'bigint'=>'integer',
          'int'=>'integer',
          'mediumint'=>'integer',
          'smallint'=>'integer',
          'tinyint'=>'integer',
        ];
        
        # OVERRIDE EXISTING RULE
        $data['rule'][$field]  = !empty($setting[$field]['rule']) ? $setting[$field]['rule'] : self::_getRule($null, $typeData);
        $label = title_case(preg_replace('/[\-_]/', ' ', $field));
        $data['field'][$field] = [
          'id'          =>$field . ($isKeyArray ? '[0]' : ''), 
          'class'       =>'', 
          'type'        =>'text', 
          'placeholder' =>$label, 
          'label'       =>$label, 
          'value'       =>($v['Default'] == 0.00 || $v['Default'] == 0) ? '' : $v['Default'],
          'req'         =>($null == 'YES') ? 0 : 1,
        ];
        
        # ADD ADDITIONAL CLASS WITH SPECIFIC DATATYPE
        if(isset($additionCls[$typeData['type']])){
          $data['field'][$field]['class'] = $data['field'][$field]['class'] . $additionCls[$typeData['type']];
        }
        
        ### COPY FIELD ###
        if(isset($copyField[$field])){
          $copyFl = $copyField[$field];
          $data['field'][$copyFl] = $data['field'][$field];
          $data['field'][$copyFl]['id'] = $copyField[$field];
          $data['rule'][$copyFl] = $data['rule'][$field];
          
          # OVERRIDE EXISTING FIELD
          if(!empty($setting['field'][$copyFl])){
            $overrideFields = $setting['field'][$copyFl];
            foreach($overrideFields as $overrideVal=>$k){
              $data['field'][$copyFl][$k] = $overrideVal;
              if($k == 'label'){
                $data['field'][$copyFl]['placeholder'] = $overrideVal;
              }
            }
          }
        }
        
        # OVERRIDE EXISTING FIELD
        if(!empty($setting['field'][$field])){
          $overrideFields = $setting['field'][$field];
          foreach($overrideFields as $k=>$overrideVal){
            $data['field'][$field][$k] = $overrideVal;
            if($k == 'label'){
              $data['field'][$field]['placeholder'] = $overrideVal;
            }
          }
        }
      }
    }
    
    ################## SELECT SPECIFIC FIELD ###############
//    dd($data['rule']['confirmPassword']);
//    dd($data, $response, $orderField);
    
//    dd($setting['rule']);
    
    if(!empty($orderField)){
      foreach($orderField as $fl){
        if(isset($data['rule'][$fl])){
          $response['rule'][$fl]  = $data['rule'][$fl]; 
          $response['field'][$fl] = $data['field'][$fl];
        }
      }
    } else{
      $response = $data;
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private static function _getRule($null, $typeData){
    $unsigned = $typeData['unsigned'];
    $type     = $typeData['type'];
    $max      = $typeData['max'];
    $rule     = ($null == 'YES' ? 'nullable' : 'required') . '|'; 
    
    $typeMap  = [
      'varchar'   => $rule . 'string|between:1,' . (!empty($max) ? $max : '1'),
      'char'      => $rule . 'string|between:1,' . (!empty($max) ? $max : '1'),
      'text'      => $rule . 'string|between:1,65535',
      'tinytext'  => $rule . 'string|between:1,255',
      'mediumtext'=> $rule . 'string|between:1,16777215',
      'longtext'  => $rule . 'string|between:1,4294967295',
      'enum'      => $rule . 'string|between:1,255',
      'bigint'    => $rule . 'integer|between:' . ($unsigned ? 1 :  -9223372036854775808) . ',' . ($unsigned ? 18446744073709551615 : 9223372036854775807 ),
      'int'       => $rule . 'integer|between:' . ($unsigned ? 1 :  -2147483648  ) . ',' . ($unsigned ? 4294967295 : 2147483647 ),
      'mediumint' => $rule . 'integer|between:' . ($unsigned ? 1 :  -8388608  ) . ',' . ($unsigned ? 16777215 : 8388607 ),
      'smallint'  => $rule . 'integer|between:' . ($unsigned ? 1 :  -32768  ) . ',' . ($unsigned ? 65535 : 32767 ),
      'tinyint'   => $rule . 'integer|between:' . ($unsigned ? 1 :  -128 ) . ',' . ($unsigned ? 255 : 127 ),
      'decimal'   => $rule . 'numeric|between:' . ($unsigned ? 0.01 : -2147483648 ) . ',' . 9999999999.99,
      'timestamp' => $rule . 'date_format:Y-m-d H:i:s',
      'datetime'  => $rule . 'date_format:Y-m-d H:i:s',
      'date'      => $rule . 'date_format:m/d/Y',
    ];
    return $typeMap[$type];
  }
//------------------------------------------------------------------------------
  private static function _splitType($type){
    $data = [];
    $p1 = explode(' ', $type);
    $data['unsigned'] = isset($p1['1']) ? 1 : 0;
    $p2 = explode('(', trim($p1[0], ')'));
    $data['type'] = $p2[0];
    $data['max']  = isset($p2[1]) ? $p2[1] : '';
    return $data;
  }
}