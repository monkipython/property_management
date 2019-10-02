<?php
namespace App\Http\Controllers\Report\SupervisorReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Graph,Html, GridData, TableName AS T, Helper};
use App\Http\Models\ReportModel as M;

class SupervisorReportController extends Controller {
  private $_viewTable  = '';
  private $_maxSize    = 5000;
  private $_mapping    = [];
  private static $_instance;

  public function __construct(){
    $this->_viewTable = T::$tntTransView;
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$prop]);
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
      'rawReq'       => $req->all(),
      'rule'         => $this->_getRule(),
      'includeCdate' => 0,
    ]);
    
    return $this->getData($valid);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $fields = [
      'dateRange'    => ['id' => 'dateRange','name'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange','req'=>1],
      'group1'       => ['id'=>'group1','name'=>'group1','label'=>'Group','type'=>'textarea','placeHolder'=>'Ex. #S82-#S93,#S96'],
      'prop_type'    => ['id'=>'prop_type','name'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>['' => 'Select Property Type'] + $this->_mapping['prop_type']]
    ];
    return ['html' => implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList($req)];
  }
//-------------------------------------------------------------------------------
  public function getForm($valid){
    $fields = [
      'dateRange'    => ['id'=>'dateRange','name'=>'dateRange','type'=>'hidden','req'=>1,'value'=>'01/01/1989 - ' . date('m/d/Y',strtotime('+1 year'))],
    ];    

    return ['html' => implode('',Form::generateField($fields))];
  }
