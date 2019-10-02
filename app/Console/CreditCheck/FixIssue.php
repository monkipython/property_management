<?php
namespace App\Console\Creditcheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Survey\Survey AS M;
use App\Library\{Elastic, Helper, TableName AS T, RuleField, File, V, GridData, Mail};

class FixIssue extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'fix:issue';
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Fix Issue';
  public function handle(){
    $selectBatch = 'AND batch in (943579)';
//    $selectBatch = 'AND batch NOT in (945621, 945605)';
//    $selectBatch = '';
    $q = "
      select count(*), sum(amount),check_no,batch,g.prop,g.remark,p.trust
      from gl_trans as g 
      left join prop_bank as p on p.prop=g.prop and p.bank=g.bank
      where date1 between '2018-07-13' and '2018-07-13' and batch < 999999 and g.rock='@@' and tx_code='CP'  " . $selectBatch . " 
      group by batch
      having count(*) > 2 and check_no <> '000000'
    ";
    $batch = array_values(Helper::keyFieldName(DB::select($q), 'batch', 'batch'));
    $batchStr=implode(',', $batch);
    $r = DB::select('
      select *  from gl_trans as g
      where g.batch in (' . implode(',', $batch) . ') 
      having check_no <> "000000"
    ');
    $glContra = Helper::keyFieldName(DB::select('select * from prop_bank'), ['prop', 'bank'], 'gl_acct');
    $summary = [];
    $summaryData = [];
    $totalSummary = 0;
    $total = 0;
    $insertData = [];
    $deleteData = [];
    foreach($r as $i=>$v){
      if(!preg_match('/Summary/i', $v['remark'])){
        $id = $this->_getSummaryPrimKey($v, $batch);
        if(!isset($summary[$id])){ 
          $summary[$id] = ['amount'=>0, 'gl_contra'=>$v['gl_contra'], 'check_no'=>$v['check_no']];
        }
        $summary[$id]['amount'] += $v['amount'];        
      }else if(preg_match('/Summary/i', $v['remark'])){
        $summaryData[$v['batch']] = $v;
        $deleteData[] = $v['seq'];
      }
    }
    foreach($summary as $id=>$v){
      $ids   = $this->_splitSummaryPrimKey($id);
      $batch = $ids['batch'];
      $prop  = $ids['prop'];
      $bank  = $ids['bank'];
      
      $val = $summaryData[$batch];
      $val['prop']   = $prop;
      $val['amount'] = $v['amount'] * -1;
      $val['bank']   = $bank;
      $val['gl_acct']     = $v['gl_contra'];
      $val['gl_contra']   = $v['gl_contra'];
      $val['gl_acct_org'] = $v['gl_contra'];
      $val['check_no']    = $v['check_no'];
      $val['doc_no']      = $v['check_no'];
      $val['title']       = 'CASH ACCOUNT - ' . $v['gl_contra'];
       
      $val['seq']    = 0;
      $insertData[]  = $val;
    }
    
//    dd($insertData, $batchStr);
//    dd($batchStr, 'DELETE FROM gl_trans WHERE seq IN ('.implode(',', $deleteData).')');
    foreach($insertData as $v){
     $result = DB::table('gl_trans')->insert($v);
     echo $result . "\n" ;
    }
    dd($batchStr, 'WHERE seq IN ('.implode(',', $deleteData).')');
  }
//------------------------------------------------------------------------------
  private function _getSummaryPrimKey($v){
    return $v['batch'] . '-' . $v['prop'] . '-' . $v['bank'];
  }
//------------------------------------------------------------------------------
  private function _splitSummaryPrimKey($id){
    $data = [];
    list($data['batch'], $data['prop'], $data['bank']) = explode('-', $id);
    return $data;
  }
}