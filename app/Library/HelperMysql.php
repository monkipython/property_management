<?php
namespace App\Library;
use App\Library\{TableName AS T, Elastic, Helper};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model; // Include the models class

class HelperMysql{
  private static $_glChart;
  private static $_service;  
  private static $_rProp;
  private static $_rBank;     
  private static $_rTenant;   
  private static $_rPropBank; 
    
  public static function getTableData($table, $where = [], $select = '*', $isFirstRow = 0) {
    $r = DB::table($table)->select($select)->where($where);
    return $isFirstRow ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
//------------------------------------------------------------------------------
  public static function getGlChat($where, $selectField = [], $firstRowOnly = 0){
    $selectField = !empty($selectField) ? $selectField : ['g.gl_acct','g.title', 's.remark AS serviceRemark','g.acct_type','g.no_post','g.type1099','s.service'];
    $r = DB::table(T::$glChart . ' AS g')->select($selectField)
        ->leftJoin(T::$service . ' AS s', function($join){
          $join->on('s.prop', '=', 'g.prop')
               ->on('s.gl_acct', '=', 'g.gl_acct');
        })
      ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getGlChartIn($whereField,$whereData,$selectField=[],$firstRowOnly=0){
    $selectField = !empty($selectField) ? $selectField : ['g.gl_acct','g.title', 's.remark AS serviceRemark','g.acct_type','g.no_post','g.type1099','s.service'];
    $r = DB::table(T::$glChart . ' AS g')->select($selectField)
        ->leftJoin(T::$service . ' AS s', function($join){
          $join->on('s.prop', '=', 'g.prop')
               ->on('s.gl_acct', '=', 'g.gl_acct');
        })
      ->whereIn($whereField,$whereData);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getProp($prop = [], $source = [], $isFirstRow = 0, $isUseElasticResult = 0){
    $r = Elastic::searchQuery([
      'index'   =>T::$propView,
      '_source' =>$source,
      'size'    =>50000,
      'query'   =>self::_getQuery($prop, 'prop.keyword') 
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getBank($must, $source = [],$param = [], $isFirstRow = 1, $isUseElasticResult = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$bankView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getCompany($source = [], $isFirstRow = 0, $isUseElasticResult = 1){
    $r = Elastic::searchQuery([
      'index'   =>T::$companyView,
      '_source' =>$source
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getGroup($must, $source = [], $param = [], $isFirstRow = 0, $isUseElasticResult = 0){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$groupView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getTrust($must, $source = [], $param = [], $isFirstRow = 1, $isUseElasticResult = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$trustView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getUnit($must, $source = [],$param = [], $isFirstRow = 1, $isUseElasticResult = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$unitView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getTenant($must, $source = [], $param = [], $isFirstRow = 1, $isUseElasticResult = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$tenantView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getTntTrans($must, $source = [], $param = [], $isFirstRow = 1, $isUseElasticResult = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$tntTransView,
      '_source' =>$source,
      'size'    =>100000,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getGlTrans($must, $source = [], $param = [], $isFirstRow = 1, $isUseElasticResult = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$glTransView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getVendor($must, $source = [], $param = [], $isFirstRow = 1, $isUseElasticResult = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$vendorView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>isset($must['must']) ? $must['must'] : $must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getGlChart($must, $source = [], $param = [], $isFirstRow = 0, $isUseElasticResult = 1){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    $r = Elastic::searchQuery([
      'index'   =>T::$glChartView,
      '_source' =>$source,
      'sort'    =>$sort,
      'query'   =>['must'=>$must]
    ]);
    return self::_getResult($r, $isFirstRow, $isUseElasticResult);
  }
//------------------------------------------------------------------------------
  public static function getService($where, $selectField = '*', $firstRowOnly = 0){
    $r = DB::table(T::$service)->select($selectField)->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }  
//------------------------------------------------------------------------------
  public static function getDefaultBank($prop, $defaultBankType = 'ar_bank'){
    $r = Elastic::searchQuery([
      'index'   =>T::$propView,
      '_source' =>[$defaultBankType],
      'query'   =>['must'=>['prop.keyword'=>$prop]]
    ]);
    //return !empty($r) ? Helper::getElasticResult($r, 1)['_source'][$defaultBankType] : ''; 
    return !empty($r) ? Helper::getValue($defaultBankType,Helper::getValue('_source',Helper::getElasticResult($r,1),[])) : '';
  }
//------------------------------------------------------------------------------
  public static function getServiceElastic($must,$source=[],$firstRowOnly = 0){
    $queryBody    = ['index' => T::$serviceView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $r            = Helper::getElasticResult(Elastic::searchQuery($queryBody),$firstRowOnly);
    if($firstRowOnly){
      return !empty($r) ? $r['_source'] : [];
    } else{
      return $r;
    }
  }
//------------------------------------------------------------------------------
  public static function getPropBank($must, $source = ['trust','prop', 'bank', 'gl_acct']){
    return Elastic::searchQuery([
      'index'   =>T::$bankView,
      '_source' =>$source,
      'query'   =>['must'=>$must]
    ]);
  }
//------------------------------------------------------------------------------
  public static function getBatchGroupNumber(){
    return DB::table(T::$vendorPayment)->max('vendor_payment_id') + 1;
  }
//------------------------------------------------------------------------------
  public static function getBatchNumber(){
    $where = ['prog'=>'batch', 'parm'=>1];
    $r = DB::table(T::$cntlparm)->select('*')->where(Model::buildWhere($where))->first();
    $batch = ++$r['numbers'];
    if(!DB::table(T::$cntlparm)->where(Model::buildWhere($where))->update(['numbers'=>$batch])){
      echo 'wrong'; exit;
    }
    return $batch;
  }
//------------------------------------------------------------------------------
  public static function getControlNumber(){
    $where = ['prog'=>'CONTROL', 'parm'=>1];
    $r = DB::table(T::$cntlparm)->select('*')->where(Model::buildWhere($where))->first();
    return $r['numberi'];
  }
//------------------------------------------------------------------------------
  public static function getReturnNumber(){
    $where = ['prog'=>'RETURN', 'parm'=>1];
    $r = DB::table(T::$cntlparm)->select('*')->where(Model::buildWhere($where))->first();
    $r['numberi'] = $r['numberi'] + 1;
    $returnNumber =  self::_calReturnNum(sprintf("%'.08d", $r['numberi']));
    if(!DB::table(T::$cntlparm)->where(Model::buildWhere($where))->update(['numberi'=>$r['numberi']])){
      echo 'wrong'; exit;
    }
    return $returnNumber;
  }
//------------------------------------------------------------------------------
  public static function updateControlNum($controlNumber){
    $where = ['prog'=>'CONTROL', 'parm'=>1];
    return DB::table(T::$cntlparm)->where(Model::buildWhere($where))->update(['numbers'=>$controlNumber]);
  }
//------------------------------------------------------------------------------
  public static function getDataSet($data, $usr, $glChart = [], $service = [], $splitCashAcct = 0){
    $dataSet     = [];
    $today       = date('Y-m-d');
    // Override Clean Data
    foreach($data as $table=>$value){
      if($table != 'summaryAcctReceivable' && $table != 'summaryAcctPayable'){
        // Determine if it is multiple or single array
        if(isset($value[0])){ // This is multiple array
          foreach($value as $i=>$val){
            if(isset($val['prop'])){
              $glChart = !empty($glChart) ? $glChart : Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$val['prop']])), 'gl_acct');
              $service = !empty($service) ? $service : Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$val['prop']])), 'service');
            }
  //          $val +=  $default;
            $val = self::_formatData($val) +  $val;
            $val += self::_getDefaultData($val, $today, $glChart, $service);
            $dataResult = self::$table($val, $usr);
            if(!empty($dataResult)){
              $dataSet[$table][$i] = $dataResult;
            }
          }
        } else{
  //        $value += $default;
          $value = self::_formatData($value) + $value;
          $value += self::_getDefaultData($value, $today, $glChart, $service);
          $dataResult = self::$table($value, $usr);
          if(!empty($dataResult)){
            $dataSet[$table] = $dataResult;
          }
        }
      }
    }
    if(isset($data['summaryAcctReceivable']) || isset($data['summaryAcctPayable'])){
      foreach($data as $table=>$value){
        if($table == 'summaryAcctReceivable' || $table == 'summaryAcctPayable'){
          $rSummaryClearCheck = self::$table($dataSet[T::$glTrans], $splitCashAcct);
          $dataSet[T::$glTrans] = array_merge($dataSet[T::$glTrans], $rSummaryClearCheck[T::$glTrans] );
          $dataSet[T::$clearedCheck] = $rSummaryClearCheck[T::$clearedCheck];
          
          foreach($rSummaryClearCheck[T::$glTrans] as $summaryValue){
            $dataSet[T::$batchRaw] = array_merge($dataSet[T::$batchRaw], [self::batch_raw($summaryValue,$usr)]);
          }
        }
      }
    }
    return $dataSet;
  }
################################################################################
######################   EACH TABLE DATA SETSECTION  ###########################  
################################################################################
  public static function tnt_trans($data, $usr){
    return self::_trimData([
      'prop'       => $data['prop'],
      'unit'       => $data['unit'],
      'tenant'     => $data['tenant'],
      'date1'      => $data['date1'],
      'journal'    => $data['journal'],
      'tx_code'    => $data['tx_code'],
      'jnl_class'  => $data['jnl_class'],
      'building'   => $data['building'],
      'per_code'   => $data['per_code'],
      'sales_off'  => $data['sales_off'],
      'sales_agent'=> $data['sales_agent'],
      'amount'     => $data['amount'],
      'dep_amt'    => 0,
      // appyto is the same as cntl_no and cntl_no is an autoincrement
      // to copy it, we need to set appyto to 0 and then run an update query
      // one more time. 
      'appyto'     => $data['appyto'],
      'remark'     => substr($data['remark'], 0, 30),
      'remarks'    => $data['remarks'],
      'bk_transit' => $data['bk_transit'],
      'bk_acct'    => $data['bk_acct'],
      'name_key'   => $data['name_key'], 
      'gl_acct'    => $data['gl_acct'],
      'gl_contra'  => $data['gl_contra'],
      'gl_acct_org'=> $data['gl_acct'],
      'check_no'   => $data['check_no'],
      'service_code'=> $data['service_code'],
      'usid'       => $usr,
      'batch'      => $data['batch'],
      'job'        => $data['job'],
      'doc_no'     => $data['check_no'],
      'bank'       => $data['bank'],
      'inv_date'   => $data['inv_date'],
      'invoice'    => $data['invoice'],
      'inv_remark' => substr($data['inv_remark'], 0, 30),
      'bill_seq'   => $data['bill_seq'],
      'net_jnl'    => $data['net_jnl'],
      'date2'      => $data['date2'],
      'rock'       => $data['rock'],
    ]);
  }
//------------------------------------------------------------------------------
  public static function tnt_security_deposit($data, $usr){
    return self::_trimData([
      'prop'       => $data['prop'],
      'unit'       => $data['unit'],
      'tenant'     => $data['tenant'],
      'date1'      => $data['date1'],
      'tx_code'    => $data['tx_code'],
      'amount'     => $data['amount'],
      'appyto'     => $data['appyto'],
      'remark'     => substr($data['remark'], 0, 30),
      'gl_acct'    => $data['gl_acct'],
      'check_no'   => $data['check_no'],
      'service_code'=> $data['service_code'],
      'is_move_out_process_trans' => $data['is_move_out_process_trans'],
      'usid'       => $usr,
      'batch'      => $data['batch'],
      'cdate'      => $data['cdate'],
      'usid'       => $usr
    ]);
  }
//------------------------------------------------------------------------------
  public static function gl_trans($data, $usr){
    if(!isset($data['prop'])){
      dd($data);
    }
    return self::_trimData([
      'prop'        => $data['prop'],
      'gl_acct'     => $data['gl_acct'],
      'date1'       => $data['date1'],
      'journal'     => $data['journal'],
      'batch'       => $data['batch'],
      'seq'         => 0, // need to increment manually
      'post_mo'     => 0,
      'tx_code'     => $data['tx_code'],
      'jnl_class'   => $data['jnl_class'], // jnl_class always A
      'building'    => $data['building'],
      'sales_off'   => $data['sales_off'],
      'sales_agent' => $data['sales_agent'],
      'amount'      => $data['amount'],
      'other_amt'   => 0,
      'appyto'      => $data['appyto'],
      'remark'      => substr($data['remark'], 0, 30),
      'remarks'     => $data['remarks'],
      'bk_transit'  => $data['bk_transit'],
      'bk_acct'     => $data['bk_acct'],
      'name_key'    => $data['name_key'],
      'gl_contra'   => $data['gl_contra'],
      'gl_acct_org' => $data['gl_acct'],
      'check_no'    => $data['check_no'],
      'service_code'=> $data['service_code'],
      'usid'        => $usr,
      'job'         => $data['job'],
      'doc_no'      => $data['check_no'],
      'bank'        => $data['bank'],
      'unit'        => $data['unit'],
      'tenant'      => $data['tenant'],
      'code1099'    => $data['code1099'],
      'vendor'      => $data['vendor'],
      'inv_date'    => $data['inv_date'],
      'invoice'     => $data['invoice'],
      'cons_prop'   => $data['cons_prop'],
      'net_jnl'     => $data['net_jnl'],
      'rock'        => $data['rock'],
      'group1'      => $data['group1'],
      'title'       => substr(preg_replace('/Payment/', '', $data['remark']), 0,30),
      'acct_type'   => $data['acct_type'],
      'date2'       => $data['date2'],
    ]);
  }
//------------------------------------------------------------------------------
  public static function batch_raw($data, $usr) {
    return self::_trimData([
      'batch'      => $data['batch'],
      'sys_date'   => Helper::mysqlDate(), // It's not timestamp so we need to set like this
      'seq'        => 0, // need to increment manually
      'prop'       => $data['prop'],
      'gl_acct'    => $data['gl_acct'],
      'date1'      => $data['date1'],
      'journal'    => $data['journal'],
      'post_mo'    => 0,
      'tx_code'    => $data['tx_code'],
      'jnl_class'  => $data['jnl_class'], // jnl_class always A
      'building'   => $data['building'],
      'per_code'   => isset($data['per_code']) ? $data['per_code'] : '',
      'sales_off'  => $data['sales_off'],
      'sales_agent'=> $data['sales_agent'],
      'debit'      => 0,
      'credit'     => 0,
      'revamt'     => 'N',
      'amount'     => $data['amount'],
      'other_amt'  => 0,
      'cntl_no'    => 0,
      'appyto'     => $data['appyto'],
      'remark'     => substr($data['remark'], 0, 30),
      'remarks'    => $data['remarks'],
      'bk_transit' => $data['bk_transit'],
      'bk_acct'    => '',
      'name_key'   => $data['name_key'],
      'gl_acct_org'=> $data['gl_acct'],
      'check_no'   => $data['check_no'],
      'service_code'=>$data['service_code'],
      'service_type'=>isset($data['service_type']) ? $data['service_type'] : '',
      'inv_remark' => substr($data['remark'], 0, 30),
      'bill_seq'   => isset($data['bill_seq']) ? $data['bill_seq'] : 0,
      'usid'       => $usr,
      'job'        => $data['job'],
      'doc_no'     => $data['check_no'],
      'bank'       => $data['bank'],
      'is_bank'    => 'N',
      'unit'       => $data['unit'],
      'tenant'     => $data['tenant'],
      'code1099'   => $data['code1099'],
      'vendor'     => $data['vendor'],
      'inv_date'   => $data['inv_date'],
      'invoice'    => $data['invoice'],
      'cons_prop'  => $data['cons_prop'],
      'appc_seq'   => 0,
      'tnt_spec'   => 'R',
      'as_is'      => 'N',
      'net_jnl'    => $data['net_jnl'],
      'postdone'   => 'P',
      'postpass1'  => 'P',
      'postpass2'  => 'P',
      'postpendck' => 'D',
      'posttnt'    => 'D',
      'posttnttx'  => 'D',
      'postarmv'   => 'D',
      'postbill'   => 'D',
      'postgltx'   => 'P',
      'date2'      => $data['date2'],
    ]);
  }  
//------------------------------------------------------------------------------
  public static function summaryAcctPayable($gltrans, $splitCashAcct = 0){
    $amount    = $data      = $clearedCheckData = $prop = [];
    $rPropBank = Helper::keyFieldNameElastic(self::getPropBank(['prop.keyword'=>array_column($gltrans, 'prop')]), ['prop', 'bank']);
    $_getId = function($v, $rPropBank){
      $bank = isset($rPropBank[$v['prop'] . $v['bank']]) || isset($v['bank']) && isset($rPropBank[$v['prop'] . $v['bank']]) ? $v['bank'] : self::getDefaultBank($v['prop']);
      return $v['batch'] . '-' . $bank . '-' . $v['prop'] . $v['check_no'];
    };
    
    foreach($gltrans as $i=>$v){
      $id          = ($splitCashAcct) ? $i : $_getId($v, $rPropBank);
      $amount[$id] = isset($amount[$id]) ?  $amount[$id] + $v['amount'] : $v['amount'];
      $data[$id]   = $v;
      $prop[$v['prop']] = $v['prop'];
    }
    
    foreach($data as $i=>$v){
      $bank   = isset($rPropBank[$v['prop'] . $v['bank']]) || isset($v['bank']) && isset($rPropBank[$v['prop'] . $v['bank']]) ? $v['bank'] : self::getDefaultBank($v['prop']);
      $id     = ($splitCashAcct) ? $i : $_getId($v, $rPropBank);
      $glAcct = $rPropBank[$v['prop']. $bank]['gl_acct'];
//      $data[$i]['unit']     = '';
//      $data[$i]['tenant']   = '255';
//      
      $data[$i]['unit']     = isset($v['unit']) ? $v['unit'] : '';
      $data[$i]['tenant']   = isset($v['tenant']) ? $v['tenant'] : '255';
      $data[$i]['tx_code']  = 'CP';
      $data[$i]['journal']  = 'CP';
      $data[$i]['jnl_class']= 'C';
      $data[$i]['remark']   = 'Summary';
      $data[$i]['remarks']  = '';
      $data[$i]['amount']   = $amount[$id] * -1;
      $data[$i]['service_code'] = '';
      $data[$i]['gl_acct']      = $glAcct;
      $data[$i]['gl_contra']    = $glAcct;
      $data[$i]['gl_acct_org']  = $glAcct;
      $data[$i]['acct_type']    = 'A';
      $data[$i]['title']        = 'CASH ACCOUNT - ' . $v['gl_acct'];
      
      $vendorName = !empty($v['vendor']) ? $v['vendor'] : $v['remark'];
      $clearedCheckData[$i] = self::cleared_check($data[$i], $rPropBank, $vendorName);
    }
    return [T::$glTrans=>array_values($data), T::$clearedCheck=>array_values($clearedCheckData)];
  }
//------------------------------------------------------------------------------
  public static function summaryAcctReceivable($gltrans, $splitCashAcct = 0){
    $amount    = [];
    $data      = $clearedCheckData = $prop = [];
    $rPropBank = Helper::keyFieldNameElastic(self::getPropBank(['prop.keyword'=>array_column($gltrans, 'prop')]), ['prop', 'bank']);
    $_getId = function($v, $rPropBank){
      $bank = isset($rPropBank[$v['prop'] . $v['bank']]) || isset($v['bank']) && isset($rPropBank[$v['prop'] . $v['bank']]) ? $v['bank'] : self::getDefaultBank($v['prop']);
      return $v['batch'] . '-' . $bank . '-' . $v['prop'] . '-' . $v['check_no'];
    };
    
    foreach($gltrans as $i=>$v){
      $id          = ($splitCashAcct) ? $i : $_getId($v, $rPropBank);
      $amount[$id] = isset($amount[$id]) ?  $amount[$id] + $v['amount'] : $v['amount'];
      $data[$id]   = $v;
      $prop[$v['prop']] = $v['prop'];
    }
    
    foreach($data as $i=>$v){
      $bank   = isset($rPropBank[$v['prop'] . $v['bank']]) || isset($v['bank']) && isset($rPropBank[$v['prop'] . $v['bank']]) ? $v['bank'] : self::getDefaultBank($v['prop']);
      $id     = ($splitCashAcct) ? $i : $_getId($v, $rPropBank);
      $glAcct = $rPropBank[$v['prop']. $bank]['gl_acct'];
//      $data[$i]['unit']     = '';
//      $data[$i]['tenant']   = '255';
//      
      $data[$i]['unit']     = isset($v['unit']) ? $v['unit'] : '';
      $data[$i]['tenant']   = isset($v['tenant']) ? $v['tenant'] : '255';
      $data[$i]['tx_code']  = 'CR';
      $data[$i]['journal']  = 'CR';
      $data[$i]['jnl_class']= 'C';
      $data[$i]['remark']   = 'Summary';
      $data[$i]['remarks']  = '';
      $data[$i]['amount']   = $amount[$id] * -1;
      $data[$i]['service_code'] = '';
      $data[$i]['gl_acct']      = $glAcct;
      $data[$i]['gl_contra']    = $glAcct;
      $data[$i]['gl_acct_org']  = $glAcct;
      $data[$i]['acct_type']    = 'A';
      $data[$i]['title']        = 'CASH ACCOUNT - ' . $v['gl_acct'];
      
      $vendorName = !empty($v['vendor']) ? $v['vendor'] : $v['remark'];
      $clearedCheckData[$i] = self::cleared_check($data[$i], $rPropBank, $vendorName);
    }
    return [T::$glTrans=>array_values($data), T::$clearedCheck=>array_values($clearedCheckData)];
  }
//------------------------------------------------------------------------------
  public static function cleared_check($v,$rPropBank,$vendorName){
    $bank = isset($rPropBank[$v['prop'] . $v['bank']]) || isset($v['bank']) && isset($rPropBank[$v['prop'] . $v['bank']]) ? $v['bank'] : self::getDefaultBank($v['prop']);
    return [
      'prop'      =>$rPropBank[$v['prop'] . $bank]['trust'],
      'bank'      =>$v['bank'], 
      'orgprop'   =>$v['prop'], 
      'batch'     =>$v['batch'], 
      'ref1'      =>$v['check_no'], 
      'amt'       =>$v['amount'] * -1, 
      'date1'     =>$v['date1'], 
      'cxl'       =>'N', 
      'vendorname'=>$vendorName, 
      'cxldate'   =>'1000-01-01', 
      'usid'      =>$v['usid'], 
      'sys_date'  =>Helper::mysqlDate(), 
      'rock'      =>'@', 
      'cleared_check_id'=>0
    ];
  }
//------------------------------------------------------------------------------
  public static function tenant($data, $usr) {
    $end_date = '9999-12-31';
    $today_date = date('Y-m-d');
    return self::_trimData([
      'prop'              => $data['prop'],
      'unit'              => $data['unit'],
      'tenant'            => $data['tenant'],
      'base_rent'         => $data['base_rent'],
      'tnt_name'          => $data['tnt_name'],
      'status'            => $data['status'],
      'bill_day'          => $data['bill_day'],
      'spec_code'         => $data['spec_code'],
      'rock'              => $data['rock'],
      'fax'               => $data['fax'],
      'e_mail'            => $data['e_mail'],
      'web'               => $data['web'],
      'move_in_date'      => date('Y-m-d', strtotime($data['move_in_date'])),
      'move_out_date'     => $end_date,
      'lease_esc_date'    => $end_date,
      'lease_opt_date'    => $end_date,
      'housing_dt1'       => $today_date,
      'housing_dt2'       => $today_date,
      'lease_exp_date'    => $end_date,
      'lease_start_date'  => date('Y-m-d', strtotime($data['move_in_date'])),
      'last_late_date'    => $end_date,
      'last_check_date'   => $today_date,
      'dep_int_last_date' => $today_date,
      'dep_date'          => $today_date,
      'phone1'            => substr(preg_replace('/[\-\(\) ]+/', '', $data['phone1']), 0, 10),
      'return_no'         => self::getReturnNumber(),
      'bal_code'          => 'O',
      'late_rate_code'    => 'M',
      'tax_code'          => 'N',
      'bank_transit'      => '000000000',
      'late_after'        => 3,
      'usid'              => $usr,
      'dep_held1'         => $data['dep_held1'], //This have to be zero because it hasn't billed yet.
//      'isManager'         => $data['isManager']
    ]);
  }
//------------------------------------------------------------------------------
  public static function tenant_utility($data, $usr){
    return self::_trimData([
      'prop'=>$data['prop'], 
      'unit'=>$data['unit'], 
      'tenant'=>$data['tenant'], 
      'water'=>$data['water'], 
      'gas'=>$data['gas'], 
      'electricity'=>$data['electricity'], 
      'trash'=>$data['trash'], 
      'sewer'=>$data['sewer'], 
      'landscape'=>$data['landscape'],
      'pet'=>0, 
      'usid'=>$usr,
    ]);
  }
//------------------------------------------------------------------------------
  public static function billing($data, $usr) {
    return self::_trimData([
      'prop'         => $data['prop'],
      'unit'         => $data['unit'],
      'tenant'       => $data['tenant'],
      'bill_seq'     => $data['bill_seq'],
      'service_code' => $data['service_code'],
      'amount'       => $data['amount'],
      'remark'       => $data['remark'],
      'remarks'      => $data['remarks'],
      'schedule'     => 'M',
      'gl_acct'      => $data['gl_acct'],
      'gl_acct_past' => $data['gl_acct_past'],
      'gl_acct_next' => $data['gl_acct_next'],
      'service_type' => $data['service_type'],
      'cam_exp_gl_acct'=> $data['cam_exp_gl_acct'],
      'amount'       => $data['amount'],
      'post_date'    => '1000-01-01',
      'tax_cd'       => 'N',
      'comm_flg'     => 'N',
      'mangt_flg'    => 'N',
      'seq'          => $data['seq'],
      'post_date'    => $data['post_date'],
      'start_date'   => date('Y-m-d', strtotime($data['start_date'])),
      'stop_date'    => date('Y-m-d', strtotime($data['stop_date'])),
      'usid'         => $usr,
      'rock'         => $data['rock']
    ]);
  }
//------------------------------------------------------------------------------
  public static function rent_raise($data,$usr){
    return self::_trimData([
      'foreign_id'        => $data['foreign_id'],
      'prop'              => $data['prop'],
      'unit'              => $data['unit'],
      'tenant'            => $data['tenant'],
      'raise'             => $data['raise'],
      'raise_pct'         => $data['raise_pct'],
      'notice'            => $data['notice'],
      'service_code'      => $data['service_code'],
      'gl_acct'           => $data['gl_acct'],
      'remark'            => $data['remark'],
      'rent'              => $data['rent'],
      'active'            => 1,
      'usid'              => $usr,
      'last_raise_date'   => $data['last_raise_date'],
      'submitted_date'    => $data['submitted_date'],
      'effective_date'    => $data['effective_date'],
      'isCheckboxChecked' => $data['isCheckboxChecked'],
      'file'              => Helper::getValue('file',$data),
    ]);
  }
//------------------------------------------------------------------------------	
  public static function vendor($data,$usr){	
    return self::_trimData([	
      'vendid'      =>$data['vendid'], 	
      'name'        =>$data['name'],	
      'line2'       =>$data['line2'],	
      'street'      =>$data['street'], 	
      'city'        =>$data['city'], 	
      'state'       =>$data['state'], 	
      'zip'         =>$data['zip'], 	
      'phone'       =>$data['phone'], 	
      'fax'         =>$data['fax'], 	
      'e_mail'      =>$data['e_mail'], 	
      'web'         =>$data['web'], 	
      'gl_acct'     =>$data['gl_acct'], 	
      'name_key'    =>$data['name_key'], 	
      'flg_1099'    =>$data['flg_1099'], 	
      'fed_id'      =>$data['fed_id'], 	
      'vendor_type' =>$data['vendor_type'], 	
      'tin_type'    =>$data['tin_type'], 	
      'tin_date'    =>$data['tin_date'], 	
      'contr_no'    =>$data['contr_no'], 	
      'remarks'     =>$data['remarks'], 	
      'pay_code'    =>$data['pay_code'], 	
      'inv_edit'    =>'I', 	
      'usid'        =>$usr	
    ]);	
  }
//------------------------------------------------------------------------------
  private static function alt_add($data, $usr) {
    return self::_trimData([
      'prop'     => $data['prop'],
      'unit'     => $data['unit'],
      'alt_code' => 'A',
      'tenant'   => $data['tenant'],
      'street'   => $data['street'],
      'city'     => $data['city'],
      'state'    => $data['state'],
      'zip'      => $data['zip'],
      'usid'     => $usr,
      'rock'     => $data['rock']
    ]);
  }
//------------------------------------------------------------------------------
  private static function mem_tnt($data, $usr) {
    return self::_trimData([
      'prop'      => $data['prop'],
      'unit'      => $data['unit'],
      'member'    => 0,
      'tenant'    => $data['tenant'],
      'last_name' => $data['last_name'],
      'first_name'=> $data['first_name'],
      'phone_bis' => $data['phone_bis'],
      'phone_ext' => $data['phone_ext'],
      'relation'  => $data['relation'],
      'occupation'=> $data['occupation'],
      'usid'      => $usr,
      'rock'      => $data['rock']
    ]);
  }
//------------------------------------------------------------------------------
  private static function track_ledgercard_fix($data, $usr){
    return [
      'cntl_no'=>$data['cntl_no'],
      'cdate'=>$data['cdate'],
      'usid' => $usr
    ];
  }
//------------------------------------------------------------------------------
  private static function vendor_insurance($data,$usr){
    $bank    = !empty($data['prop']) && !empty($data['bank']) ? $data['bank'] : self::getDefaultBank($data['prop']);
    return [
      'vendid'                           => $data['vendid'],
      'vendor_id'                        => $data['vendor_id'],
      'prop'                             => $data['prop'],
      'bank'                             => $bank,
      'policy_num'                       => $data['policy_num'],
      'invoice_date'                     => $data['invoice_date'],
      'effective_date'                   => $data['effective_date'],
      'gl_acct'                          => $data['gl_acct'],
      'auto_renew'                       => $data['auto_renew'],
      'amount'                           => $data['amount'],
      'ins_total'                        => $data['ins_total'],
      'ins_building_val'                 => $data['ins_building_val'],
      'ins_rent_val'                     => $data['ins_rent_val'],
      'ins_sf'                           => $data['ins_sf'],
      'remark'                           => $data['remark'],
      'number_payment'                   => $data['number_payment'],
      'monthly_payment'                  => Helper::getValue('monthly_payment',$data,0),
      'start_pay_date'                   => Helper::getValue('start_pay_date',$data,'1000-01-01'),
      'payer'                            => $data['payer'],
      'broker'                           => Helper::getValue('broker',$data),
      'carrier'                          => Helper::getValue('carrier',$data),
      'date_insured'                     => Helper::getValue('date_insured',$data,'1000-01-01'),
      'occ'                              => Helper::getValue('occ',$data),
      'building_value'                   => Helper::getValue('building_value',$data,0),
      'deductible'                       => Helper::getValue('deductible',$data,0),
      'lor'                              => Helper::getValue('lor',$data),
      'building_ordinance'               => Helper::getValue('building_ordinance',$data),
      'general_liability_limit'          => Helper::getValue('general_liability_limit',$data),
      'general_liability_deductible'     => Helper::getValue('general_liability_deductible',$data),
      'insurance_company'                => Helper::getValue('insurance_company',$data),
      'insurance_premium'                => Helper::getValue('insurance_premium',$data,0),
      'down_payment'                     => Helper::getValue('down_payment',$data,0),
      'installments'                     => Helper::getValue('installments',$data,0),
      'usid'                             => $usr,
    ];
  }
//------------------------------------------------------------------------------
  private static function vendor_maintenance($data,$usr){
    return [
      'vendid'         => $data['vendid'],
      'prop'           => $data['prop'],
      'gl_acct'        => $data['gl_acct'],
      'monthly_amount' => $data['monthly_amount'],
      'control_unit'   => $data['control_unit'],
      'vendor_id'      => $data['vendor_id'],
      'paid_period'    => Helper::getValue('paid_period',$data),
      'usid'           => $usr,
      'active'         => 1,
    ];
  }
//------------------------------------------------------------------------------
  private static function vendor_mortgage($data,$usr){
    $bank    = !empty($data['bank']) ? $data['bank'] : self::getDefaultBank($data['prop']);
    return [
      'prop'                => $data['prop'],
      'vendor_id'           => $data['vendor_id'],
      'vendid'              => $data['vendid'],
      'gl_acct_ap'          => $data['gl_acct_ap'],
      'gl_acct_liability'   => $data['gl_acct_liability'],
      'amount'              => $data['amount'],
      'invoice'             => $data['invoice'],
      'interest_rate'       => $data['interest_rate'],
      'loan_date'           => $data['loan_date'],
      'payment_type'        => $data['payment_type'],
      'due_date'            => $data['due_date'],
      'paid_off_loan'       => Helper::getValue('paid_off_loan',$data,0),
      'allocation'          => $data['allocation'],
      'init_principal'      => $data['init_principal'],
      'loan_term'           => $data['loan_term'],
      'journal_entry_date'  => $data['journal_entry_date'],
      'maturity_date'       => $data['maturity_date'],
      'loan_option'         => $data['loan_option'],
      'margin'              => $data['margin'],
      'dcr'                 => $data['dcr'],
      'index'               => $data['index'],
      'index_title'         => $data['index_title'],
      'payment_option'      => $data['payment_option'],
      'last_payment'        => $data['last_payment'],
      'recourse'            => Helper::getValue('recourse',$data),
      'loan_type'           => Helper::getValue('loan_type',$data),
      'note'                => Helper::getValue('note',$data),
      'prepaid_penalty'     => Helper::getValue('prepaid_penalty',$data),
      'escrow'              => Helper::getValue('escrow',$data),
      'prop_tax_impound'    => Helper::getValue('prop_tax_impound',$data,0),
      'reserve'             => Helper::getValue('reserve',$data,0),
      'additional_principal'=> Helper::getValue('additional_principal',$data,0),
      'bank'                => $bank,
      'usid'                => $usr,
    ];
  }
//------------------------------------------------------------------------------
  private static function vendor_pending_check($data, $usr){
    $bank    = !empty($data['bank']) ? $data['bank'] : HelperMysql::getDefaultBank($data['prop']);
    $unit    = Helper::getValue('unit',$data);
    $tenant  = Helper::getValue('tenant',$data);
    // NOT YET FINISH
    return [
      'prop'             =>$data['prop'],
      'unit'             =>$unit, 
      'tenant'           =>$tenant,
      'vendid'           =>$data['vendid'],
      'vendor_id'        =>$data['vendor_id'],
      'gl_acct'          =>$data['gl_acct'],
      'is_need_approved' => Helper::getValue('is_need_approved',$data,1),
      'amount'           =>$data['amount'],
      'remark'           =>$data['remark'],
      'bank'             => $bank,
      'invoice'          =>$data['invoice'],
      'invoice_date'     =>$data['invoice_date'],
      'recurring'        => $data['recurring'],
      'usid'             =>$usr
    ];
  }
//------------------------------------------------------------------------------  
  private static function vendor_payment($data, $usr){
    $bank = !empty($data['bank']) ? $data['bank'] : HelperMysql::getDefaultBank($data['prop']);
    return [
      'prop'              =>$data['prop'],
      'unit'              =>$data['unit'], 
      'tenant'            =>$data['tenant'],
      'vendid'            =>$data['vendid'],
      'vendor_id'         =>$data['vendor_id'],
      'foreign_id'        =>isset($data['foreign_id']) ? $data['foreign_id'] : 0,
      'is_with_signature' =>Helper::getValue('is_with_signature',$data,1),
      'approve'           =>Helper::getValue('approve',$data,'Pending Submission'),
      'type'              =>$data['type'],
      'bank'              =>$bank,
      'gl_acct'           =>$data['gl_acct'],
      'amount'            =>$data['amount'],
      'invoice'           =>$data['invoice'],
      'invoice_date'      =>$data['invoice_date'],
      'remark'            =>$data['remark'],
      'check_pdf'         =>$data['check_pdf'],
      'cdate'             =>$data['cdate'],
      'belongTo'          =>$usr,
      'usid'              =>$usr,
    ];
  }  
//------------------------------------------------------------------------------
  private static function vendor_gardenhoa($data,$usr){
    return [
      'prop'        =>$data['prop'],
      'vendid'      =>$data['vendid'],
      'vendor_id'   =>$data['vendor_id'],
      'remark'      =>$data['remark'],
      'amount'      =>$data['amount'],
      'gl_acct'     =>$data['gl_acct'],
      'invoice'     =>$data['invoice'],
      'account_id'  =>$data['account_id'],
      'note'        =>Helper::getValue('note',$data),
      'usid'        =>$usr,
    ];
  }
//------------------------------------------------------------------------------
  private static function vendor_util_payment($data,$usr){
    return [
      'prop'       => $data['prop'],
      'vendid'     => $data['vendid'],
      'vendor_id'  => $data['vendor_id'],
      'invoice'    => $data['invoice'],
      'gl_acct'    => $data['gl_acct'],
      'due_date'   => $data['due_date'],
      'usid'       => $usr,
    ];
  }
//------------------------------------------------------------------------------
  private static function mic_dt($tenant_dt) {
    $dt_rn = array();
    $dt_rn['bank_acct_no'] = substr($tenant_dt['return_no'], 0, 8) . substr($tenant_dt['return_no'], 9, 1);
    $dt_rn['prop1'] = $tenant_dt['prop'];
    $dt_rn['unit1'] = $tenant_dt['unit'];
    $dt_rn['tenant1'] = $tenant_dt['tenant'];
    $dt_rn['bad_check'] = 'N';
    $dt_rn['usid'] = $this->usr;
    $dt_rn['tenant2'] = '254';
    $dt_rn['tenant3'] = '254';
    $dt_rn['tenant4'] = '254';
    $dt_rn['tenant5'] = '254';
    $dt_rn['bank_transit'] = '000000000';

    return $dt_rn;
  }
//------------------------------------------------------------------------------
  private static function tnt_move_out_process($data, $usr) {
    return self::_trimData([
      'prop'     => $data['prop'],
      'unit'     => $data['unit'],
      'tenant'   => $data['tenant'],
      'status'   => $data['status'],
      'cdate'    => $data['cdate'],
      'usid'     => $usr
    ]);
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private static function _trimData($vData){
    if(isset($vData['amount']) && Format::floatNumber($vData['amount']) == 0){
      return [];
    } else{
      foreach($vData as $k=>$v){
        if(is_array($vData[$k])){
          dd('trim', $vData);
        }
        $vData[$k] = trim(preg_replace('/[\n\r]/','',$vData[$k]));
      }
      return $vData;
    }
  }
//------------------------------------------------------------------------------
  public static function _calReturnNum($wInAcct) {
    $wAcct2 = '';
    $wLen = strlen($wInAcct);
    $wCnt = 1;
    $wCnt2 = 1;
    $wNum2 = 0;
    $wNum3 = 0;
    $wNum = '';
    $wAcct = '';

    while ($wCnt <= $wLen){
      $wNum = substr($wInAcct, $wCnt - 1, 1);
      $wAcct = $wAcct . substr($wInAcct, $wCnt - 1, 1);

      If ($wCnt2 == 1) {
        if ($wNum == 0) {
          $wNum2 = $wNum2 + 0;
        } else if ($wNum == 1) {
          $wNum2 = $wNum2 + 2;
        } else if ($wNum == 2) {
          $wNum2 = $wNum2 + 4;
        } else if ($wNum == 3) {
          $wNum2 = $wNum2 + 6;
        } else if ($wNum == 4) {
          $wNum2 = $wNum2 + 8;
        } else if ($wNum == 5) {
          $wNum2 = $wNum2 + 1;
        } else if ($wNum == 6) {
          $wNum2 = $wNum2 + 3;
        } else if ($wNum == 7) {
          $wNum2 = $wNum2 + 5;
        } else if ($wNum == 8) {
          $wNum2 = $wNum2 + 7;
        } else if ($wNum == 9) {
          $wNum2 = $wNum2 + 9;
        }

        $wCnt2 = 2;
      } else {
        if ($wNum == 0) {
          $wNum2 = $wNum2 + 0;
        } else if ($wNum == 1) {
          $wNum2 = $wNum2 + 1;
        } else if ($wNum == 2) {
          $wNum2 = $wNum2 + 2;
        } else if ($wNum == 3) {
          $wNum2 = $wNum2 + 3;
        } else if ($wNum == 4) {
          $wNum2 = $wNum2 + 4;
        } else if ($wNum == 5) {
          $wNum2 = $wNum2 + 5;
        } else if ($wNum == 6) {
          $wNum2 = $wNum2 + 6;
        } else if ($wNum == 7) {
          $wNum2 = $wNum2 + 7;
        } else if ($wNum == 8) {
          $wNum2 = $wNum2 + 8;
        } else if ($wNum == 9) {
          $wNum2 = $wNum2 + 9;
        }
        $wCnt2 = 1;
      }
      $wCnt = $wCnt + 1;
    }

    $wAcct2 = $wNum2;
    $wNum3 = substr($wAcct2, -1);
    $wNum3 = 10 - $wNum3;
    $wAcct2 = $wNum3;
    $wAcct = $wAcct . substr($wAcct2, -1);

    return $wAcct;
  }
//------------------------------------------------------------------------------
  ##### THE DIFF B/W _formatData will take care of data that need to be reformat only While. This function will override the existing variable
  ##### _getDefaultData is used to get the default value if it does not exsit. This function will not override the existing variable
  private static function _formatData($data){
    return [
      'check_no' => isset($data['check_no']) ? sprintf("%06s", $data['check_no']) : '000000',
//      'amount'   => isset($data['amount']) ? Format::floatNumber($data['amount']) : 0,
    ];
  }
  private static function _getDefaultData($data, $today, $glChart, $service){
    $gl          = (isset($data['gl_acct'])) ? $data['gl_acct'] : '';
    $serviceCode = (isset($data['service_code'])) ? $data['service_code'] : '';
    $applyTo     = 0;
    return [
      'journal'     => isset($data['journal']) ? $data['journal'] : (isset($data['tx_code']) && $data['tx_code'] == 'P' ? 'CR' : 'AR'),
      'bk_transit'  => '',
      'bk_acct'     => '',
      'check_no'    => isset($data['check_no']) ? $data['check_no'] : '000000',
      'net_jnl'     => 'N',
      'inv_remark'  => isset($data['inv_remark']) ? $data['inv_remark'] : (isset($service[$serviceCode]['remark']) ? $service[$serviceCode]['remark'] : ''),
      'remarks'     => isset($data['remarks']) ? $data['remarks'] : (isset($service[$serviceCode]['remark']) ? $service[$serviceCode]['remark'] : ''),
      'bill_seq'    => isset($service[$serviceCode]['bill_seq']) ?  $service[$serviceCode]['bill_seq'] : '',
      'gl_acct_past'=> isset($service[$serviceCode]['gl_acct_past']) ?  $service[$serviceCode]['gl_acct_past'] : '',
      'gl_acct_next'=> isset($service[$serviceCode]['gl_acct_next']) ?  $service[$serviceCode]['gl_acct_next'] : '',
      'service_type'=> isset($service[$serviceCode]['service_type']) ?  $service[$serviceCode]['service_type'] : '',
      'cam_exp_gl_acct'=> isset($service[$serviceCode]['cam_exp_gl_acct']) ?  $service[$serviceCode]['cam_exp_gl_acct'] : '',
      'gl_acct_org' => $gl,
      'appyto'      => $applyTo,
      'date1'       => $today,
      'date2'       => $today,
      'jnl_class'   => 'A',
      'code1099'    => 'Y',
      'vendor'      => '',
      'post_date'   => $today,
      'bill_day'    => 1,
      'per_code'    => '',
      'sales_off'   => '',
      'sales_agent' => '',
      'debit'       => 0,
      'credit'      => 0,
      'revamt'      => 'N',
      'building'    => '',
      'spec_code'   => 'R',
      'invoice'     => 0,
      'inv_date'    => $today,
      'cons_prop'   => 999,
      'gl_contra'   => '',
      'group1'      => isset($data['group1']) ? $data['group1'] : self::_getGroup1($data),
      'tnt_name'    => isset($data['tnt_name']) ? $data['tnt_name'] : '',
      'job'         => '',
      'rock'        => '@',
      'raise'       => 0,
      'raise_pct'   => 0,
      'notice'      => 30,
      // Cleared_check section
      'cxldate'     => '1000-01-01',
      'cdate'       => Helper::mysqlDate(),
      'vendorname'  => '',
      'cxl'         => 'N',
      'acct_type'   => isset($data['acct_type']) ? $data['acct_type'] : (!empty($glChart[$gl]['acct_type']) ? $glChart[$gl]['acct_type'] : ''),
      'ref1'        => '999999',
      'dep_held1'   => 0.00, //This have to be zero unless they make the payment first
      'dep_pct'     => 0,
      'is_move_out_process_trans' => isset($data['is_move_out_process_trans']) ? $data['is_move_out_process_trans'] : 0,
      'fax'              => isset($data['fax']) ? $data['fax'] : '',
      'e_mail'           => isset($data['e_mail']) ? $data['e_mail']: '',
      'web'              => isset($data['web']) ? $data['web']: '',
      'cash_rec_remark'  => isset($data['cash_rec_remark']) ?$data['cash_rec_remark'] : '',
      'late_rate1'       => isset($data['late_rate1']) ? $data['late_rate1'] : '',
      'times_late'       => isset($data['times_late']) ? $data['times_late']: '',
      'times_nsf'        => isset($data['times_nsf']) ? $data['times_nsf']: '',
      'late_amount'      => isset($data['late_amount']) ? $data['late_amount']: '',
      'tax_rate'         => isset($data['tax_rate']) ? $data['tax_rate']: '',
      'dep_pct'          => isset($data['dep_pct']) ? $data['dep_pct']: '',
      'ytd_int_paid'     => isset($data['ytd_int_paid']) ? $data['ytd_int_paid']: '',
      'dep_held_int_amt' => isset($data['dep_held_int_amt']) ? $data['dep_held_int_amt']: '',
      'comm_pct'         => isset($data['comm_pct']) ? $data['comm_pct']: '',
      'sales_off'        => isset($data['sales_off']) ? $data['sales_off']: '',
      'sales_agent'      => isset($data['sales_agent']) ? $data['sales_agent']: '',
      'passcode'         => isset($data['passcode']) ?$data['passcode'] : '',
      'billed_deposit'   => isset($data['billed_deposit']) ? $data['billed_deposit']: '',
      'appl_inseq'       => isset($data['appl_inseq']) ? $data['appl_inseq']: '',
      'co_signer'        => isset($data['co_signer']) ? $data['co_signer']: '',
      'statement'        => isset($data['statement']) ? $data['statement']: '',
      'terms'            => isset($data['terms']) ? $data['terms']: '',
      'tenant_class'     => isset($data['tenant_class']) ? $data['tenant_class']: '',
      'bank_acct_no'     => isset($data['bank_acct_no']) ? $data['bank_acct_no']: '',
      'last_check'       => isset($data['last_check']) ? $data['last_check'] : '',
      'check_pdf'        => isset($data['check_pdf']) ? $data['check_pdf'] : '',	
      'ins_carrier'      => isset($data['ins_carrier']) ? $data['ins_carrier'] : '',	
      'fed_id'           => isset($data['fed_id']) ? $data['fed_id'] : '',	
      'contr_no'         => isset($data['contr_no']) ? $data['contr_no'] : '',	
      'tin_type'         => isset($data['tin_type']) ? $data['tin_type'] : '',	
      'tin_date'         => isset($data['tin_date']) ? $data['tin_date'] : '',	
      'contr_no'         => isset($data['contr_no']) ? $data['contr_no'] : '',	
      'pay_code'         => isset($data['pay_code']) ? $data['pay_code'] : '',
      'name_key'         => isset($data['name_key']) ? $data['name_key'] : (isset($data['tnt_name']) ? $data['tnt_name'] : '')
    ];
  }
//------------------------------------------------------------------------------
  private static function _setPropertyValue($vData){
    self::$_glChart   = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$vData['prop']])), 'gl_acct');
    self::$_service   = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$vData['prop']])), 'service');
    
    
//    self::$_rProp     = HelperMysql::getTableData(T::$prop, Model::buildWhere(['prop'=>$vData['prop']]), ['ar_bank','group1'], 1);
//    self::$_rBank     = Helper::keyFieldName(HelperMysql::getTableData(T::$propBank, Model::buildWhere(['prop'=>$vData['prop']])), 'bank');
//    self::$_rTenant   = HelperMysql::getTableData(T::$tenant, Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $vData)), 'tnt_name', 1);
//    self::$_rPropBank = Helper::keyFieldNameElastic(HelperMysql::getPropBank(['prop'=>[$vData['prop']]]), ['prop', 'bank']);
    // Need to check the batch date is the same here. 
    self::$_rData = [
      'glChart' =>self::$_glChart,
      'service' =>self::$_service,   
//      'rProp'   =>self::$_rProp,     
//      'rBank'   =>self::$_rBank,     
//      'rTenant' =>self::$_rTenant,   
//      'rPropBank'=>self::$_rPropBank, 
    ];
  }
//------------------------------------------------------------------------------
  private static function _getGroup1($data){
    return isset($data['prop']) ? Helper::getElasticResult(self::getProp($data['prop'], ['group1']), 1)['_source']['group1'] : '';
  }
//------------------------------------------------------------------------------
  private static function _getResult($r, $isFirstRow, $isUseElasticResult = 0){
    if($isFirstRow){
      return !empty($r['hits']['hits']) ? Helper::getElasticResult($r, $isFirstRow)['_source'] : [];
    } else{
      return ($isUseElasticResult) ? Helper::getElasticResult($r, $isFirstRow) : $r;
    }
  }
//------------------------------------------------------------------------------
  private static function _getQuery($mustQuery, $field){
    if(isset($mustQuery['must'])){
      return $mustQuery;
    } else{
      return !empty($mustQuery) ? ['must'=>[$field=>$mustQuery]] : [];
    }
  }
}
