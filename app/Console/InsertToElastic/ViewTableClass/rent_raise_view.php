<?php
namespace App\Console\InsertToElastic\ViewTableClass;
use App\Library\{TableName AS T, Helper, Format ,Elastic};
use App\Http\Controllers\PropertyManagement\Tenant\TenantController AS P;
use App\Http\Models\Model; // Include the models class

/*
ALTER TABLE `ppm`.`rent_raise` 
ADD COLUMN `foreign_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `rent_raise_id`;
ALTER TABLE `ppm`.`rent_raise` 
ADD INDEX `index_foreign_id` (`foreign_id` ASC);
UPDATE ppm.rent_raise AS r, ppm.tenant AS t SET r.foreign_id=t.tenant_id WHERE r.prop=t.prop AND r.unit=t.unit AND r.tenant=t.tenant;
 */
class rent_raise_view {
  private static $_tenant    = 'tenant_id, prop, unit, tenant';
  private static $_rentRaise = ['r.rent_raise_id','r.foreign_id','r.gl_acct','r.service_code','r.remark','r.billing_id','r.raise','r.raise_pct','r.notice','r.rent','r.active','r.usid','r.submitted_date','r.effective_date','r.last_raise_date','r.isCheckboxChecked','r.file'];
  public  static $maxChunk   = 25000;
  public static function getTableOfView(){
    return [T::$rentRaise,T::$tenant,T::$unit,T::$prop];
  }
//------------------------------------------------------------------------------
  public static function getCopyField(){
    return ['raise_pct' => 'yearly_pct'];
  }
//------------------------------------------------------------------------------
  public static function parseData($viewTable,$data){
    $result = [];
    $data   = !empty($data) ? Helper::encodeUtf8($data) : [];
    $props            = array_column($data,'prop');
    
    $rUnit            = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$unitView,
      'sort'     => ['prop.prop.keyword'=>'asc','unit.keyword'=>'asc'],
      '_source'  => ['prop.prop','prop.trust','unit','unit_type','market_rent','bedrooms','bathrooms','move_in_date','street','prop.group1','prop.trust','prop.cons1','prop.prop_type','prop.city','prop.rent_type'],
      'query'    => [
        'must'      => [
          'prop.prop.keyword'  => $props,
        ],
        'must_not'  => [
          'prop.prop_class.keyword'  => 'X',
        ]
      ]
    ]),['prop.prop','unit']);
    
    $rTenant         = Helper::keyFieldNameElastic(Elastic::searchQuery([
      'index'    => T::$tenantView,
      'sort'     => ['prop.keyword'=>'asc','unit.keyword'=>'asc','tenant'=>'asc'],
      '_source'  => ['tenant_id','prop','unit','tenant','tnt_name','base_rent','isManager',T::$billing],
      'query'    => [
        'must'   => [
          'prop.keyword'           => $props,
          'status.keyword' => 'C',
        ]
      ]
    ]),['prop','unit','tenant']);
    $rentRaiseCols = [];
    foreach(self::$_rentRaise as $i => $v){
      $rentRaiseCols[] = preg_replace('/r\./','',$v);
    }
    
    foreach($data as $i => $val){
      $row         = $val;
      $raiseId     = Helper::getValue('rent_raise_id',$row,0);
      $unit        = Helper::getValue($val['prop'] . $val['unit'],$rUnit,[]);
      $prop        = !empty($unit['prop'][0]) ? $unit['prop'][0] : [];

      if(!empty($unit) && !empty($prop)){
        $tenant      = Helper::getValue($val['prop'] . $val['unit'] . $val['tenant'],$rTenant,[]);
        $rentRaise   = !empty($val[T::$rentRaise]) ? explode('|',$val[T::$rentRaise]) : [];
       
        unset($row[T::$rentRaise],$prop['prop'],$unit['prop'],$unit['unit'],$tenant['prop'],$tenant['unit'],$tenant['tenant']);
        $row           = array_replace($row,$prop);
        $row           = array_replace($row,$unit);
        $row           = array_replace($row,$tenant);
        # DEAL WITH RENT RAISE DATA
        if(!empty($rentRaise)){
          foreach($rentRaise as $j => $v){
            $p = explode('~',$v);
            foreach(self::$_rentRaise as $k => $field){
              $field                            = preg_replace('/r\./','',$field);
              $row[T::$rentRaise][$j][$field]   = isset($p[$k]) ? $p[$k] : '';
            }
          }
        }
        
        $lastRentRaise = !empty($row[T::$rentRaise]) ? last($row[T::$rentRaise]) : [];
        foreach($rentRaiseCols as $c){
          $default = $c === 'rent' || $c === 'raise' || $c === 'raise_pct' || $c === 'notice' ? 0 : '';
          $default = $c === 'rent_raise_id' ? $raiseId : $default;
          $row[$c] = Helper::getValue($c,$lastRentRaise,$default);
        }
        
        $billing               = Helper::getValue(T::$billing,$tenant,[]);
        $raiseAmt              = Helper::getValue('raise',$row,0);
        $pendingNotice         = Helper::getValue('notice',$row,0);
        $stopDate              = !empty($row['effective_date']) && $row['effective_date'] != '1000-01-01' ? $row['effective_date'] : Helper::getValue('last_raise_date',$row,'1000-01-01');
        $tempStopDate          = date('Y-m-01',strtotime($stopDate . ' -' . $pendingNotice . ' days +30 days'));
        $row['id']             = $row['tenant_id'];
        $row['rent']           = Helper::getValue('base_rent',$tenant,0);
        $tenant['prop']        = $val['prop'];
        $row['tnt_name']       = !empty($row['tnt_name']) ? title_case($row['tnt_name']) : '';
        $row['street']         = !empty($row['street']) ? title_case($row['street']) : '';
        $row['city']           = !empty($row['city']) ? title_case($row['city']) : '';

        $row['yearly_pct']     = Format::roundDownToNearestHundredthDecimal(self::_calculateYearlyChange($billing, $tempStopDate, $raiseAmt));
        unset($row[T::$billing],$row['base_rent']);
        $result[$i]      = $row;
      }
    }

    return $result;
  }
