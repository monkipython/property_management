<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use App\Library\{Helper, Elastic, TableName AS T};
class FindPercentageChangeBilling extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:findPercentageChangeBilling';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Capture all tenants that have changes from one billing to another above a certain percent';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_maxPercent  = 30.0;
  public function __construct(){
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $rTenant = Helper::getElasticResult(Elastic::searchQuery([
      'index'  =>T::$tenantView,
      'size'   =>120000,
      '_source'=>['prop', 'unit', 'tenant','tenant_id',T::$billing],
      'sort'   =>['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      'query'  =>['must'=>['status.keyword'=>'C']]
    ]));
    
    $csvRows   = [];
    $csvRows[] = implode(',',['Prop','Unit','Tenant']);
    
    foreach($rTenant as $i => $v){
      $source   = $v['_source'];
     
      $billing      = Helper::getValue(T::$billing,$source,[]);
      $billing602   = $this->_capture602Billing($billing);
      
      if(count($billing602) >= 2){
        $startAmount = $billing602[0]['amount'];
        for($i = 1; $i < count($billing602); $i++){
          $endAmount  = $billing602[$i]['amount'];
          $percent    = $this->_capturePercentChange($startAmount,$endAmount);
          
          if(abs($percent) >= $this->_maxPercent){
            $csvRows[]   = implode(',',Helper::selectData(['prop','unit','tenant'],$source));
            break;
          }
          
          $startAmount= $endAmount;
        }
      }
    }
    
    echo implode("\n",$csvRows);
  }
//------------------------------------------------------------------------------
  private function _capture602Billing($billing){
    $data = [];
    
    foreach($billing as $i => $v){
      if($v['schedule'] == 'M' && $v['service_code'] == '602' && $v['gl_acct'] == '602'){
        $data[] = $v;
      }
    }
    
    return $data;
  }
//------------------------------------------------------------------------------
  private function _capturePercentChange($startAmount,$endAmount){
    $difference = $endAmount - $startAmount;
    $divisor    = !empty($startAmount) ? $startAmount : 1;
    return ($difference / floatval($divisor)) * 100.0;
  }
}