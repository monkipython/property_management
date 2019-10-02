<?php
namespace App\Http\Controllers\AccountReceivable\BatchDelete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper, HelperMysql, Format, Mail, TenantTrans};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CashRecModel AS M; // Include the models class

class BatchDeleteController extends Controller{
  private $_viewPath  = 'app/AccountReceivable/BatchDelete/';
  private $_usr       = '';
  
  public function index(Request $req){
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . 'index';
    $fields = [
      'batch'    => ['id'=>'batch','label'=>'Batch', 'type'=>'text','req'=>1],
      'prop'     => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea','placeHolder'=>'Ex. 0001-ZZZZ, 0028', 'value'=>'0001-ZZZZ','req'=>1],
      'dateRange'=> ['id'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange', 'req'=>1],
    ];
    return view($page, [
      'data'=>[
        'nav'    => $req['NAV'],
        'account'=> Account::getHtmlInfo($req['ACCOUNT']),
        'form'   => implode('',Form::generateField($fields)),
      ]
    ]); 
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
    ]);
    $vData  = $valid['data'];
    $vData['prop']   = Helper::explodeField($vData,['prop'])['prop'];
    $vData += Helper::splitDateRate($vData['dateRange'] ,'date1');
    unset($vData['dateRange']);
    $initData = $this->_getColumnButtonReportList($req);
    $r        = $this->_getAllTransaction($vData);
    $rAllTrans = $r['allTrans'];
    $isAllowToDelete = $this->_isAllowToDelete($r, $vData);
    if(!$isAllowToDelete['isAllowToDelete']){
      $rAllTrans = array_merge($isAllowToDelete['requiredBatch'], $rAllTrans);
    }
    $rAllTrans = array_merge($rAllTrans, $isAllowToDelete['sTrans']);
    
    
    return [
      'rows'    =>$this->_getGridData($rAllTrans), 
      'total'   =>count($rAllTrans),
      'gridInfo'=>$initData,
      'toolbar' =>!empty($rAllTrans && $isAllowToDelete['isAllowToDelete']) ? Html::repeatChar('&nbsp', 5) . Html::button(Html::i('',['class'=>'fa fa-fw fa-remove']) . ' Delete Batch',['id'=>'delete','class'=>'btn btn-danger']) : $isAllowToDelete['msg']
    ];
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
      'includeUsid'=>1,
    ]);
//    $invoiceAmount = [];
    $this->_usr = Helper::getUsid($req);
    $trans = $updateData = $deleteData = $tntSecurityDepositTrans = [];
    $vData = $valid['data'];
    $total = $vData['total'];
    $vData['prop']   = Helper::explodeField($vData,['prop'])['prop'];
    $vData += Helper::splitDateRate($vData['dateRange'] ,'date1');
    unset($vData['dateRange']);
    
    $allTrans = $this->_getAllTransaction($vData);
    $isAllowToDelete = $this->_isAllowToDelete($allTrans, $vData);
    
    if(!$isAllowToDelete['isAllowToDelete']){
      Helper::echoJsonError($this->_getErrorMsg('destroyNotAllowToDelete', $vData), 'sideMsg');
    }
    
    $rAllTrans = $allTrans['allTrans'];
    
    if(count($rAllTrans) == $total){
      foreach($rAllTrans as $v){
        if(isset($v['tnt_security_deposit_id'])){
          $trans[T::$tntSecurityDeposit][] = $v['tnt_security_deposit_id'];
          $tntSecurityDepositTrans[] = $v;
          
          $where = Model::buildWhere(['type'=>'deposit_refund', 'vendid'=>implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $v))]);
          $rVendorPayment = DB::table(T::$vendorPayment)->select('*')->where($where)->orderBy('vendor_payment_id', 'DESC')->first();
          if(!empty($rVendorPayment) && !$rVendorPayment['print']){
            $trans[T::$vendorPayment] = $rVendorPayment['vendor_payment_id'];
          } else if(!empty($rVendorPayment) && $rVendorPayment['print']){
            // NEED TO INSERT ON INVOICE 
//            if($v['tx_code'] == 'P'){
//              $id = $v['prop'] .'-'. $v['unit'] .'-'. $v['tenant'];
//              $invoiceAmount[$id] = isset($invoiceAmount[$id]) ? $invoiceAmount[$id] + $v['amount'] : $v['amount'];
//            }
          }
        } else if(isset($v['cntl_no'])){
          $trans[T::$tntTrans][] = $v['cntl_no'];
        } else{
          $trans[T::$glTrans][] = $v['seq']; 
        }
