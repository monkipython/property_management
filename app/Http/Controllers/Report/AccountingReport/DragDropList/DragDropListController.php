<?php
namespace App\Http\Controllers\Report\AccountingReport\DragDropList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, TableName AS T, Html};
use App\Http\Models\{Model}; // Include the models class

class DragDropListController extends Controller{

  public function update($id, Request $req) {
    $valid = V::startValidate([
      'rawReq'        => $req->all(),
      'tablez'        => [T::$reportList],
      'orderField'    => ['report_list_id', 'report_group_id', 'selected'],
      'setting'       => ['rule'=>['report_list_id'=>'required', 'report_group_id'=>'required', 'selected'=>'required']],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$reportList.'|report_list_id'
        ]
      ]
    ]);
    $vData = $valid['data'];
    ## Id is used to split the ID's in half
    $firstList  = array_slice($vData['report_list_id'], 0, $id);
    $secondList = array_slice($vData['report_list_id'], $id);
    $updateData = [];
    $count = 1;
    foreach($firstList as $k => $v) {
      $uData = [
        'whereData'   => ['report_list_id' => $v],
        'updateData'  => [
          'order' => $count++,
        ]  
      ];
      if($v == $vData['selected']) {
        $uData['updateData']['report_group_id'] = $vData['report_group_id'];
      }
      $updateData[T::$reportList][] = $uData;
    }
    $count = 1;
    foreach($secondList as $k => $v) {
      $uData = [
        'whereData'   => ['report_list_id' => $v],
        'updateData'  => [
          'order' => $count++,
        ]  
      ];
      if($v == $vData['selected']) {
        $uData['updateData']['report_group_id'] = $vData['report_group_id'];
      }
      $updateData[T::$reportList][] = $uData;
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
