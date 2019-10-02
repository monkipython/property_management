<?php
namespace App\Http\Controllers\PropertyManagement\TenantEvictionProcess\ExportCsv;
use Illuminate\Http\Request;
use App\Library\{V, GridData};
use App\Http\Controllers\Controller;
use App\Http\Controllers\PropertyManagement\TenantEvictionProcess\ExportCsv\TenantEvictionProcessCsv;

class TenantEvictionProcessExportController extends Controller {
  public function index(Request $req){
    $valid  = V::startValidate([
      'rawReq'  => $req->all(),
      'rule'    => GridData::getRule(),
    ]);
    
    $vData  = $valid['data'];
    $op     = $valid['op'];
    switch($op){
      case 'csv' : return TenantEvictionProcessCsv::getCsv($vData);
    }
  }
}