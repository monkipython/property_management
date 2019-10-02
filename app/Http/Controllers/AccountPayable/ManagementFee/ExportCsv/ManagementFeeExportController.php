<?php
namespace App\Http\Controllers\AccountPayable\ManagementFee\ExportCsv;
use Illuminate\Http\Request;
use App\Library\{V, GridData, TableName AS T, Helper};
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\ManagementFee\ExportCsv\ManagmentFeeCsv;

class ManagementFeeExportController extends Controller {
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
      case 'csv'     : return ManagementFeeCsv::getCsv($vData);
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'vendor_util_payment_id' => 'nullable|integer',
    ];
  }
}
