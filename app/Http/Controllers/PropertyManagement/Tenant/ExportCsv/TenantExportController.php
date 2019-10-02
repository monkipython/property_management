<?php
namespace App\Http\Controllers\PropertyManagement\Tenant\ExportCsv;
use Illuminate\Http\Request;
use App\Library\{V, GridData};
use App\Http\Controllers\Controller;
use App\Http\Controllers\PropertyManagement\Tenant\ExportCsv\TenantCsv;

class TenantExportController extends Controller {
  public function index(Request $req){
    $valid  = V::startValidate([
      'rawReq'  => $req->all(),
      'rule'    => GridData::getRule(),
    ]);
    
    $vData  = $valid['data'];
    $op     = $valid['op'];
    
    switch($op){
      case 'csv' : return TenantCsv::getCsv($vData);
    }
  }
}
