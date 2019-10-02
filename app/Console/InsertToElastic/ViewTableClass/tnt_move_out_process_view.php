<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;

class tnt_move_out_process_view{
  private static $_tntMoveOutProcess = 'tnt_move_out_process_id, prop, unit, tenant, status, isFileuploadComplete, udate, usid';
  private static $_fileUploadViewSelect = [
    'f.fileUpload_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'
  ];
  public  static $maxChunk = 50000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$tntMoveOutProcess, T::$tenant, T::$unit, T::$prop, T::$fileUpload];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props   = array_column($data,'prop');
    $units   = array_column($data, 'unit');
    $tenants = array_column($data, 'tenant');
    $rTenant = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'size'     => 130000, 
      '_source'  => ['tnt_name', 'base_rent', 'dep_held1', 'move_in_date', 'move_out_date', 'spec_code', 'prop', 'unit', 'tenant', 'street', 'city', 'state', 'zip', 'group1'],
      'query'    => [
        'must'   => [
          'prop.keyword'  => $props,
        ],
        'filter' => [
          'unit'  => $units,
          'tenant'=> $tenants
        ]
      ]
    ]),['prop','unit','tenant']);
    foreach($data as $i=>$val){
      $tenantKey  = $val['prop'].$val['unit'].$val['tenant'];
      if(!isset($rTenant[$tenantKey])){
        dd($val);
      }
      $fileUpload = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
      unset($data[$i][T::$fileUpload]);
      
      $data[$i]['id']            = $val['tnt_move_out_process_id'];
      $data[$i]['group1']        = $rTenant[$tenantKey]['group1'];
      $data[$i]['street']        = $rTenant[$tenantKey]['street'];
      $data[$i]['city']          = $rTenant[$tenantKey]['city'];
      $data[$i]['state']         = $rTenant[$tenantKey]['state'];
      $data[$i]['zip']           = $rTenant[$tenantKey]['zip'];
      
      $data[$i]['tnt_name']      = $rTenant[$tenantKey]['tnt_name'];
      $data[$i]['base_rent']     = $rTenant[$tenantKey]['base_rent'];
      $data[$i]['dep_held1']     = $rTenant[$tenantKey]['dep_held1'];
      $data[$i]['move_in_date']  = $rTenant[$tenantKey]['move_in_date'];
      $data[$i]['move_out_date'] = $rTenant[$tenantKey]['move_out_date'];
      $data[$i]['spec_code']     = $rTenant[$tenantKey]['spec_code'];
      
      
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
    $where = empty($where) ? '' : 'WHERE ' . preg_replace('/^ AND /', '', Model::getRawWhere($where));
    $data = 'SELECT ' . Helper::joinQuery('tp',self::$_tntMoveOutProcess) . Helper::groupConcatQuery(self::$_fileUploadViewSelect, T::$fileUpload, 1) . ' 
             FROM ' . T::$tntMoveOutProcess . ' AS tp  
             LEFT JOIN ' . T::$fileUpload . ' AS f ON tp.tnt_move_out_process_id=f.foreign_id AND (f.type="tenantMoveOutReport" OR f.type="tenantMoveOutFile") AND f.active=1 '
             . $where . ' GROUP BY tp.tnt_move_out_process_id';
    return $data;
  }
}
