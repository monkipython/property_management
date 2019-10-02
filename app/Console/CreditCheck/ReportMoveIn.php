<?php
namespace App\Console\Creditcheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Survey\Survey AS M;
use App\Library\{Elastic, Helper, TableName AS T, RuleField, File, V, GridData, Mail};
class ReportMoveIn extends Command{
  /**
   * The name and signature of the console command.
   * @var string
   * @howTo: 
   */
  protected $signature = 'report:movein';
  
  /**
   * The console command description.
   * @var string
   */
  protected $description = 'Generate Move In Daily Report';
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
    $today = date('Y-m-d');
    $rawData = [
      'sort'=>'prop',
      'order'=>'asc',
      'limit'=>'-1', 
      'filter'=>json_encode(['housing_dt2'=>$today]),
    ];
    
    $vData = V::startValidate(['rawReq'=>$rawData, 'rule'=>GridData::getRule()])['data'];
    $qData = GridData::getQuery($vData, $this->_viewTable);
    $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
    $reportClass = '\App\Http\Controllers\CreditCheck\Report\MoveinReport';
    $path = File::getLocation('report')['MoveinReport'];
    $pdfData = $reportClass::getReport($r);
    Mail::send([
      'to'=>'mike@pamamgt.com,sean@pamamgt.com,luciano@ierentalhomes.com,djkler@pamamgt.com,jesse@pamamgt.com,ryan@pamamgt.com',
      'from'=>'admin@pamamgt.com',
      'subject' =>'Move In Report Daily :: Run on ' . date("F j, Y, g:i a"),
      'pathFilename'=>$path.$pdfData['file'],
      'filename'=>$pdfData['file'],
      'msg'=>'Please find the attachment for the Move In Report on ' . date('m/d/Y', strtotime($today)) . '<br><hr><br><br>'
    ]);
  }
}