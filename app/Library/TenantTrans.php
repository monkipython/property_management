<?php
namespace App\Library;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model; // Include the models class
use App\Library\{TableName AS T, Helper, Format, HelperMysql, GlName AS G};
use \App\Http\Controllers\AccountReceivable\CashRec\PostInvoice\PostInvoiceController;

class TenantTrans{
  private static $_glChart   = [];
  private static $_service   = [];
  private static $_rPropBank = [];
  private static $_rProp     = [];
  private static $_batch     = 0;
  private static $_rBank     = [];
  private static $_rTenant   = [];
  private static $_rData     = [];
  
  public static function searchTntTrans($query, $sort = ['date1'=>'asc', 'tx_code.keyword'=>'asc','cntl_no'=>'asc']){
    return Elastic::searchQuery([
      'index'=>T::$tntTransView,
      'size'=>50000,
      'sort'=>$sort,
      '_source'=>['includes'=>[
        'prop', 'unit', 'sys_date', 'tenant', 'date1', 'cntl_no', 'journal', 'tx_code', 'jnl_class', 'building', 'per_code', 'sales_off', 'sales_agent', 'amount', 'dep_amt', 'appyto', 'remark', 'remarks', 'bk_transit', 'bk_acct', 'name_key', 'gl_acct', 'gl_contra', 'gl_acct_org', 'check_no', 'service_code', 'batch', 'job', 'doc_no', 'bank', 'inv_date', 'invoice', 'inv_remark', 'bill_seq', 'net_jnl', 'date2'
      ]],
      'query'=>$query['query']
    ]);
  }
//------------------------------------------------------------------------------  
  public static function getApplyToSumResult($vData, $param = []){
    $must = isset($param['must']) ?  $param['must'] : [];
    $r = Elastic::searchQuery([
      'index'=>T::$tntTransView, 
      'query'=>[
        'must'=>['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant']] +  $must
      ],
      'aggs'=>[
        'group_appyto'=> [
          'terms'=> ['field'=>'appyto', 'size'=>150000],
          'aggs'=>[
            'sum_amount'=>['sum'=>['field'=>'amount']],
            'by_amount_filter'=>[
              'bucket_selector'=>[
                'buckets_path'=>[
                  'sumamount'=>'sum_amount'
                ],
                'script'=>'params.sumamount != 0'
              ]
            ]
          ]
        ]
      ]
    ]);
    $data = ['balance'=>0];
    $r = !empty($r['aggregations']['group_appyto']['buckets']) ? $r['aggregations']['group_appyto']['buckets'] : [];
    // Clean up the -0.000000000000001
    foreach($r as $v){
      $amount = Format::floatNumber($v['sum_amount']['value']);
      if($amount != 0){
        $data['data'][$v['key']] = Format::floatNumber($v['sum_amount']['value']);
        $data['balance'] += $amount;
      }
    }
    return $data;
  }
  /**
   * @Desc Calculate the payment for each invoice 
   * @Return {array} it consists of 2 element - show data and store data with all 
   *    the important information needed to generate the data
   */
