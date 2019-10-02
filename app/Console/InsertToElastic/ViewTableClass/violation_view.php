<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper};
use App\Http\Models\Model; // Include the models class
class violation_view{
  private static $_violation = 'violation_id, prop, date_recieved, date_comply,agent, status, date_complete, remark, priority, inspector_fname, inspector_lname, inspector_phone, udate, cdate, usid, active';
  private static $_prop      = 'prop_id, trust, prop_class,prop_name, prop_type, cash_accrual, line2, street, city, state, zip, phone, start_date, sq_feet, county, group1, group2, cons1, cons2';
  private static $_fileUploadViewSelect = [
    'f.fileUpload_id', 'f.foreign_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'
  ];
  public  static $maxChunk   = 10000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$prop, T::$violation, T::$fileUpload];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['inspector_lname'=>'inspector_name'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    foreach($data as $i=>$val){
      $fileUpload = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
      unset($val[T::$fileUpload]);
      $val['id'] = $val['violation_id'];
      $val['inspector_name'] = $val['inspector_fname'] . ' ' . $val['inspector_lname'];
      $data[$i] = $val;
      
      $data[$i]['street']    = title_case($val['street']);
      $data[$i]['city']      = title_case($val['city']);
      $data[$i]['county']    = title_case($val['county']);
      
      # DEAL WITH FILEUPLOAD
      if(!empty($fileUpload)){
        foreach($fileUpload as $j=>$v){
          $p = explode('~', $v);
          foreach(self::$_fileUploadViewSelect as $k=>$field){
            $field = preg_replace('/f\./', '', $field);
            $data[$i][T::$fileUpload][$j][$field] = isset($p[$k]) ? $p[$k] : '';
          }
        }
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function getSelectQuery($where = []){
    $whereStr  = !empty($where) ? preg_replace('/^ AND /',' WHERE ',Model::getRawWhere($where)) : '';
    $isEndSelect = 1;
    $data = 'SELECT ' . Helper::joinQuery('v',self::$_violation) . Helper::joinQuery('p',self::$_prop). Helper::groupConcatQuery(self::$_fileUploadViewSelect, T::$fileUpload, $isEndSelect) . ' 
      FROM ' . T::$violation . ' AS v 
      INNER JOIN ' . T::$prop .  ' AS p ON p.prop=v.prop 
      LEFT JOIN ' . T::$fileUpload . ' AS f ON v.violation_id=f.foreign_id AND f.type="violation" AND f.active=1 ' . $whereStr . ' GROUP BY v.violation_id';
    return $data;
  }
}