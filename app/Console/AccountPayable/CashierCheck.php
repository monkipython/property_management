<?php
namespace App\Console\AccountPayable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\{Model, ApprovalModel AS M};
use App\Library\{Elastic, Helper, TableName AS T, RuleField, File, V, GridData, Mail};
use Storage;

class CashierCheck extends Command{
  /**
   * Hi Ryan, Sean,
   * As requested, please find below the naming conventions for the files:
   * Input file format: PAMA_ElMonte_DT_Cashierscheck_yyyyMMddHHmmssXX.csv
   * Example: PAMA_ElMonte_DT_Cashierscheck_2015071009490000.csv
   * Output files examples for above input file:
   * PAMA_ElMonte_DT_Cashierscheck_2015071009490000.csv (same as input file)
   * PROD_PRINT_PAMA_ELMONTE_DT_CASHIERSCHECK_2015071009490000.txt
   * For testing purpose, input files should have prefix “TEST_” to the above input file name. E.g. “Test_PAMA_ElMonte_DT_Cashierscheck_2015071009490000.csv” and generated output files will have word “Test” instead of “prod” in the file names. Please let us know if any questions.
   * @var string
   * @howTo: 
   */
  protected $signature = 'accoutPayable:cashierCheck';
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Keep Looking or Cashier Checks to Print';
  public function handle(){
    $data       = $bankData = $updateData =  $success = [];
    $errorMsg   =  '';
    $r          = M::getTableData(T::$cashierCheck, ['printed'=>0]);
    $filePrefix = Helper::isProductionEnvironment() ? 'PROD_PRINT_' : 'TEST_PRINT_';
    $location   = File::getLocation('Approval');
    $remorePath = Helper::isProductionEnvironment() ? 'DOWNLOAD/' : 'TEST/DOWNLOAD/';
    
    if(!empty($r)){
      foreach($r as $v){
        if(!empty($v['batch'])){
          $rGlTrans = M::getTableData(T::$glTrans, Helper::selectData(['vendor', 'prop', 'invoice', 'bank', 'batch'], $v), ['seq'], 1);
          $v['seq'] = $rGlTrans['seq'];
          $data[$v['filename']] = $v;
        }
      }
      
      foreach($data as $filename=>$val){
        $p = explode('.CSV',  strtoupper($filename));
        $encryptFilename = $filePrefix . strtoupper($p[0]) . '.txt';
        
        $localEncryptFile  = $location['cashierCheckDrop'] . $encryptFilename;
        Storage::put($localEncryptFile, Storage::disk('sftpEastWest')->get($remorePath . $encryptFilename));
        
        $localFile = $location['cashierCheck'] . $filename;
        Storage::put($localFile, Storage::disk('sftpEastWest')->get($remorePath . $filename));
        
        ############ GET DATA FROM BANK DROP FILE #############
        $rBankData = trim(Storage::get($localFile));
        if(!empty($rBankData)){
          $rBankData = explode("\r\n", $rBankData);
          array_pop($rBankData); // remove $rBankData[count($rBankData) - 1)]
          array_shift($rBankData); // remove $rBankData[0]
          
          foreach($rBankData as $i=>$v){
            list($vendor, $amount, $remark, $checkNo) = explode('","', trim($v,'"'));
            $checkNo = substr(trim($checkNo), -6);
            
            $rGlTrans = M::getTableData(T::$glTrans , Helper::selectData(['invoice', 'bank', 'batch', 'prop'], $val) + ['vendor'=>$val['vendid']]);
            $seq = array_values(Helper::keyFieldName($rGlTrans, 'seq', 'seq'));
            if(count($seq) == 2){
              $updateData = [
                T::$glTrans =>[
                  'whereInData'=>['field'=>'seq', 'data'=>$seq],
                  'updateData'=>['check_no'=>$checkNo, 'doc_no'=>$checkNo]
                ], 
                T::$cashierCheck=>[
                  'whereData'=>['id'=>$val['id']],
                  'updateData'=>['printed'=>1]
                ]
              ];
              
              try{
                # IT IS ALWAYS ONE TRANSACTION ONLY
                $success += Model::update($updateData);
                $elastic = [
                  T::$glTransView=>['seq'=>$seq],
                ];

                Model::commit([
                  'success' =>$success,
                  'elastic' =>['insert'=>$elastic],
                ]);
              } catch(\Exception $e){ // SEND OUT EMAIL SOMETHING WRONG
                dd($e);
                $errorMsg .= 'Something Wrong with Update the data File: ' . $filename;
              }
            } else { // SEND OUT EMAIL SOMETHING WRONG
              $errorMsg .= 'Gl trans result does not return back 2 transaction File: ' . $filename;
            }
          }
        }
      }
      
      if(!empty($errorMsg)){
        Mail::send([
          'to'      => 'sean@pamamgt.com',
          'from'    => 'admin@pamamgt.com',
          'subject' => 'Cashier Check Error on ' . date("F j, Y, g:i a"),
          'msg'     => $errorMsg
        ]);
      }
    }
  }
}