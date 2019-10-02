<?php
namespace App\Http\Controllers\PropertyManagement\TenantBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Upload,Html, GridData, Account, TableName AS T, Format, Helper, HelperMysql};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class
use App\Http\Controllers\Filter\OptionFilterController AS OptionFilter; // Include the models class

/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `active`) VALUES ('0', 'Property Management', 'fa fa-fw fa-user-circle-o', 'tenantBalance', 'Tenant Balance', 'fa fa-fw fa-cog', 'Tenant Balance Access', 'fa fa-fw fa-users', 'tenantBalance', 'index', 'To Access Tenant Balance', '1');
 */
class TenantBalanceController extends Controller {
  private $_viewPath    = 'app/PropertyManagement/TenantBalance/tenantBalance/';
  private $_viewTable   = '';
  private $_indexMain   = '';
  private $_mapping     = [];
  
  public function __construct(Request $req){
    $this->_viewTable = T::$tenantView;
    // $this->perm       = $this->_getPermission($req);
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$tenant]);
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $op   = isset($req['op']) ? $req['op'] : 'index';
    $page = $this->_viewPath . 'index';
    $initData = $this->_getColumnButtonReportList($req);
    switch ($op){
      case 'column':
        return $initData;
      case 'show':
//        $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
        $vData = $req->all();
        $vData['defaultSort'] = ['prop.keyword:asc', 'unit.keyword:asc', 'tenant:asc'];
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
################################################################################
##########################   GRID TABLE SECTION  ###############################  
################################################################################
  private function _getGridData($r,$vData,$qData,$req){
    $result  = Helper::getElasticResult($r,0,1);
    
    $data    = Helper::getValue('data',$result,[]);
    $total   = Helper::getValue('total',$result,0);
    
    $props   = array_column(array_column($data,'_source'),'prop');
    $balances= $this->_getRentBalance(['prop'=>$props]);

    $rows    = [];
    foreach($data as $i => $v){
      $source       = $v['_source'];
      
      $key          = $source['prop'] . $source['unit'] . $source['tenant'];
      $balanceData  = Helper::getValue($key,$balances,[]);
      $amount       = Helper::getValue('amount',$balanceData,0);
      $sysDate      = Helper::getValue('sys_date',$balanceData);
      
      $source['num']      = $vData['offset'] + $i + 1;
      $source['linkIcon'] = Html::getLinkIcon($source,['group1','prop','unit','tenant']);
      $source['sys_date'] = $sysDate;
      $source['amount']   = Format::usMoney($amount);
      $rows[]             = $source;
    }
    return ['rows'=>$rows,'total'=>$total];
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $perm        = Helper::getPermission($req);
    $button      = '';
    $reportList  = [];
    
    $_getSelectColumn = function($perm,$field,$title,$width,$source){
      $data = ['filterControl'=>'select','filterData'=>'url:/filter/' . $field . ':' . T::$tenantView,'field'=>$field,'title'=>$title,'width'=>$width,'sortable'=>true];
      
      return $data;
    };
    
    $textEditable  = ['editable'=>['type'=>'text']];
    $data   = [];
    $data[] = ['field'=>'num', 'title'=>'#', 'width'=> 25];
    $data[] = ['field'=>'linkIcon', 'title'=>'Link', 'width'=> 150];
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'unit','title'=>'Unit','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'tenant','title'=>'Tenant','sortable'=>true,'filterControl'=>'input','width'=>25];
    $data[] = ['field'=>'amount','title'=>'Balance','width'=>60];
    $data[] = ['field'=>'lease_opt_date','title'=>'Will Pay By','sortable'=>true,'filterControl'=>'input','width'=>70];
    $data[] = ['field'=>'tnt_name','title'=>'Tenant Name','sortable'=>true,'filterControl'=>'input','width'=>220];
    $data[] = ['field'=>'street','title'=>'Address','sortable'=>true,'filterControl'=>'input','width'=>220];
    $data[] = ['field'=>'city','title'=>'City','sortable'=>true,'filterControl'=>'input','width'=>120];
    $data[] = $_getSelectColumn($perm,'bedrooms','Bed',25,$this->_mapping['bedrooms']);
    $data[] = $_getSelectColumn($perm,'bathrooms','Bath',25,$this->_mapping['bathrooms']);
    $data[] = ['field'=>'move_in_date','title'=>'Move In','sortable'=>true,'filterControl'=>'input','width'=>50];
    $data[] = ['field'=>'phone1','title'=>'Phone','sortable'=>true,'filterControl'=>'input','width'=>50];
    $data[] = ['field'=>'sys_date','title'=>'Last Updated'];
    //$data[] = ['field'=>'bedrooms','title'=>'Bed','sortable'=>true,'filterControl'=>'input','width'=>25];
    //$data[] = ['field'=>'bathrooms','title'=>'Bath','sortable'=>true,'filterControl'=>'']
    
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>$button];
  }
//------------------------------------------------------------------------------
  private function _getRentBalance($vData){
    $mustBody = !empty($vData['prop']) ? ['query'=>['must'=>['prop.keyword'=>$vData['prop']]]] : [];
    $data     = [];
    $amountMap= [];
    $r        = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'      => T::$tntTransView,
      'size'       => 50000,
      'sort'       => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc','sys_date'=>'asc'],
      '_source'    => ['prop','unit','tenant','amount','sys_date']
    ]));
//    $rProp    = Helper::keyFieldNameElastic(Elastic::searchQuery([
//      'index'      => T::$propView,
//      '_source'    => ['prop','group1'],
//    ] + $mustBody),'prop');
    foreach($r as $i => $v){
      $idKey                    = $v['prop'] . $v['unit'] . Helper::getValue('tenant',$v);
      $data[$idKey]             = Helper::selectData(['prop','unit','tenant','sys_date'],$v);
      $amountMap[$idKey]        = !empty($amountMap[$idKey]) ? $amountMap[$idKey] + Helper::getValue('amount',$v,0) : Helper::getValue('amount',$v,0);
      $data[$idKey]['amount']   = $amountMap[$idKey];
    }
    return $data;
  }
}