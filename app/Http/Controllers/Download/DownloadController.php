<?php
namespace App\Http\Controllers\Download;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Library\{RuleField};
use App\Library\{RuleField, V, Form, Elastic,File, Mail, Html, Helper, Auth};
use App\Http\Models\Autocomplete AS M; // Include the models class


class DownloadController extends Controller{
  private $_data	  = [];
  private $_fieldData = [];
  private $_viewPath  = 'app/creditCheck/';
  private $_viewGlobalAjax  = 'global/ajax';
  private $_viewGlobalHtml  = 'global/html';
  
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){ 
  /**
   * @desc downlod the file 
   * @logic
   *  1. Get file name from the url
   *  2. Get the file base on what file name with static _location 
   *  3. Move the file to tmp directory
   *  4. Rename the file to $this->usr . pdf so that it will be one file only 
   *     in the user download directory
   *  5. Force users to download the file 
   */
    
    try{
      $type = $req['type'];
      $path = isset(File::getLocation('report')[$type]) ? File::getLocation('report')[$type] : (isset(File::getLocation('CreditCheck')[$type]) ? File::getLocation('CreditCheck')[$type] : '');
      if(File::isExist($path . $id)){
        $file = $path . $id;
        header('Content-Transfer-Encoding: binary');  // For Gecko browsers mainly
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
        header('Accept-Ranges: bytes');  // For download resume
        header('Content-Length: ' . filesize($file));  // File size
        header('Content-Encoding: none');
        header('Content-Type: application/pdf');  // Change this mime type if the file is not PDF
        header('Content-Disposition: attachment; filename=' . $id);  // Make the browser display the Save As dialog
        readfile($file);  //this is necessary in order to get it to actually download the file, otherwise it will be 0Kb
      }
    }catch(Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
}