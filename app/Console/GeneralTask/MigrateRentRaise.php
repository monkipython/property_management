<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Library\{Helper, HelperMysql, Elastic, Format, TableName AS T,GlName AS G,ServiceName AS S};
class MigrateRentRaise extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:migrateRentRaise';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Migrate all rent raise tables from older database to newer database table';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_defaultUsid   = 'SYS';
  private $_defaultService= '602';
  private $_defaultGlAcct = '602';
  private $_chunkAmount   = 500;
  private $_destTable     = '';
  private $_excludeView   = '';
  private $_searchPattern = '';
  private $_database      = '';
  public function __construct(){
    $this->_excludeView   = T::$rentRaiseView;
    $this->_searchPattern = T::$rentRaise;
    $this->_destTable     = T::$rentRaise;
    $this->_database      = env('DB_DATABASE');
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $numRecords    = 0;
    $likeToken     = preg_replace('/_/','\\_',$this->_searchPattern) . '%';
    $likeQuery     = 'SHOW TABLES LIKE "' . $likeToken . '"';
    $colName       = 'Tables_in_' . $this->_database . ' (' . $likeToken . ')';
    $r             = DB::select($likeQuery);
    $tables        = array_column($r,$colName);
    $rService      = Helper::keyFieldName(HelperMysql::getService(['prop'=>'Z64']),'service');
    $defaultRemark = !empty($rService[$this->_defaultService]['remark'])  ? $rService[$this->_defaultService]['remark'] : ''; 
    
    $rProp         = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$propView,
      '_source'   => ['prop','prop_type'],
      'query'     => [
        'must_not'  => [
          'prop_class.keyword' => 'X',
        ]
      ]
    ]),'prop');
    
    $tenantData    = [];
    $rTenant       = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'     => T::$tenantView,
      '_source'   => ['tenant_id','prop','unit','tenant','base_rent',T::$billing],
      'size'      => 50000,
      'query'     => [
        'must'    => [
          'status.keyword'  => 'C',
        ]
      ]
    ]),['prop','unit','tenant']);

    foreach($tables as $t){
      if($t !== $this->_excludeView && $t !== $this->_destTable){
        $data        = DB::table($t)->select('*')->get()->toArray();
        $chunks      = array_chunk($data,$this->_chunkAmount);
        $insertData  = [];
        foreach($chunks as $chunk){
          foreach($chunk as $i => $v){
            $key                            = Helper::getValue('prop',$v) . Helper::getValue('unit',$v) . Helper::getValue('tenant',$v,0);
            $row                            = [];
            $pct1                           = Helper::getValue('raise_pct',$v,0);
            $pct2                           = Helper::getValue('raise_pct2',$v,0);
            $finalPct                       = !empty($pct1) ? $pct1 : $pct2;
            $raise1                         = Helper::getValue('raise',$v,0);
            $raise1                         = !empty($raise1) ? $raise1 : Helper::getValue('raise_30',$v,0);
            $raise2                         = Helper::getValue('raise_60',$v,0);
            $finalRaise                     = $raise1 > 0 ? $raise1 : $raise2;
            if($finalRaise > 0){
              $rentVal                        = Helper::getValue('rent',$v,0);
              $finalPct                       = ((($finalRaise - $rentVal)/ (floatval(!empty($rentVal) ? $rentVal : 1))) * 100.0);
              $prop                           = Helper::getValue('Prop',$v);
              $prop                           = !empty($prop) ? $prop : Helper::getValue('prop',$v);
              $notice                         = Helper::getValue('Notice',$v);
              $notice                         = !empty($notice) ? $notice : Helper::getValue('notice',$v,30);
              $unit                           = Helper::getValue('unit',$v);
              $tenant                         = Helper::getValue('tenant',$v,0);
              $row['prop']                    = $prop;
              $row['unit']                    = $unit;
              $row['tenant']                  = $tenant;
              $row['foreign_id']              = Helper::getValue('tenant_id',Helper::getValue($prop . $unit . $tenant,$rTenant,[]),0);
              $row['usid']                    = $this->_defaultUsid;
              $row['rent']                    = $rentVal;
              $row['active']                  = 1;
              $row['service_code']            = Helper::getValue('service_code',$v,$this->_defaultService);
              $row['gl_acct']                 = Helper::getValue('gl_acct',$v,$this->_defaultGlAcct);
              $row['remark']                  = Helper::getValue('remark',$v,$defaultRemark);
              $row['billing_id']              = Helper::getValue('billing_id',$v,0);
              $row['raise']                   = Format::roundDownToNearestHundredthDecimal($finalRaise);
              $row['raise_pct']               = Format::roundDownToNearestHundredthDecimal($finalPct); 
              $row['notice']                  = $notice;
              $row['cdate']                   = Helper::mysqlDate();
              $row['isCheckboxChecked']       = 0;
              $row['effective_date']          = '1000-01-01';
              $row['last_raise_date']         = Helper::getValue('last_date',$v,'1000-01-01');
              $row['submitted_date']          = Helper::getValue('last_date',$v,'1000-01-01');
              $row['file']                    = '';
              $tenantData[$key][]             = $row;
              ++$numRecords;
            }
          }
        }
        
      }
    }
    $_sortFn  = function($a,$b){
      $aTime       = strtotime(Helper::getValue('start_date',$a,0));
      $bTime       = strtotime(Helper::getValue('start_date',$b,0));
      $aStopTime   = strtotime(Helper::getValue('stop_date',$a,0));
      $bStopTime   = strtotime(Helper::getValue('stop_date',$b,0));
      return $aTime == $bTime ? ($aStopTime == $bStopTime ? 0 : ($aStopTime < $bStopTime ? -1 : 1)) : ($aTime < $bTime ? -1 : 1);
    };
    foreach($rTenant as $k => $v){
      if(isset($rProp[$v['prop']]['prop']) && !empty($v[T::$billing])){
        $propData      = $rProp[$v['prop']];
        $billing       = $v[T::$billing];
        $billingByDate = [];
        foreach($billing as $key => $val){
          if($val['gl_acct'] == G::$rent && $val['schedule'] == 'M' && $val['service_code'] == S::$rent){
            $billingByDate[] = $val;
          }
        }
          
        usort($billingByDate,$_sortFn);
        $oldRent        = !empty($billingByDate[0]['amount']) ? $billingByDate[0]['amount'] : 0;
        
        foreach($billingByDate as $idx => $val){
          $divisor                = !empty($oldRent) ? $oldRent : 1;
          $percent                = $idx > 0 ? Format::roundDownToNearestHundredthDecimal( (($val['amount'] - $oldRent) / (floatval($divisor))) * 100.0) : 0;
          $row  = Helper::selectData(['billing_id','service_code','gl_acct','remark'],$val) + [
            'prop'                => $v['prop'],
            'unit'                => $v['unit'],
            'tenant'              => $v['tenant'],
            
            'foreign_id'          => $v['tenant_id'],
            'rent'                => $idx > 0 ? $oldRent : 0,
            'file'                => '',
            'isCheckboxChecked'   => 0,
            'submitted_date'      => $val['start_date'],
            'effective_date'      => $val['start_date'],
            'last_raise_date'     => $val['start_date'],
            'notice'              => $this->_calculatePastNoticePeriod($billingByDate,$idx,$propData['prop_type']),
            'raise'               => Format::roundDownToNearestHundredthDecimal($val['amount']),
            'raise_pct'           => $percent,
            'cdate'               => Helper::mysqlDate(),
            'active'              => 1,
            'usid'                => $this->_defaultUsid,
          ];
          $oldRent = $val['amount'];
          $tenantData[$v['prop'] . $v['unit'] . $v['tenant']][] = $row;
          ++$numRecords;
        }
        
      }
    }
    
    $insertData = [];
    foreach($tenantData as $k => $v){
      $raiseDates = array_column($v,'effective_date');
      foreach($raiseDates as $idx => $val){
        $raiseDates[$idx] = strtotime($val);
      }
      array_multisort($raiseDates,SORT_ASC,$v);
      $lastRaiseItem = last($v);
      $lastRaiseItem['billing_id'] = 0;
      $v[] = $lastRaiseItem;
      ++$numRecords;
      foreach($v as $val){
        $insertData[] = $val;
      }
    }
    
    $chunks = array_chunk($insertData,$this->_chunkAmount);
    foreach($chunks as $v){
      $response = DB::table($this->_destTable)->insert($v);
    }
    
    $msg = 'Rent Raise Migration Complete. ' . $numRecords . ' inserted.';
    dd($msg);
  }
//------------------------------------------------------------------------------
  private function _calculatePastNoticePeriod($billing,$stopIndex,$propType){
    if($propType == 'M'){
      return 90;
    } else if($stopIndex == 0){
      return 30;
    } else {
      $billingItem  = $billing[$stopIndex];
      $lastYear     = strtotime($billingItem['start_date'] . ' -12 months +1 day');
      $lastYearRent = 0; 
      foreach($billing as $i => $v){
        if(strtotime($v['start_date']) <= $lastYear){
          $lastYearRent = $v['amount'];
        } else {
          break;   
        }      
      }
    
      $firstBillingRent = !empty($billing[0]['amount']) ? $billing[0]['amount'] : 0;
      $lastYearRent     = !empty($lastYearRent) ? $lastYearRent : $firstBillingRent;
    
      $divisor          = !empty($lastYearRent) ? $lastYearRent : 1;
      $difference       = $billingItem['amount'] - $lastYearRent;
      $percentage       = (Format::roundDownToNearestHundredthDecimal($difference) / Format::roundDownToNearestHundredthDecimal($divisor)) * 100.0;
      return $percentage > 10.0 ? 60 : 30;
    }
  }
}