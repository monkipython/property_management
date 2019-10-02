<?php
namespace App\Http\Controllers\BankRec\Autocomplete;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, Elastic, Html, TableName AS T, Helper, Format};
use App\Http\Models\BankRecModel AS M;

class TrustBankInfoController extends Controller {
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => [T::$prop], 
      'orderField'      => ['trust'], 
      'includeCdate'    => 0, 
      'validateDatabase'=>[
        'mustExist'=>[
          T::$prop . '|trust',
        ]
      ]
    ]);
    $vData = $valid['data'];
    $option= [];
    $r = Helper::getElasticResult(M::getPropElastic(['trust.keyword'=>$vData['trust']],['prop','trust','ap_bank',T::$bank]));

    $defaultBank = !empty($r[0]['_source']['ap_bank']) ? $r[0]['_source']['ap_bank'] : '';
    foreach($r as $i => $v){
      $source = $v['_source'];
      $bank   = Helper::getValue(T::$bank,$source,[]);
      
      foreach($bank as $idx => $val){
        if(!empty($val['bank'])){
          $option[$val['bank']] = Format::getBankDisplayFormat($val,'cp_acct');   
        }
        
      }
    }
    ksort($option);
    return ['html' => Html::buildOption($option,$defaultBank)];
  }
}