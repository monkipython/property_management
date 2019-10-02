<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Elastic};
use App\Http\Models\Model;

class tnt_eviction_process_view{
  private static $_tntEvictionProcess = 'tnt_eviction_process_id, tenant_id, result, process_status, isFileuploadComplete, attorney, cdate, udate, usid';
  private static $_tntEvictionEvent = [
    'ee.tnt_eviction_event_id', 'ee.tnt_eviction_process_id', 'ee.status', 'ee.subject', 'ee.remark','ee.date', 'ee.tenant_attorney', 'ee.cdate', 'ee.udate', 'ee.usid'
  ];
  private static $_fileUploadViewSelect = [
    'f.fileUpload_id', 'f.foreign_id', 'f.name', 'f.file', 'f.uuid', 'f.path', 'f.type', 'f.ext'
  ];
  public  static $maxChunk = 50000;
//------------------------------------------------------------------------------
  public static function getTableOfView(){
    return [T::$tntEvictionProcess, T::$tntEvictionEvent, T::$tenant, T::$unit, T::$prop, T::$fileUpload];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['prop_name'=>'fileUpload'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable, $data){
    $data = !empty($data) ? Helper::encodeUtf8($data) : [];
    $mapping = Helper::getMapping(['tableName'=>T::$tntEvictionProcess]);
    $tenantId   = array_column($data,'tenant_id');
    $rTenant = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      '_source'  => ['tenant_id', 'tnt_name', 'base_rent', 'dep_held1', 'move_in_date', 'move_out_date', 'spec_code', 'prop', 'unit', 'tenant', 'street', 'city', 'state', 'zip', 'group1', 'bedrooms', 'bathrooms', 'unit_type', 'billing', 'status'],
      'query'    => [
        'must'   => [
          'tenant_id'  => $tenantId
        ]
      ]
    ]),['tenant_id']);
    
    foreach($data as $i=>$val){

      $tntEvictionEvent = !empty($val[T::$tntEvictionEvent]) ? explode('|', $val[T::$tntEvictionEvent]) : [];
      $fileUpload       = !empty($val[T::$fileUpload]) ? explode('|', $val[T::$fileUpload]) : [];
      unset($data[$i][T::$tntEvictionEvent], $data[$i][T::$fileUpload]);
      
      $data[$i]['process_status'] = $mapping['process_status'][$val['process_status']];
      
      $data[$i]['id']            = $val['tnt_eviction_process_id'];
      $data[$i]['group1']        = $rTenant[$val['tenant_id']]['group1'];
      $data[$i]['prop']          = $rTenant[$val['tenant_id']]['prop'];
      $data[$i]['unit']          = $rTenant[$val['tenant_id']]['unit'];
      $data[$i]['tenant']        = $rTenant[$val['tenant_id']]['tenant'];
      $data[$i]['street']        = title_case($rTenant[$val['tenant_id']]['street']);
      $data[$i]['city']          = title_case($rTenant[$val['tenant_id']]['city']);
      $data[$i]['state']         = $rTenant[$val['tenant_id']]['state'];
      $data[$i]['zip']           = $rTenant[$val['tenant_id']]['zip'];
      
      $data[$i]['tnt_name']      = current(explode(",", $rTenant[$val['tenant_id']]['tnt_name']));
      $data[$i]['base_rent']     = $rTenant[$val['tenant_id']]['base_rent'];
      $data[$i]['dep_held1']     = $rTenant[$val['tenant_id']]['dep_held1'];
      $data[$i]['move_in_date']  = $rTenant[$val['tenant_id']]['move_in_date'];
      $data[$i]['move_out_date'] = $rTenant[$val['tenant_id']]['move_out_date'];
      $data[$i]['bedrooms']      = $rTenant[$val['tenant_id']]['bedrooms'];
      $data[$i]['bathrooms']     = $rTenant[$val['tenant_id']]['bathrooms'];
      $data[$i]['unit_type']     = $rTenant[$val['tenant_id']]['unit_type'];
      $data[$i]['status']        = $rTenant[$val['tenant_id']]['status'];
      $data[$i]['spec_code']     = 'E';
      
      if(!empty($rTenant[$val['tenant_id']]['billing'])) {
        $_dateCompare = function ($a, $b){
          $t1 = strtotime($a['start_date']);
          $t2 = strtotime($b['start_date']);
          return $t2 - $t1; 
        };
        usort($rTenant[$val['tenant_id']]['billing'], $_dateCompare);
        $data[$i]['last_raise_date'] = $rTenant[$val['tenant_id']]['billing'][0]['start_date'];
      }
      
      # DEAL WITH FILEUPLOAD
      if(!empty($fileUpload)){
        foreach($fileUpload as $j=>$v){
          $p = explode('~', $v);
          foreach(self::$_fileUploadViewSelect as $k=>$field){
            $field = preg_replace('/f\./', '', $field);
            $data[$i][T::$fileUpload][$j][$field] = isset($p[$k]) ? $p[$k] : '';
          }
        }
        $fileUpload = $data[$i][T::$fileUpload];
        unset($data[$i][T::$fileUpload]);
      }
      
      # DEAL WITH TNT EVICTION EVENT
      if(!empty($tntEvictionEvent)){
        foreach($tntEvictionEvent as $j=>$v){
          $p = explode('~', $v);
          foreach(self::$_tntEvictionEvent as $k=>$field){
            $field = preg_replace('/ee\./', '', $field);
            $data[$i][T::$tntEvictionEvent][$j][$field] = isset($p[$k]) ? $p[$k] : '';
            if($field == 'tnt_eviction_event_id') {
              foreach($fileUpload as $fileUp) {
                if($p[$k] == $fileUp['foreign_id']) {
                  $data[$i][T::$tntEvictionEvent][$j][T::$fileUpload][] = $fileUp;
                }
              }
            }
          }
        }
      }
    }
    unset($rTenant, $fileUpload);
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function getSelectQuery($where = []){
    $where = empty($where) ? '' : 'WHERE ' . preg_replace('/^ AND /', '', Model::getRawWhere($where));
    $data = 'SELECT ' . Helper::joinQuery('ep',self::$_tntEvictionProcess) . Helper::groupConcatQuery(self::$_tntEvictionEvent, T::$tntEvictionEvent) . Helper::groupConcatQuery(self::$_fileUploadViewSelect, T::$fileUpload, 1) .' 
             FROM ' . T::$tntEvictionProcess . ' AS ep  
             LEFT JOIN ' . T::$tntEvictionEvent . ' AS ee ON ep.tnt_eviction_process_id=ee.tnt_eviction_process_id 
             LEFT JOIN ' . T::$fileUpload . ' AS f ON ee.tnt_eviction_event_id=f.foreign_id AND f.type="tenantEvictionEvent" AND f.active=1 '
             . $where . ' GROUP BY ep.tnt_eviction_process_id';
    return $data;
  }
}