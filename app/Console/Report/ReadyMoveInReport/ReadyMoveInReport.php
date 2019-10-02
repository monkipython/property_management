<?php
namespace App\Console\Report\ReadyMoveInReport;
use Illuminate\Console\Command;
use App\Library\{TableName AS T, Mail};
use App\Http\Controllers\Report\ReadyMoveInReport\ReadyMoveInReportController as P;

class ReadyMoveInReport extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'report:readyMoveIn';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Generate Ready Move In Daily Report';
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
    $report = 'Ready Move In Report';
    $r      = P::getInstance()->getData(['op'=>'pdf','data'=>[]]);
    Mail::send([
      'to'            => 'sean@pamamgt.com,djkler@pamamgt.com,jesse@pamamgt.com,ryan@pamamgt.com',
      'from'          => 'admin@pamamgt.com',
      'subject'       => $report . ' Daily :: Run on ' . date('F j, Y, g:i a'),
      'pathFilename'  => $r['file'],
      'filename'      => basename($r['file']),
      'msg'           => 'Please find the for the ' . $report . ' on ' . date('m/d/Y'),
    ]);
   
  }
}