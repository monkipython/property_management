<?php
namespace App\Http\Controllers\PropertyManagement\Unit\UnitHist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V,Format, Form, Elastic, Html, Helper, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\UnitModel AS M; // Include the models class

class UnitHistController extends Controller {
  private $_viewPath  = 'app/PropertyManagement/Unit/unitHist/';
  private $_viewTable = '';
  private $_indexMain = '';
  private $_mapping   = [];
  
  public function __construct(Request $req){
    $this->_viewTable = T::$unitView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';   
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$unitHist]);
  }
//------------------------------------------------------------------------------    
  public function edit($id,Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['unit_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    
    $formUnitHist1 = Form::generateForm([
      'tablez'     => $this->_getTable('create'),
      'button'     => $this->_getButton(__FUNCTION__,$req),
      'orderField' => $this->_getOrderField('editUnitHist1'),
      'setting'    => $this->_getSetting('editUnitHist1',$r,$req),
    ]);
    
    $formUnitHist2 = Form::generateForm([
      'tablez'     => $this->_getTable('create'),
      'orderField' => $this->_getOrderField('editUnitHist2'),
      'setting'    => $this->_getSetting('editUnitHist2',$r,$req),
    ]);
    
    return view($page,[
      'data'=>[
        'formUnitHist1'=>$formUnitHist1,
        'formUnitHist2'=>$formUnitHist2,
      ]
    ]);
  }
//------------------------------------------------------------------------------    
  public function update($id,Request $req){   
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'orderField'  => $this->_getOrderField(__FUNCTION__,$req),
      'setting'     => $this->_getSetting(__FUNCTION__),
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
    $updateData = [T::$unitHist=>[
      'whereData'=>['unit'=>$unitId,'prop'=>$propId],
      'updateData'=>$vData
    ]];
    
    $isExist = M::hasRecord(T::$unitHist,['prop'=>$vData['prop'],'unit'=>$vData['unit']]);
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    try{
      
      $success += !empty($isExist) ? Model::update($updateData) : Model::insert([T::$unitHist=>$vData]);
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
      'create'=>[T::$unitHist],
      'update'=>[T::$unitHist]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField = [
      'create'=>['prop','unit','street','rent_rate','market_rent','sec_dep','move_out_date','status','bedrooms','bathrooms','sq_feet'],
      'editUnitHist1' => ['count_unit','prop','street','unit','remark','building','sq_feet','sq_feet2','floor','curr_tenant','past_tenant','future_tenant','owner','rent_rate','market_rent','sec_dep'],
      'editUnitHist2' => ['move_in_date','move_out_date','status','status2','unit_type','style','bedrooms','bathrooms','cd_enforce_dt1','cd_enforce_dt2','pad_size','mh_owner','must_pay','mh_serial_no'],
      'update'=>['prop','unit','street','rent_rate','market_rent','sec_dep','move_out_date','status','bedrooms','bathrooms','sq_feet'],
    ];
    $perm = Helper::getPermission($req);
    $orderField['update']  = isset($perm['unitedit']) ? array_merge($orderField['editUnitHist1'],$orderField['editUnitHist2']) : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$default=[],$req=[]){
    $perm     = Helper::getPermission($req);    
    $disabled = isset($perm['unitupdate']) ? [] : ['disabled'=>1];
    $setting = [
      'editUnitHist1' => [
        'field' => [
          'unit_id' => $disabled + ['type'=>'hidden'],
          'prop'    => $disabled + ['type'=>'hidden'],
          'unit'    => $disabled + ['type'=>'hidden'],
          'street'  => $disabled,
          'building'=> $disabled + ['req'=>0],
          'floor'   => $disabled,
          'curr_tenant'=> $disabled + ['label'=>'Current Tenant','value'=>'1'],
          'future_tenant'=>$disabled,
          'owner'   => $disabled,
          'rent_rate' => $disabled + ['label'=>'Current Rent'],
          'past_tenant' => $disabled,
          'market_rent' => ['label'=>'Future Rent'] + $disabled,
          'sec_dep'  => $disabled + ['label' => 'Deposit'],
          'sq_feet'  => $disabled + ['label' => 'Sq Ft.'],
          'sq_feet2' => $disabled + ['label' => 'Sq Ft. 2'],
          'count_unit' => $disabled + ['label' => 'Unit Count','type'=>'option','option'=>$this->_mapping['count_unit'],'value'=>'1'],
          'remark'   => $disabled,
          'mh_serial_no' => $disabled + ['label' => 'Moble Home Ser#'],
        ],
        'rule' => [
          'sq_feet' => 'nullable|numeric|between:0,10000000000',
          'sq_feet2'=> 'nullable|numeric|between:0,10000000000',
        ]
      ],
      'editUnitHist2' => [
        'field' => [
          'move_in_date' => $disabled + ['value'=>Helper::usDate()],
          'move_out_date' => $disabled + ['value'=>Helper::usDate()],
          'status' => $disabled + ['type'=>'option','option'=>$this->_mapping['status']],
          'status2'=> $disabled + ['type'=>'option','option'=>$this->_mapping['status2']],
          'unit_type'=> $disabled + ['type'=>'option','option'=>$this->_mapping['unit_type']],
          'style' => $disabled + ['label'=>'Story','type'=>'option','option'=>$this->_mapping['style'],'value'=>'1','req'=>0],
          'bedrooms' => $disabled + ['type'=>'option','option'=>$this->_mapping['bedrooms']],
          'bathrooms'=> $disabled + ['type'=>'option','option'=>$this->_mapping['bathrooms']],
          'cd_enforce_dt1' => $disabled + ['label'=>'Code Enforce Date 1','value'=>Helper::usDate()],
          'cd_enforce_dt2' => $disabled + ['label'=>'Code Enforce Date 2','value'=>Helper::usDate()],
          'pad_size'  => $disabled + ['label'=>'Pad Size (20x40)','req'=>0],
          'mh_owner'  => $disabled + ['label'=>'Ownership (T,P)','req'=>0],
          'must_pay'  => $disabled,
          'mh_serial_no' => $disabled + ['label'=>'Moble Home Ser#','req'=>0],
        ],
      ],
      'update' => [
        'rule' => [
          'sq_feet'   => 'nullable|numeric|between:0,10000000000',
          'sq_feet2'  => 'nullable|numeric|between:0,10000000000',
          'building'  => 'nullable|string|between:1,2',
          'style'     => 'nullable|string|between:1,6',
          'pad_size'  => 'nullable|string|between:1,12',
          'mh_owner'  => 'nullable|string',
          'mh_serial_no' => 'nullable|string',
        ]
      ]
    ];
    
    if(!empty($default)){
      $setting[$fn]['field']['unit']['value'] = $default['unit'];
      if(isset($default['prop'])){
        $props = $default['prop'][0];
        foreach($props as $k=>$v){
          $setting[$fn]['field'][$k]['value'] = $k != 'street' ? $v : '';
        }
      }
      
      if(isset($default['unit_hist'])){
        $histories = $default['unit_hist'][0];
        foreach($histories as $k=>$v){
          $setting[$fn]['field'][$k]['value']= $k === 'move_out_date' ? Format::usDate($v) : $v;
        }
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $button = [
      'edit' => ['submit'=>['id'=>'submit','value'=>'Update History','class'=>'col-sm-12']]
    ];
    
    $perm  = Helper::getPermission($req);
    $button['edit'] = isset($perm['unitupdate']) ? $button['edit'] : '';
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Updated Unit History.'),
    ];
    return $data[$name];
  }
}


