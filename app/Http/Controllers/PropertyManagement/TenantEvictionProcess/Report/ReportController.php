<?php
namespace App\Http\Controllers\PropertyManagement\TenantEvictionProcess\Report;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, Elastic, GridData, TableName AS T, Helper};


class ReportController extends Controller{
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(){
    $this->_viewTable = T::$tntEvictionProcessView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $rules = [
      'type'           =>'required|string',
      'process_status' =>'required|string',
      'dateRange'      =>'nullable|string|between:21,23',
      'status'         =>'nullable|string|between:0,1'
    ];
    $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>$rules])['data'];
    $dataRange = Helper::getDateRange($vData, 'Y-m-d');
    $vData['date'] = '['.$dataRange.']';
    unset($vData['dateRange']); 
    $vData['limit'] = -1;
    $vData['defaultFilter'] = ['process_status'=>$vData['process_status'], 'tnt_eviction_event.date'=>$vData['date']];
    $vData['defaultSort']   = ['group1.keyword:asc','prop.keyword:asc','tnt_eviction_event.date.keyword:asc'];
    if(!empty($vData['status'])) {
      $vData['defaultFilter']['tnt_eviction_event.status'] = $vData['status']; 
    }
    $qData = GridData::getQuery($vData, $this->_viewTable);
    $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
    $reportClass = '\App\Http\Controllers\PropertyManagement\TenantEvictionProcess\Report\\EvictionReport';
    return class_exists($reportClass) ? $reportClass::getReport($r, $req, $vData) : '';
  }
//------------------------------------------------------------------------------
  public function create(Request $req) {
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $reportClass = '\App\Http\Controllers\PropertyManagement\TenantEvictionProcess\Report\\' . $op;
    return class_exists($reportClass) ? $reportClass::create($req) : '';
  }
}