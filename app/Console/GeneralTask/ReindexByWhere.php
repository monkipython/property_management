<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Console\InsertToElastic\InsertToElastics;
class ReindexByWhere extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  /**
   * @howToUseIt 
   * Example: 
   * To run on an index "tenant_view" to reindex documents with prop:0003 and unit:0003 and tenant:17
   * Remember to enclose the where clause in quotations to ensure it gets parsed correctly during the reindex process
   * 
   * php artisan general:elasticReindexWhere tenant_view "WHERE t.prop='0003' AND t.unit='0003' AND t.tenant=17" 
   */
  protected $signature = 'general:elasticReindexByWhere {index} {whereClause}';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Reindex all the database rows that fit the query into the specified elasticsearch index';
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
    $index      = $this->argument('index');
    $whereClause= Model::getRawWhere($this->argument('whereClause'));
    
    $cls     = '\App\Console\InsertToElastic\ViewTableClass\\' . $index;
    $queryFn = 'getSelectQuery';
  
    $r       = DB::select(DB::raw($cls::$queryFn($whereClause)));
    if(!empty($r)){
      $elasticItem = InsertToElastics::insertData($index, $r, 0)['items'];
      if(!empty($elasticItem)){
        foreach($elasticItem as $i=>$v){
          if($v['index']['status'] < 200 || $v['index']['status'] > 299){
            Model::rollback(json_encode($elasticItem, JSON_PRETTY_PRINT));
          }
        }
      }else{
        Model::rollback(json_encode($elasticItem, JSON_PRETTY_PRINT));
      }
    }
    $msg = 'Done reindexing ' . $index . ', ' . count($r) . ' documents were updated / inserted';
    dd($msg);
  }
}