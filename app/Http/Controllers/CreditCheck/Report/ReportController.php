<?php
namespace App\Http\Controllers\CreditCheck\Report;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{RuleField, V, Form, Elastic, Mail, File, Html, Helper, Format, GridData, Upload, TenantAlert, GlobalVariable, Account,SpreadSheetGenerator, TableName AS T};


class ReportController extends Controller{
  private $_viewTable = '';
  private $_indexMain = '';
  
  public function __construct(){
    $this->_viewTable = T::$creditCheckView;
    $this->_indexMain = $this->_viewTable . '/' . $this->_viewTable . '/_search?';
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $op  = isset($req['op']) ? $req['op'] : 'index';
    $vData = V::startValidate(['rawReq'=>$req->all(), 'rule'=>GridData::getRule()])['data'];
    $vData['defaultFilter'] = ['application.run_credit'=>1];
    $qData = GridData::getQuery($vData, $this->_viewTable);
    $r     = Elastic::gridSearch($this->_indexMain . $qData['query']);
    $reportClass = '\App\Http\Controllers\CreditCheck\Report\\' . $op;
    return class_exists($reportClass) ? $reportClass::getReport($r, $req) : '';
  } 
}