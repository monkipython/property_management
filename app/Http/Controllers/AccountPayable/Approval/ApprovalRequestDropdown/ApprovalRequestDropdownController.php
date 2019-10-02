<?php
namespace App\Http\Controllers\AccountPayable\Approval\ApprovalRequestDropdown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{V, Form, Elastic, Html, Helper, HelperMysql, Format, GridData, Upload, Account, TableName AS T};
use App\Http\Models\{Model, ApprovalModel AS M};
use App\Http\Controllers\AccountPayable\Approval\ApprovalController AS P;

class ApprovalRequestDropdownController extends Controller {
  private static $_instance;
  
  public function __construct(){
      
  }
//------------------------------------------------------------------------------
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  } 
//------------------------------------------------------------------------------
  public function index(Request $req){
    $html = $this->getApprovalRequestDropdown($req);
    return ['html'=>$html];
  }
//------------------------------------------------------------------------------
  public function getBatchGroupLink($batchGroup){
    return env('APP_URL') . '/approval?batch_group=' . $batchGroup;
  }
//------------------------------------------------------------------------------
  public function getApprovalRequestDropdown($req) {
    $sortByName = [];
    $dropDown   = '';
    $mustQuery  = [
      'print' => 0,
      'approve.keyword' => array_values(P::getInstance()->approvalStatus),
      'usid.keyword'    => Helper::getUsid($req)
    ];
    if(P::getInstance()->isAuthorizedApprovalCheckUser($req)){
      $mustQuery['approve.keyword'] = P::getInstance()->approvalStatus['waiting'];
      unset($mustQuery['usid.keyword']);
    }
    
    $rVendorPayment = Helper::getElasticAggResult(Elastic::searchQuery([
      'index' => T::$vendorPaymentView,
      'size'  => 0,
      'query' => [
        'must'  => $mustQuery, 
        'must_not'=>['batch_group'=>0]
      ],
      'aggs' => [
        'by_batch_group'=>[
          'terms'=>['field'=>'batch_group.keyword', 'size'=>10000], 
          'aggs'=> [
            'by_belongTo'=> [
              'terms'=>['field'=>'belongTo.keyword']
            ],
            'top_date_hits' => [
              'top_hits' => [
                '_source' => [
                  'includes' => ['send_approval_date']
                ],
                'size' => 1
              ]
            ]
          ]
        ]
      ]
    ]),'by_batch_group');

    foreach($rVendorPayment as $v) {
      $batchGroup = $v['key'];
      $belongTo   = $v['by_belongTo']['buckets'][0]['key'];
      $sortByName[$belongTo.$batchGroup] = $v;
    }
    ksort($sortByName);
    $dropDown  .= Html::li(Html::a('View All Requests', ['href'=>'/approval']));
    foreach($sortByName as $i => $val) {
      $batchGroup = $val['key'];
      $belongTo   = $val['by_belongTo']['buckets'][0]['key'];
      $date       = $val['top_date_hits']['hits']['hits'][0]['_source']['send_approval_date'];
      $dropDown  .= Html::li(Html::a($belongTo . ' Requested On ' . Format::usDate($date), ['href'=>$this->getBatchGroupLink($batchGroup)]));
    }
    $count     = Html::span(count($rVendorPayment), ['class'=>'badge bg-yellow']);
    $caret     = Html::span('',['class'=>'caret']);
    $btn       = Html::button($count . ' Approval Request ' . $caret, ['type'=>'button','class'=>'btn btn-info dropdown-toggle tip','title'=>'View All The Requests For Approval','data-toggle'=>'dropdown','aria-haspopup'=>'true','aria-expanded'=>'false']);
    $ul        = Html::ul($dropDown, ['class'=>'dropdown-menu']);
    $container = Html::div($btn . $ul, ['class'=>'btn-group','id'=>'requestDropdownList']);
    return $container; 
  }
}
