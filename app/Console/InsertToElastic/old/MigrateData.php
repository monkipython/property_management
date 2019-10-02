<?php
namespace App\Console\Creditcheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Survey\Survey AS M;
use App\Library\{Elastic, Helper, TableName AS T, RuleField, File};
class MigrateData extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'creditCheck:migrateData';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Migrate data from creditcheck page to the new one';
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
    $r = DB::table(T::$application)->select('*')->get()->toArray();
    
    foreach($r as $i=>$v){
      $insertData = [];
      // Agreement 
      if(!empty($v['signature_file'])){
        $p = explode('/', $v['signature_file']);
        $file = array_last(explode('/', $v['signature_file']));
        list($name, $ext) = explode('.', $file);
        array_pop($p);
        $path = implode('/', $p) . '/';
        $insertData[] = [
          'name'=>$file, 
          'file'=>$file, 
          'ext'=>$ext, 
          'uuid'=>'old_' . md5($file), 
//          'path'=>$path, 
          'path'=>'/var/www/html/storage/app/public/agreement/', 
          'usid'=>'SYS', 
          'active'=>1,
          'cdate'=>$v['created'],
          'type'=>'agreement', 
          'foreign_id'=>$v['application_id']
        ];
      }
      if(!empty($v['application_files'])){
        $files  = explode('|', trim($v['application_files'], '|'));
        foreach($files as $f){
          if(!empty($f) && $f != '/doc_date'){
            $p = explode('/', $f);
            $file = array_last($p);
            $peices = explode('.', $file);
            $name = $peices[0];
            if(!isset($peices[1])){
              dd($file);
            }
            $ext = $peices[1];
            array_pop($p);
            $path = implode('/', $p) . '/';
            $insertData[] = [
              'name'=>$file, 
              'file'=>$file, 
              'ext'=>$ext, 
              'uuid'=>'old_' . md5($file), 
//              'path'=>$path, 
              'path'=>'/var/www/html/storage/app/public/application/', 
              'usid'=>'SYS', 
              'active'=>1,
              'cdate'=>$v['created'],
              'type'=>'application', 
              'foreign_id'=>$v['application_id']
            ];
          }
        }
      }
      if(DB::table(T::$fileUpload)->insert($insertData)){
        echo 'insert: ' . $i . "\n";
      }
    }
  }
}