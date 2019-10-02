<?php
namespace App\Http\Controllers\PropertyManagement\Unit\ExportCsv;
use Illuminate\Http\Request;
use App\Library\{V, GridData, TableName AS T, Helper};
use App\Http\Controllers\Controller;
use App\Http\Controllers\PropertyManagement\Unit\ExportCsv\UnitCsv;

class UnitExportController extends Controller {  
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
      case 'csv': return UnitCsv::getCsv($vData);
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
}