//------------------------------------------------------------------------------
  public static function getPostPayment($vData, $param = []){
    $param['isNewBatch'] = isset($param['isNewBatch']) ? $param['isNewBatch'] : 1;
    $glAppytoNeedDelete   = isset($param['glNeedDelete']) && isset($vData['appyto']) ? $param['glNeedDelete']  . '-' . $vData['appyto'] : ''; // This mainly used for fixintg transaction
    self::_setPropertyValue($vData, $param);
    $rTntTrans = self::getApplyToSumResult($vData);
    $vDataArr  = isset($param['vDataArr']) ? $param['vDataArr'] : [];
    $data      = ['show'=>[], 'store'=>[], 'rData'=>self::$_rData];
    $paymentAmount = $vData['amount'] * -1; ##### PAYMENT IS ALWAYS NEGATIVE #####
    $data['endBalance'] = $rTntTrans['balance'] + $paymentAmount;
    $data['store']['paymentAmount'] = $paymentAmount; // WILL BE USE IN THE _fixInvoiceWithPayment FUNCTION
        
    $_getOpenItem = function($vData, $appyto = []){
      $r = self::searchTntTrans([
        'query'=>[
          'must'   => ['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant']] + (!empty($appyto) ? ['appyto'=>$appyto] : []),
        ]
      ]);
      return self::getOpenItem($r)['data'];
    };
    if($rTntTrans['balance'] > 0){
      $appyto  = array_keys($rTntTrans['data']);
//      $r = self::searchTntTrans([
//        'query'=>[
//          'must'   => ['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant'], 'appyto'=>$appyto],
//        ]
//      ]);
      ##### THIS WILL USE THE LAST TRANSACTION IF THEY HAVE MULTIPLE TRANS
//      $openItem = self::_reorderPayment($r, $vData);
      $openItems = $_getOpenItem($vData, $appyto);
      if(!empty($glAppytoNeedDelete)){
        $tmp = $openItems;
        $openItems = [];
        foreach($tmp as $v){
          $openItems[$v['gl_acct'] . '-' . $v['appyto']] = $v;
        }
        
        if(isset($vData['openItemAmount'])){
          if(count($openItems) == 1 && isset($openItems[$glAppytoNeedDelete])){
            ##### WE USE THIS $vData['inAmount'] BECAUSE $vData['inAmount'] + $paymentAmount = SHOULD GET THE RIGHT AMOUNT #####
            $paymentAmount = $param['totalLeftOverPayment'];
            $data['store']['paymentAmount'] = $paymentAmount;

            $openItems[$glAppytoNeedDelete]['amount'] = $vData['inAmount'];
            
//            dd($vData['inAmount'], $paymentAmount, $vData['openItemAmount'], $paymentAmount);
  //            $key = key($rTntTrans['data']);
  //            $rTntTrans['data'][$key] = $vData['openItemAmount'] + $paymentAmount;
  //            $rTntTrans['balance']    = $vData['openItemAmount'] + $paymentAmount;
          } else{
            unset($openItems[$glAppytoNeedDelete]);
          }
        } else{
          if(!empty($glAppytoNeedDelete)){
            unset($openItems[$glAppytoNeedDelete]);
          }
        }
      }
      
      ##### START TO REORDER THE TRANSACTION
      $openItems = self::reorderPayment($openItems);
      $openItem  = Helper::keyFieldName($openItems, ['appyto', 'gl_acct']);
//      $orderedTrans = [];
//      ##### START TO REORDER THE TRANSACTION
//      foreach($openItem as $appytoGl=>$v){
//        $orderedTrans[$appytoGl] = $rTntTrans['data'][$appytoGl];
//      }
//      $orderedTrans = $openItem;
      ##### START TO BUILD EACH TRANACTION BASED ON THE $orderedTrans
      foreach($openItem as $appytoGl=>$v){
        $posAmount = $v['amount'];
        $paymentAmount = $paymentAmount + $posAmount; 
        $transAmount   = ($paymentAmount <= 0) ? ($posAmount * -1) : ($paymentAmount - $posAmount);
        $openItem[$appytoGl]['amount'] = $posAmount;
        
        ##### OVERRIDE THE REMARK FROM THE USER INPUT
        if(isset($vDataArr[$appytoGl])){
          $openItem[$appytoGl]['remark'] = $vDataArr[$appytoGl]['remark'];
        }
        $paymentTntTrans = self::_getPaymentTntTrans($transAmount, $openItem[$appytoGl], $vData);
        $paymentGlTrans  = self::_getPaymentGlTrans($transAmount, $openItem[$appytoGl], $vData);
//        $data['show'][$appytoGl]['IN'] = $openItem[$appytoGl];
//        $data['show'][$appytoGl]['P']  = $paymentGlTrans;
        $data['show'][$v['appyto']]['IN'] = $openItem[$appytoGl];
        $data['show'][$v['appyto']]['P']  = $paymentGlTrans;
        
        $data['store'][T::$tntTrans][] = $paymentTntTrans;
        $data['store'][T::$glTrans][]  = $paymentGlTrans;
        
        // Stop, when the remaining payment is Zero 
        if($paymentAmount >= 0){  break; }
      }
    }
    if($rTntTrans['balance'] <= 0 || $paymentAmount < 0){
      $openItem = Helper::keyFieldName($_getOpenItem($vData), 'gl_acct');
      
      $vData['gl_acct'] = $vData['service_code'] = '375';
      ##### NEED TO MERGE ALL THE ADVANCE RENT AND USE ONE APPYTO
      $vData['appyto']  = isset($openItem['375']) ? $openItem['375']['appyto'] : 0;
      $vData['invoice']  = isset($openItem['375']) ? $openItem['375']['appyto'] : 0;
      $vData['remark']  = isset($vDataArr[0]) ? $vDataArr[0]['remark'] : self::$_glChart[$vData['gl_acct']]['title'];
//      dd($openItem, $paymentAmount, $rTntTrans);
      
      // Set the appyto to zero and it will be change in the store
      $paymentGlTrans   = self::_getPaymentGlTrans($paymentAmount, $vData, $vData);
      $data['show'][0]['P'] = $paymentGlTrans;
      $data['store'][T::$glTrans][] = $paymentGlTrans;
      $data['store'][T::$tntTrans][] = self::_getPaymentTntTrans($paymentAmount, $vData, $vData);
    }
    
    ##### STORE THE DATA TO THE tnt_security_deposit #####    
    $data['store'] = self::getTntSurityDeposit($data['store']);
    return $data;
  }