//        $key = isset($v['cntl_no']) ? T::$tntTrans : T::$glTrans;
//        $trans[$key][] = isset($v['cntl_no']) ? $v['cntl_no'] : $v['seq']; 
      }
      
      // Deal with Cleared_check and cleared_check_ext
      $clearedCheckDataset = $this->_getClearedCheckData($allTrans['totalCash']);
      $updateData = $clearedCheckDataset['updateData'];
      $deleteData = $clearedCheckDataset['deleteData'];
      
      // Update vendor_payment by void=0 because they delete the batch
      if(!empty($allTrans['seq'])){
        $updateData[T::$vendorPayment] = [
          'whereInData'=>[['field'=>'seq', 'data'=>$allTrans['seq']]], 
          'updateData'=>['void'=>1],
        ];
      }
      ############### DATABASE SECTION ######################
      DB::beginTransaction();
      $success = $response = $elastic = [];
      try{
        // Insert data into track_batch_delete
        $success += Model::insert([T::$trackBatchDelete=>$rAllTrans]);
        if(!empty($updateData)){
          $success += Model::update($updateData);
        }
       
        //Delete the transaction from Gl and Tnt Trans
        if(!empty($trans[T::$tntTrans])){
          $success[] = DB::table(T::$tntTrans)->whereIn('cntl_no', $trans[T::$tntTrans])->delete();
          $elastic['delete'][T::$tntTransView] = ['cntl_no'=>$trans[T::$tntTrans]];
          
          ##### NEED TO DELETE TRACK_LEDGERCARD_FIX AS WELL #####
          $r = Helper::keyFieldNameElastic(Elastic::searchQuery([
            'index'=>T::$trackLedgerCardFixView,
            'query'=>['must'=>['cntl_no'=>$trans[T::$tntTrans]]]
          ]), 'batch_group', 'batch_group');
          if(!empty($r)){
            $trackLedgerCardFixBatchGroup = array_values($r);
            $success[] = DB::table(T::$trackLedgerCardFix)->whereIn('batch_group', $trackLedgerCardFixBatchGroup)->delete();
            $elastic['delete'][T::$trackLedgerCardFixView] = ['batch_group'=>$trackLedgerCardFixBatchGroup];
          }
        }

        if(!empty($trans[T::$glTrans])){
          $success[] = DB::table(T::$glTrans)->whereIn('seq', $trans[T::$glTrans])->delete();
          $elastic['delete'][T::$glTransView] = ['seq'=>$trans[T::$glTrans]];
        }
        
        if(!empty($trans[T::$tntSecurityDeposit])){
          $success[] = DB::table(T::$tntSecurityDeposit)->whereIn('tnt_security_deposit_id', $trans[T::$tntSecurityDeposit])->delete();
          $tenantData = TenantTrans::getUpdateTenantDepositData($tntSecurityDepositTrans);
          $success   += Model::update($tenantData['updateData']);
          $elastic['insert'][T::$tenantView] = $tenantData['elastic'][T::$tenantView];
        }
        
        if(!empty($trans[T::$vendorPayment])){
          $success[] = M::deleteTableData(T::$vendorPayment, Model::buildWhere(['type'=>'deposit_refund', 'vendor_payment_id'=>$trans[T::$vendorPayment]]));
          $elastic['delete'][T::$vendorPaymentView] = ['vendor_payment_id'=>$trans[T::$vendorPayment]];
        }
        
        if(!empty($deleteData)){
          foreach($deleteData as $table=>$val){
            foreach($val as $i=>$v){
              $success[] = DB::table($table)->whereIn($v['field'], [$v['data']])->delete();
            }
          }
        }
        
        DB::table(T::$clearedCheck)->where(Model::buildWhere(['amt'=>0]))->delete();
         
        $response = [
          'sideMsg'=>$this->_getSuccessMsg('destroy', $vData),
          'success'=>1
        ];
        $this->_getEmail($vData, $rAllTrans);
        Model::commit([
          'success' =>$success,
          'elastic' =>$elastic,
        ]);
      } catch(\Exception $e){
        $response['error']['mainMsg'] = Model::rollback($e);
      }
      return $response;
    } else{
      return ['popupMsg'=>$this->_getErrorMsg('destroy')];
    }
  }
