<?php
namespace App\Http\Controllers\AccountReceivable\CashRec\RpsDeleteFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, GridData, Upload, TableName AS T, Helper, HelperMysql, TenantTrans, Format};
use \App\Http\Controllers\AccountReceivable\CashRec\CashRecController as P;
use App\Http\Models\Model; // Include the models class
use Storage;

class RpsDeleteFileController extends Controller{
//------------------------------------------------------------------------------
  public function destroy($id){
    if(Storage::disk('RPS')->has($id)){
      Storage::disk('RPS')->delete($id);
    }
  }
}