//------------------------------------------------------------------------------
  public static function cleanTransaction($vData, $batch = '', $isAllowToCleanPastTenant = 0){
    $today = date('Y-m-d');
    $r = self::getApplyToSumResult($vData);
    $rTenant = HelperMysql::getTenant(Helper::getPropUnitTenantMustQuery($vData, [], 0), ['status']);
    if((!empty($rTenant) && $rTenant['status'] == 'C') || $isAllowToCleanPastTenant){
      $updateData = $insertData = $response = [];
      # 1. UPDATE ALL THE ZERO APPLYTO USING THE LAST APPLYTO FOR THE LAST TRANSACTION
      if(isset($r['data'][0])){ // [0] Meaning appyto is zero
        $rTntTrans = self::_searchTntTrans(['must'=>['prop.keyword'=>$vData['prop'], 'tenant'=>$vData['tenant'], 'unit.keyword'=>$vData['unit'], 'appyto'=>0]]);
        $cntlNo = array_values(Helper::keyFieldNameElastic($rTntTrans, 'cntl_no', 'cntl_no'));
        $lastAppyto = end($cntlNo);
        $dataset['database']['update'][T::$tntTrans] =[ 
          'whereInData'=>['field'=>'cntl_no', 'data'=>$cntlNo], 
          'updateData'=>['appyto'=>$lastAppyto],
        ];
        $dataset['elastic'] = [
          'insert'=>[T::$tntTransView=>['tt.cntl_no'=>$cntlNo]] 
        ];
        DB::beginTransaction();
        $success = $response = $elastic = [];
        try{
          if(isset($dataset['database']['insert'])){
            $success += Model::insert($dataset['database']['insert']);
          }
          if(isset($dataset['database']['update'])){
            $success += Model::update($dataset['database']['update']);
          }
          Model::commit([
            'success' =>$success,
            'elastic' =>$dataset['elastic'],
          ]);
        } catch(\Exception $e){
          $response['error']['mainMsg'] = Model::rollback($e);
        }
      }

      ##### START TO CLEAN UP ANY OPEN ITEM #####
      $rTntTrans = self::_searchTntTrans(['must' =>['prop.keyword'=>$vData['prop'], 'tenant'=>$vData['tenant'], 'unit.keyword'=>$vData['unit']]]);
      $openItem  = self::getOpenItem($rTntTrans);
  //    dd($openItem);
      if(!empty($openItem['data'])){
        $dataset = [];
        $balance = $openItem['balance'];
        $openItemData = self::_splitTrans($openItem['data']);

        ##### TO BE ABLE TO CLEAN UP THE DATA, OPEN ITEM FOR NEGATIVE AND POSITBE BE NOT EMPTY #####
        if(!empty($openItemData['negative']) && !empty($openItemData['positive'])){
          $negativeData = $openItemData['negative'];
          $positiveData = $openItemData['positive'];
          $dataset      = [T::$tntTrans=>[], T::$glTrans=>[]];
          $currentData  = current($positiveData);
          $z64 = preg_match('/[a-zA-Z]/',  $currentData['prop']) ? $currentData['prop'] : 'Z64';

          self::$_rPropBank = Helper::keyFieldNameElastic(HelperMysql::getPropBank(['prop.keyword'=>[$currentData['prop']]]), ['prop', 'bank']);
          self::$_rProp     = Helper::getElasticResult(HelperMysql::getProp($currentData['prop'], ['prop', 'group1']), 1)['_source'];
          self::$_glChart   = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$z64])), 'gl_acct');
          self::$_service   = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$z64])), 'service');

          if($balance > 0){
           /* BEGINNING BALANCE NEGATIVE (ONLY 1 APPLYTO)
            * TX_CODE    GL    APPYTO    BALANCE
            * Old transaction
            * IN         602   13876689    1296.00
            * -----------------CASE 1---------------------------
            * Incoming Transaction
            * P          610   6382228      -46.00
            * Moving Transaction
            * S          602   13876689    -46.00
            * S          610   6382228    40.00
            * ------------ RESULT ------------------------------
            * P          602   13876689      1250
            */
  //          dd($positiveData, $negativeData);
            foreach($positiveData as $i=>$posVal){
              if(empty($negativeData)){ break; } // No Need to go to any more since we don't have to any more to offset
              $posAmount = Format::floatNumber($posVal['amount']);
              foreach($negativeData as $j=>$negVal){
                $negValAmount = Format::floatNumber($negVal['amount']);
                $posAmount = $posAmount + $negValAmount; 
                $sysAmount = ($posAmount >= 0) ? $negValAmount : ($posAmount - $negValAmount);
                $sysTntTrans = self::_getSysTntTrans($sysAmount, $negVal, $posVal, $batch);
                $sysGlTrans  = self::_getSysGlTrans($sysTntTrans);

                $dataset[T::$tntTrans] = Helper::pushArray($dataset[T::$tntTrans], $sysTntTrans);
                $dataset[T::$glTrans]  = Helper::pushArray($dataset[T::$glTrans], $sysGlTrans);

                // No longer need this transaction. Need to delete it because the amount is less than 0
                if($posAmount >= 0){
                  unset($negativeData[$j]);
                }

                if($posAmount <= 0){
                  if(!empty($negativeData[$j])){
                    $negativeData[$j]['amount'] = $posAmount;
                  }
                  break;
                }
              }
            }
          } else{
            /* BEGINNING BALANCE NEGATIVE (ONLY 1 APPLYTO)
            * TX_CODE    GL    APPYTO    BALANCE
            * Old transaction
            * P          375   1111      -25.00
            * -----------------CASE 1---------------------------
            * Incoming Transaction
            * IN         602   1234      100
            * Moving Transaction
            * S          375   1111      25.00
            * S          602   1234     -25.00
            * -----------------@CASE 1: END BAL with IN-602-1234:  75----------
            * -----------------CASE 2-----------------------
            * Incoming Transaction
            * P          375   1111      -200
            * -----------------@CASE 2: END BAL: -225 with GL 375 and appyto 1111 
            */
  //------------------------------------------------------------------------------          
           /* BEGINNING BALANCE NEGATIVE (ONLY 1 APPLYTO)
            * TX_CODE    GL    APPYTO    BALANCE
            * Old transaction
            * P          375   13914435 -1,990.00
            * -----------------CASE 1---------------------------
            * Incoming Transaction
            * IN         602   14130263 1,525.00
            * 
            * Moving Transaction
            * S          375   13914435   1,525.00
            * S          602   14130263   -1,525.00
            * -----------------@CASE 1: END BAL with IN-602-1234:  75----------
            * -----------------CASE 2-----------------------
            * Incoming Transaction
            * P          375   13914435   -465.00
            * -----------------@CASE 2: END BAL: -225 with GL 375 and appyto 1111 
            */
            foreach($negativeData as $i=>$negVal){
              if(empty($positiveData)){ break; } // No Need to go to any more since we don't have to any more to offset

              $negAmount = Format::floatNumber($negVal['amount']);
              foreach($positiveData as $j=>$posVal){
                $posValAmount = Format::floatNumber($posVal['amount']);
                $negAmount = $negAmount + $posValAmount; 
                $sysAmount = $negAmount <= 0 ? $posValAmount : $negAmount - $posValAmount;
                $sysTntTrans = self::_getSysTntTrans($sysAmount, $negVal, $posVal, $batch);
                $sysGlTrans  = self::_getSysGlTrans($sysTntTrans);
                $dataset[T::$tntTrans] = Helper::pushArray($dataset[T::$tntTrans], $sysTntTrans);
                $dataset[T::$glTrans]  = Helper::pushArray($dataset[T::$glTrans], $sysGlTrans);

                // No longer need this transaction. Need to delete it because the amount is less than 0
                if($negAmount <= 0){
                  unset($positiveData[$j]);
                }

                if($negAmount > 0){
                  $positiveData[$j]['amount'] = $negAmount;
                  break;
                }
              }
            }
          }

  //        dd($dataset);
          $insertData = HelperMysql::getDataSet($dataset, 'SYS', self::$_glChart, self::$_service);
          ##### ALWAYS CHECK THE SUM SYS TRANS IS ZERO. IF NOT, THE FUNCTION EXIT AND ISSUE ERROR #####
          Helper::isSysTransBalZero($insertData);
          if(!empty($insertData)){
            DB::beginTransaction();
            $elastic = [];
            try{
              $success = Model::insert($insertData);
              $insertElastic = [
                T::$tntTransView =>['tt.cntl_no'=>$success['insert:' . T::$tntTrans]],
                T::$glTransView  =>['gl.seq'=>$success['insert:' . T::$glTrans]]
              ];

              Model::commit([
                'success' =>$success,
                'elastic' =>['insert'=>$insertElastic],
              ]);
            } catch(\Exception $e){
              $response['error']['mainMsg'] = Model::rollback($e);
            }
          }
        }
      }
    }
  }
