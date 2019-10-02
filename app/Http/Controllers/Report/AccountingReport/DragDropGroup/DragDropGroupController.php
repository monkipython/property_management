<?php
namespace App\Http\Controllers\Report\AccountingReport\DragDropGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, TableName AS T,Html};
use App\Http\Models\{Model}; // Include the models class

class DragDropGroupController extends Controller{

  public function update(Request $req) {
    $valid = V::startValidate([
      'rawReq'      => $req->all(),
      'tablez'      => [T::$reportGroup],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$reportGroup.'|report_group_id'
        ]
      ]
    ]);
    $vData = $valid['data'];
    $updateData = [];
    $count = 1;
    foreach($vData['report_group_id'] as $k => $v) {
      $updateData[T::$reportGroup][] = [
        'whereData'   => ['report_group_id' => $v],
        'updateData'  => [
          'order' => $count++,
        ]  
      ];
    }
    DB::beginTransaction();
    $success = $response = $elastic = [];
    try{
      $success += Model::update($updateData);
      $response['mainMsg'] = Html::sucMsg('Successfully Updated.');
      Model::commit([
        'success' =>$success,
        'elastic' =>$elastic,
      ]);
    } catch(Exception $e){
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
  
}