//-------------------------------------------------------------------------------
  public function getData($valid){
    $vData = !empty($valid['data']) ? $valid['data'] : [];
    $op    = $valid['op'];
   
    $vData += !empty($vData['dateRange']) ? Helper::splitDateRate($vData['dateRange'],'date1') : [];
    $vData['prop']   = Helper::explodeField(['prop'=>'0001-9999'] + $vData,['group1','prop_type'])['prop'];
    //$vData['prop'] = !empty($vData['group1']) ? Helper::explodeField($vData,['group1'])['prop'] : Helper::explodeProp('0001-9999')['prop']; 
    unset($vData['dateRange'],$vData['group1']);
    
    $columnReportList = $this->_getColumnButtonReportList($valid);
    $column = $columnReportList['columns'];
    if(!empty($op)){
      $r = Elastic::searchQuery([
        'index'    => $this->_viewTable,
        'size'     => 0,
        'query'    => [
          'must' => [
            'gl_acct.keyword' => '602',
            'prop'            => $vData['prop'],
            'range'           => [
              'date1' => [
                'format' => 'yyyy-MM-dd',
                'gte'    => $vData['date1'],
                'lte'    => $vData['todate1'],
              ]
            ]
          ]
        ],
        'aggs'      => $this->_getAggregationJson($this->_viewTable,$vData),
      ]);
      $gridData = $this->_getGridData($r,$vData);
      switch ($op) {
        case 'show': return $gridData;
        case 'graph': return $this->_getGraphData($r,$vData);
        case 'csv':  return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':  return P::getPdf(P::getPdfData($gridData, $column), ['title'=>'Supervisor Report']);
      }
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'dateRange'    => 'required|string|between:21,23',
      'group1'       => 'nullable|string',
      'prop_type'    => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($req){
    $reportList = [
      'pdf' => 'Download PDF',
      'csv' => 'Download CSV'
    ];
    $data = [];
    
    $data[] = ['field'=>'group1','title'=>'Group','sortable'=>true,'width'=>280,'hWidth'=>150];
    $data[] = ['field'=>'total','title'=>'Total','sortable'=>true,'width'=>50,'hWidth'=>50];
    $data[] = ['field'=>'potential','title'=>'Potential','sortable'=>true,'width'=>50,'hWidth'=>50];
    $data[] = ['field'=>'goal','title'=>'Goal','sortable'=>true,'width'=>30,'hWidth'=>60];
    $data[] = ['field'=>'collected','title'=>'Collected','sortable'=>true,'width'=>50,'hWidth'=>50];
    $data[] = ['field'=>'percent','title'=>'%','sortable'=>true,'width'=>30,'hWidth'=>40];
    $data[] = ['field'=>'percent2','title'=>'%','sortable'=>true,'width'=>30,'hWidth'=>40];
    $data[] = ['field'=>'percent3','title'=>'%','sortable'=>true,'width'=>30,'hWidth'=>40];
    $data[] = ['field'=>'difference','title'=>'Difference','sortable'=>true,'width'=>40,'hWidth'=>60];
    $data[] = ['field'=>'move_in_count','title'=>'Move In','sortable'=>true,'width'=>30,'hWidth'=>50];
    $data[] = ['field'=>'move_out_count','title'=>'Move Out','sortable'=>true,'width'=>30,'hWidth'=>50];
    $data[] = ['field'=>'vacant_count','title'=>'Vact.','sortable'=>true,'width'=>30,'hWidth'=>50];
    $data[] = ['field'=>'eviction_count','title'=>'Eviction','sortable'=>true,'width'=>30,'hWidth'=>60];
    $data[] = ['field'=>'percent4','title'=>'%','sortable'=>true,'hWidth'=>30];

    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r,$vData){
    $result      = Helper::getElasticAggResult($r,'by_group1');
    $data        = [];
    $rGroupList  = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      'size'     => $this->_maxSize,
      '_source'  => ['group1','prop_name'],
      'sort'     => ['group1.keyword'=>'asc'],
      'query'    => [
        'must'   => [
          'prop' => $vData['prop'],
        ]
      ]
    ]),'group1','prop_name');
    
    $rTenantGroupList = Helper::keyFieldName(Helper::getElasticAggResult(Elastic::searchQuery([
      'index'     => T::$tenantView,
      'size'      => 0,
      'query'     => [
        'must'    => [
          'prop'  => $vData['prop']
        ]
      ],
      'aggs'      => $this->_getAggregationJson(T::$tenantView,$vData)
    ]),'by_group1'),'key');
      
      $rUnitGroupList   = Helper::keyFieldName(Helper::getElasticAggResult(Elastic::searchQuery([
        'index'       => T::$unitView,
        'size'        => 0,
        'query'       => [
          'must'      => [
            'prop.prop' => $vData['prop'], 
          ]
        ],
        'aggs'        => $this->_getAggregationJson(T::$unitView,$vData),
      ]),'by_group1'),'key');

      
    $lastRow       = ['group1'=>'','total'=>0,'collected'=>0,'potential'=>0,'difference'=>0,'move_in_count'=>0,'move_out_count'=>0,'vacant_count'=>0,'eviction_count'=>0];
    $grandTotal    = ['group1'=>Html::b('Total: '),'total'=>0,'collected'=>0,'potential'=>0,'difference'=>0,'move_in_count'=>0,'move_out_count'=>0,'vacant_count'=>0,'eviction_count'=>0];
   
    foreach($result as $i => $groupBucket){
      $group       = $groupBucket['key'];
      $collected   = $groupBucket['collected_agg']['collected']['value'];
      $unitTotal   = !empty($rUnitGroupList[$group]['doc_count']) ? $rUnitGroupList[$group]['doc_count'] : 0;
      $potential   = !empty($rTenantGroupList[$group]['potential']['value']) ? $rTenantGroupList[$group]['potential']['value'] : 0;
      $moveIn      = !empty($rTenantGroupList[$group]['move_in_count']['doc_count']) ? $rTenantGroupList[$group]['move_in_count']['doc_count'] : 0;
      $moveOut     = !empty($rTenantGroupList[$group]['move_out_count']['doc_count']) ? $rTenantGroupList[$group]['move_out_count']['doc_count'] : 0;
      $evictCount  = !empty($rTenantGroupList[$group]['eviction_count']['doc_count']) ? $rTenantGroupList[$group]['eviction_count']['doc_count'] : 0; 
      $vacantCount = !empty($rUnitGroupList[$group]['vacant_filter']['doc_count']) ? $rUnitGroupList[$group]['vacant_filter']['doc_count'] : 0;
      $percent     = (abs($collected) / (!empty($potential) ? $potential : 1)) * 100.0;

      $goal        = 0.95 * 100.0;
      $percent3    = 0.95 * 100.0;
      $percent4    = 0.95 * 100.0;
      $percent1    = 0.95 * 100.0;
      
      $difference  = ($goal - $percent1) * $potential;

      $data[] = [
        'group1'          => !empty($rGroupList[$group]) ? $group . ': ' . $rGroupList[$group] : $group,
        'goal'            => number_format($goal,2) . '%',
        'percent'         => number_format($percent1 <= 100 ? $percent1 < 0 ? 0 : $percent1 : 100,2) . '%',
        'total'           => $unitTotal,
        'collected'       => Format::usMoney($collected),
        'potential'       => Format::usMoney($potential),
        'difference'      => $difference > 0 ? Format::usMoney($difference) : 'Done',
        'percent2'        => number_format($percent,2) . '%',
        'percent3'        => number_format($percent3,2) . '%',
        'percent4'        => number_format($percent4,2) . '%',
        'move_in_count'   => $moveIn,
        'move_out_count'  => $moveOut,
        'eviction_count'  => $evictCount,
        'vacant_count'    => $vacantCount
      ];

      $grandTotal['total']          += $unitTotal;
      $grandTotal['collected']      += $collected;
      $grandTotal['potential']      += $potential;
      $grandTotal['difference']     += $difference > 0 ? $difference : 0;
      $grandTotal['move_out_count'] += $moveOut;
      $grandTotal['move_in_count']  += $moveIn;
      $grandTotal['eviction_count'] += $evictCount;
      $grandTotal['vacant_count']   += $vacantCount;
    }
    
    
    $grandPercent                   = ($grandTotal['collected'] / (!empty($grandTotal['potential']) ? $grandTotal['potential'] : 1)) * 100.0;
    $grandTotal['total']            = Html::b(Html::u($grandTotal['total']));
    $grandTotal['percent']          = Html::b(Html::u(number_format($grandPercent <= 100 ? $grandPercent < 0 ? 0 : $grandPercent : 100,2) . '%'));
    $grandTotal['collected']        = Html::b(Html::u(Format::usMoney($grandTotal['collected'])));
    $grandTotal['potential']        = Html::b(Html::u(Format::usMoney($grandTotal['potential'])));
    $grandTotal['difference']       = $grandTotal['difference'] > 0 ? Html::b(Html::u(Format::usMoney($grandTotal['difference']))) : Html::b(Html::u('DONE'));
    $grandTotal['move_out_count']   = Html::b(Html::u($grandTotal['move_out_count']));
    $grandTotal['move_in_count']    = Html::b(Html::u($grandTotal['move_in_count']));
    $grandTotal['eviction_count']   = Html::b(Html::u($grandTotal['eviction_count']));
    $grandTotal['vacant_count']     = Html::b(Html::u($grandTotal['vacant_count']));

    $data[] = $grandTotal;
    return P::getRow($data,$lastRow);
  }