//------------------------------------------------------------------------------
  public static function getSelectQuery($where = []){
    $isEndSelect = 1;
    return 'SELECT ' . Helper::joinQuery('t',self::$_tenant) . '  ' . Helper::groupConcatQuery(self::$_rentRaise,T::$rentRaise,$isEndSelect) . ' 
      FROM ' . T::$tenant . ' AS t 
      LEFT JOIN ' . T::$rentRaise . ' AS r ON  t.tenant_id=r.foreign_id
      WHERE t.status="C" ' . Model::getRawWhere($where) . '  
      GROUP BY t.prop,t.unit,t.tenant';
  }
//------------------------------------------------------------------------------
  private static function _calculateYearlyChange($billing,$stopDate,$raise){
    $lastYear   = strtotime($stopDate . ' -12 months +1 day');
    $_sortFn  = function($a,$b){
      $aTime       = strtotime(Helper::getValue('start_date',$a,0));
      $bTime       = strtotime(Helper::getValue('start_date',$b,0));
      $aStopTime   = strtotime(Helper::getValue('stop_date',$a,0));
      $bStopTime   = strtotime(Helper::getValue('stop_date',$b,0));
      return $aTime == $bTime ? ($aStopTime == $bStopTime ? 0 : ($aStopTime < $bStopTime ? -1 : 1)) : ($aTime < $bTime ? -1 : 1);
    };
    
    $billing602 = [];
    foreach($billing as $i => $v){
      if($v['schedule'] == 'M' && $v['gl_acct'] == '602'){
        $billing602[] = $v;
      }
    }
    
    $lastYearRent = 0;
    usort($billing602,$_sortFn);
    foreach($billing602 as $i => $v){
      if(strtotime($v['start_date']) <= $lastYear){
        $lastYearRent = $v['amount'];
      } else {
        break;
      }
    }
    
    $firstYearRent = !empty($billing602[0]['amount']) ? $billing602[0]['amount'] : 0;
    $lastYearRent  = !empty($lastYearRent) ? $lastYearRent : $firstYearRent;
    $difference    = $raise - $lastYearRent;
    $divisor       = !empty($lastYearRent) ? $lastYearRent : 1;
    $percentage    = ((floatval($difference) / $divisor) * 100.0);
    return $percentage;
  }
}
