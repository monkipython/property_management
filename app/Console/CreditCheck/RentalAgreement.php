<?php

namespace App\Console\Creditcheck;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Models\{Model,RentalAgreementModel as M};
use App\Http\Controllers\CreditCheck\Agreement\RentalAgreement as Agreement;
use App\Library\{TableName as T,Mail,Elastic,File,Helper,Html};
use PDF;
use Storage;

class RentalAgreement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'creditCheck:generateAgreement {--generate=*} {--generate-all=true} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and update agreement reports and corresponding download links for submitted rental agreements';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    
    private $_storageDir  = '';
    private $_viewTable   = '';
    private $_indexMain   = '';
    private $_dbTable     = '';
    private $_generateAll = true;
    private $_toGenerate  = [];
    private $_fontSize    = 6;
    private $_font        = 'times';
    
    public function __construct()
    {
        parent::__construct();
        $this->_viewTable  = T::$creditCheckView;
        $this->_indexMain  = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
        $this->_dbTable    = T::$rentalAgreement;
        $this->_storageDir = storage_path('app/public/report/creditCheck/');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Determine if generating for all rental agreements in the database
        // or for specific rental agreements specified by application id
        $this->_generateAll = (bool) $this->option('generate-all',true) == 'true';
    
        //Get all rental agreements to generate pdf's for and update
        $this->_toGenerate  = $this->_generateAll ? M::getRentalAgreement([],'*',0) : M::fetchAgreementsByColVals('application_id',$this->option('generate',[]));

        
        $debug = $this->option('debug',false);
        
        //Debug JSON for output
        $responses = [
          'generate'=>[
            'success'=>[],
            'error'  =>[],
          ]
        ];
        
        foreach($this->_toGenerate as $k => $v){
          //Don't generate a pdf link for an agreement that has a download link and an existing pdf in storage
          if(!empty($v['pdfLink']) && $this->_isValidLink($this->_parseStorageLink($v['pdfLink']))){
            $responses['generate']['error'][] =  'A valid PDF link for this file already exists';
          } else {
            //Use database html to generate a pdf file
            $linkResponse                = Agreement::generatePdfFile($v);
            //$linkResponse              = $this->_generatePdfFile($v);
            ############### DATABASE SECTION ######################
            $success    = $elastic = [];
            $updateData                = [
              $this->_dbTable => [
                'whereData'   => ['application_id' => $linkResponse['application_id']],
                'updateData'  => ['pdfLink' => $linkResponse['link']]
              ]
            ];
            DB::beginTransaction();
            try {
              $success += Model::update($updateData);
              Model::commit([
                'success' => $success,
                'elastic' => $elastic,
              ]);
              
              //Add to success output
              $responses['generate']['success'][] = ['response'=>$linkResponse];
            } catch (Exception $e){
              Model::rollback($e);
              //Add to error output
              $responses['generate']['error'][] = 'Error updating record for application: ' . $linkResponse['application_id'];
            }
          }          
        }
        
        $generateCount = count($responses['generate']['success']);
        //Always display amount of pdf's generate
        $this->info('Successfully generated/updated ' . $generateCount . ' pdfs and links.');
        
    }
################################################################################
##########################    HELPER FUNCTIONS   ###############################
################################################################################
//------------------------------------------------------------------------------
  /*
   * @desc Generate PDF file from HTML data retrieved from databsae
   * @params {array} $r: Database row
   * @returns {array}
   */
  private function _generatePdfFile($r){
    try {

      $supervisorId = $r['account_id']; 
      
      //Get supervisor from account view from Elastic search
      $supervisor   = Elastic::searchMatch(T::$accountView,['match'=>['account_id'=>$supervisorId]]);
      $supervisor   = !empty($supervisor['hits']['hits']) ? $supervisor['hits']['hits'][0]['_source'] : [];
      
      //Get supervisor name
      $superName    = !empty($supervisor) ? $supervisor['firstname'] : 'PAMA_MANAGEMENT';

      //Generate directory and path for generated pdf
      $path = File::mkdirNotExist(File::getLocation('CreditCheck')['AgreementReport']);
      //Generate pdf filename
      $file = $superName . date('-Y-m-d-H-i-s') . '.pdf';
    
      //Get agreement data from database row
      $agreementData = isset($r['agreement']) ? json_decode($r['agreement'],true): [];
      
      //Get agreement data HTML
      $content       = !empty($agreementData) ? $agreementData['chunks'] : [];
      
      PDF::reset();
      PDF::SetTitle('Rental Agreement');
      PDF::setPageOrientation(PDF_PAGE_ORIENTATION);

      # HEADER SETTING
      PDF::SetHeaderData('', '0', 'Pama Management Co. Tenant Rental Agreement',' Run on ' . date('F j, Y, g:i a'));
      PDF::setHeaderFont([$this->_font, '', $this->_fontSize]);
      PDF::SetHeaderMargin(3);

      # FOOTER SETTING
      PDF::SetFont($this->_font, '',$this->_fontSize);
      PDF::setFooterFont([$this->_font, '', $this->_fontSize]);
      PDF::SetFooterMargin(5);
      
      # MARGIN SETTING
      PDF::SetMargins(5,10,5);
      PDF::SetAutoPageBreak(TRUE, 10);
      
      PDF::AddPage();
    
      
      foreach($content as $k => $v){
        //Write HTML chunk to pdf file
        PDF::writeHTML($v,true,false,true,false,'');
      }
     
      //Generate PDF file
      PDF::Output($path . $file, 'F');
      
      //Remove no longer needed images from server storage
      $dirPath  = public_path()  . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $agreementData['dirId'];
      $imgFiles = array_diff(scandir($dirPath),array('.','..'));
      foreach($imgFiles as $v){
        unlink($dirPath . DIRECTORY_SEPARATOR . $v);
      }
      if(file_exists($dirPath)){
        rmdir($dirPath);
      }
      
      $link = \Storage::disk('public')->url('report/creditCheck/' . $file);
      return ['application_id'=>$r['application_id'],'link'=>$link];
    } catch (Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
//------------------------------------------------------------------------------    
    private function _parseStorageLink($href){
      //Extract filename from download link and add it to the storage path
      return $this->_storageDir . basename($href);
    }
//------------------------------------------------------------------------------    
    private function _isValidLink($path){
      //Determine if pdf file exists in storage
      return file_exists($path);
    }
}
