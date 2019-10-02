<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Library\{Helper, HelperMysql, Elastic, TableName AS T, TenantTrans};
class CleanTransaction extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:cleanTransaction';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Clean up all the transaction for all current tenants';
  /**
   * Create a new command instance.
   * @return void
   */
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
      'index'=>T::$tenantView,
      'size' =>50000,
      '_source'=>['prop', 'unit', 'tenant'],
      'query'=>['must'=>['status.keyword'=>'C']]
//      'query'=>['must'=>['status.keyword'=>'C', 'prop.keyword'=>'0984', 'unit.keyword'=>'0020']]
    ]));
    $batch = HelperMysql::getBatchNumber();
    echo 'Start Time: ' . date("Y-m-d H:i:s") . "\n";
//    echo 'batch: ' . $batch . "\n";
    foreach($rTenant as $i=>$v){
      $v = $v['_source'];
      echo ($i + 1) . ': batch - ' .  $batch . ' = ' . $v['prop'] . '-' . $v['unit'] . '-' . $v['tenant'] . "\n";
      $v['batch'] = $batch;
      TenantTrans::cleanTransaction($v, $batch);
      unset($rTenant[$i]);
    }
    echo 'Stop Time: ' . date("Y-m-d H:i:s") . "\n";
    dd('Batch Number: ' . $batch);
  }
}