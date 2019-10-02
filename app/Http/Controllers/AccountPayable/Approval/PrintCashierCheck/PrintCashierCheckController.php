<?php
namespace App\Http\Controllers\AccountPayable\Approval\PrintCashierCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, File, Format, TableName AS T};
use App\Http\Models\{Model, ApprovalModel AS M};
use \App\Http\Controllers\AccountPayable\Approval\ApprovalController AS P;
use PDF;
use Storage;

class PrintCashierCheckController extends Controller {
  public function create(Request $req){
    $num     = !empty($req['num']) ? $req['num'] : 0;
    $label   = ($num) ? $num : 'all';
    $option  = [0=>'Print All Cashier Checks'];
    $option  = $option + (!empty($num) ? [$num=>'Print ' . $label .' Transaction(s).'] : []);
    $text = Html::h4('Are you sure you want to print these cashiers check(s)?', ['class'=>'text-center']) . Html::br();
    $fields = [
      'numTransaction'=>['id'=>'numTransaction','label'=>'How Many Transactions?','type'=>'option', 'option'=>$option, 'req'=>1],
      'date1'         =>['id'=>'date1','label'=>'Post Date', 'class'=>'date', 'type'=>'text', 'value'=>date('m/d/Y')],
    ];
    return ['html'=>$text . Html::tag('form', implode('',Form::generateField($fields)), ['id'=>'showConfirmForm', 'class'=>'form-horizontal'])];
  }
//------------------------------------------------_-----------------------------
  public function store(Request $req){
    $dataset = [T::$glTrans=>[],T::$batchRaw=>[],'summaryAcctPayable'=>[]];
    $insertDataCashierCheck = $cashierCheckCSV = [];
    $batch = HelperMysql::getBatchNumber();
    $location = File::getLocation('Approval');
    $id = 'vendor_payment_id';
    $ids = $invoiceCheck = [];
    $req->merge([$id=>$req['id']]);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$vendorPayment,T::$glTrans],
      'orderField'      => [$id, 'date1'],
      'includeCdate'    => 0, 
      'isExistIfError'  => 0,
      'includeUsid'    => 1, 
      'setting'         => $this->_getSetting('store', $req), 
    ]);
    $vData   = $valid['data'];
    $usid    = $vData['usid'];
    $rSource = P::getInstance()->getApprovedResult($valid);
    ##### CHECK IF THE SAME INVOICE NUMBER #####
    foreach($rSource as $v){
      $id = $this->_getUniqueId($v, $batch);
      if(isset($invoiceCheck[$id])){ // ERROR OUT
        Helper::echoJsonError($this->_getErrorMsg('storeDuplicateInvoice'), 'popupMsg');
      }
      $invoiceCheck[$id] = 1;
    }
    
    $rBank = Helper::keyFieldNameElastic(M::getBank(['prop.keyword'=>array_column($rSource, 'prop')], ['bank', 'trust', 'name', 'cp_acct']), ['trust', 'bank']);
    foreach($rSource as $i => $v){
      $key = $v['trust'] . $v['bank'];
      $vendor = Helper::keyFieldNameElastic(M::getVendorElastic(['vendor_id'=>$v['vendor_id']], ['vendor_id', 'name']), 'vendor_id', 'name');

      if(isset($rBank[$key]) && preg_match('/^EAST WEST BANK/', $rBank[$key]['name'])){
        $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$v['prop']])),'gl_acct');
        $ids[]   = $v['vendor_payment_id'];
        $dataset[T::$glTrans][] = P::getInstance()->getGlTransData($v, $vData, $batch, $glChart);        
        
        $filename = $this->_getCashierCheckFileName();
        $insertDataCashierCheck[T::$cashierCheck][] = [
          'filename'=> $filename,
          'vendid'   => $v['vendid'],
          'prop'     => $v['prop'],
          'invoice'  => $v['invoice'],
          'bank'     => $v['bank'],
          'batch'    => $batch,
          'cdate'    => Helper::mysqlDate(), 
          'seq'      => 0,
          'usid'     => $usid
        ];
        
        $cashierCheckCSV[$filename] = [
          'PayeeName,CheckAmount,Reference',
          implode(',', [preg_replace('/,/', ' ', $vendor[$v['vendor_id']]), $v['amount'], preg_replace('/,/', ' ', $v['remark'])]),
          'FileTotals,' . $v['amount'],
          'DebitAccountFrom,,' . preg_replace('/[^0-9]+/', '', $rBank[$key]['cp_acct'])
        ];
        
        sleep(1);
      } else{
        Helper::echoJsonError($this->_getErrorMsg('storeBankNotAllow'), 'popupMsg');
      }
    }
    
    $dataset[T::$batchRaw] = $dataset[T::$glTrans];
    $insertData            = HelperMysql::getDataset($dataset,$usid);
    
    $updateData            = [
      T::$vendorPayment    => [
        'whereInData'      => ['field'=>'vendor_payment_id','data'=>$ids],
        'updateData'       => [
          'posted_date'    => $vData['date1'],
          'batch'          => $batch,
          'print'          => 1,
          'print_type'     => 'cashier check',
          'print_by'       => $usid,
        ]
      ]
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    
    try {
      $success  += Model::insert($insertData);
      $success  += Model::insert($insertDataCashierCheck);
      $success  += Model::update($updateData);
      $elastic   = ['insert'=>[
          T::$glTransView         => ['gl.seq'=>$success['insert:'.T::$glTrans]],
          T::$vendorPaymentView   => ['vp.vendor_payment_id'=>$ids], 
        ]
      ];
      
      foreach($cashierCheckCSV as $filename=>$csv){
        ##### UPLOAD FILE TO EAST WEST BANK #####
        $remorePath = Helper::isProductionEnvironment() ? 'UPLOAD/' : 'TEST/UPLOAD/';
        Storage::disk('sftpEastWest')->put($remorePath . $filename, implode("\r\n", $csv));
      }
      
      Model::commit([
        'success' => $success,
        'elastic' => $elastic,
      ]);
      
      $response['popupMsg'] = $this->_getSuccessMsg(__FUNCTION__,$success['insert:'.T::$glTrans]);
    } catch(\Exception $e) {
      $response['error']['mainMsg']  = Model::rollback($e);
    }
    
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'storeNoApprovedCheck' => Html::errMsg('There are no approved check(s). Please double check it again.'),
      'storeBankNotAllow'    => Html::errMsg('Only EAST WEST BANK is allowed to print Cashier Check. Please double check.'),
      'storeDuplicateInvoice'=> Html::errMsg('Invoice cannot be in the same. If you want it to be the same, please print multiple time.')
    ];
    return $data[$name];
  }  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$ids=[]){
    $data = [
      'store' =>Html::sucMsg('Successfully Printed ' . count($ids) . ' Cashiers Checks'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $req){
    $data = [
      'store'=>[
        'field'=>[
        ],
        'rule'=>[
          'vendor_payment_id'=>'required|integer',
        ]
      ],
    ];
    return $data[$fn];
  } 
//------------------------------------------------------------------------------
  private function _getCashierCheckFileName(){
    $str = (env('APP_ENV') == 'production' ? '' : 'TEST_');
    return $str . 'Pama_ElMonte_DT_CashiersCheck_' .  date('mdyHis') . '.CSV';
  }
//------------------------------------------------------------------------------
  private function _getUniqueId($v, $batch){
    return implode('-', Helper::selectData(['vendid', 'prop', 'invoice', 'trust', 'bank'], $v)) . '-' . $batch;
  }
//------------------------------------------------------------------------------
}