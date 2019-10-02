<?php
namespace App\Http\Controllers\AccountPayable\GardenHoa\ExportCsv;
use Illuminate\Http\Request;
use App\Library\{V, GridData, TableName AS T, Helper};
use App\Http\Controllers\Controller;
use App\Http\Controllers\AccountPayable\GardenHoa\ExportCsv\GardenHoaCsv;

class GardenHoaExportController extends Controller {
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
      case 'csv'     : return GardenHoaCsv::getCsv($vData);
    }
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################
  private function _getRule(){
    return [
      'vendor_gardenHoa_id' => 'nullable|integer',
    ];
  }
}
