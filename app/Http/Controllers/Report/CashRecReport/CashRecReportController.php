<?php
namespace App\Http\Controllers\Report\CashRecReport;
use Illuminate\Http\Request;
use App\Http\Controllers\Report\ReportController as P;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Format,Elastic, Html, GridData, TableName AS T, Helper};
use App\Http\Models\ReportModel AS M; // Include the models class
/*
INSERT INTO `ppm`.`accountProgram` (`accountProgram_id`, `category`, `categoryIcon`, `module`, `moduleDescription`, `moduleIcon`, `section`, `sectionIcon`, `classController`, `method`, `programDescription`, `cdate`, `udate`, `active`) VALUES ('0', 'Report', 'fa fa-fw fa-file-pdf-o', 'report', 'Report', 'fa fa-fw fa-cog', 'Report', 'fa fa-fw fa-users', 'cashRecReport', '', 'To Access Cash Receipt Report', '2019-06-17 23:45:53', '2019-06-17 23:45:53', '1');
*/

class CashRecReportController extends Controller {
  public $typeOption           = ['trust' => 'Trust','group1'=>'Group','prop'=>'Prop'];
  private $_tabs               = [];
  private $_viewTable          = '';
  private $_mapping            = [];
  private static $_numSpaces   = 3;
  private static $_instance;
  
