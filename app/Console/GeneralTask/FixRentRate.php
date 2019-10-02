<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Survey\Survey AS M;
use App\Http\Models\Model; // Include the models class
use App\Console\InsertToElastic\InsertToElastics;
use App\Library\{Elastic, Helper, TableName AS T, RuleField, File, V, GridData, Mail};

class FixRentRate extends Command{
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
  protected $signature = 'general:fixRentRate';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Fix the Rent Rate using elastic and update database';
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
    $r = Elastic::searchQuery([
      'index'=>T::$unitView,
      '_source'=>['prop.prop', 'unit', 'rent_rate'], 
    ]);
    $row = [];
    $updateData = [T::$unit=>[]];
    foreach($r['hits']['hits'] as $v){
      $v = $v['_source'];
      $row[$v['prop'][0]['prop'] . $v['unit']] = $v['rent_rate'];
    }
    $rUnit = DB::table(T::$unit)->select(['prop', 'unit', 'rent_rate'])->where([])->get();
    
    foreach($rUnit as $v){
      $id = $v['prop']. $v['unit'];
      if(isset($row[$id]) && $row[$id] != $v['rent_rate'] ){
        $updateData[T::$unit][] = [
          'whereData'   => ['prop'=>$v['prop'], 'unit'=>$v['unit']],
          'updateData'  => ['rent_rate'=>$row[$id]],
        ];  
      }
    }
    DB::beginTransaction();
    try {
      $success = Model::update($updateData);
      Model::commit([
        'success'  => $success,
      ]);
    } catch (\Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    
    dd('done');
  }
}