<?php
namespace App\Console\PropertyManagement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Elastic, Helper, PDFMerge, TableName AS T, TenantStatement, V};
class TenantStatementByGroup extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'tenant:statementByGroup';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Generate Tenant Statments arranged by Group';
  /**
   * Create a new command instance.
   * @return void
   */
  public function __construct(){
    parent::__construct();
  }
  public function handle(){
    $props = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$propView,
      '_source'  => ['prop'],
      'query'    => [
        'must_not'  => [
          'prop_class.keyword' => 'X', 
        ], 
      ]
    ]),'prop','prop');
    $rTenant     = Helper::getElasticResult(Elastic::searchQuery([
      'index'   => T::$tenantView,
      '_source' => ['tenant_id','group1','prop'],
      'query'   => [
        'must'  => [
          'prop.keyword'  => array_values($props),
          'status.keyword'=> 'C',
        ]
      ]
    ]));
    
    $tenantProps = array_column(array_column($rTenant,'_source'),'prop');
    $r           = Helper::getElasticGroupBy($rTenant,'group1');
    
    $rBank  = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'   => T::$bankView,
      '_source' => ['trust','prop'],
      'query'   => [
        'must'  => [
          'prop.keyword' => $tenantProps,
        ]
      ]
    ]),'prop');
    $rGroup = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$groupView,
      '_source'  => ['prop','prop_id'],
    ]),'prop','prop_id');
    
    $insertData = $groupMerge = $elasticIds = [];
    foreach($r as $group => $val){
      if(!empty($rGroup[$group])){
        $dir = $files = $paths = [];
        foreach($val as $i => $v){
          if(!empty($rBank[$v['prop']])){
            $filePath       = TenantStatement::getPdf([$v['tenant_id']],['isDeleteGroupFolder'=>false])['filePath'];
            $deletePath     = preg_replace('/(.*)\/public\/tmp\/(.*)/','public/tmp/$2',$filePath);
        
            $dir[]          = dirname($filePath);
            $files[]        = $deletePath;
            $paths[]        = $filePath;
          }
        }
      
        $dirPath     = Helper::getValue(0,$dir);
      
        $filePath      = $dirPath . '/' . $group . '.pdf';
        $mergeFilePath = preg_replace('/(.*)\/public\/tmp\/(.*)/','public/tmp/$2',$filePath);
        $merge         = PDFMerge::mergeFiles([
          'msg'           => '',
          'href'          => '',
          'fileName'      => $mergeFilePath,
          'files'         => $files,
          'paths'         => $paths,
          'isDeleteFile'  => false,
        ]);
      
        $insertData[T::$fileUpload][] = [
          'foreign_id'    => Helper::getValue($group,$rGroup,0),
          'name'          => basename($filePath),
          'ext'           => 'pdf',
          'file'          => basename($filePath),
          'uuid'          => '',
          'path'          => rtrim($dirPath,'/') . '/',
          'type'          => 'group_tenant_statement',
          'cdate'         => Helper::mysqlDate(),
          'usid'          => 'SYS',
          'active'        => 1
        ];
        $elasticIds[]= Helper::getValue($group,$rGroup,0);  
      }

    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    $msg     = '';
    $success = $elastic = $response = [];
    try {
      $success   += Model::insert($insertData);
      $elastic    = [
        'insert'  => [T::$groupView => ['p.prop_id'=>$elasticIds]]
      ];
      Model::commit([
        'success' => $success,
        'elastic' => $elastic,
      ]);
      $msg .= 'Generated Tenant Statement(s) for ' . count($elasticIds) . ' groups';
    } catch (\Exception $e) {
      $msg .= Model::rollback($e);
    }
    dd($msg);
  }
}