  public function __construct(){
    $this->_viewTable = T::$glTransView;
    $this->_mapping   = Helper::getMapping(['tableName'=>T::$prop]);
    $this->_tabs      = [
      'Detail'          => 'Detail',
      'Bank-Batch'      => 'By Bank Batch',
      'Bank'            => 'By Bank',
    ];
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
    $fields  = [
      'type'      => ['id'=>'type','label'=>'Group By','type'=>'option', 'option'=>$this->typeOption, 'req'=>1],
      'dateRange' => ['id'=>'dateRange','name'=>'dateRange','label'=>'From/To Date','type'=>'text','class'=>'daterange','req'=>1],
      'trust'     => ['id'=>'trust','label'=>'Trust','type'=>'textarea','placeHolder'=>'Ex. ****83-**83, *ZA67'],
      'batch'     => ['id'=>'batch','name'=>'batch','label'=>'From Batch','type'=>'text'],
      'to_batch'  => ['id'=>'to_batch','name'=>'to_batch','label'=>'To Batch','type'=>'text'],
      'prop_type' => ['id'=>'prop_type','label'=>'Property Type','type'=>'option','option'=>[''=>'Select Property Type'] + $this->_mapping['prop_type']],
    ];
    return ['html'=>implode('',Form::generateField($fields)),'column'=>$this->_getColumnButtonReportList(),'tab'=>[]];
  }
//------------------------------------------------------------------------------
  public function getData($valid){
    $vData          = $valid['data'];
    $op             = $valid['op'];
    $type           = isset($this->typeOption[$vData['type']]) ? $vData['type'] : 'trust';
    $vData['prop']  = Helper::explodeField($vData,['trust','prop_type'])['prop'];
    $vData         += Helper::splitDateRate($vData['dateRange'],'date1');
    unset($vData['dateRange']);
    $tabOption          = Helper::getValue('selected',$vData,'Detail');
    $columnReportList = $this->_getColumnButtonReportList($tabOption);
    $column           = $columnReportList['columns'];
    $sortKey  = $type !== 'trust' ? $type : 'prop';
    $mustQuery= [
      'must'  => [
        [
          'terms' => ['prop.keyword'=>$vData['prop']],
        ],
        [
          'term'  => [
            'journal.keyword' => 'CR',
          ]
        ],
        [
          'range' => [
            'date1'   => [
              'gte'   => $vData['date1'],
              'lte'   => $vData['todate1'],
            ]
          ]
        ]
      ],
      'must_not'  => [
        [
          'terms'     => ['acct_type.keyword'=>['A']],
        ]
      ]
    ];
    
    $batchRange                     = ['range'=>['batch'=>[]]];
    $batchRange['range']['batch']  += !empty($vData['batch']) ? ['gte'=>$vData['batch']] : [];
    $batchRange['range']['batch']  += !empty($vData['to_batch']) ? ['lte'=>$vData['to_batch']] : [];
    
    $mustQuery['must']  = array_merge($mustQuery['must'], !empty($batchRange['range']['batch']) ? [$batchRange] : []);
    
    
    
    $queryBody= [
      'index'   => $this->_viewTable,
      'sort'    => [$sortKey . '.keyword'=>'asc','bank.keyword'=>'asc','check_no.keyword'=>'asc','batch'=>'asc','prop.keyword'=>'asc'],
      '_source' => array_merge(P::getSelectedField($this->_getColumnButtonReportList(),1),['bank','group1','prop','unit','tenant','job']),
      'query'   => [
        'raw'   => $mustQuery,
      ]
    ];
    if(!empty($op)){
      $r        = Elastic::searchQuery($queryBody);
      switch ($op) {
        case 'tab'            : return $this->_getTabData($type);
        case 'show'           : return $this->_getGridData($r,$type,$vData,$tabOption); 
        case 'csv'            : return P::getCsv($this->_getGridData($r,$type,$vData,'Detail'), ['column'=>$column]);
        case 'printDetail'    : return P::getPdf(P::getPdfData($this->_getGridData($r,$type,$vData,'Detail'),$column),['title'=>'Cash Receipt Report Detail By ' . $this->typeOption[$type]]);
        case 'printBankBatch' : return P::getPdf(P::getPdfData($this->_getGridData($r,$type,$vData,'Bank-Batch'),$this->_getColumnButtonReportList('Bank-Batch')['columns']),['titleSpace'=>50,'chunk'=>75,'orientation'=>'P','title'=>'Cash Receipt Report By Bank, Batch, ' . $this->typeOption[$type]]);
        case 'printBank'      : return P::getPdf(P::getPdfData($this->_getGridData($r,$type,$vData,'Bank'),$this->_getColumnButtonReportList('Bank')['columns']),['titleSpace'=>55,'chunk'=>75,'orientation'=>'P','title'=>'Cash Receipt Report By Bank, ' . $this->typeOption[$type]]);
      }
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'report'    => 'required|string',
      'trust'     => 'nullable|string',
      'type'      => 'required|string|between:4,6',
      'dateRange' => 'required|string|between:21,23',
      'batch'     => 'nullable|string',
      'to_batch'  => 'nullable|string',
      'prop_type' => 'nullable|string|between:0,1',
      'selected'  => 'nullable|string',
    ] + GridData::getRuleReport();
  }
//------------------------------------------------------------------------------
  private function _getColumnButtonReportList($tab='Detail',$type='trust'){
    $data       = [];
    $reportList = ['printDetail'=>'Print Report Detail','printBankBatch'=>'Print Report By Bank and Batch','printBank'=>'Print Report By Bank','csv'=>'Export Detail to CSV'];
    switch(($tab)){
      case 'Bank':
        $data[]   = ['field'=>$type,'title'=>$this->typeOption[$type],'sortable'=>true,'width'=>120,'hWidth'=>80];
        $data[]   = ['field'=>'bank','title'=>'Bank','sortable'=>true,'width'=>300,'hWidth'=>220];
        $data[]   = ['field'=>'amount','title'=>'Total Amount','sortable'=>true,'hWidth'=>300];
        break;
      case 'Bank-Batch':
        $data[]   = ['field'=>$type,'title'=>$this->typeOption[$type],'sortable'=>true,'width'=>120,'hWidth'=>80];
        $data[]   = ['field'=>'bank','title'=>'Bank','sortable'=>true,'width'=>300,'hWidth'=>200];
        $data[]   = ['field'=>'batch','title'=>'Batch/Check No','sortable'=>true,'width'=>200,'hWidth'=>140];
        $data[]   = ['field'=>'amount','title'=>'Total Amount','sortable'=>true,'hWidth'=>240];
        //$data[] = ['field'=>'batch','title'=>'Total Amount','sortable'=>true,'hWidth'=>1000];
        break;
      case 'Detail':
        $data[] = ['field'=>'batch','title'=>'Batch','sortable'=>true,'width'=>400,'hWidth'=>220];
        $data[] = ['field'=>'check_no','title'=>'Check#','sortable'=>true,'width'=>100,'hWidth'=>40];
        $data[] = ['field'=>'invoice','title'=>'Item#','sortable'=>true,'width'=>50,'hWidth'=>50];
        $data[] = ['field'=>'prop','title'=>'Prop','sortable'=>true,'width'=>40,'hWidth'=>40];
        $data[] = ['field'=>'gl_acct','title'=>'GL','sortable'=>true,'width'=>40,'hWidth'=>50];
        $data[] = ['field'=>'amount','title'=>'Amount','sortable'=>true,'width'=>80,'hWidth'=>50];
        $data[] = ['field'=>'remark','title'=>'Description','sortable'=>true,'width'=>240,'hWidth'=>115];
        $data[] = ['field'=>'unit','title'=>'Unit','sortable'=>true,'width'=>30,'hWidth'=>40];
        $data[] = ['field'=>'tnt_name','title'=>'Tenant Name','sortable'=>true,'width'=>250,'hWidth'=>115];
        $data[] = ['field'=>'date1','title'=>'Date','sortable'=>true,'width'=>50,'hWidth'=>50];
        $data[] = ['field'=>'usid','title'=>'Usid','sortable'=>true,'hWidth'=>60];
        break;
    }
    return ['columns'=>$data,'reportList'=>$reportList,'button'=>''];
  }
//------------------------------------------------------------------------------
  private function _getTabData($type='trust'){
    $tabData = $columnData = [];
    foreach($this->_tabs as $k => $v){
      $tabData[$k]     = Html::table('',['id'=>$k]);
      $columnData[$k]  = $this->_getColumnButtonReportList($k,$type);
    }
    $tab     = Html::buildTab($tabData,['tabClass'=>'']);
    return ['tab'=>$tab,'column'=>$columnData,'sortTabs'=>false];
  }
//------------------------------------------------------------------------------
  private function _getGroupedData($r,$type='trust',$vData=[]){
    $result    = Helper::getElasticResult($r);
    $props     = $vData['prop'];
    $rProp  = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$propView,
      '_source'   => ['prop','trust'],
      'sort'      => ['prop.keyword'=>'asc'],
      'query'     => [
        'must'    => [
          'prop'  => $props,
        ],
        'must_not'=> [
          'prop_class.keyword'=>'X',
        ]
      ]
    ]),'prop','trust');
    
    $rTenant    = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   => T::$tenantView,
      '_source' => ['prop','unit','tenant','tnt_name'],
      'query'   => [
        'must'  => [
          'prop'=> $props,
        ]
      ]
    ]),['prop','unit','tenant'],'tnt_name');
    
    $data = $banks = [];
    foreach($result as $i => $v){
      $source      = $v['_source'];
      $groupValue  = $type !== 'trust' ? Helper::getValue($type,$source) : Helper::getValue($source['prop'],$rProp);
      $bank        = $source['bank'];
      $batch       = strlen($source['batch']) >= 9 ? $source['check_no'] : $source['batch'];
      $prop        = $source['prop'];
      
      $tntName     = Helper::getValue($source['prop'] . $source['unit'] . Helper::getValue('tenant',$source,0),$rTenant);
      
      $banks[$bank]= $bank;
      $data[$groupValue][$bank][$batch][$prop][] = Helper::selectData(['batch','check_no','invoice','gl_acct','remark','amount','unit','date1','prop','tenant','usid','job'],$source) + [
        'tnt_name'   => $tntName,
      ];
    }
    return ['data'=>$data,'bank'=>array_keys($banks),'props'=>$props];
  }