//------------------------------------------------------------------------------
  private function _getGraphData($r,$vData){
     $result = Helper::getElasticAggResult($r,'by_group1');
     $dataset   = $data = $groupKeys = $potentialList = $collectedList = [];

     $rTenantGroupList   = Helper::keyFieldName(Helper::getElasticAggResult(Elastic::searchQuery([
       'index'   => T::$tenantView,
       'size'    => 0,
       'query'   => [
         'must'  => [
           'prop'=> $vData['prop'],
         ]
       ],
       'aggs'    => $this->_getAggregationJson(T::$tenantView,$vData),
     ]),'by_group1'),'key');

     foreach($result as $groupBucket){
       $group             = $groupBucket['key'];
       $collected         = abs($groupBucket['collected_agg']['collected']['value']);
       $potential         = !empty($rTenantGroupList[$group]['potential']['value']) ? $rTenantGroupList[$group]['potential']['value'] : 0;
       $groupKeys[]       = $group;
       $potentialList[]   = $potential;
       $collectedList[]   = $collected;
     }
    
     $dataset[] = ['label'=>'Potentials','data'=>$potentialList];
     $dataset[] = [
       'label' => 'Collections',
       'data'  => $collectedList,
       'params'=> [
         'backgroundColor'  => 'rgba(54, 162, 235, 0.5)',
         'borderColor'      => 'rgb(54, 162, 235)'
       ]
     ];
    
     $graphOptions   = Graph::getGraphSettings([
       'labels'     => $groupKeys,
       'graphType'  => 'horizontalBar',
       'dataset'    => $dataset,
       'title'      => 'Group Earnings as of ' . Helper::fullDate(),
       'yLabel'     => 'Groups',
       'xLabel'     => 'Total Revenue',
       'options'    => [
         'elements' => [
           'rectangle' => [
             'borderWidth' => 2
           ]
         ]
       ],
       'datalabels' => [
         'anchor'   => 'end',
         'align'    => 'end',
         'font'     => [
           'size'  => 12
         ]
       ],
     ]);
    
     return [
       'options'         => $graphOptions,
       'transformLabels' => 'currencyXAxes'
     ];
  }
