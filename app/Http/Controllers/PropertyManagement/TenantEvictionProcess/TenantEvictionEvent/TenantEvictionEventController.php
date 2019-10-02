<?php
namespace App\Http\Controllers\PropertyManagement\TenantEvictionProcess\TenantEvictionEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{Format, V, Form, Html, Helper, HelperMysql, Elastic, Upload, TableName AS T};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class

class TenantEvictionEventController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/TenantEvictionProcess/tenantEvictionEvent/';
  private $_viewTable = '';
  
  public function __construct(Request $req){
    $this->_viewTable    = T::$tntEvictionProcessView;
    $this->_mappingEvent = Helper::getMapping(['tableName'=>T::$tntEvictionEvent]);
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $page = $this->_viewPath . 'edit';
    $perm = Helper::getPermission($req);
    $valid = V::startValidate([
      'rawReq'          => ['tnt_eviction_process_id'=>$id],
      'tablez'          => [T::$tntEvictionProcess],
      'orderField'      => ['tnt_eviction_process_id'],
      'validateDatabase'=> [
        'mustExist'=>[
          T::$tntEvictionProcess . '|tnt_eviction_process_id' 
        ]
      ]
    ]);
    $r = Helper::getElasticResult(Elastic::searchQuery([
      'index'   =>T::$tntEvictionProcessView,
      '_source' =>['tnt_eviction_event'],
      'query'   =>['must'=>['tnt_eviction_process_id'=>$id]]
    ]), 1);
    $rTntEvictionProcess = !empty($r) ? $r['_source'] : [];
    $rTntEvictionEvent   = $rTntEvictionProcess['tnt_eviction_event'];
   
    $date_compare = function ($a, $b)
    {
      $t1 = strtotime($a['date']);
      $t2 = strtotime($b['date']);
      $compare = $t2 - $t1; 
      if($compare == 0) {
        return $b['tnt_eviction_event_id'] - $a['tnt_eviction_event_id'];
      }else {
        return $compare;
      }
    };    
    usort($rTntEvictionEvent, $date_compare);
    $li = $date = '';
    $eventId = [];
    foreach($rTntEvictionEvent as $i => $v) {
      $dateString = strtotime($v['date']);
      if($date != $dateString) {
        $dateColor = $v['status'] == 0 ? 'bg-red' : ($v['status'] == 1 ? 'bg-green' : 'bg-yellow');
        $span = Html::span(date('d M.Y', $dateString), ['class'=>$dateColor]);
        $li .= Html::li($span, ['class'=>'time-label']);
      }
      $date = empty($date) || $date != $dateString ? $dateString : $date;
      $evictionEventData = [
        'tnt_eviction_process_id' => $id,
        'tnt_eviction_event_id'   => $v['tnt_eviction_event_id'],
        'subject'                 => $v['subject'],
        'remark'                  => $v['remark'],
        'status'                  => $v['status'],
        'tenant_attorney'         => $v['tenant_attorney'],
        'date'    => Format::usDate($v['date'])
      ];
      ## Form Container
      $evictionEventForm = Form::generateForm([
        'tablez'    =>$this->_getTable(__FUNCTION__),
        'orderField'=>$this->_getOrderField(__FUNCTION__), 
        'setting'   =>$this->_getSetting(__FUNCTION__, $evictionEventData),
        'button'    =>$this->_getButton(__FUNCTION__, $req),
      ]);
      $evictionEventForm = Html::div($evictionEventForm, ['class'=>'col-sm-4']);
      
      ##Get Files 
      $evictionEventFiles = [];
      $fileUpload = !empty($v['fileUpload']) ? $v['fileUpload'] : [];
      foreach($fileUpload as $value){
        $evictionEventFiles[] = $value;
      }
      $fileUploadList = !empty($evictionEventFiles) ? Upload::getViewlistFile($evictionEventFiles, '/uploadTenantEvictionEvent', $v['tnt_eviction_event_id']) : '';
      ## Upload Container
      $upload = Upload::getHtml('evictionEventUpload'.$v['tnt_eviction_event_id']);
      $upload = Html::div($upload['container'] . $upload['hiddenForm'] . $fileUploadList, ['class'=>'col-sm-8']);
      
      $accordion = Html::buildAccordion([$v['subject'] . ' by ' . $v['usid'] => $evictionEventForm . $upload], 'evictionEvent'.$i, ['class'=>'box-primary'], 1);       

      $hourglassIcon = $v['status'] == 0 ? 'hourglass-o bg-red' : ($v['status'] == 1 ? 'hourglass bg-green' : 'hourglass-2 bg-yellow');
      $icon = Html::i('',['class'=>'fa fa-'.$hourglassIcon]);
      
      $accordionForm = Html::form($accordion, ['id'=>'tntEvictionEventForm'.$v['tnt_eviction_event_id'], 'class'=>'form-horizontal', 'autocomplete'=>'off']);
      $timelineItem = Html::div($accordionForm, ['class'=>'timeline-item']);
      $li .= Html::li($icon . $timelineItem);
      $eventId[] = $v['tnt_eviction_event_id'];
    }
    $startIcon = Html::i('',['class'=>'fa fa-clock-o bg-gray']);
    $li .= Html::li($startIcon);
    $evictionEvent = Html::ul($li, ['class'=>'timeline timeline-inverse']);
    
    
    ### BUTTON SECTION ###
    $_getButtons = function($perm){
      $button = '';
      if(isset($perm['tenantEvictionEventcreate'])) {
        $button .= Html::button(Html::i('', ['class'=>'fa fa-fw fa-plus-square']) . ' New', ['id'=> 'new', 'class'=>'btn bg-teal']) . ' ';
      }
      return $button;
    };
    return [
      'html'=>view($page, [ 'data'=>[
        'evictionEvent' => $evictionEvent,
        'button' => $_getButtons($perm),
      ]])->render(),
      'eventId'=> $eventId
    ];
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
    $req->merge(['tnt_eviction_event_id'=>$id]);
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => $this->_getTable(__FUNCTION__),
      'setting'     => $this->_getSetting(__FUNCTION__, $req),
  //    'orderField'  => $this->_getOrderField(__FUNCTION__, $req->all()),
      'includeCdate'=> 0,
      'includeUsid' => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tntEvictionEvent.'|tnt_eviction_event_id'
        ]
      ]
    ]);
    $vData  = $valid['dataNonArr'];
    # PREPARE THE DATA FOR UPDATE AND INSERT
    $updateData[T::$tntEvictionEvent] = ['whereData'=>['tnt_eviction_event_id'=>$id],'updateData'=>$vData];
    
    ## Change tnt_eviction_process process_status to 1 if tnt_eviction_event status has 1
    if($vData['status'] == 1) {
      $updateData[T::$tntEvictionProcess] = ['whereData'=>['tnt_eviction_process_id'=>$vData['tnt_eviction_process_id']],'updateData'=>['process_status'=>1]];
    }else {
      $r = HelperMysql::getTableData(T::$tntEvictionEvent, Model::buildWhere(['tnt_eviction_process_id'=>$vData['tnt_eviction_process_id'],'status'=>1]), 'tnt_eviction_event_id', 1);
      if(!empty($r) && $r['tnt_eviction_event_id'] == $id) {
        $updateData[T::$tntEvictionProcess] = ['whereData'=>['tnt_eviction_process_id'=>$vData['tnt_eviction_process_id']],'updateData'=>['process_status'=>0]];  
      }
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[$this->_viewTable=>['ep.tnt_eviction_process_id'=>[$vData['tnt_eviction_process_id']]]]
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
//------------------------------------------------------------------------------
  public function create(Request $req) {
    $page = $this->_viewPath . 'create';
   
    $formEvictionEvent = Form::generateForm([
      'tablez'    =>$this->_getTable(__FUNCTION__), 
      'orderField'=>$this->_getOrderField(__FUNCTION__), 
      'setting'   =>$this->_getSetting(__FUNCTION__, $req),
      'button'    =>$this->_getButton(__FUNCTION__, $req),
    ]);
    
    return view($page, [
      'data'=>[
        'formEvictionEvent' => $formEvictionEvent,
        'upload'            => Upload::getHtml()
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid = V::startValidate([
      'rawReq'          => $req->all(),
      'tablez'          => $this->_getTable(__FUNCTION__), 
      'orderField'      => $this->_getOrderField('create', $req),
      'setting'         => $this->_getSetting('create', $req), 
      'includeUsid'     => 1,
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tntEvictionProcess.'|tnt_eviction_process_id'
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];
    $uuid = !empty($vData['uuid']) ? explode(',', (rtrim($vData['uuid'], ','))) : [];
    unset($vData['uuid']);
    $response = $success = $elastic = $updateDataSet = [];
    if($vData['status'] == 1) {
      $updateDataSet[T::$tntEvictionProcess] = ['whereData'=>['tnt_eviction_process_id'=>$vData['tnt_eviction_process_id']],'updateData'=>['process_status'=>1]];
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    try{
      $success += Model::insert([T::$tntEvictionEvent=>$vData]);
      if(!empty($uuid) || !empty($updateDataSet)) {
        foreach($uuid as $v){
          $updateDataSet[T::$fileUpload][] = ['whereData'=>['uuid'=>$v], 'updateData'=>['foreign_id'=>$success['insert:'.T::$tntEvictionEvent][0]]];
        }
        $success += Model::update($updateDataSet);
      }
      $elastic = [
        'insert'=>[$this->_viewTable=>['ep.tnt_eviction_process_id'=>[$vData['tnt_eviction_process_id']]]]
      ];
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
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
      'edit'  => [T::$tntEvictionEvent, T::$tntEvictionProcess],
      'update'=> [T::$tntEvictionEvent, T::$tntEvictionProcess],
      'create'=> [T::$tntEvictionEvent, T::$tntEvictionProcess, T::$fileUpload],
      'store' => [T::$tntEvictionEvent, T::$tntEvictionProcess, T::$fileUpload],
    ];
    return $tablez[$fn];
  }
//------------------------------------------------------------------------------
  private function _getOrderField($fn, $req = []){
    $orderField = [
      'edit'  => [
        'tnt_eviction_process_id', 'tnt_eviction_event_id', 'date', 'subject', 'status', 'tenant_attorney', 'remark'
      ],
      'create' => [
        'uuid', 'tnt_eviction_process_id', 'date', 'subject', 'status', 'tenant_attorney', 'remark'
      ]
    ];
    return $orderField[$fn];
  }
//------------------------------------------------------------------------------
  private function _getSetting($fn, $default = []){
    $setting = [
      'edit'  => [
        'field'=>[
          'tnt_eviction_process_id' => ['type'=>'hidden'],
          'tnt_eviction_event_id'   => ['type'=>'hidden'],
          'status'                  => ['type'=>'option', 'option'=>$this->_mappingEvent['status']],
          'remark'                  => ['type'=>'textarea']
        ]
      ],
      'create' => [
        'field'=>[
          'uuid'                    => ['type'=>'hidden'],
          'tnt_eviction_process_id' => ['type'=>'hidden'],
          'status'                  => ['type'=>'option', 'option'=>$this->_mappingEvent['status']],
          'remark'                  => ['type'=>'textarea']
        ],
        'rule'=>[
          'uuid' => 'nullable|string',
        ]
      ]
    ];
    if(!empty($default)){
      foreach($default as $k=>$v){
        $setting[$fn]['field'][$k]['value'] =  $v;
      }
    }
    return $setting[$fn];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn, $req){
    $perm = Helper::getPermission($req);
    $buttton = [
      'edit'   => ['submit' => ['class' => 'btn btn-info pull-right btn-sm col-sm-12', 'value'=>'Update']],
      'create' => ['submit' => ['class' => 'btn btn-info pull-right btn-sm col-sm-12', 'value'=>'Submit']]
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private function _getSuccessMsg($name){
    $data = [
      'store' => Html::sucMsg('Tenant Eviction Event was added Successfully.'),
      'update' => Html::sucMsg('Update was Successful.')
    ];
    return $data[$name];
  }


}