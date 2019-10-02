<?php
namespace App\Http\Controllers\Report\GeneralLedgerReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, HelperMysql, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};
use App\Http\Models\{Model}; // Include the models class

class GeneralLedgerReportController extends Controller {
  public $typeOption  = ['gl_acct'=>'Gl Acct.'];
  private $_viewTable = '';
  private $_mapping   = [];
  private static $_instance;
  public function __construct(){
    $this->_viewTable = T::$glTransView;
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
      'rawReq'      => $req->all(),
      'rule'        => $this->_getRule(),
      'includeCdate'=>0,
    ]);
    return $this->getData($valid);
  }
//------------------------------------------------------------------------------
  public function create(Request $req){
    $fields = [
      'type'         => ['id'=>'type','label'=>'Group By','type'=>'option', 'option'=>$this->typeOption, 'req'=>1],
      'dateRange'    => ['id'=>'dateRange','name'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange','req'=>1],
      'prop'         => ['id'=>'prop','label'=>'Prop', 'type'=>'textarea', 'value'=>'0001-9999', 'req'=>1],
      'glRange'      => ['id'=>'glRange','label'=>'Gl Acct','type'=>'textarea','placeHolder'=>'Ex. 400,602-607','req'=>0],
      'cons1'        => ['id'=>'cons1','label'=>'Owner','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, Z64'],
      'batch'        => ['id'=>'batch','label'=>'Batch','type'=>'text','req'=>0],
      'trust'        => ['id'=>'trust','label'=>'Trust','type'=>'textarea', 'placeHolder'=>'Ex. ****83-**83, *ZA67'],
      'prop_type'    => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type'],'req'=>0],
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList()];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData   = $valid['data'];
    $op      = $valid['op'];
    $type    = isset($this->typeOption[$vData['type']]) ? $vData['type'] : 'gl_acct';
    $prop    = Helper::explodeField($vData,['prop','prop_type','trust','cons1'])['prop'];
    
    $vData  += Helper::splitDateRate($vData['dateRange'],'date1');
    $vData  += Helper::getGlAcctRange($vData);
    unset($vData['dateRange']);
    $columnReportList = $this->_getColumnButtonReportList($type);
    $column = $columnReportList['columns'];
    $sortKey = $type . '.keyword';
    $glRange = Helper::getValue('glRange',$vData,'100-999');

    if(!empty($op)){
      $field = P::getSelectedField($columnReportList, 1);
      $r = Elastic::searchQuery([
        'index'  =>$this->_viewTable,
        '_source'=>array_merge($field, ['amount','prop','bank','gl_acct_num']),
        'sort'   =>['date1'=>'asc',$sortKey=>'asc','prop.keyword'=>'asc'],
        'query'  =>[
          'raw'  => [
              'must' => array_merge([
                [
                  'terms' => [
                    'prop.keyword' => $prop,
                  ]
                ],
                [
                  'bool'  => [
                    'should' => [
                      Helper::explodeGlAcct($glRange),
                    ]
                  ]
                ],
                [
                  'range' => [
                    'date1' => [
                      'gte'    => $vData['date1'],
                      'lte'    => $vData['todate1']
                    ]
                  ]
                ]
              ],!empty($vData['batch']) ? [['term'=>['batch'=>$vData['batch']]]] : [])
            ]
          ]
        ]
      );
      $r['prop']    = $prop;
      $r['groupBy'] = $type;
      $r['glRange'] = $glRange;
      $gridData  = $this->_getGridData($r,$vData);
      switch ($op) {
        case 'show':  return $gridData; 
        case 'csv':   return P::getCsv($gridData, ['column'=>$column]);
        case 'pdf':   return P::getPdf(P::getPdfData($gridData, $column), ['title'=>'General Ledger Report By ' . $this->typeOption[$vData['type']]]);
      }
    }
  }  
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'dateRange'      => 'required|string|between:21,23',
      'prop'           => 'nullable|string',
      'cons1'          => 'nullable|string',
      'batch'          => 'nullable|string',
      'type'           => 'nullable|string|between:4,10',
      'trust'          => 'nullable|string',
      'glRange'        => 'nullable|string',
      'prop_type'      => 'nullable|string|between:0,1',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($type = 'city'){
    $reportList = [
      'pdf'  =>'Download PDF',
      'csv'  =>'Download CSV',
    ];
    
    $data = [];
    $data[]   = ['field'=>'date1','title'=>'Date','sortable'=>true,'width'=>90,'hWidth'=>55];
    $data[]   = ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>40,'hWidth'=>35];
    $data[]   = ['field'=>'check_no','title'=>'Check No','sortable'=>true,'width'=>100,'hWidth'=>50];
    $data[]   = ['field'=>'gl_acct','title'=>'Account','sortable'=>true,'width'=>120,'hWidth'=>80];
    $data[]   = ['field'=>'remark','title'=>'Description','sortable'=>true,'width'=>500,'hWidth'=>185];
    $data[]   = ['field'=>'debit','title'=>'Debit','sortable'=>true,'width'=>35,'hWidth'=>70];
    $data[]   = ['field'=>'credit','title'=>'Credit','sortable'=>true,'width'=>35,'hWidth'=>70];
    $data[]   = ['field'=>'balance','title'=>'Balance','sortable'=>true,'width'=>35,'hWidth'=>70];
    $data[]   = ['field'=>'vendor','title'=>'Ref 2 / Cons','sortable'=>true,'width'=>50,'hWidth'=>60];
    $data[]   = ['field'=>'journal','title'=>'Jnl','sortable'=>true,'width'=>20,'hWidth'=>30];
    $data[]   = ['field'=>'batch','title'=>'Batch','sortable'=>true,'width'=>25,'hWidth'=>50];
    $data[]   = ['field'=>'usid','title'=>'Usid','sortable'=>true,'hWidth'=>60];
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r,$vData=[]){
    $props     = $r['prop'];
    $result         = Helper::getElasticResult($r); 
    $groupByType    = $r['groupBy'];
    $rows = $propGl = $titleKeys = $data = $rBalanceFwd = [];
   
    foreach($result as $i => $val){
      $v              = $val['_source'];
      $amount         = $v['amount'];
      $glAcct         = $v['gl_acct'];
      $data[$glAcct][]= Helper::selectData(['usid','date1','check_no','gl_acct','remark','vendor','journal','batch','bank','prop'],$v) + [
        'debit'       => $amount > 0 ? $amount : 0,
        'credit'      => $amount < 0 ? $amount : 0,
        'balance'     => $amount,
      ];
      
      $propIndexKey          = is_numeric($v['prop']) && $v['prop'] >= 1 && $v['prop'] <= 9999 ? 'Z64' : $v['prop'];
      $propGl[$propIndexKey] = [];
    }
    
    $rProp     = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'      => T::$propView,
      '_source'    => ['prop','bank.bank','bank.cp_acct','bank.cr_acct'],
      'query'      => [
        'must'     => [
          'prop.keyword'   => $props,
        ],
        'must_not' => [
          'prop_class.keyword' => 'X'
        ]
      ]
    ]),'prop');
    
    //Get balance forward data
    $rAggs    = Helper::getElasticAggResult(Elastic::searchQuery([
      'index'      => T::$glTransView,
      'size'       => 0,
      'query'  =>[
        'raw'  => [
          'must' => array_merge([
            [
              'terms' => [
                'prop.keyword' => $props,
              ]
            ],
            [
              'bool'  => [
                'should' => [
                  Helper::explodeGlAcct(Helper::getValue('glRange',$r,'100-999')),
                ]
              ]
            ],
            [
              'range' => [
                'date1' => [
                  'lt'    => $vData['date1'],
                ]
              ]
            ]
          ],!empty($vData['batch']) ? [['term'=>['batch'=>$vData['batch']]]] : [])
        ]
      ],
      'aggs'       => [
        'by_gl_acct'  => [
          'terms'     => [
            'field'   => 'gl_acct.keyword',
          ],
          'aggs'      => [
            'balFwd'  => [
              'sum'   => [
                'field' => 'amount',
              ]
            ]
          ]
        ]
      ]
    ]),'by_gl_acct');

    
    $glBalanceFwd = [];
    //Add balance forward data
    foreach($rAggs as $i => $v){
      $key    = $v['key'];
      $amount = $v['balFwd']['value'];
      
      //Add balance forward amount as first row item if the gl acct is already in the report.
      if(!empty($data[$key])){
        $acctList = $data[$key];
        $firstRow = $data[$key][0];
        $row      = ['date1'=>$vData['date1'],'remark'=>'Balance Forward','debit'=>0,'credit'=>0,'balance'=>$amount] + Helper::selectData(['journal','prop','bank'],$firstRow);
        $data[$key] = array_merge([$row],$data[$key]);
      } else {
      //Otherwise simply add the gl acct with balance forward as its only data row / item
        $data[$key][] = ['date1'=>$vData['date1'],'remark'=>'Balance Forward','debit'=>0,'credit'=>0,'balance'=>$amount,'journal'=>'','bank'=>'','prop'=>''];
      }
      $glBalanceFwd[$key] = $amount;
    }
    
    
    //Capture and all zero dollar balance forwards for gl acct that do not have transactions before start date. 
    foreach($data as $k => $v){
      if(!isset($glBalanceFwd[$k])){
        $acctList   = $v;
        $firstRow   = $v[0];
        $row        = ['date1'=>$vData['date1'],'remark'=>'Balance Forward','debit'=>0,'credit'=>0,'balance'=>0] + Helper::selectData(['journal','prop','bank'],$firstRow);
        $data[$k] = array_merge([$row],$v);
      }
    }
    
    ksort($data);
    foreach($propGl as $k => $v){
      $r = Helper::keyFieldName(HelperMysql::getGlChat(Model::buildWhere(['g.prop'=>$k]),['g.gl_acct','g.title']),'gl_acct','title');
      $propGl[$k] = $r;
    }

    $grandTotal   = ['remark'=>Html::b(Html::u('Total Amounts')),'debit'=>0,'credit'=>0,'balance'=>0];
    $num          = 0;
    foreach($data as $acct => $val){
      $balance      = 0;
      $titleIdx     = $num;
      $rows[$num++] = ['date1' => Html::b($acct),'remark'=>''];
      $acctSum      = ['gl_acct'=>Html::b($acct),'remark'=>'','debit'=>0,'credit'=>0,'balance'=>0];
      foreach($val as $i => $v){
        $prop                = Helper::getValue($v['prop'],$rProp,[]);
        $balance            += $v['balance'];
        $acctSum['debit']   += $v['debit'];
        $acctSum['credit']  += $v['credit'];
        $acctSum['balance'] += $v['balance'];
        
        $grandTotal['debit']   += $v['debit'];
        $grandTotal['credit']  += $v['credit'];
        $grandTotal['balance'] += $v['balance'];
        
        $lastProp               = $v['prop'];
        $rows[$num++]           = Helper::selectData(['check_no','vendor','prop','journal','batch'],$v) + [
          'date1'        => Format::usDate($v['date1']),
          'gl_acct'      => $this->_getAccountNum($v,$prop),
          'remark'       => title_case($v['remark']),
          'debit'        => Format::usMoney($v['debit']),
          'credit'       => Format::usMoney($v['credit']),
          'balance'      => Format::usMoney($balance),
          'usid'         => Helper::getValue(0,explode('@',Helper::getValue('usid',$v))),
        ];
      }
      
      $propGlKey       = is_numeric($lastProp) && $lastProp >= 1 && $lastProp <= 9999 ? 'Z64' : $lastProp;
      $title           = Html::b(!empty($propGl[$propGlKey][$acct]) ? $propGl[$propGlKey][$acct] : '');
      $rows[$titleIdx]['remark'] = $title;
      $rows[$num++] = [
        'gl_acct'  => $acctSum['gl_acct'],
        'remark'   => $title,
        'debit'    => Html::b(Format::usMoney($acctSum['debit'])),
        'credit'   => Html::b(Format::usMoney($acctSum['credit'])),
        'balance'  => Html::b(Format::usMoney($acctSum['balance']))
      ];
      $rows[$num++] = [];
    }
    
    $grandTotal['debit']    = Html::b(Html::u(Format::usMoney($grandTotal['debit'])));
    $grandTotal['credit']   = Html::b(Html::u(Format::usMoney($grandTotal['credit'])));
    $grandTotal['balance']  = Html::b(Html::u(Format::usMoney($grandTotal['balance'])));
    $rows[] = $grandTotal;
    return $rows;
  }
//------------------------------------------------------------------------------
  private function _getAccountNum($source,$prop){
    $journal   = $source['journal'];
    $bankNum   = $source['bank'];
    $rBank     = Helper::keyFieldName(Helper::getValue(T::$bank,$prop,[]),'bank');
    $bank      = Helper::getValue($bankNum,$rBank,[]);
    $suffix    = '';
    switch($journal){
      case 'CP' : $suffix = Format::bankAccountDisplayFormat(Helper::getValue('cp_acct',$bank)); break;
      default   : $suffix = Format::bankAccountDisplayFormat(Helper::getValue('cr_acct',$bank)); break;
    }
    return $suffix;
  }
}