//------------------------------------------------------------------------------
  public static function splitTxcode($tntTrans){
    $data = ['IN'=>[], 'P'=>[]];
    foreach($tntTrans as $v){
      $v = isset($v['_source']) ? $v['_source'] : $v;
//      $v['tx_code'] = $v['tx_code'] == 'S' ? 'P' :$v['tx_code'];
      $data[$v['tx_code']][] = $v;  
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getTntSurityDeposit($data, $isFixedTrans = 0){
    $deleteData = [];
    ##### STORE THE DATA TO THE tnt_security_deposit #####   
    if(!empty($data[T::$glTrans])){
      $amount = 0;
      $trans = [];
      foreach($data[T::$glTrans] as $v){
        if(($v['tx_code'] == 'S' || $v['tx_code'] == 'P') && $v['gl_acct'] == '607'){
          $amount += $v['amount'];
          $trans = $v;
        }
      }
      
      ##### CHECK TO SEE IF VENDOR PAYMENT HAS ANY CHECK OR NOT #####
      if($amount != 0){
        $trans['amount'] = $amount * -1;
        $data[T::$tntSecurityDeposit][] = $trans;
      }
    }
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getUpdateTenantDepositData($tntSecurityDeposit){
    $updateData = [];
    $tenantId   = [];
    foreach($tntSecurityDeposit as $v){
      $propUnitTenant = Helper::selectData(['prop', 'unit', 'tenant'], $v);
      $where = Model::buildWhere($propUnitTenant + ['gl_acct'=>'607']);
      $sumDepositAmount = DB::table(T::$tntSecurityDeposit)->select(['amount'])->where($where)->sum('amount');
      $id = HelperMysql::getTenant(Helper::getPropUnitTenantMustQuery($v, [], 0), ['tenant_id'])['tenant_id'];
      
      $updateData[T::$tenant][] = [
        'whereData'=>$propUnitTenant,
        'updateData'=>['dep_held1'=>$sumDepositAmount]
      ];
      $tenantId[] = $id;
    }
    
    return ['updateData'=>$updateData, 'elastic'=>[T::$tenantView=>['t.tenant_id'=>$tenantId]]];
  }
//------------------------------------------------------------------------------
  public static function deleteVendorPaymentIdDeposit($dataset){
    $vendorPaymentId = $rVendorPayment = $tntTrans = [];
    ##### CHECK TO SEE IF VENDOR PAYMENT HAS ANY CHECK OR NOT #####
    if(isset($dataset['tnt_security_deposit'][0])){
      $trans = $dataset['tnt_security_deposit'][0];
      if($trans['amount'] < 0){
        $where = Model::buildWhere(['type'=>'deposit_refund', 'vendid'=>implode('-', Helper::selectData(['prop', 'unit', 'tenant'], $trans))]);
        $rVendorPayment = DB::table(T::$vendorPayment)->select('*')->where($where)->orderBy('vendor_payment_id', 'DESC')->first();
        if(!empty($rVendorPayment) && !$rVendorPayment['print']){
          $where = Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $trans) + ['tx_code'=>'D']);
          $rTntSecurityDeposit = DB::table(T::$tntSecurityDeposit)->select(['batch'])->where($where)->orderBy('tnt_security_deposit_id', 'DESC')->first();

          $vendorPaymentId = $rVendorPayment['vendor_payment_id'];
          $rVendorPayment['batch'] = $rTntSecurityDeposit['batch'];
          $rVendorPayment['service_code'] = $rVendorPayment['gl_acct'];
          $rVendorPayment['remark'] = 'Remove ' . $rVendorPayment['remark'];
        } else if(!empty($rVendorPayment) && $rVendorPayment['print']){
//          $gl_acct = '607';
//          $rVendorPayment['appyto'] = 0;
//          $rVendorPayment['batch']    = HelperMysql::getBatchNumber();
//          $rVendorPayment['amount']   = abs($trans['amount']);
//          $rVendorPayment['service']  = $rVendorPayment['gl_acct'] = $gl_acct;
//          $rVendorPayment['tnt_name'] = DB::table(T::$tenant)->select(['tnt_name'])->where(Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $rVendorPayment)))->first()['tnt_name']; 
//          $rVendorPayment['_rProp']   = DB::table(T::$prop)->select(['ar_bank','group1'])->where(Model::buildWhere(['prop'=>$rVendorPayment['prop']]))->first();
//          $rVendorPayment['_glChart'] = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$rVendorPayment['prop']])), 'gl_acct');
//          $rVendorPayment['_service'] = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$rVendorPayment['prop']])), 'service');
//          $rVendorPayment['_rBank']   = Helper::keyFieldName(DB::table(T::$propBank)->where(Model::buildWhere(['prop'=>$rVendorPayment['prop']]))->get()->toArray(), 'bank');
//          $rVendorPayment['remark']   = $rVendorPayment['inv_remark'] = $rVendorPayment['_service'][$gl_acct]['remark'];
//          
//          $tntTrans =  PostInvoiceController::getInstance()->getStoreData($rVendorPayment);
//          $tntTrans = current($tntTrans[T::$tntTrans]);
        }
      }
    }
    return ['vendorPaymentId'=>$vendorPaymentId, T::$tntSecurityDeposit=>$rVendorPayment, T::$tntTrans=>$tntTrans];
  }
//------------------------------------------------------------------------------
  public static function invoiceTenantSecurityDeposit($vData){
    $batch = 0;
    $r = self::searchTntTrans(['query'=>Helper::getPropUnitTenantMustQuery($vData, ['gl_acct'=>'607'])]);
    if(!empty($r['hits']['hits'])){
      $where = Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $vData));
      $rTntSecurityDeposit  = DB::table(T::$tntSecurityDeposit)->select('amount')->where($where)->get()->toArray();

      $sumSecurityDeposit    = array_sum(array_column(array_column($r['hits']['hits'], '_source'), 'amount'));
      $sumTntSecurityDeposit = array_sum(array_column($rTntSecurityDeposit,'amount'));
      ##### START TO ADD THE INVOICE IF THE CONDITION MET #####
      if(Format::floatNumber($sumSecurityDeposit) == 0 && Format::floatNumber($sumTntSecurityDeposit) < 0){
        $batch             = HelperMysql::getBatchNumber();
        $glAcct            = '607';
        $vData['appyto']   = 0;
        $vData['batch']    = $batch;
        $vData['amount']   = abs($sumTntSecurityDeposit);
        $vData['service']  = $vData['gl_acct'] = $glAcct;
        $vData['tnt_name'] = DB::table(T::$tenant)->select(['tnt_name'])->where(Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $vData)))->first()['tnt_name']; 
        $vData['_rProp']   = DB::table(T::$prop)->select(['ar_bank','group1'])->where(Model::buildWhere(['prop'=>$vData['prop']]))->first();
        $vData['_glChart'] = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$vData['prop']])), 'gl_acct');
        $vData['_service'] = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$vData['prop']])), 'service');
        $vData['_rBank']   = Helper::keyFieldName(DB::table(T::$propBank)->where(Model::buildWhere(['prop'=>$vData['prop']]))->get()->toArray(), 'bank');
        $vData['remark']   = $vData['inv_remark'] = $vData['_service'][$glAcct]['remark'];

        $tntTrans   = PostInvoiceController::getInstance()->getStoreData($vData);
