<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Survey\Survey AS M;
use App\Console\InsertToElastic\InsertToElastics;
use App\Library\{Elastic, Helper, TableName AS T, RuleField, File, V, GridData, Mail};
class Reindex extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  /**
   * @howToUseIt 
   * To run everything single available index
   * php artisan general:elasticReindex  //OR
   * TO run specific index 
   * php artisan general:elasticReindex prop_view tenant_view tnt_trans_view
   */
  protected $signature = 'general:elasticReindex {index?*}';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Reindex all the data in the elastic given the index name. ';
  /**
   * Create a new command instance.
   * @return void
   */
  public function __construct(){
    ini_set('memory_limit','10000M');
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $index      = $this->argument('index');
    $viewTablez = !empty($index) ? $index : File::listFileByDir(base_path() . '/app/Console/InsertToElastic/ViewTableClass'); 
     # IMPORTANT: view table name CANNOT have any upper case
//    $viewTablez = [T::$creditCheckView, T::$accountRoleView, T::$accountView,T::$tenantView,T::$unitView, T::$propView, T::$bankView]; 
//    $r  = Helper::keyFieldName(DB::select('SHOW FULL TABLES IN ppm WHERE TABLE_TYPE LIKE "VIEW"'), 'Tables_in_ppm', 'Tables_in_ppm');
    foreach($viewTablez as $viewTable){
      //list($viewTable, $ext) = explode('.', $viewTable);
      $viewTable  = preg_replace('/\.php$/','',$viewTable);
      $command = 'curl -XDELETE "' . env('ELASTICSEARCH_HOST') . ':' . env('ELASTICSEARCH_PORT') . '/' . $viewTable . '"';
      exec($command);
      
      if(!Elastic::isIndexExist($viewTable)){ // We know that the view is exist in the database but it doesnt exist in the elastic search now start to add
        InsertToElastics::insertDataCLI($viewTable);
      }
    }
    echo "\n";
    dd('Done');
  }
}