//------------------------------------------------------------------------------
  private function _getAggregationJson($name,$vData){
    $data = [
      T::$tntTransView => [
          'by_group1' => [
            'terms' => [
              'field'=>'group1.keyword'
            ],
            'aggs' => [
              'collected_agg'=>[
                'filter' => [
                  'match'=>['tx_code'=>'P']
                ],
                'aggs' => [
                  'collected' => [
                    'sum' => [
                      'field' => 'amount'
                    ]
                  ]
                ]
              ],
              'group1_sort' => [
                'bucket_sort' => [
                  'sort' => [['_key'=>['order'=>'asc']]]
                ]
              ]
            ]
          ]
        ],
      T::$tenantView => [
          'by_group1' => [
            'terms' => [
              'field' => 'group1.keyword'
            ],
            'aggs' => [
              'potential' => [
                'sum' => [
                  'field' => 'base_rent'
                ]
              ],
              'eviction_count' => [
                'filter' => [
                  'bool' => [
                    'must' => [
                      ['term'  => ['spec_code.keyword' => 'E']],
                      ['range'  => [
                        'move_out_date' => [
                          'gte'    => $vData['date1'],
                          'lte'    => $vData['todate1'],
                          'format' => 'yyyy-MM-dd'
                        ]
                      ]]
                    ]
                  ]
                ]
              ],
              'move_in_count' => [
                'filter' => [
                  'range' => [
                    'move_in_date' => [
                      'gte'   => $vData['date1'],
                      'lte'   => $vData['todate1'],
                      'format'=> 'yyyy-MM-dd'
                    ]
                  ]
                ]
              ],
              'move_out_count' => [
                'filter' => [
                  'range' => [
                    'move_out_date' => [
                      'gte'    => $vData['date1'],
                      'lte'    => $vData['todate1'],
                      'format' => 'yyyy-MM-dd'
                    ]
                  ]
                ]
              ],
              'group1_sort' => [
                'bucket_sort' => [
                  'sort' => [['_key' => ['order'=>'asc']]]
                ]
              ]
            ]
          ]
        ],
      T::$unitView  => [
        'by_group1' => [
          'terms' => [
            'field' => 'prop.group1.keyword'
          ],
          'aggs' => [
            'vacant_filter' => [
              'filter' => [
                'bool' => [
                  'must' => [
                    [
                      'term' => [
                        'status.keyword' => 'V',
                      ]
                    ]
                  ]
                ]   
              ]
            ],
            'group1_sort' => [
              'bucket_sort' => [
                'sort' => [['_key' => ['order'=>'asc']]]
              ]
            ]
          ]
        ]
      ]
    ];
    return !empty($data[$name]) ? $data[$name] : [];
  }
}