//        $tntTrans   = Helper::keyFieldName($tntTrans[T::$tntTrans], 'tx_code');
//        $insertData = HelperMysql::getDataSet([T::$tntTrans=>[$tntTrans['IN']]], 'SYS', $vData['_glChart'], $vData['_service']);
        $insertData = HelperMysql::getDataSet($tntTrans, 'SYS', $vData['_glChart'], $vData['_service']);
        ############### DATABASE SECTION ######################
        DB::beginTransaction();
        $success = $elastic = [];
        try{
          # IT IS ALWAYS ONE TRANSACTION ONLY
          $success += Model::insert($insertData);
          $cntlNo   = end($success['insert:'.T::$tntTrans]);
          $success += Model::update([
            T::$tntTrans=>[ 
              'whereInData'=>[['field'=>'cntl_no', 'data'=>$success['insert:'.T::$tntTrans]], ['field'=>'appyto', 'data'=>[0]]], 
              'updateData'=>['appyto'=>$cntlNo, 'invoice'=>$cntlNo],
            ]
          ]);
          $success += Model::update([
            T::$tntTrans=>[ 
              'whereInData'=>['field'=>'cntl_no', 'data'=>$success['insert:'.T::$tntTrans]], 
              'updateData'=>['invoice'=>DB::raw('appyto')],
            ]
          ]);
          $elastic = [
            T::$tntTransView =>['tt.cntl_no'=>$success['insert:' . T::$tntTrans]],
          ];
          
          if(!empty($success['insert:'.T::$glTrans])){
            $success += Model::update([
              T::$glTrans=>[
                'whereInData'=>[['field'=>'seq', 'data'=>$success['insert:'.T::$glTrans]],['field'=>'appyto', 'data'=>[0]]], 
                'updateData'=>['appyto'=>$cntlNo, 'invoice'=>$cntlNo],
              ]
            ]);
            $elastic[T::$glTransView] = ['gl.seq'=>$success['insert:' . T::$glTrans]];
          }
          
          Model::commit([
            'success' =>$success,
            'elastic' =>['insert'=>$elastic],
          ]);
        } catch(\Exception $e){
          Model::rollback($e);
        }
      }
    }
    return $batch;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private static function _splitTrans($openItem){
    $data = ['negative'=>[], 'positive'=>[]];
    foreach($openItem as $v){
      $k = Format::floatNumber($v['amount']) > 0 ? 'positive' : 'negative';
      $data[$k][] = $v;
    }
    return $data;
  }
