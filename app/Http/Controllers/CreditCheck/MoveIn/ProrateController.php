<?php
namespace App\Http\Controllers\CreditCheck\MoveIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{RuleField, V, Form, Elastic, Mail, File, Html, Helper,TableName AS T, FullBilling,HelperMysql};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\CreditCheckModel AS M; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class
use PDF;

class ProrateController extends Controller{
  private $_viewPath        = 'app/CreditCheck/movein/';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $id = $req['id'];
    $moveinDate = !empty($req['moveinDate']) ? $req['moveinDate'] : '';
    $baseRent   = !empty($req['baseRent']) ? preg_replace('/\$|\,|\s+/', '', $req['baseRent']) : '';
//    $leaseData  = !empty($req['leaseData']) ? $req['leaseData'] : '';
    $r = M::getApplication(Model::buildWhere(['a.application_id'=>$id]), 1);
    if(!empty($baseRent) && !empty($r) && Helper::validateDate($moveinDate)){
      $r['amount'] = $baseRent;
      $prorateAmountData = FullBilling::getProrateAmount($r, $moveinDate);
      $rGlChat = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$r['prop']]), ['remark', 'service']), 'service');
      return FullBilling::getFullBillingField($prorateAmountData, $rGlChat, $moveinDate);
    }
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
  }
//------------------------------------------------------------------------------
  public function edit($id){
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
  }
//------------------------------------------------------------------------------
  public function create(){
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
}