//------------------------------------------------------------------------------
  private function _getGridData($r,$type='trust',$vData=[],$tabOption='Detail'){
    $data        = $this->_getGroupedData($r,$type,$vData);
    $groupedData = $data['data'];
    $banks       = $data['bank'];
    switch($tabOption){
      case 'Detail'    : return $this->_getGridDetail($groupedData,$type);
      case 'Bank-Batch': return $this->_getGridByBankBatch($groupedData,$type,$banks);
      case 'Bank'      : return $this->_getGridByBank($groupedData,$type,$banks);
    }
  }
//------------------------------------------------------------------------------
  private function _getGridDetail($groupedData,$type='trust'){
    $blankRow     = ['batch'=>'','check_no'=>'','invoice'=>'','gl_acct'=>'','amount'=>'','remark'=>'','unit'=>'','tnt_name'=>'','date1'=>''];
    $totalRow     = ['batch'=>0,'check_no'=>'','invoice'=>'','gl_acct'=>'','amount'=>'','remark'=>'','unit'=>'','tnt_name'=>'','date1'=>''];
    $blankSumRow  = ['batch'=>0,'check_no'=>'','invoice'=>'','gl_acct'=>'','amount'=>'','remark'=>'','unit'=>'','tnt_name'=>'','date1'=>''];
    
    $rows     = [];
    $num      = 0;
    foreach($groupedData as $groupValue => $value){
      $groupNum                    = $num;
      $rows[$num++]                = $blankSumRow;
      foreach($value as $bank => $val){
        $bankNum                   = $num;
        $rows[$num++]              = $blankSumRow;
        foreach($val as $batch => $vl){
          $batchNum                   = $num;
          $rows[$num++]               = $blankSumRow;
          foreach($vl as $prop => $v){
            $propNum           = $num;
            $rows[$num++]      = $blankSumRow;
            foreach($v as $i => $row){
              $amount                    = Helper::getValue('amount',$row,0);
              $rows[$groupNum]['batch'] += $amount;
              $rows[$batchNum]['batch'] += $amount;
              $rows[$bankNum]['batch']  += $amount;
              $rows[$propNum]['batch']  += $amount;
              $totalRow['batch']        += $amount;
              
              $batch                     = $row['batch'];
              $rows[$num++]              = Helper::selectData(['check_no','invoice','gl_acct','unit','prop'],$row)  + [
                'amount'     => Format::usMoney($amount),
                'batch'      => Html::repeatChar('&nbsp;',12)  . ($row['batch'] >= 100000000 && !empty($row['job']) ? Html::span(Html::i('',['class'=>'fa fa-fw fa-dollar']) . ' ' . $batch . ' ' . Html::i('',['class'=>'fa fa-fw fa-download']),['class'=>'rpsViewClick clickable pointer text_underline text-green']) : $batch),
                'remark'     => title_case(Helper::getValue('remark',$row)),
                'tnt_name'   => $row['tnt_name'],
                'date1'      => Format::usDate($row['date1']),
                'tenant'     => Helper::getValue('tenant',$row,255),
                'job'        => Helper::getValue('job',$row),
                'usid'       => Helper::getValue(0,explode('@',Helper::getValue('usid',$row))),
              ];
            }
            $rows[$propNum]['batch'] = Html::repeatChar('&nbsp;',9) . 'Prop: ' . $prop . ' | Sub Total: ' . Html::span(Format::usMoney($rows[$propNum]['batch']),['class'=>'text-red']);
          }
          $rows[$batchNum]['batch'] = Html::repeatChar('&nbsp;',6) . 'Batch/Check No: ' . $batch . ' | Sub Total: ' . Html::span(Format::usMoney($rows[$batchNum]['batch']),['class'=>'text-red']);
        }
        $rows[$bankNum]['batch']  = Html::repeatChar('&nbsp;',3) . Html::u('Bank: ' . $bank . ' | Sub Total: ' . Html::span(Format::usMoney($rows[$bankNum]['batch']),['class'=>'text-red']));
      }
      $rows[$groupNum]['batch'] = Html::b(Html::u(title_case($this->typeOption[$type]) . ': ' . $groupValue . ' | Grand Total: ' . Html::span(Format::usMoney($rows[$groupNum]['batch']),['class'=>'text-red'])));
      $rows[$num++]             = $blankRow;
    }

    $totalRow['batch']     = Html::b(Html::u('Grand Total: ') . Html::span(Format::usMoney($totalRow['batch'],['class'=>'text-red'])));
    //$rows[$num++]          = $totalRow; 
    return P::getRow($rows,$totalRow);
  }