//------------------------------------------------------------------------------  
  public static function getOpenItem($r){
    $data = $openItem = $row = [];
    $r = Helper::getElasticResult($r);
    foreach($r as $i=>$v){
      $v = $v['_source']; 
      $v['amount'] = Format::floatNumber($v['amount']);
      $openItem[$v['appyto']][] = $v;
    }
//    foreach($openItem['14320739'] as $v){
//      echo $v['tx_code'] . ',' .  $v['gl_acct'] . ',' . $v['amount'] . "\n";
//    }
//    $test = $openItem['14320739'];
//    $openItem = [];
//    $openItem['14320739'] = $test;
    foreach ($openItem as $applyto=>$val) {
      $balance = 0;
      foreach ($val as $v) {
        $balance += $v['amount'];
      }
      $balance = Format::floatNumber($balance);
      if($balance != 0) {
        $tmp = [];
        foreach($val as $v){
          if(isset($tmp[$v['gl_acct']])){
            $tmp[$v['gl_acct']]['amount'] += $v['amount'];
          } else{
            $tmp[$v['gl_acct']] = $v;
          }
        }
        foreach($tmp as $gl=>$v){
          if(Format::floatNumber($v['amount']) != 0){
//            $id = isset($tmp[G::$rentCollection]) ? $applyto : $applyto . $v['gl_acct'];
            $id =  $applyto . $v['gl_acct'];
            $data[$id] = $v;
          }
        }
//        foreach(array_reverse($val) as $reverseVal){
//          if(($balance < 0 && $reverseVal['tx_code'] == 'P') || ($balance > 0 && $reverseVal['tx_code'] == 'IN') || $reverseVal['tx_code'] == 'D'){
//            $data[$applyto] = $reverseVal;
//            break;
//          }
//        }
//        if(empty($data[$applyto])){
//          $data[$applyto] = end($val);
//        }
//        $data[$applyto]['amount'] = $balance;
      }
    }
//    dd($data);
    $balance = 0;
    foreach($data as $v){
//      if(isset($v['tx_code']) && $v['tx_code'] != 'S'){
//        $v['tx_code'] = Format::floatNumber($v['amount']) > 0 ? 'IN' : 'P';
//        if(Format::floatNumber($v['amount']) < 0 && $v['tx_code'] == 'P'){
//          $v['remark']  = 'ADVANCE RENT';
//          $v['gl_acct'] = $v['service_code'] = '375';
//        }
        
        $balance      += $v['amount'];
        $v['balance']  = $balance;
        $row[] = $v;
//      }
    }
//    dd($balance, $row);
    return ['data'=>$row, 'balance'=>$balance];
  }
