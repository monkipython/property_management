<?php
namespace App\Http\Controllers\PropertyManagement\Unit\UnitDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Html, Helper, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\UnitModel AS M; // Include the models class

class UnitDateController extends Controller {
  private $_viewPath = 'app/PropertyManagement/Unit/unitDate/';
  private $_viewTable = '';
  private $_indexMain = '';
    
  public function __construct(Request $req){
    $this->_viewTable = T::$unitView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';   
  }
//------------------------------------------------------------------------------    
  public function edit($id,Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['unit_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    $formUnitDate = Form::generateForm([
      'tablez'       => $this->_getTable('create'),
      'button'       => $this->_getButton('edit',$req),
      'orderField'   => $this->_getOrderField('create'),
      'setting'      => $this->_getSetting('create',$r,$req)
    ]);
    
    return view($page,[
      'data'=>[
        'formUnitDate'=>$formUnitDate
      ]
    ]);
  }
//------------------------------------------------------------------------------    
  public function update($id,Request $req){
    $req->merge(['unit_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'orderField'  => $this->_getOrderField(__FUNCTION__,$req->all()),
      'includeCdate'=>0,
    ]);
    $vData  = $valid['dataNonArr'];
    $propId = $vData['prop'];
    $unitId = $vData['unit'];
    if(!empty($vData['prop']) && !empty($vData['unit'])){
      V::validateionDatabase(['mustExist'=>['unit|prop,unit']], $valid);
    } else if(!empty($vData['prop'])){
      V::validateionDatabase(['mustExist'=>['prop|prop']], $valid);
    }
    $success = $response = $elastic = [];
    
    $updateData = [T::$unitDate=>[
      'whereData'=>['unit'=>$unitId,'prop'=>$propId],
      'updateData'=>$vData
    ]];
    
    $isExist = M::hasRecord(T::$unitDate,['prop'=>$propId,'unit'=>$unitId]);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    
    try{
      $success += !empty($isExist) ? Model::update($updateData) : Model::insert([T::$unitDate=>$vData]);
      $elastic = [
        'insert'=>[T::$unitView=>['unit_id'=>[$id]]]
      ];
      
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success'=>$success,
        'elastic'=>$elastic
      ]);
    } catch (Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getTable($fn){
    $tablez = [
      'create'=>[T::$unitDate],
      'update'=>[T::$unitDate]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField = [
      'create'=>['date_code','unit','prop','last_date','next_date','amount','remark','vendor'],
      'update'=>['date_code','unit','prop','last_date','next_date','amount','remark','vendor']
    ];
    $perm = Helper::getPermission($req);
    $orderField['update'] = isset($perm['unitedit']) ? $orderField['update'] : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$default=[],$req=[]){
    $perm     = Helper::getPermission($req);
    $disabled = isset($perm['unitupdate']) ? [] : ['disabled'=>1];
    $setting = [
      'create' => [
        'field'=> [
          'unit_id'  => $disabled + ['type'=>'hidden'],
          'unit'     => $disabled + ['type'=>'hidden'],
          'prop'=>$disabled + ['type'=>'hidden'],
          'last_date'=>$disabled + ['value'=>Helper::usDate()],
          'next_date'=>$disabled + ['value'=>Helper::usDate()],
          'date_code'=>$disabled + ['label'=>'Code','value'=>''],
          'remark'   =>$disabled + ['value'=>''],
          'vendor'   =>$disabled,
          'code' => $disabled,
          'amount'=>$disabled,
        ]
      ],
    ];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $k != 'remark' ? $v : '';
      }
      
      if(isset($default['prop'])){
        $props = $default['prop'][0];
        foreach($props as $k=>$v){
          $setting[$fn]['field'][$k]['value'] = $v;
        }
      }
      
      if(isset($default['unit_date'])){
        $dates = $default['unit_date'][0];
        foreach($dates as $k=>$v){
          $setting[$fn]['field'][$k]['value'] = ($k === 'last_date' || $k === 'next_date') ? Format::usDate($v) : $v;
        }
      }
    }
    
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $button = [
      'edit' => ['submit'=>['id'=>'submit','value'=>'Update Features','class'=>'col-sm-12']]
    ];
    $perm           = Helper::getPermission($req);
    $button['edit'] = isset($perm['unitupdate']) ? $button['edit'] : '';
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Updated Unit\'s Dates.'),
    ];
    return $data[$name];
  }
}