//------------------------------------------------------------------------------
  private function _getGridByBankBatch($groupedData,$type='trust',$banks=[]){
    $blankRow     = [$type=>'','bank'=>'','batch'=>'','amount'=>''];
    $blankSumRow  = [$type=>'','bank'=>'','batch'=>'','amount'=>0];
    $totalRow     = $blankSumRow;
    
    $rBank        = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   => T::$bankView,
      'query'   => [
        'must'  => [
          'bank' => $banks,
        ]
      ]
    ]),'bank');
    
    $rows     = [];
    $num      = 0;
    foreach($groupedData as $groupValue => $value){
      $groupNum                    = $num;
      $rows[$num++]                = $blankSumRow;
      foreach($value as $bank => $val){
        $bankData                  = Helper::getValue($bank,$rBank,[]);
        $bankNum                   = $num;
        $rows[$num++]              = $blankSumRow;
        foreach($val as $batch => $vl){
          $batchNum                   = $num;
          $rows[$num++]               = $blankSumRow;
          foreach($vl as $prop => $v){
            foreach($v as $i => $row){
              $amount                     = Helper::getValue('amount',$row,0);
              $rows[$groupNum]['amount'] += $amount;
              $rows[$batchNum]['amount'] += $amount;
              $rows[$bankNum]['amount']  += $amount;
              $totalRow['amount']        += $amount;
            }
          }
          $rows[$batchNum]  = [
            $type     => '',
            'bank'    => '',
            'batch'   => $batch,
            'amount'  => Format::usMoney($rows[$batchNum]['amount']),
          ];
        }
        $rows[$bankNum]   = [
          $type      => '',
          'bank'     => !empty($bankData) ? Html::u(Format::getBankDisplayFormat($bankData,'cr_acct')) : Html::u($bank),
          'batch'    => '',
          'amount'   => Html::u(Html::span(Format::usMoney($rows[$bankNum]['amount']),['class'=>'text-red'])),
        ];
      }
      $rows[$groupNum]   = [ 
        $type        => Html::b(Html::u($groupValue)),
        'bank'       => '',
        'batch'      => '',
        'amount'     => Html::b(Html::u(Html::span(Format::usMoney($rows[$groupNum]['amount']),['class'=>'text-red']))),
      ];
      $rows[$num++]             = $blankRow;
    }

    $totalRow[$type]       = Html::b(Html::u('Grand Total: '));
    $totalRow['amount']    = Html::b(Html::u(Html::span(Format::usMoney($totalRow['amount']),['class'=>'text-red'])));
    //$rows[$num++]          = $totalRow; 
    return P::getRow($rows,$totalRow);  
  }
