<?php
namespace App\Http\Controllers\PropertyManagement\Unit\UnitFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{ V, Form, Elastic, Html, Helper, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\UnitModel AS M; // Include the models class

class UnitFeatureController extends Controller {
  private $_viewPath = 'app/PropertyManagement/Unit/unitFeature/';
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
    
    $formUnitFeatureGeneral = Form::generateForm([
      'tablez'         => $this->_getTable('create'),
      'button'         => $this->_getButton('edit',$req),
      'orderField'     => $this->_getOrderField('editUnitFeature1'),
      'setting'        => $this->_getSetting('create',$r,$req)
    ]);
    
    $formUnitFeatureFurnishing = Form::generateForm([
      'tablez'         => $this->_getTable('create'),
      'orderField'     => $this->_getOrderField('editUnitFeature2'),
      'setting'        => $this->_getSetting('create',$r,$req)
    ]);
   
    return view($page,[
      'data'=>[
        'formUnitFeature1'=>$formUnitFeatureGeneral,
        'formUnitFeature2'=>$formUnitFeatureFurnishing
      ]
    ]);
  }
//------------------------------------------------------------------------------    
  public function update($id,Request $req){
    $req->merge(['unit_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__),
      'orderField'  => $this->_getOrderField(__FUNCTION__,$req),
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
    
    $updateData = $success = $response = $elastic = [];
    $updateData = [T::$unitFeatures=>[
      'whereData'=>['unit'=>$unitId,'prop'=>$propId],
      'updateData'=>$vData
    ]];
    
    $isExist = M::hasRecord(T::$unitFeatures,['unit'=>$unitId,'prop'=>$propId]);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    
    try{
      $success += !empty($isExist) ? Model::update($updateData) : Model::insert([T::$unitFeatures => $vData]);
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
      'create'=>[T::$unitFeatures],
      'update'=>[T::$unitFeatures]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField = [
      'create'=>['unit','prop','persons','furnished','pets','stove','refrigerator','parking_spaces','desirability','other','total_rooms','microwave','dishwasher','washer_dryer','garbage_disposal','fireplace','den_study','security','carpet_color','mini_blinds','bay_window','carport_garage','wet_bar','ceiling_fan','ice_maker','walk_in_closet','enclosed_patio','pool_jacuzzi'],
      'editUnitFeature1' => ['unit','prop','persons','furnished','pets','parking_spaces','desirability','total_rooms','carport_garage','wet_bar','security','enclosed_patio','pool_jacuzzi','den_study','other'],
      'editUnitFeature2' => ['stove','refrigerator','microwave','dishwasher','washer_dryer','garbage_disposal','fireplace','carpet_color','mini_blinds','bay_window','ceiling_fan','ice_maker','walk_in_closet'],
    ];
    
    $perm = Helper::getPermission($req);
    $orderField['update'] = isset($perm['unitedit']) ? array_merge($orderField['editUnitFeature1'],$orderField['editUnitFeature2']) : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$default=[],$req=[]){
    $rAccount = Helper::keyFieldName(M::getAccount([], 0), 'account_id', 'name');
    $yesNo = [''=>'N/A','N'=>'No', 'Y'=>'Yes'];
    $perm  = Helper::getPermission($req);
    $disabled = isset($perm['unitupdate']) ? [] : ['disabled'=>1];
    $setting = [
      'create' => [
        'field'=> [
          'unit_id'  => $disabled + ['type'=>'hidden'],
          'unit'     => $disabled + ['type'=>'hidden'],
          'prop'     => $disabled + ['type'=>'hidden'],
          'persons'  => $disabled + ['type'=>'number'],
          'furnished'=> $disabled + ['type'=>'option','option'=>$yesNo],
          'stove'    => $disabled + ['type'=>'option','option'=>$yesNo],
          'refrigerator' => $disabled + ['type'=>'option','option'=>$yesNo],
          'pets'     => $disabled,
          'desirability' => $disabled,
          'other'    => $disabled,
          'carpet_color'=>$disabled,
          'parking_spaces' => $disabled + ['type'=>'number'],
          'total_rooms'  => $disabled + ['label'=>'Rooms','type'=>'number'],
          'microwave'    => $disabled + ['type'=>'option','option'=>$yesNo],
          'dishwasher'   => $disabled + ['type'=>'option','option'=>$yesNo],
          'washer_dryer' => $disabled + ['type'=>'option','option'=>$yesNo],
          'garbage_disposal' => $disabled + ['type'=>'option','option'=>$yesNo],
          'fireplace'    => $disabled + ['type'=>'option','option'=>$yesNo],
          'den_study'    => $disabled + ['label'=>'Den','type'=>'option','option'=>$yesNo],
          'security'     => $disabled + ['type'=>'option','option'=>$yesNo],
          'mini_blinds'  => $disabled + ['type'=>'option','option'=>$yesNo],
          'bay_window'   => $disabled + ['type'=>'option','option'=>$yesNo],
          'carport_garage'=> $disabled + ['type'=>'option','option'=>$yesNo],
          'wet_bar'      => $disabled + ['type'=>'option','option'=>$yesNo],
          'ceiling_fan'  => $disabled + ['type'=>'option','option'=>$yesNo],
          'ice_maker'    => $disabled + ['type'=>'option','option'=>$yesNo],
          'walk_in_closet'=>$disabled + ['label'=>'Closet','type'=>'option','option'=>$yesNo],
          'enclosed_patio'=> $disabled + ['type'=>'option','option'=>$yesNo],
          'pool_jacuzzi'  => $disabled + ['label'=>'Pool','type'=>'option','option'=>$yesNo]
        ],
        'rule'=>[
          'persons'  => 'nullable|integer',
          'pets'     => 'nullable|integer',
          'furnished'=> 'nullable',
          'stove'    => 'nullable',
          'refrigerator' => 'nullable',
          'parking_spaces' => 'nullable|integer',
          'desirability' => 'nullable',
          'other'        => 'nullable',
          'total_rooms'  => 'nullable|integer',
          'microwave'    => 'nullable',
          'dishwasher'   => 'nullable',
          'washer_dryer' => 'nullable',
          'garbage_disposal' => 'nullable',
          'fireplace'     => 'nullable',
          'den_study'     => 'nullable',
          'security'      => 'nullable',
          'carpet_color'  => 'nullable',
          'mini_blinds'   => 'nullable',
          'bay_window'    => 'nullable',
          'carport_garage'=> 'nullable',
          'wet_bar'       => 'nullable',
          'ceiling_fan'   => 'nullable',
          'ice_maker'     => 'nullable',
          'walk_in_closet'=> 'nullable',
          'enclosed_patio'=> 'nullable',
          'pool_jacuzzi'  => 'nullable'
        ]
      ],
      'update' => [
        'rule'=>[
          'persons'  => 'nullable|integer',
          'pets'     => 'nullable|integer',
          'furnished'=> 'nullable',
          'stove'    => 'nullable',
          'refrigerator' => 'nullable',
          'parking_spaces' => 'nullable|integer',
          'desirability' => 'nullable',
          'other'        => 'nullable',
          'total_rooms'  => 'nullable|integer',
          'microwave'    => 'nullable',
          'dishwasher'   => 'nullable',
          'washer_dryer' => 'nullable',
          'garbage_disposal' => 'nullable',
          'fireplace'     => 'nullable',
          'den_study'     => 'nullable',
          'security'      => 'nullable',
          'carpet_color'  => 'nullable',
          'mini_blinds'   => 'nullable',
          'bay_window'    => 'nullable',
          'carport_garage'=> 'nullable',
          'wet_bar'       => 'nullable',
          'ceiling_fan'   => 'nullable',
          'ice_maker'     => 'nullable',
          'walk_in_closet'=> 'nullable',
          'enclosed_patio'=> 'nullable',
          'pool_jacuzzi'  => 'nullable'
        ]
      ]
    ];
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
      
      if(isset($default['prop'])){
        $props = $default['prop'][0];
        foreach($props as $k=>$v){
          $setting[$fn]['field'][$k]['value'] = $v;
        }
      }
      
      if(isset($default['unit_features'])){
        $features = $default['unit_features'][0];
        foreach($features as $k=>$v){
          $setting[$fn]['field'][$k]['value']=$v;
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
    
    $perm   = Helper::getPermission($req);
    $button['edit'] = isset($perm['unitupdate']) ? $button['edit'] : '';
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Updated Unit\'s Features.'),
    ];
    return $data[$name];
  }
}