################################################################################
##########################   GRID FUNCTION   ###################################  
################################################################################ 
  ##### NEED TO REPLACE THIS ONE WHEN WORKING ON THE BANK REC #####
  private function _getClearedCheckData($totalCash){
    $data = ['updateData'=>[], 'deleteData'=>[]];
    foreach($totalCash as $id=>$amount){
      $pieceData = [];
      list($pieceData['c.batch'], $pieceData['c.bank'], $pieceData['c.orgprop']) = explode('-', $id);
      $rClearedCheck = M::getClearedCheck($pieceData, ['c.cleared_check_id','c.prop','c.bank','c.orgprop','c.batch','c.ref1','c.amt','c.date1','c.cxl', 'cx.journal', 'cx.match_id', 'cx.source'], 1);
      if(!empty($rClearedCheck)){
        // Deal with cleared_check
        $data['updateData'][T::$clearedCheck][] = [ 
          'whereData'=>['cleared_check_id'=>$rClearedCheck['cleared_check_id']], 
          'updateData'=>['amt'=>$rClearedCheck['amt'] + $amount],
        ]; 

        // Deal with Cleare_check_ext
        if($rClearedCheck['cxl'] === 'Y' && !empty($rClearedCheck['match_id'])){
          $rMatchTrans = M::getClearedCheck(['cx.match_id'=>$rClearedCheck['match_id']], ['c.cleared_check_id','c.prop','c.bank','c.orgprop','c.batch','c.ref1','c.amt','c.date1','c.cxl', 'cx.journal', 'cx.match_id', 'cx.source']);
          foreach($rMatchTrans as $v){
            $data['updateData'][T::$clearedCheck][] = [ 
              'whereData'=>['cleared_check_id'=>$rClearedCheck['cleared_check_id']], 
              'updateData'=>['cxl'=>'N', 'cxldate'=>'1000-01-01', 'usid'=>$this->_usr, 'sys_date'=>Helper::mysqlDate(), 'rock'=>'@'],
            ]; 

            if($v['orgprop'] == '-1'){
              $data['updateData'][T::$clearedCheckExtend][] = [
                'whereData'=>['cleared_check_id'=>$rClearedCheck['cleared_check_id']], 
                'updateData'=>['match_id'=>0],
              ];
            } else{
               $data['deleteData'][T::$clearedCheckExtend][] = ['field'=>'cleared_check_id', 'data'=>$v['cleared_check_id']];
            }
          }
        }
      }
    }
    return $data;
  }
//  private function clear_check_extend($cl_val){
//    $is_ok = 1;
//    //1. only do special care if it is cleared
//    if($cl_val['cxl'] === 'Y'){
//      //2. Get match_id here, not change query get_cleared_check 
//      //   because cleared by ppm won't have match_id, then no further action needed.
//      $r_match_id = $this->m->get_match_id($cl_val);
//      if( !empty($r_match_id) ){
//        //3. Get a list of other trans that matched with deleting trans
//        //   and then unclear them.
//        $r_matched_trans = $this->m->get_matched_trans(['match_id'=>$r_match_id[0]['match_id']]);
//        foreach( $r_matched_trans as $matched_trans ){
//          //4. Unclear the trans, and update with the current user and time for record
//          $matched_trans['usid'] = $this->usr;
//          $matched_trans['sys_date'] = date('Y-m-d H:i:s');
//          if(!$this->m->update_cleared_check_unclear($matched_trans)){$is_ok = 0;}
//
//          //5. If it is bank trans, then will only update the match_id to 0,
//          //   so it will keep the source (file) column record.
//          //   Otherwise, it will be delete from cleared_check_extend
//          if($matched_trans['orgprop'] === '-1'){ //if it is bank_trans, only update the match_id to 0
//            if(!$this->m->update_cleared_check_extend_unclear($matched_trans)){$is_ok = 0;}
//          }
//          else{ //otherwise delete it from extend.
//            if(!$this->m->delete_cleared_check_extend($matched_trans)){$is_ok = 0;}
//          }
//        }
//      }
//    }
//    return $is_ok;
//  }
//------------------------------------------------------------------------------
  private function _getGridData($rAllTrans){
    $rows = [];
    foreach($rAllTrans as $i=>$v){
      $v['num']    = $i + 1;
      $v['amount'] = $v['amount'] == '' ? '-' : Format::usMoney($v['amount']);
      $rows[] = $v;
    }
    return $rows;
  }
