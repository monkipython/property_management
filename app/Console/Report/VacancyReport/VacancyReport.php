<?php
namespace App\Console\Report\VacancyReport;
use Illuminate\Console\Command;
use App\Library\{TableName AS T, Mail};
use App\Http\Controllers\Report\VacancyReport\VacancyReportController as P;

class VacancyReport extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'report:vacancy';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Generate Vacancy Daily Report';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_viewTable = '';
  private $_indexMain = '';
  public function __construct(){
    parent::__construct();
    $this->_viewTable = T::$creditCheckView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $report = 'Vacancy Report';
    $type  = P::getInstance()->typeOption;
    foreach($type as $fl=>$v){
      $r  = P::getInstance()->getData(['op'=>'pdf', 'data'=>['prop'=>'0001-9999','type'=>$fl]]);
      $to = ($fl == 'city') ? ',ryan@pamamgt.com': '';
      Mail::send([
        'to'=>'sean@pamamgt.com,djkler@pamamgt.com,jesse@pamamgt.com' . $to,
        'from'=>'admin@pamamgt.com',
        'subject' =>$report . ' By '.$v.' Daily :: Run on ' . date("F j, Y, g:i a"),
        'pathFilename'=>$r['file'],
        'filename'=>basename($r['file']),
        'msg'=>'Please find the attachment for the '.$report.' Report By '.$v.' on ' . date('m/d/Y', strtotime(date('Y-m-d')))
      ]);
      sleep(5);
    }
  }
}