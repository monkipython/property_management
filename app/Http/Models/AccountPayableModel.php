<?php
namespace App\Http\Models;
use App\Library\{TableName AS T, Elastic, Helper};
use Illuminate\Support\Facades\DB;
use App\Http\Models\Model;

class AccountPayableModel extends DB {
//------------------------------------------------------------------------------
  public static function getBankElastic($query, $source = []){
    return Elastic::searchQuery([
      'index'   =>T::$bankView,
      'sort'    =>[['bank.keyword'=>'asc'],['name.keyword'=>'asc']],
      '_source' =>['includes'=>$source],
      'size'    =>100,
      'query'   =>[
        'must'  =>$query
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public static function getBank($where, $select = '*', $firstRowOnly = 1){
    $r = DB::table(T::$prop . ' AS p')->select($select)
        ->join(T::$propBank . ' AS pb', 'pb.prop', '=', 'p.prop')
        ->leftJoin(T::$bank . ' AS b', 'b.prop', '=', 'pb.trust')
        ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function deleteTableData($table,$where){
    return DB::table($table)->where($where)->delete();
  }
//------------------------------------------------------------------------------
  public static function deleteWhereIn($table,$field,$data){
    return DB::table($table)->whereIn($field,$data)->delete();
  }
//------------------------------------------------------------------------------
  public static function getDataFromTable($index, $query, $param = []){
    $sort = !empty($param['sort']) ? $param['sort'] : [];
    return Elastic::searchQuery([
      'index'=>$index,
      'sort'    =>$sort,
      '_source'=>['includes'=>isset($param['source']) ? $param['source'] : []],
      'query'=>[
        'must'=>$query
      ]
    ]);
  }
//------------------------------------------------------------------------------
  public static function getVendorPaymentIn($whereField,$whereData,$select='*',$firstRowOnly=0,$where=[]){
    $r = DB::table(T::$vendorPayment)->select($select)->whereIn($whereField,$whereData);
    $r = !empty($where) ? $r->where($where) : $r;
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getFileUpload($where, $firstRowOnly = 0,$select='*'){
    $r = DB::table(T::$fileUpload . ' AS f')->select($select)
        ->leftJoin(T::$vendorMortgage . ' AS v', 'f.foreign_id', '=', 'v.vendor_mortgage_id')
        ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getFileUploadIn($whereField,$whereData,$select='*',$firstRowOnly=0,$where=[]){
    $r = DB::table(T::$fileUpload)->select($select)->whereIn($whereField,$whereData);
    $r = !empty($where) ? $r->where($where) : $r;
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getFileUploadJoin($where,$joinTable,$joinColumn,$firstRowOnly=0,$select='*'){
    $r   = DB::table(T::$fileUpload . ' AS f')->select($select)
           ->leftJoin($joinTable . ' AS v','f.foreign_id','=','v.' . $joinColumn)
           ->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
################################################################################
#########################   GET VIEW DATA FUNCTION   ###########################  
################################################################################
//------------------------------------------------------------------------------
  public static function getVendorElastic($must,$source=[]){
    $queryBody    = ['index'=>T::$vendorView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getUtilPaymentElastic($must,$source=[],$firstRowOnly=0){
    $queryBody    = ['index' => T::$vendorUtilPaymentView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody   += $firstRowOnly ? ['size'=>1] : [];
    $r            = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getValue('_source',Helper::getElasticResult($r,1),[]) : $r;
  }
//------------------------------------------------------------------------------
  public static function getInsuranceElastic($must,$source=[]){
    $queryBody    = ['index' => T::$vendorInsuranceView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getVendorApprovalElastic($query,$source=[]){
    $queryBody    = ['index' => T::$vendorPaymentView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($query['query']) ? ['query'=>$query['query']] : ['query'=>['must'=>$query]];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getBusinessLicenseElastic($must,$source=[],$firstRowOnly=0){
    $queryBody    = ['index' => T::$vendorBusinessLicenseView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody   += $firstRowOnly ? ['size'=>1] : [];
    $r            = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getValue('_source',Helper::getElasticResult($r,1),[]) : $r;
  }
//------------------------------------------------------------------------------
  public static function getVendorPaymentElastic($must=[],$source=[],$firstRowOnly=1){
    $queryBody    = ['index'=>T::$vendorPaymentView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody   += $firstRowOnly ? ['size'=>1] : ['size'=>50000];
    $r            = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getValue('_source',Helper::getElasticResult($r,1),[]) : $r;
  }
//------------------------------------------------------------------------------
  public static function getGardenHoaElastic($must,$source=[]){
    $queryBody    = ['index' => T::$vendorGardenHoaView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getMaintenanceElastic($must,$source=[]){
    $queryBody    = ['index' => T::$vendorMaintenanceView,'size'=>50000];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getMortgageElastic($must,$source=[],$firstRowOnly=0){
    $queryBody    = ['index' => T::$vendorMortgageView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody   += $firstRowOnly ? ['size'=>1] : [];
    $r            = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getValue('_source',Helper::getElasticResult($r,1),[]) : $r;
  }
//------------------------------------------------------------------------------
  public static function getPendingCheckElastic($must,$source=[]){
    $queryBody    = ['index' => T::$vendorPendingCheckView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getPendingCheckIn($whereField,$whereData,$select='*',$firstRowOnly=0){
    $r    = DB::table(T::$vendorPendingCheck)->select($select)->whereIn($whereField,$whereData);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
//------------------------------------------------------------------------------
  public static function getPropTaxElastic($must,$source=[]){
    $queryBody    = ['index' => T::$vendorPropTaxView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must]] : [];
    return Elastic::searchQuery($queryBody);
  }
//------------------------------------------------------------------------------
  public static function getManagementFeeInGl($param){
    $q  = 'SELECT (SUM(g.amount) * p.man_pct) / 100.0 AS result, g.prop, p.street, p.ap_bank ';
    $q .= 'FROM ' . T::$glTrans . ' AS g INNER JOIN ' . T::$prop . ' AS p ON g.prop=p.prop ';
    $q .= 'WHERE p.man_pct > 0 ' . Model::getRawWhere(['g.gl_acct'=>$param['gl_acct'],'g.prop'=>$param['prop']]);
    $q .= ' AND g.date1 BETWEEN "' . $param['date1'] . '" AND "' . $param['todate1'] . '" GROUP BY g.prop';
    return DB::select($q);
  }
//------------------------------------------------------------------------------
  public static function getPropElastic($must=[],$source=[],$firstRowOnly=0){
    $queryBody    = ['index'=>T::$propView];
    $queryBody   += !empty($source) ? ['_source'=>$source] : [];
    $queryBody   += !empty($must) ? ['query'=>['must'=>$must,'must_not'=>['prop_class.keyword'=>'X']]] : [];
    $queryBody   += $firstRowOnly ? ['size'=>1] : [];
    $r            = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getValue('_source',Helper::getElasticResult($r,1),[]) : $r;
  }
//------------------------------------------------------------------------------
  public static function getMgtFeeElastic($must=[],$source=[],$firstRowOnly=0){
    $queryBody   = ['index'=>T::$vendorManagementFeeView];
    $queryBody  += !empty($source) ? ['_source'=>$source] : [];
    $queryBody  += !empty($must) ? ['query'=>['must'=>$must]] : [];
    $queryBody  += $firstRowOnly ? ['size'=>1] : [];
    $r           = Elastic::searchQuery($queryBody);
    return $firstRowOnly ? Helper::getValue('_source',Helper::getElasticResult($r,1),[]) : $r;
  }
################################################################################
######################   MAINTENANCE PAYMENT FUNCTION   ########################  
################################################################################
//------------------------------------------------------------------------------
  public static function getTotalControlUnit($data,$whereStr,$groupBy='vm.vendid,p.group1,vm.gl_acct'){
    $q   = 'SELECT vm.vendid,vm.gl_acct,p.group1,SUM(control_unit) AS totalControlUnit ';
    $q  .= ' FROM ' . T::$vendorMaintenance . ' AS vm ';
    $q  .= ' INNER JOIN ' . T::$prop . ' AS p ON vm.prop=p.prop ';
    $q  .= ' WHERE vm.control_unit > 0 ' . $whereStr . ' GROUP BY ' . $groupBy;
    return DB::select($q);
  }
//------------------------------------------------------------------------------
  public static function getGenerateMaintenancePaymentData($whereStr='',$select='*'){
    $q  = 'SELECT ' . $select . ' FROM ' . T::$vendorMaintenance . ' AS vm';
    $q .= ' INNER JOIN ' . T::$prop . ' AS p ON vm.prop=p.prop WHERE vm.control_unit > 0 ' . $whereStr;
    return DB::select($q);
  }
//------------------------------------------------------------------------------
  public static function getMaintenancePayment($fromDate,$toDate,$where,$select='*',$firstRowOnly=0){
    $r   =  DB::table(T::$vendorPayment)->select($select)->whereBetween([$fromDate,$toDate])->where($where);
    return $firstRowOnly ? $r->first() : $r->get()->toArray();
  }
}
