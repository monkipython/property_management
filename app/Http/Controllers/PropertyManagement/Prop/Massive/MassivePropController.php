<?php
namespace App\Http\Controllers\PropertyManagement\Prop\Massive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Html, Helper, TableName AS T};
use App\Http\Models\Model; // Include the models class

class MassivePropController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Prop/prop/';
  private $_viewPathMassive = 'app/PropertyManagement/Prop/massive/';
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(Request $req){
    $this->_mappingProp   = Helper::getMapping(['tableName'=>T::$prop . '_massive']);
    $this->_viewTable = T::$propView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page = $this->_viewPathMassive . 'create';
    
    $formProp = Form::generateForm([
      'tablez'    =>$this->_getTable('update'), 
      'orderField'=>$this->_getOrderField('editProperties'), 
      'setting'   =>$this->_getSetting('updateProperties'),
      'button'    =>$this->_getButton(__FUNCTION__),
    ]);
    return view($page, [
      'data'=>[
        'formProp' => $formProp
      ]
    ]);
  }
  //------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable('update'),
      'setting'     => $this->_getSetting('updateProperties'),
      'includeCdate'=> 0
    ]);
    $vData       = $valid['dataNonArr'];
    $explodeProp = Helper::explodeProp($vData['prop']);
    $propIds     = $explodeProp['propId'];
    $rProps      = $explodeProp['prop'];
    unset($vData['prop']);
 
    # DELETE ALL THE EMPTY VALUE
    foreach($vData as $k=>$v){
      if(empty($vData[$k])){
        unset($vData[$k]);
      }
    }
    if(empty($vData)){
      Helper::echoJsonError($this->_getErrorMsg(__FUNCTION__));
    }
    $vData['usid'] = Helper::getUsid($req);
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData = [
      T::$prop=>[
        'whereInData'=>['field'=>'prop', 'data'=>$rProps],
        'updateData'=>$vData
      ],
    ];
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['p.prop_id'=>$propIds]]
      ];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
    
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic
      ]);
    } catch(\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
  private function _getTable($fn){
    $tablez = [
      'create' => [T::$prop],
      'update' => [T::$prop]
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn){
    $orderField = [
      'editProperties' => ['prop', 'ap_bank', 'ar_bank', 'prop_class', 'prop_type', 'mangtgroup', 'mangtgroup2', 'mangt_pct']
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $setting = [
      'updateProperties' => [
        'field' => [
          'prop'            => ['label'=>'Property Numbers', 'type'=>'textarea', 'placeholder'=>'e.g: 0001, 0002 OR 0005-0150'],
          'ap_bank'         => ['label'=>'AP Default Bank', 'req'=>0],
          'ar_bank'         => ['label'=>'AR Default Bank', 'req'=>0],
          'prop_class'      => ['label'=>'Property Class', 'type'=>'option', 'option'=>$this->_mappingProp['prop_class'], 'req'=>0],
          'prop_type'       => ['label'=>'Property Type', 'type'=>'option', 'option'=>$this->_mappingProp['prop_type'], 'req'=>0],
          'mangtgroup'      => ['label'=>'Mgt Group', 'req'=>0],
          'mangtgroup2'     => ['label'=>'Mgt Group 2', 'req'=>0],  
          'mangt_pct'       => ['label'=>'Mgt %', 'req'=>0, 'class'=>'']
        ],
        'rule' => [
          'prop' =>'required|string|between:4,1000',
          'ap_bank' =>'nullable|between:1,2',
          'ar_bank' =>'nullable|between:1,2',
          'prop_class' =>'nullable',
          'prop_type' =>'nullable',
          'mangtgroup' =>'nullable',
          'mangtgroup2' =>'nullable',
          'mangt_pct' =>'nullable',
        ]
      ]
    ];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] = $v;
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn){
    $buttton = [
      'create'  =>['submit'=>['id'=>'submit', 'value'=>'Update', 'class'=>'col-sm-12']]
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'  =>Html::sucMsg('Successfully Created.'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name){
    $data = [
      'store' =>Html::errMsg('All the fields below property field cannot be empty. At least one field is required.'),
    ];
    return $data[$name];
  }
}
