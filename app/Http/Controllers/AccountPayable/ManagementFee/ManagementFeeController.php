<?php
namespace App\Http\Controllers\AccountPayable\ManagementFee;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Library\{V, Elastic, Html, Helper, Format, GridData, Account, TableName AS T};
use App\Http\Models\{Model,AccountPayableModel AS M};

class ManagementFeeController extends Controller {
  private $_viewPath          = 'app/AccountPayable/ManagementFee/managementFee/';
  private $_viewTable         = '';
  private $_indexMain         = '';
  private $_mapping           = [];
  private static $_instance;
  
  public function __construct(){
    $this->_viewTable = T::$vendorManagementFeeView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_mapping           = Helper::getMapping(['tableName'=>T::$prop]);
  }
//------------------------------------------------------------------------------
  public static function getInstance(){
    if(is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  } 
//------------------------------------------------------------------------------
  public function index(Request $req){
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . 'index';
    $initData = $this->_getColumnButtonReportList($req);
    switch ($op){
      case 'column':
        return $initData;
      case 'show':
        $this->_refreshManagementFees();
        $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
        $qData = GridData::getQuery($vData, $this->_viewTable);
        $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
        return $this->_getGridData($r, $vData, $qData, $req); 
      default:
        return view($page, ['data'=>[
          'nav'=>$req['NAV'],
          'account'=>Account::getHtmlInfo($req['ACCOUNT']), 
          'initData'=>$initData
        ]]);  
    }
  }
//------------------------------------------------------------------------------
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################

################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
//------------------------------------------------------------------------------
  private function _getGridData($r, $vData, $qData, $req){
    $perm     = Helper::getPermission($req);
    $rows     = [];

    $result      = Helper::getElasticResult($r,0,1);
    $data        = Helper::getValue('data',$result,[]);
    $total       = Helper::getValue('total',$result,0);
    
    foreach($data as $i => $v){
      $source                  = $v['_source'];
      $source['num']           = $vData['offset'] + $i + 1;
      $source['linkIcon']      = Html::getLinkIcon($source,['prop','trust','group1']);
      $source                  = $this->_gatherAggregations($source);
      $source['prop_type']     = Helper::getValue(Helper::getValue('prop_type',$source),$this->_mapping['prop_type']);
      $source['prop_class']    = Helper::getValue(Helper::getValue('prop_class',$source),$this->_mapping['prop_class']);
      $rows[]                  = $source;
    }
    return ['rows'=>$rows,'total'=>$total];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){  
    $perm          = Helper::getPermission($req);  
    ### REPORT AND EXPORT LIST SECTION ###
    $reportList    = ['csv'=>'Export to CSV'];
    ### BUTTON SECTION ###
    $_getButtons   = function($perm){
      $button  = '';
      //$button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-plus-square']) . ' New',['id'=>'new','class'=>'btn btn-success']) . ' ';
      $button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-paper-plane-o']) . ' Generate Payment',['id'=>'generatePayment','class'=>'btn btn-primary']) . ' ';
      //$button .= Html::button(Html::i('',['class'=>'fa fa-fw fa-trash']) . ' Delete',['id'=>'delete','class'=>'btn btn-danger','disabled'=>true]) . ' ';
      return $button;
    };

    $_getSelectColumn = function ($perm, $field, $title, $width, $source){
      $data = ['filterControl'=> 'select','filterData'=> 'url:/filter/'.$field.':' . $this->_viewTable,'field'=>$field,'title'=>$title,'sortable'=> true,'width'=> $width];
      //$data['editable'] = ['type'=>'select','source'=>$source];
      return $data;
    };
    
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'linkIcon','title'=>'Link','width'=>80];
    $data[] = ['field'=>'trust','title'=>'Trust','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'entity_name','title'=>'Entity Name','sortable'=>true,'filterControl'=>'input','width'=>300];
    $data[] = $_getSelectColumn($perm,'prop_type','Prop Type',30,$this->_mapping['prop_type']);
    $data[] = $_getSelectColumn($perm,'prop_class','Prop Class',30,$this->_mapping['prop_class']);
    //$data[] = ['field'=>'vendid','title'=>'Vendor','sortable'=>true,'filterControl'=>'input','width'=>80];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'street','title'=>'Street','sortable'=>true,'filterControl'=>'input','width'=>200];
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true,'filterControl'=>'input','width'=>125];
    $data[] = ['field'=>'zip','title'=>'Zip','sortable'=>true,'filterControl'=>'input','width'=>50];
    $data[] = ['field'=>'county','title'=>'County','sortable'=>true,'filterControl'=>'input','width'=>65];
    $data[] = ['field'=>'start_date','title'=>'Pur. Date','sortable'=>true,'filterControl'=>'input','width'=>30];
    $data   = $this->_addDateColumns($data);
    return ['columns'=>$data, 'reportList'=>$reportList, 'button'=>$_getButtons($perm)]; 
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _addDateColumns($data){
    $nowTs       = strtotime(date('M-Y') . ' +1 months');
    $pastTs      = strtotime(date('M-Y') . ' -6 months');
    $currentTs   = $nowTs;
    
    while($currentTs > $pastTs){
      $code      = date('M-Y',$currentTs);
      $data[]    = ['field'=>$code,'title'=>$code,'width'=>50];
      $curDate   = date('M-Y',$currentTs);
      $currentTs = strtotime($curDate . ' -1 months');
    }
    return $data;
  }
//------------------------------------------------------------------------------
  private function _gatherAggregations($r){
    $rPayment    = Helper::getValue(T::$vendorPayment,$r,[]);
    $codes       = $printed = [];
    foreach($rPayment as $i => $v){
      $invoiceDate    = Helper::getValue('invoice_date',$v);
      $amount         = Helper::getValue('amount',$v,0);
      $print          = Helper::getValue('print',$v,0);
      $code           = date('M-Y',strtotime($invoiceDate));
      $r[$code]       = $amount;
      $codes[$code]   = $code;
      $printed[$code] = $print;
    }
    
    foreach($codes as $v){
      $r[$v]          = !empty($printed[$v]) ? Html::span(Format::usMoney($r[$v]),['class'=>'text-red']) : Format::usMoney($r[$v]);
    }
    return $r;
  }
//------------------------------------------------------------------------------
  private function _refreshManagementFees(){
    $insertIds = $deleteIds  = $updateIds = [];
    $rProp     = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      '_source'  => ['prop_id','prop','prop_class'],
      'query'    => [
        'must'   => [
          'range'   => [
            'prop'  => [
              'gte' => '0001',
              'lte' => '9999',
            ]
          ]
        ]
      ]
    ]),'prop');
    
    $rMgt      = Helper::keyFieldNameElastic(M::getMgtFeeElastic([],['prop','prop_id','prop_class']),'prop');
    $deleteIds = array_diff(array_keys($rMgt),array_keys($rProp));
    foreach($rProp as $k => $v){
      $mgtFee     = !empty($rMgt[$k]) ? $rMgt[$k] : [];
      $insertIds  = array_merge($insertIds,empty($mgtFee) ? [$v['prop_id']] : []);
      $propClass  = $v['prop_class'];
      $mgtClass   = Helper::getValue('prop_class',$mgtFee);
      $updateIds  = !empty($mgtClass) && $mgtClass !== $propClass ? array_merge($updateIds,[$v['prop_id']]) : $updateIds;
    }

    ############### DATABASE SECTION ######################
    $response = $elastic = $success = $commit = []; 
    try {
      if(!empty($insertIds) || !empty($updateIds) || !empty($deleteIds)){
        DB::beginTransaction();
        
        $elastic     += !empty($insertIds) || !empty($updateIds) ? ['insert'=>[T::$vendorManagementFeeView=>['p.prop_id'=>array_merge($insertIds,$updateIds)]]] : [];
        $elastic     += !empty($deleteIds) ? ['delete'=>['prop_id'=>$deleteIds]] : [];
        
        Model::commit([
          'success'   => [T::$prop=>array_merge($insertIds,$updateIds,$deleteIds)],
          'elastic'   => $elastic,
        ]);
      }
      $response['success'] = 1;
    } catch(\Exception $e) {
      dd($e);
    }
    return $response;
  }
}
