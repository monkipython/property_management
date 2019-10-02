<?php
namespace App\Console\InsertToElastic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Survey\Survey AS M;
use App\Library\{Elastic, Helper, TableName AS T, RuleField, File};
class InsertToElastics extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   *   php artisan survey:report --email='sean.hayes@siemens.com' --date=week --tac_id=verhoeve
   */
  protected $signature = 'elastic:insert';
  
  /**
   * The console command description.
   * @var string
   */ 
  protected $description = 'Insert the Data to Elastic Search from the View MySQL Databse';
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
   *  php artisan survey:report --email='sean.hayes@siemens.com' --date=week --tac_id='riches,gerlich'
   */
  public function handle(){
    # IMPORTANT: view table name CANNOT have any upper case
//    $viewTablez = [T::$creditCheckView, T::$accountRoleView, T::$accountView,T::$tenantView,T::$unitView, T::$propView, T::$bankView]; 
    $viewTablez = File::listFileByDir(base_path()  . '/app/Console/InsertToElastic/ViewTableClass');
//    $r  = Helper::keyFieldName(DB::select('SHOW FULL TABLES IN ppm WHERE TABLE_TYPE LIKE "VIEW"'), 'Tables_in_ppm', 'Tables_in_ppm');
    foreach($viewTablez as $viewTable){
      list($viewTable, $ext) = explode('.', $viewTable);
      if(!Elastic::isIndexExist($viewTable)){ // We know that the view is exist in the database but it doesnt exist in the elastic search now start to add
        echo $viewTable . "\n";
        if(php_sapi_name() == 'cli'){
          self::insertDataCLI($viewTable);
        }else{
          self::insertData($viewTable); 
        }
      }
    }
    echo "\n";
    dd('Done');
  }
//------------------------------------------------------------------------------
  public static function insertData($viewTable, $r = []){
    $cls  = '\App\Console\InsertToElastic\ViewTableClass\\' . $viewTable;
    $method = 'parseData';
    $insertData = $cls::$method($viewTable, $r);
    if(!empty($insertData)){
      $ruleFieldData = [
        'tablez'=>$cls::getTableOfView(),
        'copyField'=>method_exists($cls, 'getCopyField') ? $cls::getCopyField() : []
      ];
      $field = RuleField::generateRuleField($ruleFieldData)['field'];
      return Elastic::insert($insertData, $viewTable, $field);
    }
  }
//------------------------------------------------------------------------------
  public static function insertDataCLI($viewTable){
    echo 'Start Time index: ' . $viewTable . ' - ' . date("Y-m-d H:i:s") . "\n"; 
    $field = $r = []; // Since we run multiple index, we need to make sure we empty memory first
    $_getDatabaseData = function($viewTable, $offset, $limit){
      return DB::table($viewTable)->offset($offset)->limit($limit)->get()->toArray();
    };
    $cls  = '\App\Console\InsertToElastic\ViewTableClass\\' . $viewTable;
    # START TO CREATE VIEW
    DB::select('CREATE OR REPLACE VIEW '.$viewTable.' AS ' . $cls::getSelectQuery());
    DB::select('SET global group_concat_max_len = 100000');
    # START TO QUERY THE DATA AND PUSH TO FILE
    $num   = 0;
    $ruleFieldData = [
      'tablez'=>$cls::getTableOfView(),
      'copyField'=>method_exists($cls, 'getCopyField') ? $cls::getCopyField() : []
    ];
    $_elasticInsert = function($cls, $r, $viewTable, $field){
      $maxBucketChunk = !empty($cls::$maxBucketChunk) ? $cls::$maxBucketChunk : 400;
      
      foreach(array_chunk($r, $maxBucketChunk) as $v){
        $datum = $cls::parseData($viewTable,$v);
        $flag  = !empty($datum) ? Elastic::insert($datum,$viewTable,$field) : [];
      }
    };
    
    $field = RuleField::generateRuleField($ruleFieldData)['field'];
    $r = $_getDatabaseData($viewTable, $num, $cls::$maxChunk);
    self::msg($viewTable, $num);
    $_elasticInsert($cls, $r, $viewTable, $field);
//    Elastic::insert($cls::parseData($viewTable, $r), $viewTable, $field);
    while(!empty(count($r))){
      $offset = ++$num * $cls::$maxChunk;
      $r = $parseData = []; // Need to reset before big data
      $r = $_getDatabaseData($viewTable, $offset, $cls::$maxChunk);
      if(!empty($r)){
        $parseData = $cls::parseData($viewTable, $r);
        if(!empty($parseData)){
          self::msg($viewTable, $num);
          $_elasticInsert($cls, $r, $viewTable, $field);
//          Elastic::insert($parseData, $viewTable, $field);
        } else{
          if(env('APP_ENV') != 'production1'){
            dd($viewTable . ' has no data');
          }
        }
      } 
    }
    
    # INCREASE THE MAX IF THE RESULT AUTOMATICALLY
    $maxResult = isset($cls::$maxResult) ? $cls::$maxResult : '500000';
    $localHost  = env('ELASTICSEARCH_HOST') . ':' . env('ELASTICSEARCH_PORT') . '/' . $viewTable . '/_settings';
    $command = 'curl -XPUT "'.$localHost.'" -H \'Content-Type: application/json\' -d\' { "index" : { "max_result_window" : '.$maxResult.' } }\'';
    exec($command);
//    $elasticHost = env('ELASTICSEARCH_SCHEME') . '://' . env('ELASTICSEARCH_HOST') . ':' . env('ELASTICSEARCH_PORT') . '/' . $viewTable;
//    exec('curl -XPUT "' . $viewTable . '/_settings" -H \'Content-Type: application/json\' -d\' { "index" : { "max_result_window" : 100000 } }\'');
    echo 'Stop Time: ' . date("Y-m-d H:i:s") . "\n-----------------------------------------------------------------------------------------------\n"; 
  }
//------------------------------------------------------------------------------
  public static function update($viewTable, $r){
    $response = [];
    $cls  = '\App\Console\InsertToElastic\ViewTableClass\\' . $viewTable;
    $method = 'parseData';
    $updateData = $cls::$method($viewTable, $r);
    if(!empty($updateData)){
      foreach($updateData as $v){
        $id = $v['id'];
        unset($v['id']);
        $response[$id] = Elastic::update($v, $viewTable, $id);
      }
    }
    return $response;
  }
####################################################################################################
#####################################     HELPER FUNCTION      #####################################
####################################################################################################
  private static function msg($viewTable, $num){
    echo "Start to insert: " . ($num + 1) . " set insert to " . $viewTable . "\n";
  }
}