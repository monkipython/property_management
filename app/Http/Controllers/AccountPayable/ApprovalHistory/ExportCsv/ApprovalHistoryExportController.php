<?php
namespace App\Http\Controllers\AccountPayable\ApprovalHistory\ExportCsv;
use Illuminate\Http\Request;
use App\Library\{V, GridData, TableName AS T, Helper};
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\ApprovalHistory\ExportCsv\{ApprovalHistoryCsv,ApprovalHistoryPdf,ApprovalHistoryCheckCopy};

class ApprovalHistoryExportController extends Controller {
  public function __construct(){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $valid = V::startValidate([
      'rawReq'   => $req->all(),
      'rule'     => GridData::getRule() + $this->_getRule(),
    ]);

    $vData = $valid['data'];
    $op    = $valid['op'];

    switch($op){
      case 'csv'                  : return ApprovalHistoryCsv::getCsv($vData);
      case 'pdf'                  : return ApprovalHistoryPdf::getPdf($vData);
      case 'checkCopy'            : return ApprovalHistoryCheckCopy::getPdf($vData);
      case 'checkCopyUploadView'  : return ApprovalHistoryCheckCopy::getUploadViewListModal($vData);
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'vendor_payment_id' => 'nullable|integer',
      'path'              => 'nullable|string',
    ];
  }
}

