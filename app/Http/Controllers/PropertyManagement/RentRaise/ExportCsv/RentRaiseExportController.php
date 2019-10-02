<?php
namespace App\Http\Controllers\PropertyManagement\RentRaise\ExportCsv;
use Illuminate\Http\Request;
use App\Library\{V, GridData, TableName AS T, Helper};
use App\Http\Controllers\Controller;
use App\Http\Controllers\PropertyManagement\RentRaise\ExportCsv\{RentRaiseCsv, RentRaisePdf, RentRaisePrint};

class RentRaiseExportController extends Controller {
  public function __construct(){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'rule'        => GridData::getRule() + $this->_getRule(),
      'includeUsid' => 1,
    ]);

    $vData = $valid['data'];
    $op    = $valid['op'];
  
    switch($op){
      case 'csvAll'         : return RentRaiseCsv::getCsv($vData,0);
      case 'csvPending'     : return RentRaiseCsv::getCsv($vData,1);
      case 'printAll'       : return RentRaisePrint::getPrint($vData,0);
      case 'printPending'   : return RentRaisePrint::getPrint($vData,1);
      case 'pdfAll'         : return RentRaisePdf::getPdf($vData,0);
      case 'pdfPending'     : return RentRaisePdf::getPdf($vData,1);
    } 
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'tenant_id'     => 'nullable|integer',
      'includePopup'  => 'nullable',
    ];
  }
}
