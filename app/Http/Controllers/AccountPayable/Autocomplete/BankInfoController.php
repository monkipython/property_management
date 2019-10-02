<?php
namespace App\Http\Controllers\AccountPayable\Autocomplete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Account, TableName AS T, Helper, Format, TenantTrans};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\AccountPayableModel AS M;
use SimpleCsv;
use PDF;

class BankInfoController extends Controller {
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$prop], 
      'orderField'      => ['prop'], 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          'prop_bank|prop',
        ]
      ]
    ]);
    $vData = $valid['data'];
    $option= [];
    $r = Helper::getElasticResult(M::getBankElastic(['prop.keyword'=>$vData['prop']]));
    $defaultBank = 0;
    foreach($r as $v){
      $v = $v['_source'];
      $defaultBank = $v['ap_bank'];
      $option[$v['bank']] = Format::getBankDisplayFormat($v,'cp_acct');
    }
    return ['html'=>Html::buildOption($option, $defaultBank)];
  }
}