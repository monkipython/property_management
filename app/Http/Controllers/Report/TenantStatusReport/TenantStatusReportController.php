<?php
namespace App\Http\Controllers\Report\TenantStatusReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};

class TenantStatusReportController extends Controller {
  public $typeOption   = ['city'=>'City', 'group1'=>'Group','cons1'=>'Owner','prop'=>'Property','trust'=>'Trust'];
  private $_mapping    = [];
  private $_maxProps   = 75;
  private $_maxSize    = 500000;
  
  public function __construct(Request $req){
    $this->_mapping    = Helper::getMapping(['tableName'=>T::$prop]);
  }
/**
 * @desc this getInstance is important because to activate __contract we need to call getInstance() first
 */
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'        => $req->all(),
      'rule'          => $this->_getRule(),
      'includeCdate'  => 0,
    ]);
    return $this->getData($valid);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $propGroup = $this->_getPropGroup();  
    $fields    = [
      'type'         => ['id'=>'type','name'=>'type','label'=>'Group By','type'=>'option','option'=>$this->typeOption,'value'=>'group1','req'=>1],
      'dateRange'    => ['id'=>'dateRange','name'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange','req'=>1],
      'prop'         => ['id'=>'prop','name'=>'prop','label'=>'Prop','type'=>'textarea','value'=>'0001-9999'],
      'group1'       => ['id'=>'group1','name'=>'group1','label'=>'Group','type'=>'option', 'option'=>[''=>'Select Group'] + $propGroup,'req'=>0],
      'city'         => ['id'=>'city','name'=>'city','label'=>'City','type'=>'textarea', 'placeHolder'=>'Ex. Alameda-Fresno,Torrance'],
      'cons1'     => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'****83-**83,Z64'],
      'trust'     => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'****83-**83,*ZA67'],
      'prop_type'    => ['id'=>'prop_type','name'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type']],
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList($req)];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData = $valid['data'];
    $op    = $valid['op'];
    
    $type            = !empty($vData['type']) ? $vData['type'] : 'group1';
    $vData          += Helper::splitDateRate($vData['dateRange'],'date1');
    $dateRange       = $vData['dateRange'];
    unset($vData['dateRange']);
    
    $this->_validateProp($vData);
    $vData['prop']     = Helper::explodeField($vData,['prop','group1','city','cons1','trust','prop_type'])['prop'];
    $column            = $this->_getColumnButtonReportList()['columns'];
    $r            = Elastic::searchQuery([
      'index'     => T::$tenantView,
      'size'      => 500000,
      'sort'      => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'   => ['tenant_id','prop','unit','tenant','tnt_name','move_in_date','move_out_date','status','dep_held1',T::$billing],
      'query'     => [
        'must'    => [
          'prop.keyword'    => $vData['prop'],
          'status.keyword'  => 'C',
        ]
      ]
    ]);
    $r['dateRange']  = $dateRange;
    $r['prop']       = $vData['prop'];
    $r['date1']      = $vData['date1'];
    $r['todate1']    = $vData['todate1'];
    $title           = 'Tenant Status Report by ' . $this->typeOption[$type] . ' on ' . $dateRange;
    if(!empty($op)){
      $gridData = $this->_getGridData($r,$type);
      switch ($op) {
        case 'show': return $gridData; 
        case 'csv':  return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':  return P::getPdf(P::getPdfData($gridData, $column), ['title'=>$title]);
      }
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
//------------------------------------------------------------------------------
  private function _getGridData($r,$type='group1'){
    $data           = $rows = $aggs = $units = $endBalance = [];
    $dateRange      = $r['dateRange'];
    $groupUnitType  = 'prop.' . $type;
    
    $rUnit          = Helper::getElasticResult(Elastic::searchQuery([
      'index'       => T::$unitView,
      '_source'     => ['prop.prop',$groupUnitType,'prop.prop_name','prop.street','prop.city','prop.state','prop.zip','unit','rent_rate','bedrooms','bathrooms','status'],
      'sort'        => [$groupUnitType . '.keyword' => 'asc','prop.prop.keyword'=>'asc','unit.keyword'=>'asc'],
      'size'        => $this->_maxSize,
      'query'       => [
        'must'      => [
          'prop.prop.keyword' => $r['prop'],
        ]
      ]
    ]));
    
    $rTenant   = Helper::groupBy(Helper::getElasticResultSource($r),['prop','unit']);
    
    $propAddr  = [];
    foreach($rUnit as $i => $v){
      $source      = $v['_source'];
      
      $propData    = $source['prop'][0];
      $prop        = $propData['prop'];
      $unit        = $source['unit'];
      
      $group       = Helper::getValue($type,$propData);
      $tenantData  = Helper::getValue($prop . $unit,$rTenant,[]);
      $propAddr[$prop] = implode(', ',[title_case($propData['street']),title_case($propData['city']),$propData['state']]);
      if($source['status'] != 'V' && !empty($tenantData)){
        foreach($tenantData as $idx => $tenant){
          $units[]       = $unit;
          $tenantRents   = $this->_getTenantRent($tenant);
          $moveOut       = Format::usDate(Helper::getValue('move_out_date',$tenant,'9999-12-31'));
          $name          = Helper::getValue('tnt_name',$tenant);
          $name          = Helper::isBetweenDateRange($moveOut,$dateRange) ? '*** Vacant ***' : $name;
          
          $data[$group][$prop][] = [
            'type'          => $type,
            'prop'          => $prop,
            'unit'          => $tenant['unit'],
            'tenant'        => $tenant['tenant'],
            'tnt_name'      => $name,
            'tnt_rent'      => $tenantRents['tntRent'],
            'hud'           => $tenantRents['HUD'],
            'rent_rate'     => Helper::getValue('rent_rate',$source,0),
            'bathrooms'     => isset($source['bathrooms']) ? $source['bathrooms'] : '',
            'bedrooms'      => isset($source['bedrooms']) ? $source['bedrooms'] : '',
            'dep_held1'     => Helper::getValue('dep_held1',$tenant,0),
            'move_in_date'  => Format::usDate(Helper::getValue('move_in_date',$tenant,'1969-12-31')),
          ];
        }
      } else {
        $name  = '*** Vacant ***';
        $data[$group][$prop][] = [
          'type'          => $type,
          'prop'          => $prop,
          'unit'          => $unit,
          'tenant'        => '',
          'tnt_name'      => $name,
          'tnt_rent'      => 0,
          'hud'           => 0,
          'rent_rate'     => Helper::getValue('rent_rate',$source,0),
          'bathrooms'     => isset($source['bathrooms']) ? $source['bathrooms'] : '',
          'bedrooms'      => isset($source['bedrooms']) ? $source['bedrooms'] : '',
          'dep_held1'     => 0,
          'move_in_date'  => '',
        ];
      }
    }
    
    $rTntTrans             = Helper::getElasticAggResult(Elastic::searchQuery([
      'index'   => T::$tntTransView,
      'size'    => 0,
      'query'   => [
        'must'  => [
          'prop.keyword'    => $r['prop'],
          'unit.keyword'    => $units,
          //'status.keyword'  => 'C',
        ]
      ],
      'aggs'    => [
        'by_prop' => [
          'terms' => [
            'field' => 'prop.keyword',
            'size'  => 100000,
          ],
          'aggs'  => [
            'prop_sort' => [
              'bucket_sort' => [
                'sort'  => ['_key'],
              ]
            ],
            'by_unit' => [
              'terms' => [
                'field' => 'unit.keyword',
                'size'  => 20000,
              ],
              'aggs'  => [
                'unit_sort' => [
                  'bucket_sort' => [
                    'sort'      => ['_key'],
                  ]
                ],
                'by_tenant' => [
                  'terms'   => [
                    'field' => 'tenant',
                    'size'  => 10000,
                  ],
                  'aggs'    => [
                    'tenant_sort'=> [
                      'bucket_sort' => [
                        'sort'      => ['_key'],
                      ]
                    ],
                    'endBalance' => [
                      'sum'      => [
                        'field'  => 'amount',
                      ]
                    ]
                  ]
                ]
              ]
            ]
          ]
        ]
      ]
    ]),'by_prop');
    
    $result = Helper::getElasticResult(Elastic::searchQuery([
      'index'    => T::$tntTransView,
      'size'     => 500000,
      '_source'  => ['prop','unit','tenant', 'amount','tx_code','date1'],
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      'query'    => [
        'must'   => [
          'tx_code.keyword' => ['IN','P'],
          'prop.keyword'    => $r['prop'],
          'unit.keyword'    => $units,   
          //'status.keyword'  => 'C',
          'range' => [
            'date1' => [
              'gte'    => $r['date1'],
            ],
          ],
        ]
      ],
    ]));
    foreach($rTntTrans as $i => $propBucket){
      $prop = $propBucket['key'];
      foreach($propBucket['by_unit']['buckets'] as $unitBucket){
        $unit            = $unitBucket['key'];
        foreach($unitBucket['by_tenant']['buckets'] as $tenantBucket){
          $tenant        = $tenantBucket['key'];
          $searchKey     = $prop . $unit . $tenant;
          $endBalance[$prop . $unit . $tenant] = $tenantBucket['endBalance']['value'];
          $aggs[$searchKey]  = ['balanceFwd'=>0,'cash'=>0,'charge'=>0,'endBalance'=>$tenantBucket['endBalance']['value']];
        }
      }
    }
    
    foreach($result as $i => $v){
      $source      = $v['_source'];
      $searchKey   = $source['prop'] . $source['unit'] . $source['tenant'];
      $code        = $source['tx_code'];
      $amount      = Helper::getValue('amount',$source,0);
      $aggs[$searchKey]['cash']        += ($code == 'P') && Helper::isBetweenDateRange($source['date1'],$dateRange) ? $amount : 0;
      $aggs[$searchKey]['charge']      += ($code == 'IN') && Helper::isBetweenDateRange($source['date1'],$dateRange) ? $amount : 0;
      $aggs[$searchKey]['balanceFwd']  += $amount;
    }

    $grandTotal = ['tnt_name'=>0,'bedrooms'=>0,'bathrooms'=>0,'dep_held1'=>0,'rent_rate'=>0,'balanceFwd'=>0,'charge'=>0,'cash'=>0,'endBalance'=>0,'tnt_rent'=>0,'hud'=>0];
    foreach($data as $groupType => $props){
      $groupSum = ['tnt_name'=>0,'bedrooms'=>0,'bathrooms'=>0,'dep_held1'=>0,'rent_rate'=>0,'balanceFwd'=>0,'charge'=>0,'cash'=>0,'endBalance'=>0,'tnt_rent'=>0,'hud'=>0];
      foreach($props as $propId => $val){
        $propSum = ['tnt_name'=>0,'bedrooms'=>0,'bathrooms'=>0,'dep_held1'=>0,'rent_rate'=>0,'balanceFwd'=>0,'charge'=>0,'cash'=>0,'endBalance'=>0,'tnt_rent'=>0,'hud'=>0];
        $addr    = Helper::getValue($propId,$propAddr);
        $rows[]  = [
          'prop'     => Html::b($propId),
          'tnt_name' => Html::b($addr),
        ];
        foreach($val as $i => $v){
          $beds  = is_numeric($v['bedrooms']) ? $v['bedrooms'] : 0;
          $baths = is_numeric($v['bathrooms']) ? $v['bathrooms'] : 0;

          $searchKey                      = $v['prop'] . $v['unit'] . $v['tenant'];
          $tntTransData                   = Helper::getValue($searchKey,$aggs,[]);
          $balanceFwd                     = Helper::getValue('balanceFwd',$tntTransData,0);
          $v['bathrooms']                 = !empty($v['bathrooms']) ? $v['bathrooms'] : 0;
          $v['bedrooms']                  = !empty($v['bedrooms']) ? $v['bedrooms'] : 0;
          $v['cash']                      = Helper::getValue('cash',$tntTransData,0);
          $v['charge']                    = Helper::getValue('charge',$tntTransData,0);
          $v['endBalance']                = Helper::getValue('endBalance',$tntTransData,0);
          $v['balanceFwd']                = Format::floatNumber($v['endBalance']  - $balanceFwd);
          $propSum['rent_rate']          += $v['rent_rate'];
          $propSum['bathrooms']          += $baths;
          $propSum['bedrooms']           += $beds;
          $propSum['dep_held1']          += $v['dep_held1'];
          $propSum['balanceFwd']         += $v['balanceFwd'];
          $propSum['charge']             += $v['charge'];
          $propSum['cash']               += $v['cash'];
          $propSum['endBalance']         += $v['endBalance'];
          $propSum['tnt_rent']           += $v['tnt_rent'];
          $propSum['hud']                += $v['hud'];
          $propSum['tnt_name']++;

          $groupSum['rent_rate']         += $v['rent_rate'];
          $groupSum['bathrooms']         += $baths;
          $groupSum['bedrooms']          += $beds;
          $groupSum['dep_held1']         += $v['dep_held1'];
          $groupSum['balanceFwd']        += $v['balanceFwd'];
          $groupSum['charge']            += $v['charge'];
          $groupSum['cash']              += $v['cash'];
          $groupSum['endBalance']        += $v['endBalance'];
          $groupSum['tnt_rent']          += $v['tnt_rent'];
          $groupSum['hud']               += $v['hud'];
          $groupSum['tnt_name']++;
          
          $grandTotal['rent_rate']       += $v['rent_rate'];
          $grandTotal['bathrooms']       += $baths;
          $grandTotal['bedrooms']        += $beds;
          $grandTotal['dep_held1']       += $v['dep_held1'];
          $grandTotal['balanceFwd']      += $v['balanceFwd'];
          $grandTotal['charge']          += $v['charge'];
          $grandTotal['cash']            += $v['cash'];
          $grandTotal['endBalance']      += $v['endBalance'];
          $grandTotal['tnt_rent']        += $v['tnt_rent'];
          $grandTotal['hud']             += $v['hud'];
          $grandTotal['tnt_name']++;

          $rows[] = [
            'prop'         => $v['prop'] . '-' . $v['unit'],
            'tnt_name'     => title_case($v['tnt_name']),
            'rent_rate'    => Format::usMoney($v['rent_rate']),
            'bathrooms'    => $v['bathrooms'],
            'bedrooms'     => $v['bedrooms'],
            'tnt_rent'     => Format::usMoney($v['tnt_rent']),
            'hud'          => Format::usMoney($v['hud']),
            'dep_held1'    => Format::usMoney($v['dep_held1']),
            'balanceFwd'   => Format::usMoney($v['balanceFwd']),
            'charge'       => Format::usMoney($v['charge']),
            'cash'         => Format::usMoney($v['cash']),
            'endBalance'   => Format::usMoney($v['endBalance']),
            'move_in_date' => !empty($v['move_in_date']) ? Format::usDate($v['move_in_date']) : '',
          ];
        }
        $propSum['tnt_name']      = Html::b('Total ' . $propId . ': ' . $propSum['tnt_name']);
        $propSum['dep_held1']     = Html::b(Format::usMoney($propSum['dep_held1']));
        $propSum['rent_rate']     = Html::b(Format::usMoney($propSum['rent_rate']));
        $propSum['bathrooms']     = Html::b($propSum['bathrooms']);
        $propSum['bedrooms']      = Html::b($propSum['bedrooms']);
        $propSum['tnt_rent']      = Html::b(Format::usMoney($propSum['tnt_rent']));
        $propSum['hud']           = Html::b(Format::usMoney($propSum['hud']));
        $propSum['balanceFwd']    = Html::b(Format::usMoney($propSum['balanceFwd']));
        $propSum['charge']        = Html::b(Format::usMoney($propSum['charge']));
        $propSum['cash']          = Html::b(Format::usMoney($propSum['cash']));
        $propSum['endBalance']    = Html::b(Format::usMoney($propSum['endBalance']));
        $rows[] = $propSum;
      }
      $columnName                = $type === 'city' ? 'City' : 'Group';
      $typeFormatted             = $type === 'city' ? title_case($groupType) : $groupType;
      $groupSum['tnt_name']      = Html::b(Html::u('Total ' . $columnName . ' ' . $typeFormatted . ': ' . $groupSum['tnt_name']));
      $groupSum['dep_held1']     = Html::b(Html::u(Format::usMoney($groupSum['dep_held1'])));
      $groupSum['tnt_rent']      = Html::b(Html::u(Format::usMoney($groupSum['tnt_rent'])));
      $groupSum['hud']           = Html::b(Html::u(Format::usMoney($groupSum['hud'])));
      $groupSum['balanceFwd']    = Html::b(Html::u(Format::usMoney($groupSum['balanceFwd'])));
      $groupSum['charge']        = Html::b(Html::u(Format::usMoney($groupSum['charge'])));
      $groupSum['cash']          = Html::b(Html::u(Format::usMoney($groupSum['cash'])));
      $groupSum['endBalance']    = Html::b(Html::u(Format::usMoney($groupSum['endBalance'])));
      $groupSum['rent_rate']     = Html::b(Html::u(Format::usMoney($groupSum['rent_rate'])));
      $groupSum['bedrooms']      = Html::b(Html::u($groupSum['bedrooms']));
      $groupSum['bathrooms']     = Html::b(Html::u($groupSum['bathrooms']));
      $rows[] = $groupSum;
    }
    $grandTotal['tnt_name']      = Html::b(Html::u('Grand Total: ' . $grandTotal['tnt_name']));
    $grandTotal['dep_held1']     = Html::b(Html::u(Format::usMoney($grandTotal['dep_held1'])));
    $grandTotal['rent_rate']     = Html::b(Html::u(Format::usMoney($grandTotal['rent_rate'])));
    $grandTotal['tnt_rent']      = Html::b(Html::u(Format::usMoney($grandTotal['tnt_rent'])));
    $grandTotal['hud']           = Html::b(Html::u(Format::usMoney($grandTotal['hud'])));
    $grandTotal['balanceFwd']    = Html::b(Html::u(Format::usMoney($grandTotal['balanceFwd'])));
    $grandTotal['charge']        = Html::b(Html::u(Format::usMoney($grandTotal['charge'])));
    $grandTotal['cash']          = Html::b(Html::u(Format::usMoney($grandTotal['cash'])));
    $grandTotal['endBalance']    = Html::b(Html::u(Format::usMoney($grandTotal['endBalance'])));
    $grandTotal['bedrooms']      = Html::b(Html::u($grandTotal['bedrooms']));
    $grandTotal['bathrooms']     = Html::b(Html::u($grandTotal['bathrooms']));
    return P::getRow($rows,$grandTotal);
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req=[]){
    $reportList = [
      'pdf'  =>'Download PDF',
      'csv'  =>'Download CSV',
    ];
    $data = [];
    
    $data[]     = ['field'=>'prop','title'=>'Prop-Unit','sortable'=>true,'width'=>10,'hWidth'=>50];
    $data[]     = ['field'=>'tnt_name','title'=>'Tenant Name','sortable'=>true,'width'=>500,'hWidth'=>220];
    $data[]     = ['field'=>'bedrooms','title'=>'Bd','sortable'=>true,'width'=>40,'hWidth'=>30];
    $data[]     = ['field'=>'bathrooms','title'=>'Bth','sortable'=>true,'width'=>40,'hWidth'=>30];
    $data[]     = ['field'=>'dep_held1','title'=>'Dep Held','sortable'=>true,'width'=>40,'hWidth'=>55];
    $data[]     = ['field'=>'rent_rate','title'=>'Current Rate','sortable'=>true,'width'=>50,'hWidth'=>55];
    $data[]     = ['field'=>'tnt_rent','title'=>'Tnt Rent','sortable'=>true,'width'=>50,'hWidth'=>55];
    $data[]     = ['field'=>'hud','title'=>'HUD','sortable'=>true,'width'=>50,'hWidth'=>55];
    $data[]     = ['field'=>'balanceFwd','title'=>'BalFwd','sortable'=>true,'width'=>50,'hWidth'=>55];
    $data[]     = ['field'=>'charge','title'=>'Charge','sortable'=>true,'width'=>50,'hWidth'=>55];
    $data[]     = ['field'=>'cash','title'=>'Cash','sortable'=>true,'width'=>50,'hWidth'=>55];
    $data[]     = ['field'=>'endBalance','title'=>'EndBal','sortable'=>true,'width'=>65,'hWidth'=>55];
    $data[]     = ['field'=>'move_in_date','title'=>'Move In','sortable'=>true,'hWidth'=>50];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getPropGroup() {
    $r = [];
    $search = Elastic::searchQuery([
      'index'=>T::$groupView,
      '_source'=>['prop'],
      "sort"=>['prop.keyword']
    ]);
    if(!empty($search['hits']['hits'])){
      $data = Helper::getElasticResult($search);
      foreach($data as $i=>$val){
        $r[$val['_source']['prop']] = $val['_source']['prop']; 
      }
    }
    return $r;
  }
//------------------------------------------------------------------------------
  private function _getRule(){
    return [
      'type'         => 'nullable|string|between:4,6',
      'city'         => 'nullable|string',
      'dateRange'    => 'required|string|between:21,23',
      'prop'         => 'nullable|string',
      'group1'       => 'nullable|string',
      'prop_type'    => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getTenantRent($source){
    $hud = $tenantRent = 0;
    $billing = Helper::getValue(T::$billing,$source,[]);
    foreach($billing as $i=>$v) {
      if($v['stop_date'] == '9999-12-31' && $v['schedule'] == 'M') {
        if($v['service_code'] == 'HUD') {
          $hud = $v['amount'];
        } else if( ($v['service_code'] == '602' && !preg_match('/MJC[1-9]+/', $source['prop'])) || ($v['service_code'] == '633' && preg_match('/MJC[1-9]+/', $source['prop'])) ) {
          $tenantRent += $v['amount'];
        }
      }
    }
    return ['tntRent'=>$tenantRent,'HUD'=>$hud];
  }
//------------------------------------------------------------------------------
  private function _validateProp($vData){
    if(empty($vData['group1']) && empty($vData['city'])){
      $props = Helper::explodeField($vData,['prop'])['prop'];
      if(count($props) > $this->_maxProps){
        Helper::echoJsonError(Html::errMsg('Error: You Selected ' . count($props) . ' Properties. The maximum is ' . $this->_maxProps));
      }
    }
  }
}
