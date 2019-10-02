<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use App\Http\Models\Model;
use App\Library\Elastic;
class DeleteElasticByWhere extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  /**
   * @howToUseIt 
   * Example: 
   * 
   * To delete from the 'vendor_mortgage_view' documents with invoices 23342 or 2342
   * Remember to enclose the where clause in quotations it gets parsed correctly in the Elasticsearch
   * 
   * php artisan general:deleteElasticByWhere vendor_mortgage_view "(invoice:23342) OR (invoice:2342)"
   */
  protected $signature = 'general:deleteElasticByWhere {index} {queryString}';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Delete documents from an index that fit the query string filter entered';
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
    $index       = $this->argument('index');
    $queryString = $this->argument('queryString');

    $elasticItem = Elastic::deleteByQuery([
      'index'     => $index,
      'query'     => [
        'raw'     => [
          'must'  => [
            'query_string' => [
              'query'      => $queryString,
            ]
          ]
        ]
      ]
    ]);
    
    if((!empty($elasticItem['deleted']) && $elasticItem['deleted'] < 0) || !empty($elasticItem['failures'])){
      Model::rollback(json_encode($elasticItem, JSON_PRETTY_PRINT));
    }

    $msg = 'Done delete from index: ' . $index . ', ' . $elasticItem['deleted'] . ' documents removed';
    dd($msg);
  }
}