//------------------------------------------------------------------------------
  private static function _getPaymentGlTrans($amount, $oldTrans, $vData){
    $bank  = isset($vData['ar_bank']) ? $vData['ar_bank'] : $vData['bank'];
    $bank  = isset(self::$_rPropBank[$vData['prop'] . $bank]) ? $bank : HelperMysql::getDefaultBank($vData['prop']); 
    Helper::exitIfError($vData['prop'] . $bank, self::$_rPropBank, Html::errMsg('Property # ' . $vData['prop'] . ' with bank ' . $bank . ' Does not exist.'));
    $_getRemark = function($oldTrans){
      if($oldTrans['gl_acct'] == '375'){
        return $oldTrans['remark'];
      } else{
        return preg_match('/Payment/i', $oldTrans['remark']) ? $oldTrans['remark'] : 'Payment ' . $oldTrans['remark'];
      }
    };
    $remark = $_getRemark($oldTrans);
    $rTenant = HelperMysql::getTenant(['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant']], ['tnt_name']);
    return [
      'tx_code'      => 'P',
      'service_code' => isset(self::$_service[$oldTrans['gl_acct']]['service']) ? self::$_service[$oldTrans['gl_acct']]['service'] : $oldTrans['service_code'],
      'batch'        => self::$_batch,
      'bank'         => $bank,
      'journal'      => 'CR',
      'amount'       => $amount,
      'gl_contra'    => self::$_rPropBank[$vData['prop'] . $bank]['gl_acct'],
      'remark'       => $remark,
      'name_key'     => $rTenant['tnt_name'],
      'tnt_name'     => $rTenant['tnt_name'],
      'check_no'     => isset($vData['check_no']) ? $vData['check_no'] : '000000',
      'remark'       => $remark,
      'inv_remark'   => $remark,
      'bill_seq'     => '8',
      'date1'        => $vData['date1'],
    ] + $oldTrans; // + $vData must be at the end so that if there is duplication, it won't override it
  }
//------------------------------------------------------------------------------
  private static function _getPaymentTntTrans($amount, $oldTrans, $vData){
    $bank  = isset($vData['ar_bank']) ? $vData['ar_bank'] : $vData['bank'];
    $bank  = isset(self::$_rPropBank[$vData['prop'] . $bank]) ? $bank : HelperMysql::getDefaultBank($vData['prop']); 
    Helper::exitIfError($vData['prop'] . $bank, self::$_rPropBank, Html::errMsg('Property # ' . $vData['prop'] . ' with bank ' . $bank . ' Does not exist.'));
    $rTenant = HelperMysql::getTenant(['prop.keyword'=>$vData['prop'], 'unit.keyword'=>$vData['unit'], 'tenant'=>$vData['tenant']], ['tnt_name']);

    return [
      'tx_code'      => 'P',
      'journal'      => 'CR',
      'service_code' => isset(self::$_service[$oldTrans['gl_acct']]['service']) ? self::$_service[$oldTrans['gl_acct']]['service'] : $oldTrans['service_code'],
      'batch'        => self::$_batch,
      'bank'         => $bank,
      'amount'       => $amount,
      'gl_contra'    => self::$_rPropBank[$vData['prop'] . $bank]['gl_acct'],
      'remark'       => $oldTrans['remark'],
      'inv_remark'   => $oldTrans['remark'],
      'name_key'     => $rTenant['tnt_name'],
      'check_no'     => isset($vData['check_no']) ? $vData['check_no'] : '000000',
      'tnt_name'     => $rTenant['tnt_name'],
      'bill_seq'     => '8',
      'date1'        => $vData['date1'],
      'date2'        => $vData['date1'],
    ] + $oldTrans; // + $vData must be at the end so that if there is duplication, it won't override it
  }
//------------------------------------------------------------------------------
  private static function _getSysTntTrans($amount, $oldTrans, $vData, $batch = ''){
    $batch   = !empty($batch) ? $batch : $vData['batch'];
    $amount = abs($amount);
//    print_r($oldTrans);
//    print_r($vData);
    $tntName = isset($vData['name_key']) ? $vData['name_key'] :  '';
    $oldTrans['amount']   = $amount;
    $oldTrans['journal']  = 'JE';
    $oldTrans['tx_code']  = 'S';
    $oldTrans['usid']     = 'SYS';
    $oldTrans['check_no'] = isset($vData['check_no']) ? $vData['check_no'] : '000000';
//    $oldTrans['date1']    = $oldTrans['inv_date'] = date('Y-m-d');
    $oldTrans['date1']    = $oldTrans['inv_date'] = $vData['date1'];
    $oldTrans['batch']    = $batch;
    $oldTrans['tnt_name'] = $tntName;
    $oldTrans['name_key'] = $tntName;
    unset($oldTrans['cntl_no']);
    $copyData = $oldTrans;
    
    $copyData['amount']       = $amount * -1;
    $copyData['service_code'] = $vData['service_code'];
    $copyData['appyto']       = !empty($vData['appyto']) ? $vData['appyto'] : 0;
    $copyData['remark']       = isset(self::$_service[$vData['service_code']]['remark']) ? self::$_service[$vData['service_code']]['remark'] : '';
    $copyData['remarks']      = $copyData['remark'];
    $copyData['check_no']     = isset($vData['check_no']) ? $copyData['check_no'] : '000000';
    $copyData['inv_remark']   = $copyData['remark'];
    $copyData['gl_acct']      = self::$_service[$vData['service_code']]['gl_acct'];
    return [$oldTrans, $copyData];
  }
