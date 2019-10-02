<?php
namespace App\Console\Report\Section8Report;
use Illuminate\Console\Command;
use App\Library\{TableName AS T, Mail};
use App\Http\Controllers\Report\Section8Report\Section8ReportController as P;

class Section8Report extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'report:section8';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Generate Section 8 Daily Report';
  /**
   * Create a new command instance.
   * @return void
   */
  private $_viewTable = '';
  private $_indexMain = '';
  public function __construct(){
    parent::__construct();
    $this->_viewTable = T::$section8View;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
  /**
   * Execute the console command.
   * @return mixed
   * @howToRun
   */
  public function handle(){
    $report = 'Section 8 Inspection Report';
    $type  = P::getInstance()->typeOption;
    foreach($type as $fl=>$v){
      $r = P::getInstance()->getData(['op'=>'pdf', 'data'=>['prop'=>'0001-9999','type'=>$fl, 'dateRange'=>'03/01/2000 - ' . date('m/d/Y')]]);
      Mail::send([
        'to'=>'mike@pamamgt.com,sean@pamamgt.com,djkler@pamamgt.com,jesse@pamamgt.com,ryan@pamamgt.com,brian@pamamgt.com',
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