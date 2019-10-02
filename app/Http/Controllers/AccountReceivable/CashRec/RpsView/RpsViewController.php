<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\RpsView;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, Html, TableName AS T, RpsApi, Helper};

class RpsViewController extends Controller {
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'       => $req->all(),
      'tablez'       => $this->_getTable(__FUNCTION__),
      'orderField'   => $this->_getOrderField(__FUNCTION__),
      'setting'      => $this->_getSetting(__FUNCTION__),
      'includeCdate' => 0,
      'isExistIfError'=>0,
//      'validateDatabase' => [
//        'mustExist' => [
//          T::$tntTrans . '|prop,unit,tenant,batch,job',
//        ],
//      ],
    ]);

    if(isset($valid['error'])){
      Helper::echoJsonError(Html::errMsg('The RPS image does not exist.'), 'popupMsg');
    }
    $vData  = $valid['dataNonArr'];
    return  ['html' => $this->_getCheckImages($vData)];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getTable($fn){
    $tablez = [
      'index' => [T::$tntTrans],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField = [
      'index'  => ['prop','unit','tenant','batch','job','date1'],
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$req=[]){
    $data = [
      'index'=>[
        'field'=>[
        ],
      ],
    ];
    return $data[$fn];
  }
//------------------------------------------------------------------------------
  private function _getCheckImages($vData){
    $html       = $moneyDiv = $couponDiv = '';
    $prefix     = substr($vData['date1'],0,3);
    $drn        = $prefix . $vData['batch'] . str_pad($vData['job'],6,'0',STR_PAD_LEFT);
    $docImages  = RpsApi::getInstance()->getRelatedDocs($drn);
    $docImages  = !empty($docImages) ? $docImages : [];
    
    foreach($docImages as $i => $v){
      $docDiv       = '';
      $indexLabel   = $i + 1;
      $html   .= Html::h3('Check Image ' . $indexLabel,['class'=>'text-center']);
      $docDiv .= Html::tag('h4','Front of Document ' . $indexLabel,['class'=>'text-center']) . $v['check']['front'];
      $docDiv .= Html::tag('h4','Back of Document ' . $indexLabel,['class'=>'text-center']) . $v['check']['rear'];
      $html   .= Html::div($docDiv,['id'=>'checkContainer' . $indexLabel]);
    }
    
    $html = !empty($html) ? $html : Html::errMsg('No Image Found.');  
    return Html::div($html,['id'=>'imageContainer']);
  }
}
