<?php
namespace App\Console\PropertyManagement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Survey\Survey AS M;
use App\Library\{Elastic, Helper, TableName AS T, RuleField, File, V, GridData, Mail};
class TenantMassiveBilling extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'tenant:massiveBilling';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Invoice all the current tenant';
  /**
   * Create a new command instance.
   * @return void
   */
  public function __construct(){
    parent::__construct();
  }
  public function handle(){
    exec('/usr/bin/php /var/www/html/artisan general:elasticReindex tenant_view');
    
    $massiveBillingClass = '\App\Http\Controllers\PropertyManagement\Tenant\MassiveBilling\MassiveBillingController';
    $r = $massiveBillingClass::getInstance()->storeData([
      'date1'=>date('Y-m-d', strtotime('first day of next month')), 
      'prop'=>'0001-ZZZZ'
    ]);
    $insertElasticClass = 'App\Console\InsertToElastic\InsertToElastics';
    $insertElasticClass::insertDataCLI(T::$tenantView);
    Mail::send([
      'to'      =>'mike@pamamgt.com,sean@pamamgt.com,djkler@pamamgt.com,jesse@pamamgt.com,ryan@pamamgt.com',
      'from'    =>'admin@pamamgt.com',
      'subject' =>'Massive Billling Run on ' . date("F j, Y, g:i a"),
      'msg'     =>'Finished Running Massive Billling on ' . date("F j, Y, g:i a") . '<br><hr><br><br>'
    ]);
  }
}