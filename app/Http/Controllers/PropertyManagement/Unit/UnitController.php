<?php
namespace App\Http\Controllers\PropertyManagement\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{Format, V, Form, Elastic, File, Html, Helper, GridData, Account, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\UnitModel AS M; // Include the models class

class UnitController extends Controller {
  private $_viewPath = 'app/PropertyManagement/Unit/unit/';
  private $_viewTable = '';
  private $_indexMain = '';
  private $_location  = [];
  private $_mapping   = [];
    
  public function __construct(Request $req){
    $this->_location  = File::getLocation(__CLASS__);
    $this->_viewTable = T::$unitView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?'; 
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$unit]);
    uasort($this->_mapping['unit_type'], [$this, '_sortByName']);
  }
//------------------------------------------------------------------------------    
  public function index(Request $req){
    $op       = isset($req['op']) ? $req['op'] : 'index';
    $page     = $this->_viewPath . 'index';
    $initData = $this->_getColumnButtonReportList($req); 
    switch($op){
      case 'column':
        return $initData;
      case 'report':
      case 'show':
        $vData = V::startValidate(['rawReq'=>$req->all(),'rule'=>GridData::getRule()])['data'];
        $vData['defaultSort'] = ['prop.prop.keyword:asc','unit.keyword:asc'];
        $qData = GridData::getQuery($vData,$this->_viewTable);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData, $req);
      default:
        return view($page,['data'=>[
          'nav'=>$req['NAV'],
          'account'=>Account::getHtmlInfo($req['ACCOUNT']),
          'initData'=>$initData,
        ]]);
    }
  }
