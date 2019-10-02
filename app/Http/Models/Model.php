<?php 
namespace App\Http\Models;
use Illuminate\Support\Facades\DB;
use App\Library\{Mail, Html, Elastic, TableName AS T};
use App\Console\InsertToElastic\InsertToElastics;

class Model extends DB{
/**
 * @desc to commit the code to the database and elastic search if everything is good
 * @param {array} $success is can be single or double array
 * @Ex: $success = [
 *   'fileUpload' => [1, 1, 1],
 *   'application' => 19326
 *   'application_info' => 1
 * ]
 * @param {array} $elastic is an array that contact the table view as key and the main 
 *    id as child key and value of the id as an array to insert the data into the 
 *    elastic search
 * @Ex: $elastic = [
 *   'creditcheck_view' => [
 *     'application_id' => [19326]
 *   ]
 * @return {boolean} 1 successful insert the data, 0 fail to insert
 * @howToUse 
 */
  public static function commit($data){
    $success  = $data['success'];
    $elastic  = !empty($data['elastic']) ? $data['elastic'] : [];
        
    try{
      if(empty($success)){
        dd('Something wrong with update or insert');
      }
      # DETERMIND IF WE SHOULD ROLLBACK 
      foreach($success as $table=>$val){
        if(is_array($val)){
          foreach($val as $v){
            if(empty($v)){
              self::rollback(json_encode([$table=>$val], JSON_PRETTY_PRINT));
            }
          }
        }else{
          if(!$val){
            self::rollback(json_encode([$table=>$val], JSON_PRETTY_PRINT));
          }
        }
      }
    } catch(\Exception $e){
      dd($e);
    }

    try{
      # START TO INSERT DATA TO ELASTIC SEARCH
      if(!empty($elastic['insert'])){
        foreach($elastic['insert'] as $viewTable=>$val){
          if(!empty($val)){
            $elasticClass  = '\App\Console\InsertToElastic\ViewTableClass\\' . $viewTable ;
            $elasticMethod = 'getSelectQuery';
            $q = $elasticClass::$elasticMethod($val);
            $r = DB::select(DB::raw($q));
            if(!empty($r)){
              $elasticItem = InsertToElastics::insertData($viewTable, $r, 0)['items'];
              if(!empty($elasticItem)){
                foreach($elasticItem as $i=>$v){
                  if($v['index']['status'] < 200 || $v['index']['status'] > 299){
                    self::rollback(json_encode($elasticItem, JSON_PRETTY_PRINT));
                  }
                }
              }else{
                self::rollback(json_encode($elasticItem, JSON_PRETTY_PRINT));
              }
            }
          }
        }
      }
      if(!empty($elastic['update'])){
        foreach($elastic['update'] as $viewTable=>$val){
          if(!empty($val)){
            $elasticClass  = '\App\Console\InsertToElastic\ViewTableClass\\' . $viewTable ;
            $elasticMethod = 'getSelectQuery';
            $q = $elasticClass::$elasticMethod($val);
            $r = DB::select(DB::raw($q));
            if(!empty($r)){
              $elasticItem = InsertToElastics::update($viewTable, $r);
              foreach($elasticItem as $id=>$val){
                if(empty($val['_shards']['successful'])){
                  self::rollback(json_encode($elasticItem, JSON_PRETTY_PRINT));
                }
              }
            }
          }
        }
      }
      if(!empty($elastic['delete'])){
        foreach($elastic['delete'] as $viewTable=>$mustQuery){
          $elasticItem = Elastic::deleteByQuery(['index'=>$viewTable, 'query'=>['must'=>$mustQuery]]);
          if($elasticItem['deleted'] < 0 || !empty($elasticItem['failures'])){
            self::rollback(json_encode($elasticItem, JSON_PRETTY_PRINT));
          }
        }
      }
    } catch(\Exception $e){
      dd($e);
    }
    DB::commit();
    sleep(1);
    return 1;
  }
/**
 * @desc update the data 
 *  IMPORTANT NOTE: the primary is must be table_id if it is not, then need to add it manuelly
 * @param {array} $updateData key is the table name and 
 *    value can be : 
 *    1. consists of whereData and updateData 
 *    2. is another array consisting of whereData and updateData 
 *   ex: 
 *    [
 *      tableName=>[
 *        'whereData'=>[], 'updateData'=>[]
 *      ]
 *    ] OR
 *    [
 *      tableName=>[
 *        ['whereData'=>[], 'updateData'=>[]],
 *        ['whereData'=>[], 'updateData'=>[]]
 *      ]
 *    ] OR
 * @return {array} return the array if the primary key
 */
  public static function update($updateData){
    $data = [];
    if(empty($updateData)){
      echo 'Update data is empty'; exit;
    }
    foreach($updateData as $table=>$value){
      $isMultipleUpdateDat = empty($value[0]) ? 0 : 1; 
      $value = empty($value[0]) ? [$value] : $value;
      foreach($value as $i=>$val){
        $where   = isset($val['whereData']) ? $val['whereData']: []; 
        $whereIn = isset($val['whereInData']) ? $val['whereInData'] : []; 
        $whereIn = isset($whereIn['field']) ? [$whereIn]  : $whereIn; 
        
        $whereBetween = isset($val['whereBetweenData']) ? $val['whereBetweenData'] : [];
        
        $update  = empty($val['updateData'][0]) ? [$val['updateData']] : $val['updateData'];
        $key     = 'update:' . $table . ($isMultipleUpdateDat ? $i : '');

        if(!empty($update)){
          foreach($update as $v){
            try{
              if(empty($where) && empty($whereIn) && empty($whereBetween)){
                dd('where,whereIn,or whereBetween cannot be empty');
              }
              
//              $r = DB::table($table);
              if(!empty($where)){
                DB::table($table)->where(self::buildWhere($where))->update($v);
                $data[$key][] = implode('-', $where);
              }
              if(!empty($whereIn)){
                $db = DB::table($table);
                foreach($whereIn as $i=>$val){
                  $db->whereIn($val['field'], $val['data']);
                }
                $db->update($v);
                $data[$key][] = $whereIn[0]['data'];
              }
              if(!empty($whereBetween)){
                DB::table($table)->whereBetween($whereBetween['field'], $whereBetween['data'])->update($v);
                $data[$key][] = $whereIn['data'];
              }
//              $r->update($v);
//              dd($where);
//              $data[$key][] = implode('-', $where);
            } catch(\Exception $e){
//              if(env('APP_ENV') == 'local') { 
                dd('Update error for table '.$table.'. Check the following:', $e, $update, $v); 
//              }
              $data[$key] = 0;
            }
          }
        } else{ // If it is empty let pass
          echo 'Update data from ' . $table . ' is empty'; exit;
        }
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  /**
   * @desc insert into the data
   * @param {array} $where is one demension array
   * @param {array} $updateData is 2 demension array
   * @return return return the array if the primary key
   */
//  public static function insert($table, $insertData){
//    $data = [];
//    $insertData = empty($insertData[0]) ? [$insertData] : $insertData;
//    foreach($insertData as $v){
//      $data[] = DB::table($table)->insertGetId($v);
//    }
//    
//    return $data;
//  }
  public static function insert($insertData){
    $data = [];
    foreach($insertData as $table=>$val){
      $insert = empty($val[0]) ? [$val] : $val;
      foreach($insert as $v){
        $data['insert:'.$table][] = DB::table($table)->insertGetId($v);
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function insertUpdate(){
  }
/**
 * @desc Will roll back the data if there is any error and email the e
 * @param {string} $e is a error string
 * @return {string} will return html error message
 */
  public static function rollback($e, $key = ''){
    DB::rollback();
    dd($e);
    self::emailMysqlError($e);
    echo json_encode(['error'=>['msg'=>Html::mysqlError()]]);
    exit;
  }
//------------------------------------------------------------------------------
  public static function emailMysqlError($e){
    Mail::send([
      'to'      =>Mail::emailList('debug'),
      'from'    =>Mail::emailList('debug'),
      'subject' =>'MySQL QUERY ERRROR',
      'msg'     => 'Some query is not correct: ' . Html::br(2) . json_encode($e, JSON_PRETTY_PRINT)
    ]); 
  }

  public static function performQuery($dataset, $debug = 0){
    DB::beginTransaction();
    try{
      $sucecss = 0;
      foreach($dataset as $v){
        switch ($v['action']) {
          case 'insert' : $sucecss = DB::table($v['table'])->insert($v['data']); break;
          case 'update' : $sucecss = DB::table($v['table'])->where($v['where'])->update($v['data']); break;
        }
        
        if(!$sucecss){
          DB::rollback();
          self::_email(json_encode($v, JSON_PRETTY_PRINT)); break;
        }
      }
      
      if(!$debug){
        DB::commit();
      } else{
        DB::rollback();
      }
    } catch (\Exception $e) {
      DB::rollback();
      self::_email( json_encode($e, JSON_PRETTY_PRINT));
    }
    return $sucecss;
  }
//------------------------------------------------------------------------------
  public static function buildWhere($data, $sign = '='){
    $response = [];
    foreach($data as $k=>$v){
      $response[] = [$k, $sign, $v];
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public static function getRawWhere($where = []){
    $whereStr = '';
    if(!empty($where)){
      if(is_array($where)){
        foreach($where as $field=>$val){
          $id = [];
          foreach($val as $idNum){
            if(is_numeric($idNum)){
              $id[] = $idNum;
            } else {
              $id[] = '"' . $idNum . '"';
            }
          }
          $whereStr .= ' AND ' . $field .' IN (' . implode(',', $id) . ')';
        }
      } else {
        $whereStr = ' ' . preg_replace('/^WHERE /i',' AND ',trim($where));
      }
    }
    return $whereStr;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
}