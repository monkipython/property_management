<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\LateCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper,HelperMysql, TableName AS T, Format};
use App\Http\Models\Model; // Include the models class
use App\Http\Models\TenantModel AS M; // Include the models class

class LateChargeController extends Controller{
  private $_viewPath  = 'app/PropertyManagement/Tenant/latecharge/';
  
  public function __construct(Request $req){
    $this->_mappingProp   = Helper::getMapping(['tableName'=>T::$prop]);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $page = $this->_viewPath . 'create';
    $form   = Html::div(implode('',Form::generateField($this->_getFields())),['class'=>'box-body']);
    $button = Html::div(Html::input('Submit', $this->_getButton(__FUNCTION__)), ['class'=>'box-footer']);
    
    return view($page, [
      'data'=>[
        'lateChargeForm'=> $form . $button
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    $valid   = V::startValidate([
      'rawReq'        => $req->all(),
      'rule'          => $this->_getRule(),
      'includeUsid'   => 1,
    ]);
    
    $vData = $valid['dataNonArr'];
    $batch           = HelperMysql::getBatchNumber();
    $rProps          = Helper::explodeField($vData,['prop','group1','prop_type','city'])['prop'];
    $date            = Format::mysqlDate($vData['date1']);
    $capAmount       = $vData['amount'];
    $fixedAmount     = $vData['fixed_amount'];
    $fixedPercentage = $vData['fixed_percentage'];
    $usr             = $vData['usid'];
    $lateChargeCode  = $vData['gl_acct'];
    unset($vData['prop']);

    // Search all tenant trans sum amount in Elastic search engine (Greater than zero).
    $rows = Helper::getElasticAggResult(Elastic::searchQuery([
      'index' => T::$tntTransView,
      'size'  => '0',
      'query' => [
        'must' => [
          'gl_acct.keyword' => '602',
          'prop.keyword'    => $rProps
        ]
      ],
      'aggs' => ['by_prop'=>['terms'=>['field'=>'prop.keyword'],'aggs'=>[
                  'by_unit'=>['terms'=>['field'=>'unit.keyword'],'aggs'=>[
                    'by_tenant'=>['terms'=>['field'=>'tenant'],'aggs'=>[
                      'amount_sum'=>['sum'=>['field'=>'amount']],
                      'amount_sum_filter'=>['bucket_selector'=>['buckets_path'=>['amountSum'=>'amount_sum'],'script'=>'params.amountSum >= '.$capAmount]]
                    ]]]
                  ]]
                ]]
    ]),'by_prop');
 
    $dataset = [];
    $rProp   = Helper::keyFieldNameElastic(HelperMysql::getProp($rProps), 'prop');
    $service = Helper::keyFieldName(HelperMysql::getService(['prop'=>'z64']), 'service');
    $glChart = Helper::keyFieldName(HelperMysql::getGlChat(['g.prop'=>'z64']), 'gl_acct');

    $startDate = date('Y-m-01', strtotime($date));
    $stopDate  = date('Y-m-t', strtotime($date));
    
    foreach($rows as $propBuckets){
      $tenantMust = [
        'status.keyword' => 'C',
        'prop.keyword'   => $propBuckets['key']
      ];
      $rTnt = Helper::keyFieldNameElastic(HelperMysql::getTenant($tenantMust,['prop','unit','tenant','tnt_name','late_after','prop_type'],[],0,0), ['prop','unit','tenant']);
      foreach($propBuckets['by_unit']['buckets'] as $unitBuckets){
        foreach($unitBuckets['by_tenant']['buckets'] as $tenantBuckets){
          $prop   = $propBuckets['key'];
          $unit   = $unitBuckets['key'];
          $tenant = $tenantBuckets['key'];
          // Compare late rate by prop
          $nameKey = $prop . $unit . $tenant;
          if($tenantBuckets['amount_sum']['value'] > $capAmount && isset($rTnt[$nameKey]) && date('j') >= $rTnt[$nameKey]['late_after']){
            if(!empty($fixedPercentage)) {
              $rTntTrans   = HelperMysql::getTntTrans(['prop.keyword'=>$prop,'unit.keyword'=>$unit,'tenant'=>$tenant,'gl_acct.keyword'=>'602','tx_code.keyword'=>'IN','range'=>['date1'=>['gte'=>$startDate, 'lte'=>$stopDate]]],[],[],0);
              $amount      = array_sum(array_column(array_column($rTntTrans,'_source'),'amount'));
              $fixedAmount = $fixedPercentage * .01 * $amount;
            }
            $dataset[T::$tntTrans][] = [
              'prop'         => $prop,
              'unit'         => $unit,
              'tenant'       => $tenant,
              'amount'       => !empty($fixedAmount) ? $fixedAmount : $rProp[$prop]['late_rate'],
              'bank'         => $rProp[$prop]['ar_bank'],
              'remark'       => $glChart[$lateChargeCode]['title'],
              'gl_acct'      => $glChart[$lateChargeCode]['gl_acct'],
              'service_code' => $service[$lateChargeCode]['service'],
              'tx_code'      => 'IN',
              'batch'        => $batch,
              'date1'        => $date,
              'date2'        => $date,
              'check_no'     => '000001',
              'tnt_name'     => title_case($rTnt[$nameKey]['tnt_name']),
            ];
          }
        }
      }
    }

    $insertData = HelperMysql::getDataSet($dataset,$usr, $glChart, $service);
    // ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::insert($insertData);
      $insertIds = $success['insert:'.T::$tntTrans];
      $updateData = [T::$tntTrans=>[
        'whereInData'=>['field'=>'cntl_no', 'data'=>$insertIds],
        'updateData'=>['appyto'=>DB::raw('cntl_no'),'invoice'=>DB::raw('cntl_no')]
      ]];

      $success += Model::update($updateData);
      $elastic = [
        'insert'=>[
          T::$tntTransView=>['tt.cntl_no'=>$insertIds]
        ]
      ];
      $response['msg'] = $this->_getSuccessMsg(__FUNCTION__);
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
    } catch(Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }  

################################################################################
##########################    FIELD SECTION    #################################  
################################################################################  
   private function _getFields(){
    $propGroup = Helper::keyFieldNameElastic(HelperMysql::getGroup([], 'prop'), 'prop', 'prop');
    $city = M::getCityList();
    return [
      'prop'             => ['id'=>'prop','label'=>'Property Numbers', 'id'=>'prop','type'=>'textarea', 'placeholder'=>'e.g: 0001, 0002 OR 0005-0150','value'=>'0001-9999','req'=>1],
      'date1'            => ['id'=>'date1','label' => 'Transaction Date', 'class'=>'date', 'id'=>'date1','value'=>date('m/05/Y'),'type'=>'text','req'=>1],
      'amount'           => ['id'=>'amount','label'=>'Cap Amount','type'=>'text', 'value'=>100,'class'=>'decimal','req'=>1],
      'gl_acct'          => ['id'=>'gl_acct','label'=>'Late Charge GL','type'=>'text', 'hint'=>'You can type GL Account or Title for autocomplete', 'class'=>'autocomplete','value'=>610,'autocomplete'=>'false','req'=>1],
      'fixed_amount'     => ['id'=>'fixed_amount','label'=>'Fixed Amount','type'=>'text','value'=>0, 'class'=>'decimal'],
      'fixed_percentage' => ['id'=>'fixed_percentage','label'=>'Fixed Percentage','type'=>'text','value'=>0, 'class'=>'percent-mask'],
      'prop_type'        => ['id'=>'prop_type','label'=>'Property Type', 'type'=>'option', 'option'=>[''=>'All'] + $this->_mappingProp['prop_type']],
      'group1'           => ['id'=>'group1','label'=>'Group','type'=>'option', 'option'=>[''=>'All'] + $propGroup],
      'city'             => ['id'=>'city','label'=>'City','type'=>'option', 'option'=>[''=>'All'] + $city]
    ];
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'prop'             => 'required|string',
      'date1'            => 'required|string|between:8,10',
      'amount'           => 'required|numeric',
      'gl_acct'          => 'required|string',
      'fixed_amount'     => 'nullable|numeric',
      'fixed_percentage' => 'nullable|numeric',
      'prop_type'        => 'nullable|string|between:1,1',
      'group1'           => 'nullable|string|between:1,7',
      'city'             => 'nullable|string'
    ];
  }
//------------------------------------------------------------------------------
  private function _getButton($fn){
    $buttton = [
      'create'=>['id'=>'submit', 'value'=>'Generate Massive Late Charge','type'=>'submit','class'=>'btn btn-info pull-right btn-sm col-sm-12'],
    ];
    return $buttton[$fn];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  //------------------------------------------------------------------------------
  private function _getSuccessMsg($name){
    $data = [
      'store' =>Html::sucMsg("Tenant's Massive Late Charge generate Successfully!!!"),
    ];
    return $data[$name];
  }
} 