//------------------------------------------------------------------------------
  private static function _getSysGlTrans($sysTntTrans, $rProp = []){
    if(!isset($sysTntTrans[0]['prop'])){
      dd($sysTntTrans);
    }
    $bank = !isset(self::$_rPropBank[$sysTntTrans[0]['prop'] . $sysTntTrans[0]['bank']]) ? HelperMysql::getDefaultBank($sysTntTrans[0]['prop']) : $sysTntTrans[0]['bank'];

    $sysTntTrans[0]['journal'] = $sysTntTrans[1]['journal'] = 'JE';
    $sysTntTrans[0]['group1']  = $sysTntTrans[1]['group1']  = self::$_rProp['group1'];
    
    $sysTntTrans[0]['gl_contra'] = isset(self::$_rPropBank[$sysTntTrans[0]['prop'] . $bank]) ? self::$_rPropBank[$sysTntTrans[0]['prop'] . $bank]['gl_acct'] : '103';
    $sysTntTrans[1]['gl_contra'] = isset(self::$_rPropBank[$sysTntTrans[1]['prop'] . $bank]) ? self::$_rPropBank[$sysTntTrans[1]['prop'] . $bank]['gl_acct'] : '103';
    return $sysTntTrans;
  }  
//------------------------------------------------------------------------------
  private static function _searchTntTrans($query){
    return Elastic::searchQuery([
      'index'=>T::$tntTransView,
      'size'=>100000,
      'sort'=>['date1'=>'asc'],
      '_source'=>['includes'=>[
        'prop', 'unit', 'tenant', 'date1', 'cntl_no', 'journal', 'tx_code', 'jnl_class', 'building', 'per_code', 'sales_off', 'sales_agent', 'amount', 'dep_amt', 'appyto', 'remark', 'remarks', 'bk_transit', 'bk_acct', 'name_key', 'gl_acct', 'gl_contra', 'gl_acct_org', 'check_no', 'service_code', 'batch', 'job', 'doc_no', 'bank', 'inv_date', 'invoice', 'inv_remark', 'bill_seq', 'net_jnl', 'date2'
      ]],
      'query'=>$query
    ]);
  }
//------------------------------------------------------------------------------
  private static function _setPropertyValue($vData, $param){
    if($param['isNewBatch']){
      self::$_batch   = empty($vData['batch']) ? HelperMysql::getBatchNumber() : $vData['batch'];
    }
    $z64 = preg_match('/[a-zA-Z]/', $vData['prop']) ? $vData['prop'] : 'Z64'; 
    self::$_glChart   = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$z64])), 'gl_acct');
    self::$_service   = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$z64])), 'service');
    self::$_rProp     = HelperMysql::getTableData(T::$prop, Model::buildWhere(['prop'=>$vData['prop']]), ['ar_bank','group1'], 1);
    self::$_rBank     = Helper::keyFieldName(HelperMysql::getTableData(T::$propBank, Model::buildWhere(['prop'=>$vData['prop']])), 'bank');
    self::$_rTenant   = HelperMysql::getTableData(T::$tenant, Model::buildWhere(Helper::selectData(['prop', 'unit', 'tenant'], $vData)), 'tnt_name', 1);
    self::$_rPropBank = Helper::keyFieldNameElastic(HelperMysql::getPropBank(['prop.keyword'=>[$vData['prop']]]), ['prop', 'bank']);
    
    // Need to check the batch date is the same here. 
    self::$_rData = [
      'batch'   =>self::$_batch,
      'glChart' =>self::$_glChart,
      'service' =>self::$_service,   
      'rProp'   =>self::$_rProp,     
      'rBank'   =>self::$_rBank,     
      'rTenant' =>self::$_rTenant,   
      'rPropBank'=>self::$_rPropBank, 
    ];
  }
//------------------------------------------------------------------------------
  public static function reorderPayment($openItems){
    $tmp = $data = [];
    foreach($openItems as $glAppyto=>$v) {
      if(preg_match('/\-/', $glAppyto)){
        $p  = explode('-', $glAppyto);
        $gl = $p[0];
        $i = ($gl == '602') ? 1000 : $gl; // PUT 1000 BECAUSE WE WANT IT TO BE THE LAST ONE WHEN WE SORT
        $tmp[$i] = [$glAppyto=>$v];
      } else{
        $i = $v['gl_acct'] == '602' ? 1000 : $v['gl_acct'];
        $tmp[$i][] = $v;
      }
    }
    ksort($tmp);
    foreach($tmp as $i=>$val){
      foreach($val as $glAppyto=>$v){
        $data[$i . $glAppyto] = $v;
      }
    }
    return $data;
  }
}