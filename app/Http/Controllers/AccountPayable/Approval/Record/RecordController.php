<?php
namespace App\Http\Controllers\AccountPayable\Approval\Record;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, File, Format, TableName AS T};
use App\Http\Models\{Model, ApprovalModel AS M};
use \App\Http\Controllers\AccountPayable\Approval\ApprovalController AS P;
use PDF;

class RecordController extends Controller {
  public function create(Request $req){
    $num     = !empty($req['num']) ? $req['num'] : 0;
    $label   = ($num) ? $num : 'all';
    $option  = [0=>'Record All Checks'];
    $option  = $option + (!empty($num) ? [$num=>'Request ' . $label .' Transaction(s) to be Recorded.'] : []);
    $text = Html::h4('Are you sure you want to record these check(s)?', ['class'=>'text-center']) . Html::br();
    $fields = [
      'numTransaction'=>['id'=>'numTransaction','label'=>'How Many Transactions?','type'=>'option', 'option'=>$option, 'req'=>1],
      'date1'         =>['id'=>'date1','label'=>'Post Date', 'class'=>'date', 'type'=>'text', 'value'=>date('m/d/Y')],
    ];
    return ['html'=>$text . Html::tag('form', implode('',Form::generateField($fields)), ['id'=>'showConfirmForm', 'class'=>'form-horizontal'])];
  }
//------------------------------------------------/.,;',;-----------------------------
  public function store(Request $req){
    $batch   = HelperMysql::getBatchNumber();
    $ids     = [];
    $dataset = [T::$glTrans=>[], T::$batchRaw=>[], 'summaryAcctPayable'=>[]];
    
    $id = 'vendor_payment_id';
    $req->merge([$id=>$req['id']]);
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$vendorPayment,T::$glTrans],
      'orderField'      => [$id, 'date1'],
      'includeCdate'    => 0, 
      'includeUsid'     => 1, 
      'isExistIfError'  => 0,
      'setting'         => $this->_getSetting('store', $req), 
//      'validateDatabase'=>[
//        'mustExist'=>[
//          'prop|prop'
//        ],
//      ]
    ]);
    $vData   = $valid['data'];
    $usid    = $vData['usid'];
    
    $rSource = P::getInstance()->getApprovedResult($valid);
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>'Z64'])),'gl_acct');
    $service = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>'Z64'])),'service');

    foreach($rSource as $i => $v){
      $glChart = !preg_match('/[a-zA-Z]+/', $v['prop']) ? $glChart : Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$v['prop']])),'gl_acct');
      $service = !preg_match('/[a-zA-Z]+/', $v['prop']) ? $service : Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$v['prop']])),'service');
      
      $ids[]   = $v['vendor_payment_id'];
      $dataset[T::$glTrans][] = P::getInstance()->getGlTransData($v, $vData, $batch, $glChart);
    }
    $dataset[T::$batchRaw] = $dataset[T::$glTrans];
    $insertData            = HelperMysql::getDataset($dataset,$usid,$glChart,$service);
    
    $updateData            = [
      T::$vendorPayment    => [
        'whereInData'      => ['field'=>'vendor_payment_id','data'=>$ids],
        'updateData'       => [
          'posted_date'    => $vData['date1'],
          'batch'          => $batch,
          'print'          => 1,
          'print_type'     => 'post only',
          'print_by'       => $usid,
        ]
      ]
    ];
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = [];
    
    try {
      $success  += Model::insert($insertData);
      $success  += Model::update($updateData);
      $elastic   = ['insert'=>[
          T::$glTransView         => ['gl.seq'=>$success['insert:'.T::$glTrans]],
          T::$vendorPaymentView   => ['vp.vendor_payment_id'=>$ids], 
        ]
      ];
      
      Model::commit([
        'success' => $success,
        'elastic' => $elastic,
      ]);
      
      $response['mainMsg']           = $this->_getSuccessMsg(__FUNCTION__,$success['insert:'.T::$glTrans]);
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
      'storeNoApprovedCheck' => Html::errMsg('There is no approved check. Please double check it again.'),
      'noBank'               =>Html::errMsg('There is no bank '. Helper::getValue('bank', $vData) .' for trust ' . Helper::getValue('trust', $vData)),
    ];
    return $data[$name];
  }  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$ids=[]){
    $data = [
      'store' =>Html::sucMsg('Successfully Recorded ' . count($ids) . ' checks'),
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
}