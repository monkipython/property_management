<?php
namespace App\Http\Controllers\AccountReceivable\Service\Report;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, GridData};
use App\Http\Controllers\AccountReceivable\Service\ExportCsv\ServiceCsv;
use App\Http\Controllers\AccountReceivable\Service\Report\ServiceReport;

class ReportController extends Controller{

  public function index(Request $req){
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
    
    switch($op){
      case 'csv' : return ServiceCsv::getCsv($vData);
      case 'ServiceReport' : return ServiceReport::getReport($vData);
    }
  } 
}