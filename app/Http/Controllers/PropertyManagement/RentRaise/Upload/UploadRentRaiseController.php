<?php
namespace App\Http\Controllers\PropertyManagement\RentRaise\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\{RuleField,Form, Elastic, Html, Helper, Upload, V, File, TableName AS T};
use App\Http\Models\{Model,RentRaiseModel AS M}; // Include the models class
use Illuminate\Support\Facades\DB;

/*
UPDATE ppm.accountProgram SET subController='uploadRentRaise' WHERE classController='rentRaise' AND method='index';
 */
class UploadRentRaiseController extends Controller{
  public function __construct(Request $req){
  }
//------------------------------------------------------------------------------
  public function index(Request $req){
    $perm = Helper::getPermission($req);
    $req->merge(['tenant_id'=>$req['id']]);
    unset($req['id']);
    $valid = V::startValidate([
      'rawReq'=>$req->all(),
      'isAjax'=>$req->ajax(),
      'rule'  =>['tenant_id'=>'required|integer','rent_raise_id'=>'nullable|integer'],
      'orderField'=>$this->_getOrderField(__FUNCTION__),
      'validateDatabase'=>[
        'mustExist'=>[
          T::$tenant . '|tenant_id,status:C', 
        ]
      ]
    ]);
    $vData = $valid['dataNonArr'];

    $r  = !empty($vData['rent_raise_id']) ? $this->_getNoticeFiles($vData['tenant_id'],$vData['rent_raise_id']) : $this->_getFileLinks($vData['tenant_id']);
    return $this->_getViewlistFileByPath($r, '/uploadRentRaise');
  }
//------------------------------------------------------------------------------
  public function show($id, Request $req){
    $valid = V::startValidate([
      'rawReq'    =>['rent_raise_id'=>$id] + $req->all(),
      'rule'      =>['rent_raise_id'=>'required|integer'],
      'orderField'=>['rent_raise_id'],
      'validateDatabase'=>[
        'mustExist'=>[
          T::$rentRaise . '|rent_raise_id'
        ]
      ]
    ]);
    $r  = M::getRentRaise(Model::buildWhere(['rent_raise_id'=>$id]),['rent_raise_id','file']);
    $path  = $r['file'];
    if($req->ajax()) {
      return Upload::getViewContainerType($path);
    } else{
      header('Location: ' . $path);
      exit;
    }
  }
//------------------------------------------------------------------------------
  public function edit($id, Request $req){
    $html = Upload::getHtml();
    $ul = Html::ul('', ['class'=>'nav nav-pills nav-stacked', 'id'=>'uploadList']);
    return Html::div(
      Html::div($html['container'], ['class'=>'col-md-12']), 
      ['class'=>'row']
    ) .
    Html::div(
      Html::div($ul, ['class'=>'col-md-3']) . Html::div('', ['class'=>'col-md-9', 'id'=>'uploadView']), 
      ['class'=>'row']
    ) . 
    $html['hiddenForm'];
  }
//------------------------------------------------------------------------------
  public function update($id, Request $req){
  }
//------------------------------------------------------------------------------
  public function create(){
  }
//------------------------------------------------------------------------------
  public function store(Request $req){
    
  }
//------------------------------------------------------------------------------
  public function destroy($id, Request $req){
   
  }
################################################################################
##########################    FIELD SECTION    #################################  
################################################################################
  private function _getOrderField($fn){
    $orderField = [
      'index' =>['tenant_id'],
    ];
    return $orderField[$fn];
  }  
//------------------------------------------------------------------------------
  private function _getViewlistFileByPath($r, $endPoint, $id = 'upload',$param=[]){
    $ul                = [];
    $isMobileIos       = preg_match('/(ipod|iphone|ipad|ios)/',strtolower($_SERVER['HTTP_USER_AGENT']));
    foreach($r as $i=>$v){
      $active = $i == 0 ? ' active' : '';

      $href               = $v['file'];
      $pdfParam           = $isMobileIos ? '' : 'eachPdf';
      $hrefParam          = $isMobileIos ? ['href'=>$href,'target'=>'_blank','title'=>'Click to View File'] : [];
      $deleteIcon         = !empty($param['includeDeleteIcon']) ? Html::i('',['class'=>'deleteFile fa fa-fw fa-trash text-red','uuid'=>$v['rent_raise_id'],'data-type'=>'pdf']) . '   ' : '';
      $ul[]               = [
        'value'   => Html::a($deleteIcon . Html::span('',['class'=>'fa fa-fw fa-file-pdf-o']) . ' ' . date('Y-m-d',strtotime($v['submitted_date'])) . ': ' . basename($v['file']),$hrefParam),
        'param'   => ['class'=>$pdfParam . ' pointer ' . $active,'uuid'=>$v['rent_raise_id']],
      ];
    }
    $ul = Html::buildUl($ul, ['class'=>'nav nav-pills nav-stacked', 'id'=>$id . 'List']);
    $script = "<script> 
      $('.eachPdf').unbind('click').on('click', function(){
        var self = $(this);
        var uuid = $(this).attr('uuid');
        $('.eachPdf').removeClass('active');
        $(this).addClass('active');

        AJAX({
          url: '" . $endPoint . "/' + uuid,
          data: {size: 200}, 
          success: function(ajaxData){
            $('#".$id."View').html(ajaxData.html);
            
            // DELETE UPLOAD FILE
            UPLOADDELETE(self, uuid, '" . $endPoint . "','#gridTable');
          }
        });
      });
      $('.eachPdf').first().trigger('click');
    </script>";

    return Html::div(
      Html::div($ul, ['class'=>'col-md-3']) . Html::div('', ['class'=>'col-md-9', 'id'=>$id . 'View']), 
      ['class'=>'row']
    ). $script;
  }
//------------------------------------------------------------------------------
  private function _getFileLinks($id){
    $rRentRaise    = M::getRentRaiseElastic(['tenant_id'=>$id],['tenant_id',T::$rentRaise]);
    $rentRaiseData = Helper::getValue(T::$rentRaise,$rRentRaise,[]);
    
    $rentRaiseLinks= $rentRaiseIds = [];
    foreach($rentRaiseData as $i=>$v){
      if(!empty($v['file'])){
        $rentRaiseLinks[$v['file']] = ['file'=>$v['file'],'submitted_date'=>$v['submitted_date'],'rent_raise_id'=>$v['rent_raise_id']];
      }
    }
    $rentRaiseLinks = array_reverse($rentRaiseLinks);
    return $rentRaiseLinks;
  }
//------------------------------------------------------------------------------
  private function _getNoticeFiles($tenantId,$rentRaiseId){
    $r = M::getRentRaise(Model::buildWhere(['foreign_id'=>$tenantId,'rent_raise_id'=>$rentRaiseId]),['rent_raise_id','file','submitted_date']);
    return !empty($r) && !isset($r[0]) ? [$r] : [];
  }
}