//------------------------------------------------------------------------------
  private function _getGridByBank($groupedData,$type='trust',$banks=[]){
    $blankRow     = [$type=>'','bank'=>'','amount'=>''];
    $blankSumRow  = [$type=>'','bank'=>'','amount'=>0];
    $totalRow     = [$type=>'','bank'=>'','amount'=>0];
      
    $rBank        = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   => T::$bankView,
      'query'   => [
        'must'  => [
          'bank' => $banks,
        ]
      ]
    ]),'bank');
    
    $rows     = [];
    $num      = 0;
    foreach($groupedData as $groupValue => $value){
      $groupNum                    = $num;
      $rows[$num++]                = $blankSumRow;
      foreach($value as $bank => $val){
        $bankData                  = Helper::getValue($bank,$rBank,[]);
        $bankNum                   = $num;
        $rows[$num++]              = $blankSumRow;
        foreach($val as $batch => $vl){
          foreach($vl as $prop => $v){
            foreach($v as $i => $row){
              $amount                     = Helper::getValue('amount',$row,0);
              $rows[$groupNum]['amount'] += $amount;
              $rows[$bankNum]['amount']  += $amount;
              $totalRow['amount']        += $amount;
            }
          }
        }
        $rows[$bankNum] = [
          $type      => '',
          'bank'     => !empty($bankData) ? Format::getBankDisplayFormat($bankData,'cr_acct') : $bank,
          'amount'   => Format::usMoney($rows[$bankNum]['amount']),
        ];
      }
      $rows[$groupNum]  = [
        $type       => Html::b(Html::u($groupValue)),
        'bank'      => '',
        'amount'    => Html::b(Html::u(Html::span(Format::usMoney($rows[$groupNum]['amount']),['class'=>'text-red']))),
      ];
      $rows[$num++]             = $blankRow;
    }

    $totalRow[$type]    = Html::b(Html::u('Grand Total: '));
    $totalRow['amount'] = Html::b(Html::u(Html::span(Format::usMoney($totalRow['amount']),['class'=>'text-red'])));
    //$rows[$num++]          = $totalRow; 
    return P::getRow($rows,$totalRow);
  }
}