//------------------------------------------------------------------------------    
  public function edit($id,Request $req){
    $page = $this->_viewPath . 'edit';
    $r = Elastic::searchMatch($this->_viewTable,['match' =>['unit_id' => $id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    
    $formUnit1 = Form::generateForm([
      'tablez'     => $this->_getTable(__FUNCTION__),
      'button'     => $this->_getButton(__FUNCTION__,$req),
      'orderField' => $this->_getOrderField('editUnit1'),
      'setting'    => $this->_getSetting('editUnit1',$r,$req),
    ]);
    
    $formUnit2 = Form::generateForm([
      'tablez'     => $this->_getTable(__FUNCTION__),
      'orderField' => $this->_getOrderField('editUnit2'),
      'setting'    => $this->_getSetting('editUnit2',$r,$req),
    ]);
    
    return ['html'=>view($page,[
      'data'=>[
        'formUnit1' => $formUnit1,
        'formUnit2' => $formUnit2,
      ]
    ])->render()];
  }
//------------------------------------------------------------------------------    
  public function update($id,Request $req){
    $req->merge(['unit_id'=>$id]);
    $insertData = [];
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'includeCdate'=>0,
      'includeUsid' =>1,
      //'orderField'  => $this->_getOrderField(__FUNCTION__,$req->all()),
      'setting'     => $this->_getSetting(__FUNCTION__),
      'validateDatabase' => [
          'mustExist'=>[
            'unit|unit_id',
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    ## Get propId to update unit_count in prop
    $propId = M::getPropId(Model::buildWhere(['prop'=>$vData['prop']]));
    $msgKey = count(array_keys($vData)) > 4 ? 'msg' : 'mainMsg';
    $updateData[T::$unit] = [
      'whereData'=>['unit_id'=>$id],
      'updateData'=>$vData
    ];
    
    if(isset($vData['rent_rate'])){
      // GET LAST TENANT 
      $rLastUnit = M::getLastUnit(Model::buildWhere(['unit_id'=>$vData['unit_id']]));
      $where     = Model::buildWhere(['prop'=>$vData['prop'], 'unit'=>$rLastUnit['unit'], 'tenant'=>$rLastUnit['curr_tenant']]);
      // GET THE LAST BILLING
      $rBilling = M::getLastBilling($where);
      $rTenant  = M::getTableData(T::$tenant, $where);
      
      if(!empty($rTenant)){
        $updateData[T::$tenant] = [
          'whereData'=>['tenant_id'=>$rTenant['tenant_id']],
          'updateData'=>['base_rent'=>$vData['rent_rate']]
        ];
        if(!empty($rBilling) && $rLastUnit['status'] == 'V' && $rTenant['status'] == 'P'){
          $updateData[T::$billing] = [
            'whereData'=>['billing_id'=>$rBilling['billing_id']],
            'updateData'=>['stop_date'=>date('Y-m-t')]
          ];
          $rBilling['start_date'] = date('Y-m-d', strtotime('first day of next month'));
          $rBilling['stop_date'] = '9999-12-31';
          $rBilling['amount'] = $vData['rent_rate'];
          $rBilling['billing_id'] = 0;
          $insertData = [T::$billing=>$rBilling];
        }
      }
    }
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{      
      $success += Model::update($updateData);
      if(!empty($insertData)){
        $success += Model::insert($insertData);
      }
      $elastic = [
        'insert'=>[
          T::$unitView => ['unit_id'=>[$id]],
          T::$propView => ['p.prop_id'=>[$propId]]
        ]
      ];
      if(isset($vData['rent_rate']) && !empty($rTenant['tenant_id'])){
        $elastic['insert'][T::$tenantView] = ['t.tenant_id'=>[$rTenant['tenant_id']]];
      }
      $response[$msgKey] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success'=>$success,
        'elastic'=>$elastic
      ]);
    } catch (Exception $e) {
      dd($e);
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
    
    $form1 = Form::generateForm([
      'tablez'     => $this->_getTable(__FUNCTION__),
      'button'     => $this->_getButton(__FUNCTION__),
      'orderField' => $this->_getOrderField('createUnit1'),
      'setting'    => $this->_getSetting('createUnit1'),
    ]);
    
    $form2 = Form::generateForm([
      'tablez'     => $this->_getTable(__FUNCTION__),
      'orderField' => $this->_getOrderField('createUnit2'),
      'setting'    => $this->_getSetting('createUnit2'),
    ]);
    
    return ['html'=>view($page,[
      'data' => [
        'unitCreateForm1' => $form1,
        'unitCreateForm2' => $form2,
        'nav'=>$req['NAV'],
        'account'=>Account::getHtmlInfo($req['ACCOUNT'])
      ]])->render()
    ];
  }
//------------------------------------------------------------------------------    
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq' => $req->all(),
      'tablez' => $this->_getTable(__FUNCTION__),
      'orderField'=>$this->_getOrderField(__FUNCTION__,$req),
      'setting'   =>$this->_getSetting('create'),
      'includeCdate' => 0,
      'includeUsid'  => 1,
      'validateDatabase' => [
        'mustExist' => [
          'prop|prop',
        ],
        'mustNotExist' => [
          'unit|prop,unit',
        ]
      ]
    ]);
    
    $vData = $valid['dataNonArr'];
    $success = $elastic = $response = [];
    $vData['owner'] = $vData['future_tenant'] = $vData['past_tenant'] = '255';
    
    ## Get propId to update unit_count in prop
    $propId = M::getPropId(Model::buildWhere(['prop'=>$vData['prop']]));
   
    ############### DATABASE SECTION ######################
    DB::beginTransaction();    
    try {
      $success += Model::insert([T::$unit=>$vData, T::$unitHist=>$vData]);
      $unitId   = $success['insert:' . T::$unit][0];

      $elastic = [
        'insert' => [
          T::$unitView => ['u.unit_id'=>[$unitId]],
          T::$propView => ['p.prop_id'=>[$propId]]
        ]
      ];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__,$vData);
      Model::commit([
        'success'=>$success,
        'elastic'=>$elastic,
      ]);
      
    } catch (\Exception $e) {
      Model::rollback($e);
      $response['error']['mainMsg'] = $this->_getErrorMsg('storeError',$vData);
    }
    
    return $response;
  }
