<?php
namespace App\Console\Report\MoveOutReport;
use Illuminate\Console\Command;
use App\Library\{TableName AS T, Mail};
use App\Http\Controllers\Report\MoveOutReport\MoveOutReportController as P;

class MoveOutReport extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'report:moveout';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Generate Move Out Daily Report';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_viewTable = '';
  private $_indexMain = '';
  public function __construct(){
    parent::__construct();
    $this->_viewTable = T::$tenantView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $report = 'Move Out Report';   
    $dateRange = date('m/d/Y - m/d/Y');
    $r  = P::getInstance()->getData(['op'=>'pdf', 'data'=>['dateRange'=>$dateRange,'prop_type'=>'']]);

    Mail::send([
      'to'=>'mike@pamamgt.com,sean@pamamgt.com,djkler@pamamgt.com,jesse@pamamgt.com,ryan@pamamgt.com,brian@pamamgt.com',
      'from'=>'admin@pamamgt.com',
      'subject' =>$report . ' Daily :: Run on ' . date("F j, Y, g:i a"),
      'pathFilename'=>$r['file'],
      'filename'=>basename($r['file']),
      'msg'=>'Please find the attachment for the '.$report.' Report on ' . date('m/d/Y', strtotime(date('Y-m-d')))
    ]);
  }
}