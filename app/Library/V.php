<?php
namespace App\Library;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class V{
/**
 * @desc validate the request from user to see if it follows the rule or not
 * @params {array} $data is data that contain the following key
 *   'rawReq' {array} is the raw data from the Request usually $req->all(),
 *   'tablez' {array} is the table name that we want to validate in array ex. ['application','application_info'], 
 *   'rule' {array} is the rule IMPORTANT if rule exist, it will add more into the ruleField for the table.
 *   'orderField' {array} is what field we want to use to validate the date as array
 *   'validateDatabase' {array=>string} is the string of table and field and default value 
 *     combine in the mustExist OR mustNotExist element
 *     format in mustExist Or mustNotExist is: 
 *      table|field1,field2,field3:Default_value
 * @return {array} validated requests that meet all the rule and return only the field that exit in 
 *  rule
 * @howToUse
 *  $vData = V::startValidate([
 *    'rawReq'=>$req->all(),
 *    'tablez'=>['application','application_info'], 
 *    'rule' =>[
 *       'search' =>'nullable|string|between:1,255',
 *       'filter' =>'nullable|string|between:1,5000',
 *     ], 
 *    'orderField'=> array_merge(self::_getOrderField('create'), self::_getOrderField('formAppInfo')),
 *     'validateDatabase'=>[
 *      'mustExist'=>[
 *        'unit|prop,unit:0001', 
 *        'unit|prop,unit', 
 *      ],
 *      'mustNotExist'=>[
 *        'application_info|social_security',
 *      ]
 *    ]
 *  ]);
 */
  public static function startValidate($data){
    $rawReq     = $data['rawReq'];
    $usid       = Helper::getUsid($rawReq);
    $tablez     = !empty($data['tablez']) ? $data['tablez'] : [];
    $setting    = !empty($data['setting']) ? $data['setting'] : [];
    $orderField = !empty($data['orderField']) ? $data['orderField'] : [];
    $validateDatabase = !empty($data['validateDatabase']) ? $data['validateDatabase'] : [];
    $ruleField        = !empty($data['rule']) ? $data['rule'] : [];
    $includeCdate     = isset($data['includeCdate']) ? $data['includeCdate'] : 1;
    $includeUsid      = isset($data['includeUsid']) ? $data['includeUsid'] : 0;
    $isPopupMsgError  = isset($data['isPopupMsgError']) ? $data['isPopupMsgError'] : 0; 
    $isAjax           = isset($data['isAjax']) ? $data['isAjax'] : 1;
    $isExistIfError   = isset($data['isExistIfError']) ? $data['isExistIfError'] : 1;
    $formId           = isset($data['formId']) ? $data['formId'] : '';
//    $includeUsid = isset($data['includeUsid']) ? $data['includeUsid'] : 1;
    $rule = $fieldData = [];
    # This is is from Middleware IsLoginMiddleware 
    # No need to validate it so just delete it
    unset($rawReq['ACCOUNT'], $rawReq['PERMISSION'], $rawReq['NAV'], $rawReq['PERM'], $rawReq['ALLPERM'], $rawReq['ISADMIN']);
    ### GET THE RULE ###
    if(!empty($tablez)){
      $ruleField = RuleField::generateRuleField($data);
      $fieldData += $ruleField['field'];
      $rule  += $ruleField['rule'];
    }
    
    if(!empty($ruleField)){
      $rule += $ruleField;
    }
    
    ### OVERRIDE RULE BY USING SETTING ###
    if(!empty($setting['rule'])){
      foreach($setting['rule'] as $field=>$v){
        $rule[$field] = $v;
      }
    }
    ############## START TO VALIDATE DATA ##################
    $vData = ['op'=>self::_getOperator($rawReq), 'noRule'=>[], 'dataArr'=>[], 'formid'=>$formId];
    unset($rawReq['op'], $rawReq['_']);
    
    // Check if the $rawReq exist
//    dd($orderField, $rawReq);
//    dd($orderField);
    foreach($orderField as $fl){
      if(!array_key_exists($fl, $rawReq)){
        $vData['noRequiredField'] = $fl;
        $vData['error'][$fl] = 'Fields do not exist;';
      }
    }
    foreach($rawReq as $fl=>$val){
      if(is_array($val)){
        foreach($val as $i=>$v){
          if(isset($rule[$fl])){
            $v    = self::_cleanValue($rule[$fl], $v);
            $vVal = Validator::make([$fl=>$v], [$fl=>$rule[$fl]]);
            $data = $vVal->passes() ? $rawReq : $vVal->errors()->toArray();
            $key  = $vVal->passes() ? 'data' : 'error';
            $vData[$key][$fl][$i] = $vVal->passes() ? self::_getRealValue($rule[$fl],$v) : title_case($data[$fl][0]);
            $vData['dataArr'][$i][$fl] = $vVal->passes() ? self::_getRealValue($rule[$fl],$v) : title_case($data[$fl][0]);
          } else{
            $vData['noRule'][$fl] = $rawReq[$fl];
          }
        }
      }else{
        if(isset($rule[$fl])){
          $val  = self::_cleanValue($rule[$fl], $val);
          $vVal = Validator::make([$fl=>$val], [$fl=>$rule[$fl]]);
          $data = $vVal->passes() ? $rawReq : $vVal->errors()->toArray();
          $key  = $vVal->passes() ? 'data' : 'error';
          $vData[$key][$fl] = $vVal->passes() ? self::_getRealValue($rule[$fl],$val) : title_case($data[$fl][0]);
          $vData['dataNonArr'][$fl] = $vVal->passes() ? self::_getRealValue($rule[$fl],$val) : title_case($data[$fl][0]);
        }else{
          $vData['noRule'][$fl] = $rawReq[$fl];
        }
      }
    }
    if(empty($vData['error']) && !empty($validateDatabase) && !empty($vData['data'])){
      $wherez = [];
      ########### START GET WHERE QUERY FOR EACH TABLE #############
      foreach($validateDatabase as $exist=>$rule){
        foreach($rule as $i=>$val){
          list($table, $fieldz) = explode('|', $val);
          $fieldPieces = explode(',', $fieldz);
          foreach($fieldPieces as $v){
            $fieldVal = explode(':', $v);
            $field = $fieldVal[0];
            $value = !empty($fieldVal[1]) ? $fieldVal[1] : $vData['data'][$field];
            
            if(is_array($value)){
              foreach($value as $j=>$eachVal){
                $wherez[$exist][$table]['array_' . $j][$i][]  = [$field, '=', $eachVal];
              }
            } else{
              $wherez[$exist][$table][$i][] = [$field, '=', $value];
            }
          }
        }
      }
      ####### START TO DETERMINE IF ERROR NOT GIVEN EACH VALUE ########
      foreach($wherez as $exist=>$whereData){
        foreach($whereData as $table=>$value){
          foreach($value as $i=>$where){
            if(preg_match('/^array/', $i)){
              list($tmp, $num) = explode('_', $i);
              foreach($where as $w){
                $r = self::_getErrorMessageDb($exist, $table, $w);
                if(!empty($r)){
                  $vData['error'][$r['field']][$num] = $r['msg'];
                }
              }
            } else{
              $r = self::_getErrorMessageDb($exist, $table, $where);
              if(!empty($r)){
                $vData['error'][$r['field']] = $r['msg'];
              }
            }
          }
        }
      }
    } 
    
    if(empty($vData['data'])){
      $vData['error']['msg'] = Html::errMsg('No Data is provided.');
    }
    if(!empty($vData['data'])){
//      foreach($vData['data'] as $k=>$v){
//        if(preg_match('/^to/', $k)){
//          list($tmp, $key) = explode('to', $k);
//          $val = $vData['data'][$key]; 
//          $toval = $vData['data'][$k]; 
//
//          if(empty($val) && empty($toval)){ // Do nothing
//          } else if(empty($val) || empty($toval)){
//            $msg = 'Either ' . $key . ' or ' . $k . ' cannot be empty';
//            $vData['error'][$key] = $vData['error'][$k] = $msg; 
//          }
//        }
//      }
    }
    
    if(!empty($vData['noRule'])){
      if(env('APP_ENV') == 'local'){
        $vData['noRule'] = $vData['noRule'];
      }
    }
    
    if(!empty($vData['noRequiredField'])){
      $vData['error']['uploadMsg'] = Html::errMsg('Required Field is missing.');
    }
    
    if(isset($vData['error'])){
      // Manipulate the upload error message
      if(!empty($vData['error']['uuid'])){
        $vData['error']['uploadMsg'] = Html::errMsg('File Upload is required.');
        unset($vData['error']['uuid']);
      }
      
      if($isPopupMsgError){
        $error = reset($vData['error']);
        $error = is_array($error) ? reset($error) : $error;
        Helper::echoJsonError(Html::errMsg($error), 'popupMsg');
      }
      
      // Just print out the out put here so that we don't have to bother the place 
      // where we call this function
      if($isExistIfError){
        header('Content-type: application/json');
        if($isAjax){
          echo json_encode($vData);
        }
        exit;
      } else{
        return $vData;
      }
    }
    
    # ADD cdate
    if($includeCdate){
      if(!empty($vData['dataArr'])){
        foreach($vData['dataArr'] as $i=>$v){
          $vData['dataArr'][$i]['cdate'] = Helper::mysqlDate(); 
        }
      } else{
        $vData['dataNonArr']['cdate'] = Helper::mysqlDate(); 
      }
      $vData['data']['cdate'] = Helper::mysqlDate();
    }
    
    if($includeUsid){
      if(!empty($vData['dataArr'])){
        foreach($vData['dataArr'] as $i=>$v){
          $vData['dataArr'][$i]['usid'] = $usid; 
        }
      } else{
        $vData['dataNonArr']['usid'] = $usid; 
      }
      $vData['data']['usid'] = $usid;
    }
    
    return $vData;
  }
//------------------------------------------------------------------------------
  public static function validateionDatabase($validateDatabase, $vData){
    $wherez = [];
    $isExitIfError = isset($vData['isExitIfError']) ? $vData['isExitIfError'] : 1;
    ########### START GET WHERE QUERY FOR EACH TABLE #############
    foreach($validateDatabase as $exist=>$rule){
      foreach($rule as $i=>$val){
        list($table, $fieldz) = explode('|', $val);
        $fieldPieces = explode(',', $fieldz);
        foreach($fieldPieces as $v){
          $fieldVal = explode(':', $v);
          $field = $fieldVal[0];
          $value = !empty($fieldVal[1]) ? $fieldVal[1] : $vData['data'][$field];

          if(is_array($value)){
            foreach($value as $j=>$eachVal){
              $wherez[$exist][$table]['array_' . $j][$i][]  = [$field, '=', $eachVal];
            }
          }
          else{
            $wherez[$exist][$table][$i][] = [$field, '=', $value];
          }
        }
      }
    }
    ####### START TO DETERMINE IF ERROR NOT GIVEN EACH VALUE ########
    foreach($wherez as $exist=>$whereData){
      foreach($whereData as $table=>$value){
        foreach($value as $i=>$where){
          if(preg_match('/^array/', $i)){
            list($tmp, $num) = explode('_', $i);
            foreach($where as $w){
              $r = self::_getErrorMessageDb($exist, $table, $w);
              if(!empty($r)){
                $vData['error'][$r['field']][$num] = $r['msg'];
              }
            }
          }
          else{
            $r = self::_getErrorMessageDb($exist, $table, $where);
            if(!empty($r)){
              $msg = Html::errMsg($r['msg']);
              $vData['error']= ['mainMsg'=>$msg,$r['field']=>$msg];
            }
          }
        }
      }
    }
    if(isset($vData['error'])){
      if($isExitIfError){
        // Just print out the out put here so that we don't have to bother the place 
        // where we call this function
        header('Content-type: application/json');
        echo json_encode($vData);
        exit;
      } else{
        return $vData;
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
/**
 * @desc each request must have op field. If it doesn't have, we assign it to empty
 * @params {array} $rawReq request getting from users without anything filter
 * @return {string} either empty or op from the user
 */  
  private static function _getOperator($rawReq){
    return (isset($rawReq['op'])) ? $rawReq['op'] : '';
  }
/**
 * @desc check to see if the there is any error 
 * @param {string} $exist is mustExist Or mustNotExist
 * @param {string} $table is what table we are validate against with
 * @param {array} $where is the where query to put to get the data
 * @return {array|null} if there is an error, it will return an array
 *  if not, it will return null
 */
  private static function _getErrorMessageDb($exist, $table, $where){
    $_getErrorMessage = function($exist, $where){
      $str = $field = '';
      $num = 0;
      foreach($where as $i=>$where){
        ++$num;
        $str .= implode(' ', $where) . ' and ';
        $field = $where[0];
      }
      $s = ($num >= 2) ? 's' : '';
      return [
        'field'=>$field,
        'msg'=>rtrim($str, ' and ') . ($exist == 'mustExist' ? ' NOT exist.' : ' already exist'. $s . '.')
      ];
    };
    
    $r = DB::table($table)->where($where)->first();
    if(($exist == 'mustExist' && empty($r)) || ($exist == 'mustNotExist' && !empty($r))){
      // Send an error message that it doesn't exist in the database OR 
      // Send an error message that it exists in the data already
      return $_getErrorMessage($exist, $where);
    }
  }
//------------------------------------------------------------------------------
  private static function _getRealValue($rule, $v){
    if(preg_match('/date_format\:m\/d\/Y/', $rule)){
      return empty($v) ? '' : date('Y-m-d', strtotime($v));      
    }
    return $v;  
  }
//------------------------------------------------------------------------------
  private static function _cleanValue($rule, $value){
    $value = strip_tags($value);
    if(preg_match('/numeric/', $rule)){
      return preg_replace('/[\$|\%|\s+|,]+/', '',$value);
    }else if(preg_match('/integer/', $rule)){
      return preg_replace('/[,]+/', '',$value);
    }else if(preg_match('/date_format/', $rule)){
      return !empty($value) ? date('m/d/Y', strtotime($value)) : '';
    }
    return trim($value);
  }
}