//------------------------------------------------------------------------------
  public function destroy($id){    
    $valid = V::startValidate([
      'rawReq' => [
        'unit_id' => $id,
      ],
      'tablez' => $this->_getTable(__FUNCTION__),
      'validateDatabase' => [
        'mustExist' => [
          'unit|unit_id',
        ]
      ]
    ]);
    
    $vData = $valid['dataNonArr'];
    
    $r = M::getUnit(Model::buildWhere(['unit_id'=>$vData['unit_id']]));
    ## Get propId to update unit_count in prop
    $propId = M::getPropId(Model::buildWhere(['prop'=>$r['prop']]));
    
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $elastic = $response = $commit = [];
    try{
      $success[T::$unit]         = M::deleteTableData(T::$unit,Model::buildWhere(['unit_id'=>$vData['unit_id']]));
      $success[T::$unitHist]     = M::deleteTableData(T::$unitHist,Model::buildWhere(['prop'=>$r['prop'],'unit'=>$r['unit']]));
      $success[T::$unitFeatures] = M::deleteTableData(T::$unitFeatures,Model::buildWhere(['prop'=>$r['prop'],'unit'=>$r['unit']]));
      $success[T::$unitDate]     = M::deleteTableData(T::$unitDate,Model::buildWhere(['prop'=>$r['prop'],'unit'=>$r['unit']]));
      
      $success           = array_filter($success,function($v) use(&$success){return $v == T::$unit || $success[$v] != 0;},ARRAY_FILTER_USE_KEY);

      $commit['success'] = $success;
      $commit['elastic'] = [
        'insert'=>[T::$propView      => ['p.prop_id'=>[$propId]]],
        'delete'=>[$this->_viewTable => ['unit_id'=>$vData['unit_id']]]
      ];
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit($commit);
    } catch (\Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    
    return $response;
  }
#################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getTable($fn){
    $tablez = [
      'create' =>[T::$unit],
      'edit'   =>[T::$unit], 
      'store'  =>[T::$unit],
      'update' =>[T::$unit],
      'destroy'=>[T::$unit],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn,$req=[]){
    $orderField = [
      'editUnit1' => ['count_unit','unit_id','prop','street','unit','remark','building','sq_feet','sq_feet2','curr_tenant','past_tenant','future_tenant','owner','rent_rate','market_rent','late_charge','sec_dep'],
      'editUnit2' => ['move_in_date','move_out_date','status','unit_type','style','bedrooms','bathrooms','pad_size','unit_size','mh_owner','mh_serial_no','usid'],
      'create'=>['prop','unit','street','rent_rate','market_rent','sec_dep','move_in_date','move_out_date','status','bedrooms','bathrooms','sq_feet'],
      'createUnit1' => ['count_unit','prop','unit','street','curr_tenant','move_in_date','move_out_date','rent_rate','market_rent','late_charge','sec_dep'],
      'createUnit2' => ['remark','building','sq_feet','bedrooms','bathrooms','status','unit_type'],
      //'update'=>['unit_id','unit','street','rent_rate','market_rent','sec_dep','move_out_date','status','bedrooms','bathrooms','sq_feet'],
      //'store' =>['prop','unit','street','rent_rate','market_rent','sec_dep','move_in_date','move_out_date','status','bedrooms','bathrooms','sq_feet'],
    ];
    
    $perm = Helper::getPermission($req);
    $orderField['update'] = isset($perm['unitedit']) ? array_merge($orderField['editUnit1'],$orderField['editUnit2']) : [];
    $orderField['store']  = isset($perm['unitcreate']) ? array_merge($orderField['createUnit1'],$orderField['createUnit2']) : [];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn,$default=[],$req=[]){
    $rAccount = Helper::keyFieldName(M::getAccount([], 0), 'account_id', 'name');
    $rUnit = !empty($default) ? Helper::keyFieldName(M::getUnit(Model::buildWhere(['prop'=>$default['prop'][0]['prop']]), ['unit'], 0), 'unit', 'unit') : [''=>'Select Unit'];

    $perm = Helper::getPermission($req);
    $disabled = isset($perm['unitupdate']) ? [] : ['disabled'=>1];
    $setting = [
      'editUnit1' => [
        'field' => [
          'unit_id' => $disabled + ['type'=>'hidden'],
          'prop'    => $disabled + ['readonly'=>1, 'placeholder'=>'0001'],
          'street'  => $disabled + ['label'=>'Address'],
          'unit'    => $disabled + ['readonly'=>1, 'label'=>'Unit No.'],
          'building'=> $disabled + ['req'=>0],
          'curr_tenant' => ['disabled'=>1,'label'=>'Current Tenant'],
          'future_tenant' => $disabled,
          'owner'   => $disabled,
          'past_tenant'=>$disabled + ['readonly'=>1],
          'rent_rate' => $disabled + ['label'=>'Current Rent'],
          'market_rent' => ['label'=>'Future Rent'] + $disabled,
          'late_charge'=> $disabled,
          'sec_dep'   => $disabled + ['label'=>'Deposit'],
          'sq_feet'   => $disabled + ['label'=>'Sq Ft.'],
          'sq_feet2'  => $disabled + ['label'=>'Sq Ft. 2'],
          'count_unit' => $disabled + ['label'=>'Ready','type'=>'option','option'=>$this->_mapping['count_unit']],
          'remark'    => $disabled,
          
        ],
        'rule' => [
          'sq_feet'    => 'nullable|numeric|between:0,10000000000',
          'sq_feet2'   => 'nullable|numeric|between:0,10000000000',
        ]
      ],
      'editUnit2' => [
        'field' => [
          'move_in_date' => ['disabled'=>1,'value'=>Helper::usDate()],
          'move_out_date' => $disabled + ['readonly'=>1, 'value'=>Helper::usDate()],
          'status' => $disabled + ['readonly'=>1],
          'unit_type' => $disabled + ['type'=>'option','option'=>$this->_mapping['unit_type']],
          'style' => $disabled + ['label'=>'Story','value'=>'1','type'=>'option','option'=>$this->_mapping['style'],'req'=>0],
          'bedrooms'=>$disabled + ['type'=>'option','option'=>$this->_mapping['bedrooms']],
          'bathrooms'=>$disabled + ['type'=>'option','option'=>$this->_mapping['bathrooms']],
          'pad_size'       => $disabled + ['label'=>'Pad Size(20x40)','req'=>0],
          'mh_owner'       => $disabled + ['label'=>'Ownership(T,P)','type'=>'option','option'=>[''=>'Select Mobile Home Ownership'] + $this->_mapping['mh_owner'],'req'=>0],
          'mh_serial_no' => $disabled + ['label'=>'Moble Home Ser#','req'=>0],
          'usid'         => $disabled + ['readonly'=>1,'req'=>1],
        ],
      ],
      'update' => [
        'rule'=>[
          'sq_feet'      => 'nullable|numeric|between:0,10000000000',
          'building'     => 'nullable|string|between:1,2',
          'style'        => 'nullable|string|between:1,6',
          'pad_size'     => 'nullable|string|between:1,12',
          'mh_owner'     => 'nullable|string',
          'mh_serial_no' => 'nullable|string',
          'usid'         => 'nullable|string',
        ]
      ],
      'create' => [
        'field'=> [
          'prop'       => ['label' => 'Property','hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'street' => ['label'=>'Address'],
          'rent_rate'=>['label'=>'Rent'],
          'late_charge'=>['value'=>50],
          'sec_dep'=>['label'=>'Deposit'],
          'status'     => ['type'=>'option','option'=>$this->_mapping['status']],
          'unit_type'  => ['type'=>'option','option'=>$this->_mapping['unit_type']],
          'bedrooms'   => ['type'=>'option','option'=>$this->_mapping['bedrooms']],
          'bathrooms'  => ['type'=>'option','option'=>$this->_mapping['bedrooms']],
          'sq_feet'    => ['label' => 'Sq Ft.'],
        ],
        'rule' => [
          'sq_feet' => 'nullable|numeric|between:0,100000000'
        ]
      ],
      'createUnit1' => [
        'field' => [
          'prop'       => ['label' => 'Property','hint'=>'You can type property address or number or trust for autocomplete', 'class'=>'autocomplete', 'autocomplete'=>'false'],
          'unit'       => ['label' => 'Unit No.','placeholder'=>'0001'],
          'curr_tenant'=> ['label' => 'Current Tenant','value'=>'1','readonly'=>'1'],
          'move_in_date' => ['value'=>Helper::usDate()],
          'move_out_date' => ['value'=>Helper::usDate()],
          'rent_rate'  => ['label'=>'Current Rent'],
          'market_rent'=> ['label'=>'Future Rent'],
          'sec_dep'    => ['label'=>'Deposit'], 
          'count_unit' => ['type'=>'option','option'=>$this->_mapping['count_unit'],'label'=>'Ready','value'=>'1'],
        ]
      ],
      'createUnit2' => [
        'field' => [
          'building'   => ['value' => '1'],
          'sq_feet'    => ['label'=>'Sq Ft.','value'=>'1'],
          'status' => ['type'=>'option','option'=>$this->_mapping['status'],'value'=>'V'],
          'unit_type'=>['type'=>'option','option'=>$this->_mapping['unit_type']],
          'bedrooms'=>['type'=>'option','option'=>$this->_mapping['bedrooms']],
          'bathrooms'=>['type'=>'option','option'=>$this->_mapping['bathrooms']],
          
        ],
        'rule' => [
          'sq_feet' => 'nullable|numeric|between:0,100000000', 
        ]
      ],
      'store' => [
        'rule' => [
          'sq_feet' => 'nullable|numeric|between:0,100000000',
          'sq_feet2'=> 'nullable|numeric|between:0,100000000',
        ]
      ]
    ];
    
    
    if(!empty($default)){
      foreach($default as $k=>$v){
        if(is_array($v)){
          $setting[$fn]['field'][$k]['value'] = isset($v[0][$k]) ? $v[0][$k] : '';
        } else if($k === 'move_in_date' || $k === 'move_out_date'){
          $setting[$fn]['field'][$k]['value'] = Format::usDate($v);
        } else {
          $setting[$fn]['field'][$k]['value'] = $v;
        }
      }
    }
    return $setting[$fn];  
  }
//------------------------------------------------------------------------------
  private function _getButton($fn,$req=[]){
    $button = [
      'edit'   => ['submit'=>['id'=>'submit','value'=>'Update','class'=>'col-sm-12']],
      'create' => ['submit' => ['id'=>'submit','value'=>'Add Unit','class'=>'col-sm-12']]
    ];
    
    $perm           = Helper::getPermission($req);
    $button['edit'] = isset($perm['unitupdate']) ? $button['edit'] : '';
    return $button[$fn];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm     = Helper::getPermission($req);
    $editUnit = isset($perm['unitupdate']) ? ['editable'=>['type'=>'text']] : [];
    $bathroomMapping = json_encode($this->_mapping['bathrooms']);

    $selectKeys = ['bathrooms','bedrooms','status', 'unit_type'];
    $reportList = isset($perm['unitExport']) ? ['csv'=>'Export to CSV'] : [];
    $selectSources = [];
    
    foreach($selectKeys as $v){
      $selectSources[$v] = isset($perm['unitupdate']) ? ['editable'=>['type'=>'select','source'=>$this->_mapping[$v]]] : [];
    }

    $selectSources['status'] = !empty($selectSources['status']) ? $selectSources['status'] : [
      'formatter' => 'statusFormatter',
    ];
 
    $columns = [
      ['field'=>'num','title'=>'#','width'=>25],
      ['field'=>'action', 'title'=>'Action', 'events'=> 'operateEvents', 'width'=> 125],
      ['field'=>'prop.group1','title'=>'Group','filterControl'=>'input','sortable'=>true,'width'=>50],
      ['field'=>'prop.prop','title'=>'Prop','filterControl'=>'input','sortable'=>true,'width'=>50],
      ['field'=>'unit','title'=>'Unit #','filterControl'=>'input','sortable'=>true,'width'=>50],
      ['field'=>'curr_tenant','title'=>'Cnt Tenant','sortable'=> true, 'filterControl'=> 'input','width'=>50],
      ['field'=>'status','title'=>'Status','sortable'=> true, 'filterControl'=> 'input','width'=>100],
      ['field'=>'street','title'=>'Address','sortable'=> true, 'filterControl'=> 'input','width'=>250] + $editUnit,
      ['field'=>'prop.city','title'=>'City','filterControl'=>'input','sortable'=>true,'width'=>150],
      ['field'=>'rent_rate','title'=>'Current Rent','sortable'=> true, 'filterControl'=> 'input','width'=>75] + $editUnit,
      ['field'=>'market_rent','title'=>'Future Rent','sortable'=> true, 'filterControl'=> 'input','width'=>75] + $editUnit,
      ['field'=>'late_charge','title'=>'Late Charge','sortable'=>true,'filterControl'=>'input','width'=>75] + $editUnit,
      ['field'=>'sec_dep','title'=>'Deposit','sortable'=> true, 'filterControl'=> 'input','width'=>75] + $editUnit,
      ['field'=>'move_in_date','title'=>'Move In Date','sortable'=> true, 'filterControl'=> 'input','width'=>25,'filterControlPlaceholder'=>'yyyy-mm-dd'],
      ['field'=>'move_out_date','title'=>'Move Out Date','sortable'=> true, 'filterControl'=> 'input','width'=>25,'filterControlPlaceholder'=>'yyyy-mm-dd'],
      ['field'=>'unit_type','title'=>'Unit Type','sortable'=>true,'filterControl'=>'select','filterData'=>'url:/filter/unit_type:' . T::$unitView,'width'=>70] + $selectSources['unit_type'],
      ['field'=>'bedrooms','title'=>'Bedrooms','sortable'=>true,'filterControl'=>'select','filterData'=>'json:' . json_encode($this->_mapping['bedrooms']),'width'=>25] + $selectSources['bedrooms'],
      ['field'=>'bathrooms','title'=>'Bathrooms','sortable'=>true,'filterControl'=>'select','filterData'=>'json:' . $bathroomMapping,'width'=>25] + $selectSources['bathrooms'],
      ['field'=>'sq_feet','title'=>'Sq Ft.','sortable'=> true, 'filterControl'=> 'input', 'width'=>25] + $editUnit,
      ['field'=>'count_unit','title'=>'Livable','sortable'=>true,'filterControl'=>'select','filterData'=>'json:' . json_encode($this->_mapping['count_unit'],JSON_FORCE_OBJECT)],  
    ];
    
    $button = isset($perm['unitcreate']) ? Html::a(Html::button(Html::i('',['class'=>'fa fa-fw fa-plus-square']) . '&nbsp;New',['id'=>'new','class'=>'btn btn-success']),['style'=>'color:#fff;'])  : '';
    return ['columns'=>$columns,'reportList'=>$reportList,'button'=>$button];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r,$vData,$qData,$req){
      $rows = [];
      $dateColumns = ['move_in_date','move_out_date','prop.start_date','prop.tax_date','prop.man_fee_date','prop.last_raise_date'];
      $actionData = $this->_getActionData($req);
      
      foreach($r['hits']['hits'] as $i=>$v){
        $source = $v['_source'];
        $id            = $source['unit_id'];
        $source['num'] = $vData['offset'] + $i + 1;
        $source['action'] = implode(' ',$actionData['icon']);
        
        $source['rent_rate']   = Format::usMoney($source['rent_rate']);
        $source['market_rent'] = Format::usMoney($source['market_rent']);
        $source['late_charge'] = Format::usMoney($source['late_charge']);
        $source['sec_dep']     = Format::usMoney($source['sec_dep']);
        $source['sq_feet']     = Format::intNumberSeperate($source['sq_feet']);
        $source['status']      = $this->_mapping['status'][$source['status']];
        $source['count_unit']  = isset($perm['unitupdate']) ? $source['count_unit'] : $this->_mapping['count_unit'][$source['count_unit']];
        $source['isEditable']  = false; 
        
        foreach($source[T::$prop] as $i=>$v){
          foreach($v as $k=>$val){
            $source[T::$prop . '.' .$k] = isset($source[T::$prop . '.' . $k]) ? $source[T::$prop . '.' . $k] . $val : $val;
          }
        }
        
        $source[T::$prop . '.prop'] = Html::a($source[T::$prop . '.prop'],['href'=>action('PropertyManagement\Prop\PropController@index',['prop'=>$source[T::$prop . '.prop']]),'target'=>'_blank','title'=>'View Prop']);

        $source['curr_tenant'] = Html::a($source['curr_tenant'],['href'=>action('PropertyManagement\Tenant\TenantController@index',['prop'=>$source[T::$prop . '.prop'],'unit'=>$source['unit'],'tenant'=>$source['curr_tenant']]),'target'=>'_blank','title'=>'View Tenant']);
        
        foreach($dateColumns as $c){
          $dateVal    = $source[$c];
          $source[$c] = date('Y-m-d',strtotime($dateVal));
        }
        unset($source['prop']);       
        $rows[] = $source;
      }
      return ['rows'=>$rows,'total'=>$r['hits']['total']];
  }
//------------------------------------------------------------------------------
  private function _getErrorMsg($name,$vData=[]){
    $data = [
      'storeError'  =>Html::sucMsg((!empty($vData)) ? 'Error Adding Unit: ' . $vData['unit'] . ' to Property ' . $vData['prop'] : 'Error Adding Unit'),
      'tenantAlert' =>Html::errMsg('There is a problem. Please contact administration!'),
      'mysqlError'  =>Html::mysqlError(),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getSuccessMsg($name,$vData=[]){
    $data = [
      'update'  =>Html::sucMsg('Successfully Update.'),
      'store'   =>Html::sucMsg((!empty($vData)) ? 'Successfully Added Unit: ' . $vData['unit'] . ' to Property ' . $vData['prop'] : 'Successfully Added Unit '),
      'destroy'  =>Html::sucMsg('Successfully Deleted Unit'),
    ];
    return $data[$name];
  }
//------------------------------------------------------------------------------
  private function _getActionData($req){
    $perm = Helper::getPermission($req);
    $data = [];
    if(isset($perm['unitedit'])){
      $data[] = '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer tip" title="Edit Unit Information"></i></a> ';
      $data[] = '<a class="editHist" href="javascript:void(0)" title="Edit History"><i class="fa fa-archive text-aqua pointer tip" title="Edit Unit History"></i></a> ';
      $data[] = '<a class="editFeatures" href="javascript:void(0)" title="Edit Features"><i class="fa fa-home text-aqua pointer tip" title="Edit Unit Features"></i></a> ';
      $data[] = '<a class="editDate" href="javascript:void(0)" title="Edit Dates"><i class="fa fa-calendar text-aqua pointer tip" title="Edit Unit Dates"></i></a> ';
    }
    
    if(isset($perm['unitdestroy'])){
      $data[] = '<a class="delete" href="javascript:void(0)" title="Delete Unit"><i class="fa fa-trash-o text-red pointer tip" title="Delete this Unit"></i></a> ';
    }

    $num = count($data);
    return ['icon'=>$data,'width'=>$num * 42];
  }
//------------------------------------------------------------------------------
  private function _sortByName($a, $b) {
    return strcmp($a, $b);
  }
}
