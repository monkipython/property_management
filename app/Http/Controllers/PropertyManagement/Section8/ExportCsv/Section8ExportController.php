<?php
namespace App\Http\Controllers\PropertyManagement\Section8\ExportCsv;
use Illuminate\Http\Request;
use App\Library\{V, GridData};
use App\Http\Controllers\Controller;
use App\Http\Controllers\PropertyManagement\Section8\ExportCsv\Section8Csv;

class Section8ExportController extends Controller {  
  public function __construct(){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'   => $req->all(),
      'rule'     => GridData::getRule()
    ]);
    
    $vData = $valid['data'];
    $op    = $valid['op'];
    
    switch($op){
      case 'csv': return Section8Csv::getCsv($vData);
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
}