//------------------------------------------------------------------------------  
  private function _getColumnButtonReportList($req = []){
    $columns = [
      ['field'=>'num','title'=>'#','width'=>25],
      ['field'=>'type','title'=>'Transaction Type','sortable'=>true,'width'=>200],
      ['field'=>'date1','title'=>'Date','sortable'=>true,'width'=>100],
      ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>50],
      ['field'=>'batch','title'=>'Batch','sortable'=>true,'width'=>50],
      ['field'=>'unit','title'=>'Unit','sortable'=>true,'width'=>50],
      ['field'=>'bank','title'=>'Bank','sortable'=> true, 'width'=>50],
      ['field'=>'tx_code','title'=>'Desc','sortable'=> true, 'width'=>50],
      ['field'=>'gl_acct','title'=>'Gl Acct','sortable'=> true, 'width'=>50],
      ['field'=>'appyto','title'=>'Invoice','sortable'=> true, 'width'=>50],
      ['field'=>'check_no','title'=>'Check No.','sortable'=>true,'width'=>75],
      ['field'=>'remark','title'=>'Remark','sortable'=> true, 'width'=>250],
      ['field'=>'amount','title'=>'Amount','sortable'=> true,'width'=>100],
      ['field'=>'usid','title'=>'Usid','sortable'=> true, 'width'=>100],
      ['field'=>'sys_date','title'=>'System Date','sortable'=> true],
    ];
    return ['columns'=>$columns];
  }
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'batch'    => 'required',
      'prop'     => 'required|string', 
      'total'    => 'required|integer|between:1,20548', 
      'dateRange'=>'required|string|between:21,23',
    ]; 
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name, $vData = []){
    $data = [
      'destroy' =>Html::errMsg('There is some issues while trying to delete the batch.'),
      'destroyNotAllowToDelete'=>Html::errMsg('You cannot delete this batch. Please delete the requiered batch first before delete this batch.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------  
  private function _getSuccessMsg($name, $vData = []){
    $data = [
      'destroy'  =>Html::sucMsg('Successfully delete the batch #: ' . $vData['batch']),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getAllTransaction($vData){
    $allTrans = ['allTrans'=>[], 'cashTrans'=>[], 'totalCash'=>[], 'seq'=>[]];
    $field = ['date1', 'batch', 'prop', 'unit', 'tenant', 'bank', 'tx_code', 'gl_acct', 'check_no', 'appyto', 'remark', 'amount', 'usid', 'sys_date', 'seq'];
    $rGlTrans = M::getGlTrans($vData, $field);
    $rTntSecurityDeposit = M::getTntSecurityDeposit(Model::buildWhere(['batch'=>$vData['batch']]), $vData['prop']);

    $rTntTrans =  Helper::getElasticResult(HelperMysql::getTntTrans(
      [ 'prop.keyword'=>$vData['prop'], 
        'batch'=>$vData['batch'],
        'range' => [
          'date1' => [
            'gte'    => $vData['date1'],
            'lte'    => $vData['todate1'],
            'format' => 'yyyy-MM-dd'
          ]
        ]
      ], 
      array_merge($field, ['cntl_no']), ['sort'=>['date1'=>'DESC', 'prop.keyword'=>'ASC', 'gl_acct.keyword'=>'DESC']], 0,0
    ));
    
    foreach($rGlTrans as $v){
      $v['type'] = Html::span('Gl Transaction',['class'=>'text-warning']);
      $v['usid'] = isset($vData['usid']) ? $vData['usid'] : $v['usid'];
      $allTrans['allTrans'][] = $v; 
      $allTrans['seq'][] = $v['seq'];
      
      if($v['remark'] == 'Summary'){
        $id = $v['batch'].'-'.$v['bank'].'-'.$v['prop'];
        $allTrans['cashTrans'][] = $v;
        $allTrans['totalCash'][$id] = isset($allTrans['totalCash'][$id]) ? $allTrans['totalCash'][$id] + $v['amount'] : $v['amount'];
      }
    }
    
    foreach($rTntTrans as $i=>$v){
      $v = $v['_source'];
      $v['usid'] = isset($vData['usid']) ? $vData['usid'] : $v['usid'];
      $v['type']  = Html::span('Tenant Transaction', ['class'=>'text-green']);
      $allTrans['allTrans'][] = $v;
      $allTrans['tntTrans'][] = $v;
    }
    
    foreach($rTntSecurityDeposit as $i=>$v){
      $v['usid'] = isset($vData['usid']) ? $vData['usid'] : $v['usid'];
      $v['type']  = Html::span('Deposit Transaction', ['class'=>'text-green']);
      $allTrans['securityTrans'][] = $v;
      
      unset($v['service_code'], $v['is_move_out_process_trans'],$v['cdate']);
      $allTrans['allTrans'][] = $v;
    }
    return $allTrans;
  }
//------------------------------------------------------------------------------
  private function _getEmail($vData, $rAllTrans){
    $tableData = [];
    $rProp = Helper::keyFieldNameElastic(HelperMysql::getProp(array_column($rAllTrans, 'prop'), ['prop', 'trust']), 'prop');
    foreach($rAllTrans as $v){
      $tableData[] = [
        'date'    =>['val'    =>$v['date1'], 'header'=>['val'=>'Date']], 
        'trust'   =>['val'   =>$rProp[$v['prop']]['trust'], 'header'=>['val'=>'Date']], 
        'prop'    =>['val'    =>$v['prop'], 'header'=>['val'=>'Prop']], 
        'check_no'=>['val'=>$v['check_no'], 'header'=>['val'=>'Check No']], 
        'remark'  =>['val'    =>$v['remark'],'header'=>['val'=>'Remark']],
        'amount'  =>['val'    =>$v['amount'],'header'=>['val'=>'Amount']],
        'usid'    =>['val'    =>$v['usid'], 'header'=>['val'=>'Usid']],
      ];
    }
    $msg = Html::buildTable(['data'=>$tableData, 'isAlterColor'=>1, 'tableParam'=>['border'=>1, 'cellpadding'=>'10']]);
    Mail::send([
      'to'      =>'ryan@pamamgt.com,sean@pamamgt.com,cindy@pamamgt.com,kary@pamamgt.com',
      'from'    =>'admin@pamamgt.com',
      'subject' =>'Batch: #' . $vData['batch'] . ' Deletd by ' . $this->_usr,
      'msg'     =>$msg
    ]);
  } 
//------------------------------------------------------------------------------
  private function _isAllowToDelete($r, $vData){
    $isAllowToDelete = 1;
    $requiredBatch = $requiredBatchData = $sTrans = [];
    if(!empty($r['tntTrans'])){
      $msg = 'You Need to Delete the Following Transactions First before You Can Delete Orignal Transactions. Please Note: Need to Delete in Sequential Order. Start With Row 2,3,4 and so on....';
      $appyto          = array_column($r['tntTrans'], 'appyto');
      $rTntTrans       = HelperMysql::getTntTrans(['appyto'=>$appyto], [], [], 0, 1);
      $mainBatch       = HelperMysql::getTntTrans(['appyto'=>$appyto, 'batch'=>$vData['batch']], [], [], 1, 1);
      
      foreach($rTntTrans as $v){
        $v = $v['_source'];
        $requiredBatch[$v['batch'] . $v['prop']] = $v;
      }
      
      foreach($requiredBatch as $batch=>$v){
        $v['type'] = Html::bu('Delete First: ' . $v['batch'], ['class'=>'text-danger']);
        $v['amount'] = '';
        unset($v['unit'],  $v['bank'], $v['gl_acct'], $v['tx_code'], $v['appyto'], $v['check_no'], $v['remark']);
        $requiredBatchData[$v['cntl_no']] = $v;
      }
      krsort($requiredBatchData);
      
      #### WILL NOT DISPLAY ANYTHING AFTER THE MAIN BATCH NUMBER ##### 
      $isDelete = 0;
      foreach($requiredBatchData as $appyto=>$v){
        if($vData['batch'] == $v['batch']){
          $isDelete = 1;
        }
        if($isDelete){
          unset($requiredBatchData[$appyto]);
        }
      }
      if(empty($requiredBatchData)){
        $isAllowToDelete = 1;
      }
      
      if(!empty($requiredBatchData)){
        $firstElement    = current($requiredBatchData); 
        $isAllowToDelete = ($firstElement['batch'] == $mainBatch['batch']) ? 1 : 0;

        array_unshift($requiredBatchData, ['type'=>Html::bu('Required To Delete', ['class'=>'text-danger']), 'amount'=>'']);
        $requiredBatchData[] = ['type'=>'-', 'amount'=>''];
        $requiredBatchData[] = ['type'=>Html::bu('Orignal Transactions', ['class'=>'text-info']), 'amount'=>''];
      }
    }
    return ['sTrans'=>[], 'isAllowToDelete'=>$isAllowToDelete, 'requiredBatch'=>$requiredBatchData, 'msg'=>(!$isAllowToDelete ? Html::repeatChar('&nbsp', 5) .  Html::b($msg, ['class'=>'text-danger']) : '')];
  }
}