<?php
namespace App\Http\Controllers\GeneratePDF;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Library\{RuleField};
use App\Library\{RuleField,Form, Elastic, Mail, Html, Helper, Auth, GridData, Upload, V, File};
use App\Http\Models\CreditCheck AS M; // Include the models class
//use App\Http\Controllers\Upload\ProcessUpload\ChecekCheck;
use App\Http\Controllers\Upload\ProcessUpload\{CreditCheck};
use Illuminate\Support\Facades\DB;

class GeneratePDFController extends Controller{
  private $_data	  = [];
  private $_fieldData = [];
  private $_viewPath  = 'app/creditCheck/';
  private $_viewGlobalAjax  = 'global/ajax';
  private $_viewGlobalHtml  = 'global/html';
  private $_indexMain       = 'creditcheck_view/creditcheck_view/_search?';
  private $_table      = 'fileUpload';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $v = $this->_validateUUID($id, $req);
    $vData = $v['data'];
    return ['html'=>Html::tag('iframe', '', ['src'=>$vData['path'], 'class'=>'viewEach', 'width'=>'100%', 'height'=>'500', 'style'=>'height:100%;'])];
  }
//------------------------------------------------------------------------------
  public function edit(Request $req){
     
  }
//------------------------------------------------------------------------------
  public function update(Request $req){

  }
//------------------------------------------------------------------------------
  public function create(){
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $v = V::startValidate([
      'rawReq'=>$req->all(),
      'rule'=>$this->_getRule(),
      'orderField'=> key($req->all())
    ]);
    $op = $v['op'];
    $location = !empty(File::getLocation($op)['storeUpload']) ? File::getLocation($op)['storeUpload'] : '';
    $defaultError = 'Fail to upload.';
    
    if(!empty($location)){
      $vData = $v['data'];
      $fileInfo = pathinfo($vData['qqfilename']);
      $v['data']['ext']  = $fileInfo['extension'];
      $v['data']['file'] = hash('ripemd160', $fileInfo['filename'] . rand(1, 10000)) . '.' . $fileInfo['extension'];
      
      $uploadR = Upload::handleUpload($location, $this->_getAllowExtension($op), $v['data']['file']);
      if(empty($uploadR['error']) && $uploadR['success']){
        $uploadR['name'] = $vData['qqfilename'];
        $uploadR['file'] = $v['data']['file'];
        $uploadR['type'] = $op;
        
        switch ($op){
          case 'CreditCheck':
            return (CreditCheck::store($v, $location, Helper::mysqlDate())) ? $uploadR : Helper::echoJsonError($defaultError);
        }
      } else{
        Helper::echoJsonError($uploadR['error']);
      }
    } else{
      Helper::echoJsonError('Location does not exist.');
    }
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req){
    $v = $this->_validateUUID($id, $req);
    if(!empty($v['op'])){
      $vData = $v['data'];
      return DB::table($this->_table)->where([['uuid', '=', $vData['uuid']], ['type', '=', $v['op']]])->delete();
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private  function _getRule($orderField = ''){
    $data = [
      'qqfile'=>'required',
      'qqfilename'=>'required|string|between:1,1000000|regex:/^[^,]+$/',
      'qqpath'=>'required|string|between:1,1000000',
      'qquuid'=>'required|string|between:1,1000000',
      'qqtotalfilesize'=>'required',
      'qqpartindex'=>'nullable',
      'qqchunksize'=>'nullable',
      'qqpartbyteoffset'=>'nullable',
      'qqtotalparts'=>'nullable|integer',
      'done'=>'nullable|string|between:1,1000000',
      'op'=>'required|string|between:1,100',
    ];
    return !empty($orderField) ? [$orderField=>$data[$orderField]] : $data;
  }
//------------------------------------------------------------------------------
  private function _getAllowExtension($op){
    $default = ['pdf', 'png', 'jpg', 'gif'];
    $data = [
      'CreditCheck'=>$default,
    ];
    return $data[$op];
  }
//------------------------------------------------------------------------------
  private function _validateUUID($id, $req){
    $v = V::startValidate([
      'rawReq'=>['uuid'=>$id] + $req->all(),
      'tablez'=>[$this->_table],
      'orderField'=>['uuid'],
      'validateDatabase'=>[
        'mustExist'=>[
          $this->_table . '|uuid', 
        ]
      ]
    ]);
    $r = DB::table($this->_table)->where('uuid', '=', $id)->first();
    
    $v['data']['path'] = File::getLocation('CreditCheck')['showUpload'] . implode('/', [$r['type'], $r['uuid'], $r['file']]);
    $v['data']['ext']  = $r['ext'];
    return $v;
  }
}