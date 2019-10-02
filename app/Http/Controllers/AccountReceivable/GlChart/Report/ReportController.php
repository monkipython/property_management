<?php
namespace App\Http\Controllers\AccountReceivable\GlChart\Report;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{V, GridData};
use App\Http\Controllers\AccountReceivable\GlChart\ExportCsv\GlChartCsv;
use \App\Http\Controllers\AccountReceivable\GlChart\Report\GlChartReport;

class ReportController extends Controller{
  
  public function index(Request $req){
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];

    switch($op){
      case 'csv' : return GlChartCsv::getCsv($vData);
      case 'GlChartReport' : return GlChartReport::getReport($vData);
    }
  } 
}