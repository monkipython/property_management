<?php
namespace App\Console\BankRec;
use Illuminate\Console\Command;
use App\Library\{Mail, Helper, TableName AS T, V, HelperMysql, Format, Elastic};
use Illuminate\Support\Facades\Storage;
use App\Http\Models\Model; // Include the models class
use Illuminate\Support\Facades\DB;

class BankRecUpload extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'bankRec:upload';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Upload Bank Files';
  /**
   * Create a new command instance.
   * @return void
   */
  //private $_bankList = ['EastWestBank'=>'sftpEastWest','FarmersMerchantBank'=>'sftpFarmer','MechanicsBank'=>'sftpMechanicsBank','NanoBank'=>'sftpNanoBanc','TorryPinesBank'=>'sftpTorryPines'];
  private $_bankList = ['MechanicsBank'=>'sftpMechanicsBank', 'EastWestBank'=>'sftpEastWest','FarmersMerchantBank'=>'sftpFarmer'];
  
  private $_errorList = [];
  public function __construct(){
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   */
  public function handle(){
    $bankFileList = $bankFiles = $allList = $insertData = $updateData = $dataset = [];
    list($root, $tmp) = explode('app', (__DIR__)); 
    $path  = $root . 'storage/app/public/';
    $batch = HelperMysql::getBatchNumber();
    $rBank = $this->_keyFieldNameCleanAcct(HelperMysql::getTableData(T::$bank, [], ['cp_acct', 'prop', 'bank', 'bank_id'], 0), 'cp_acct');
    $cdate = Helper::mysqlDate();
  
    ## Get all the file name
    foreach($this->_bankList as $bank => $sftp) {
      $bankFileList[$bank] = Storage::disk($sftp)->allFiles($this->_getSftpFilePath($bank));
      //$bankFileList[$bank] = Storage::disk('public')->allFiles($bank);
    }
    ## Get all the file contents
    foreach($bankFileList as $bank => $val) {
      $rTrackFile = Helper::keyFieldName(HelperMysql::getTableData(T::$trackFile, Model::buildWhere(['type'=>$bank]), ['file', 'type', 'isError']), 'file');
      foreach($val as $i => $fileName) {
        ## Only get files that are not in the trackFile database or files that has isError = 1
        if( !isset($rTrackFile[$fileName]) || (isset($rTrackFile[$fileName]) && $rTrackFile[$fileName]['isError'] == 1)) {
          //$bankFiles[$bank][$fileName] = Storage::disk('public')->get($fileName);
          $bankFiles[$bank][$fileName] = Storage::disk($this->_bankList[$bank])->get($fileName);
          ## Insert to local storage
          Storage::disk('public')->put($bank .'/'. $fileName, $bankFiles[$bank][$fileName]);
        }
      }
    }

    ## Validate file columns and data types
    foreach($bankFiles as $bank => $val){
      foreach($val as $fileName => $v) {
        $rows     = preg_split('/\n|\r\n?/', trim($v));
        $funcName = '_get' . $bank;
        $allList[$bank][$fileName] = $this->$funcName($rows, $fileName);
        foreach($allList[$bank][$fileName] as $i => $row) {
          ## VALIDATE EACH DATA
          $valid = V::startValidate([
            'rawReq'          => $row,
            'orderField'      => ['date','journal','amount','remark', 'check_no', 'source', 'line', 'acct_no'], 
            'rule'            => $this->_getBankRecRules(),
            'includeCdate'    => 0,
            'isExistIfError'  => 0,
          ]); 
          ## store error if any
          if(!empty($valid['error'])) {
            $vData = $valid['data'];
            foreach($valid['error'] as $field => $val) {
              $this->_storeLogError($field . ': '. $val, $vData['source'], $vData['line']);
            }
          }
        }
      }
    }
 
    ## Add bank information based on acct_no and validate the data
    foreach($allList as $bank => $val) {
      foreach($val as $fileName => $v) {
        foreach($v as $i => $row) {
          if(isset($rBank[$row['acct_no']])){
            $allList[$bank][$fileName][$i]['bank_id'] = $rBank[$row['acct_no']]['bank_id'];
            $allList[$bank][$fileName][$i]['trust']   = $rBank[$row['acct_no']]['prop'];
            $allList[$bank][$fileName][$i]['bank']    = $rBank[$row['acct_no']]['bank']; 
            ## VALIDATE EACH DATA
            $valid = V::startValidate([
              'rawReq'          => $allList[$bank][$fileName][$i],
              'orderField'      => ['bank_id','trust','bank','date','journal','amount','remark','check_no','source','line','acct_no'], 
              'rule'            => $this->_getBankRecRules(),
              'includeCdate'    => 0,
              'isExistIfError'  => 0,
              'validateDatabase'=> [
                'mustExist' => [
                  T::$propBank . '|trust,bank'
                ]
              ]
            ]); 
            ## store error if any
            if(!empty($valid['error'])) {
              $vData = $valid['data'];
              foreach($valid['error'] as $field => $val) {
                $this->_storeLogError($val, $vData['source'], $vData['line']);
              }
            }
          }else {
            $this->_storeLogError('Account # does not match bank account #', $row['source'], $row['line']);
          }
        }
      }
    }

    ## Remove files with no valid rows and add files to the trackFile insert array and update trackFile array
    foreach($allList as $bank => $val) {
      $rTrackFile = Helper::keyFieldName(HelperMysql::getTableData(T::$trackFile, Model::buildWhere(['type'=>$bank]), ['file', 'type', 'isError', 'track_file_id']), 'file');
      foreach($val as $fileName => $v) {
        if(empty($allList[$bank][$fileName])) {
          $this->_storeLogError('This file has no valid rows to insert', $fileName);
          unset($allList[$bank][$fileName]);
        }
        if(!isset($rTrackFile[$fileName])) {
          ## Check the error list to see which file has error
          $insertData[T::$trackFile][] = [
            'file'    => $fileName,
            'path'    => $path . $bank . '/' . $fileName,
            'type'    => $bank,
            'isError' => isset($this->_errorList[$fileName]) ? 1 : 0,
            'cdate'   => $cdate
          ];
        }else if($rTrackFile[$fileName]['isError'] == 1 && !isset($this->_errorList[$fileName])) {
          ## If the file has no error and has isError = 1 in the database, get the track_file_id to update isError to 0
          $trackFileIdList[] = $rTrackFile[$fileName]['track_file_id'];
        }
      }
      if(empty($allList[$bank])) {
        $this->_storeLogError('This bank has no valid files to insert', $bank);
        unset($allList[$bank]);
      }
    }

    ## Map data, remove files that have errors
    foreach($allList as $bank => $val) {
      foreach($val as $fileName => $v) {
        if(!isset($this->_errorList[$fileName])) {
          foreach($v as $i => $row) {
            $v[$i]['batch']        = $batch;
            $v[$i]['match_id']     = 0;
            $v[$i]['cleared_date'] = '1000-01-01';
            $v[$i]['cdate']        = $cdate;
            unset($v[$i]['line'], $v[$i]['acct_no']);
            $dataset[$bank][$fileName][] = $v[$i];
          }
        }
      }
    }

    if(empty($dataset) && empty($insertData) && empty($trackFileIdList)){
      return $this->_sendEmail('No valid files to upload.');
    }
   
    if(!empty($trackFileIdList)){
      $updateData = [
        T::$trackFile=>['whereInData'=>['field'=>'track_file_id','data'=>$trackFileIdList], 'updateData'=>['isError'=>0]],
      ];
    }
    
    ## Compare bank trans data with the cleared trans data
    foreach($dataset as $bank => $val) {
      foreach($val as $fileName => $v) {
        foreach($v as $i => $row) {
          $r = Helper::getElasticResult(Elastic::searchQuery([
            'index'   =>T::$clearedTransView,
            '_source' =>['cleared_trans_id'],
            'query'   =>['must'=>['check_no.keyword'=>$row['check_no'], 'amount'=>$row['amount'], 'bank_id'=>$row['bank_id']]]
          ]), 1);
          if(!empty($r)) {
            $clearedTransId  = $r['_source']['cleared_trans_id'];
            $row['match_id'] = $clearedTransId;
            $updateData[T::$clearedTrans][] = ['whereData'=>['cleared_trans_id'=>$clearedTransId], 'updateData'=>['match_id'=>$clearedTransId]];
          }
          $insertData[T::$bankTrans][] = $row;
        }
      }
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = [];
    try{
      if(!empty($insertData)) {
        $success += Model::insert($insertData);
      }
      if(!empty($updateData)){
        $success += Model::update($updateData);
      }
      if(!empty($success['insert:' . T::$bankTrans])) {
        $elastic = [
          T::$bankTransView=>['bt.bank_trans_id'=>$success['insert:' . T::$bankTrans]]
        ];
      }
      Model::commit([
        'success' =>$success,
        'elastic' =>['insert'=>$elastic]
      ]);
      $this->_sendEmail('Bank Rec Upload Completed.'. $this->_getErrorMsg());
    } catch(\Exception $e){
      $this->_sendEmail(Model::rollback($e));
    }
  }
//------------------------------------------------------------------------------
  private function _formatBAI2($rows, $fileName) {
    $data = [];
    $date = '';
    foreach($rows as $i => $value) {
      $entry = explode(',', trim($value));
      if($entry[0] == '1') {
        $date =  Format::mysqlDate(implode("-", str_split($entry[3], 2)));
      }else if($entry[0] == '16') {
        $columnCount = count($entry);
        if($columnCount == 7 || $columnCount == 6){
          $data[] = [
            'date'     => $date, 
            'amount'   => $entry[2],
            'acct_no'  => $entry[4],
            'check_no' => $entry[5] != '/' ? sprintf("%06s", $entry[5]) : '000000', 
            'remark'   => isset($entry[6]) ? trim($entry[6]) : '', 
            'journal'  => 'CP',
            'source'   => $fileName,
            'line'     => $i + 1
          ];
        }else {
          $this->_storeLogError('Not enough valid columns', $fileName, $i + 1);
        }
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getMechanicsBank($rows, $fileName){
    return $this->_formatBAI2($rows, $fileName);
  }
//------------------------------------------------------------------------------
  private function _getEastWestBank($rows, $fileName) {
    return $this->_formatBAI2($rows, $fileName);
  }
//------------------------------------------------------------------------------
  private function _getNanoBank($rows, $fileName) {
    return $this->_formatBAI2($rows, $fileName);
  }
//------------------------------------------------------------------------------
  private function _getFarmersMerchantBank($rows, $fileName) {
    $data = [];
    foreach($rows as $i => $value) {
      $entry = explode(',', trim($value));
      $monthPrefix = strlen($entry[0]) == 5 ? '0' : '';
      $date =  Format::usDate($monthPrefix.$entry[0]);
      $data[] = [
        'date'     => $date, 
        'remark'   => $entry[2], 
        'amount'   => $entry[3], 
        'journal'  => !empty(trim($entry[4])) ? $entry[4] : 'CP', 
        'check_no' => sprintf("%06s", $entry[5]), 
        'acct_no'  => $entry[6],
        'source'   => $fileName,
        'line'     => $i + 1
      ];
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _getSftpFilePath($bank){
    switch ($bank){
      case 'MechanicsBank':
        return 'BAI2/';
      case 'FarmersMerchantBank':
        return 'CURRENT/';
      case 'EastWestBank':
        return 'DOWNLOAD';
      case 'NanoBank':
        return '';
      case 'TorryPinesBank':
        return '';
      default:
        return;
    }
  }
//------------------------------------------------------------------------------
  private function _getBankRecRules() {
    return [
      'trust'    => 'required|string',
      'bank'     => 'required|string',
      'bank_id'  => 'required|integer',
      'date'     => 'required|date_format:m/d/Y',
      'journal'  => 'required|string',
      'amount'   => 'required|numeric',
      'remark'   => 'nullable|string',
      'check_no' => 'required|string',
      'source'   => 'required|string',
      'acct_no'  => 'required|string',
      'line'     => 'integer'
    ];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg() {
    $msg = '';
    foreach($this->_errorList as $file => $values) {
      $msg .= $file . '<br>';
      foreach($values as $i => $v) {
        $msg .= '-' . $v['value'] . ' ';
        $msg .= !empty($v['line']) ? 'Line: ' . $v['line'] . '<br>' : '<br>';
      }
    }
    $msg = empty($msg) ? '' : 'List of errors: <br>' . $msg;
    return $msg;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _keyFieldNameCleanAcct($r, $key_field, $val_field = ''){
    /********************** ANONYMOUS FUNCTION ********************************/
    $getId = function($v, $key_field){
      $id = (gettype($key_field) == 'string') ? $v[$key_field] : '';
      if(gettype($key_field) == 'array'){
        foreach($key_field as $fl ){
          $id .= $v[$fl];
        }
      }
      return $id;
    };
    /**********************@ENd ANONYMOUS FUNCTION ****************************/
    $data = [];
    foreach($r as $v){
      $v['cp_acct'] = trim(preg_replace('/[^0-9]+/', '', $v['cp_acct']));
      $key = $getId($v, $key_field);
      $data[$key] = (empty($val_field)) ? $v : $v[$val_field];
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _storeLogError($value, $fileName, $line = '') {
    $this->_errorList[$fileName][] = [
      'line'  => $line,
      'value' => $value,
    ];
  }
//------------------------------------------------------------------------------
  private function _sendEmail($msg) {
    Mail::send([
      'to'      =>'sean@pamamgt.com',
      'from'    =>'admin@pamamgt.com',
      'subject' =>'Bank Rec :: Run on ' . date("F j, Y, g:i a"),
      'msg'     =>$msg
    ]);
  }
}