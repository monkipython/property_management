<?php
namespace App\Console\GeneralTask;
use Illuminate\Console\Command;
use App\Library\{Helper, Elastic, TableName AS T};

class GetTenantNoBilling extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'general:getTenantNoBilling';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Gather the tenants that do not have billing data';
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
    $csvRows      = [implode(',',['Prop','Unit','Tenant'])];
    $rTenant      = Helper::getElasticResultSource(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'size'     => 500000,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'  => ['tenant_id','prop','unit','tenant', 'base_rent', 'isManager', 'tnt_name'],
      'query'    => [
        'raw'    => [
          'must' => [
            [
              'term' => [
                'status.keyword' => 'C',
              ] 
            ]
          ],
          'must_not' => [
            [
              'exists'  => [
                'field'  => T::$billing,
              ]
            ]
          ]
        ]
      ]
    ]));
    foreach($rTenant as $i => $v){
      $rTntTrans = Helper::getElasticResultSource(Elastic::searchQuery([
        'index'    => T::$tntTransView,
        'size'     => 1,
        'sort'     => ['cntl_no'=>'desc'],
        '_source'  => ['amount'],
        'query'    => ['must'=>['prop.keyword'=>$v['prop'], 'unit.keyword'=>$v['unit'], 'tenant'=>$v['tenant'], 'gl_acct.keyword'=>'602', 'tx_code.keyword'=>'IN']]
      ]), 1);
      if(isset($rTntTrans['amount']) && $v['base_rent'] > 0 && $v['isManager'] == 0 && !preg_match('/laundry|not rentable/i', $v['tnt_name']) && $v['base_rent'] < $rTntTrans['amount']){
        $csvRows[]  = implode(',',[$v['prop'],$v['unit'],$v['tenant']]);
      }
    }
    
    $csvStr       = implode("\n",$csvRows);
    echo $csvStr;
  }
}
