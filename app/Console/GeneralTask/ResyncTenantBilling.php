<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;
use App\Library\{Helper, Elastic, TableName AS T,ServiceName AS S,GlName AS G};

class ResyncTenantBilling extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:resyncTenantBilling';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Insert tenant billings into the database for tenants if they are present in Elasticsearch';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_defaultUsid = 'SYS';
  public function __construct(){
    parent::__construct();
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $tenantIds = $updateData = [];
    $billingUpdated = 0;
    $rTenant      = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'size'     => 500000,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'  => ['tenant_id','prop','unit','tenant','base_rent','lease_start_date',T::$billing],
      'query'    => [
        'raw'    => [
          'must' => [
            [
              'term'  => ['status.keyword'=>'C'],
            ],
            [
              'term'  => [T::$billing . '.schedule.keyword' => 'M'],
            ],
            [
              'terms' => [T::$billing . '.service_code.keyword'=>[S::$rent,S::$hud,G::$resort]],
            ],
//            [
//              'term'  => [T::$billing . '.stop_date' => '9999-12-31']
//            ],
            [
              'range' => [
                T::$billing . '.start_date'  => ['lte'=>Helper::date()]
              ]
            ],
            [
              'range' => [
                'lease_start_date'  => ['lte'=>Helper::date()]
              ]
            ]
          ],
          'must_not'  => [
            [
              'term'  => ['prop_type.keyword'=>'M'],
            ]
          ]
        ],
      ]
    ]));
  
    $csvRows           = [implode(',',['Prop','Unit','Tenant'])];
    foreach($rTenant as $i => $v){
      $tenantIds            = $insertData = $updateData = [];
      $billing              = Helper::getValue(T::$billing,$v,[]);
      $elasticBillingIds    = !empty($billing) ? Helper::keyFieldName($billing,'billing_id') : [];
        
      $notExistDbRows       = [];
      foreach($elasticBillingIds as $k => $val){
        $databaseBilling     = $this->_getTenantBilling(Model::buildWhere(['billing_id'=>$val['billing_id']]),'billing_id');
        //Insert into database all billing rows that exist in the elastic search document but not in the database
        if(empty($databaseBilling)){
          $insertRow       = $val + [
            'prop'         => $v['prop'],
            'unit'         => $v['unit'],
            'tenant'       => $v['tenant'],
            'gl_acct_past' => $val['gl_acct'],
            'gl_acct_next' => $val['gl_acct'],
            'tax_cd'       => 'N',
            'comm_flg'     => 'N',
            'mangt_flg'    => 'N',
            'rock'         => '@@@',
            'usid'         => $this->_defaultUsid,
          ];
          
          $notExistDbRows[$k]  = $insertRow;
          $insertData[T::$billing][] = $insertRow;
        }
      }
      
      if(!empty($notExistDbRows)){
        $csvRows[] = implode(',',[$v['prop'],$v['unit'],$v['tenant']]);
        ############### DATABASE SECTION ######################
        $success = $elastic = $response = [];
        DB::beginTransaction();
        try {
          $success += Model::insert($insertData);
          $elastic  = ['insert'=>[T::$tenantView=>['t.tenant_id'=>[$v['tenant_id']]]]];
          $response = Model::commit([
            'success' => $success,
            'elastic' => $elastic,
          ]);
          ++$billingUpdated;
        } catch(\Exception $e) {
          Model::rollback($e);
        }
      }
    }
    
//    $csvStr = implode("\n",$csvRows);
//    echo $csvStr;
    $msg  = 'Update Complete, ' . $billingUpdated . ' tenant billing(s) were updated';
    dd($msg);
  }
//------------------------------------------------------------------------------
  private function _getTenantBilling($where,$select='*'){
    $r = DB::table(T::$billing)->select($select)->where($where)->get()->toArray();
    return $r;
  }
}
