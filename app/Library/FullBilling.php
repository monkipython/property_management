<?php
namespace App\Library;
use App\Library\RuleField;

class FullBilling{
  public static function getFullBillingField($prorateAmountData, $rGlChat, $moveinDate,$prorateOptions=[]){
    $_getField  = function ($prorateType, $i, $v){
      $form = '';
      $default = ['req'=>1, 'includeLabel'=>0];
      $fields = [
        'service_code'=>$default + [
          'id'=>'service_code[' . $i . ']', 
          'type'=>'text', 
          'value'=>$v['glAcct'], 
          'class'=>'autocomplete',
          'readonly'=>1],
        'remark'=> $default + [
          'id'=>'remark[' . $i . ']', 
          'type'=>'text', 
          'value'=>$v['title'], 
          'readonly'=>1],
        'schedule'=> $default + [
          'id'=>'schedule[' . $i . ']', 
          'type'=>'option', 
          'option'=>['S'=>'Single', 'M'=>'Monthly'], 
          'value'=>$v['schedule']],
        'amount' =>$default + [
          'id'=>'amount[' . $i . ']', 
          'type'=>'text', 
          'class'=>'decimal',
          'value'=>$v['amount']],
        'start_date'=>$default+ [
          'id'=>'start_date[' . $i . ']', 
          'type'=>'text', 
          'class'=>'date', 
          'value'=>$v['leaseStartDate'], 
          'readonly'=>1],
        'stop_date' =>$default+ [
          'id'=>'stop_date[' . $i . ']',
          'type'=>'text', 
          'class'=>'date', 
          'value'=>$v['moveOutDate'], 
          'readonly'=>1],
//        'isInBilling' =>$default+ [
//          'id'=>'InBilling[' . $i . ']',
//          'type'=>'option',
//          'option'=>[0=>'Add to Ledger Card', 1=>'Add to Billing'],
//          'class'=>'',
//          'value'=>isset($v['isInBilling']) ? $v['isInBilling'] : 0
//        ],
      ];
      $map = [
        'service_code'=>'Service#',
        'remark'=>'Service Description',
        'schedule'=>'Schedule',
        'amount'=>'Amount',
        'start_date'=>'Start Date',
        'stop_date'=>'End Date',
        'isInBilling'=>'Add to Billing?',
      ];
      foreach($fields as $k=>$v){
        $col = 2;
//        if($k == 'service_code' || $k == 'schedule') { $col = 1; }
//        else if($k == 'remark') { $col = 2; }
        if($k == 'service_code') { $col = 1; }
        else if($k == 'remark') { $col = 3; }
        $header = ($i == 0 && $prorateType != 'emptyField') ? Html::tag('h4', $map[$k]) : '';
        $form .= Html::div($header . Form::getField($v),['class'=>'col-md-' . $col]);
      }
      return Html::div($form, ['class'=>'row ' . $prorateType, 'data-key'=>$i]);
    };
    
    $gl602 = '602';
    $gl607 = '607';
    $data  = [];
    $noEndDate = '12/31/9999';
    $endMonthDate = date('m/t/Y', strtotime($moveinDate));
    $endNextMonthDate = date('m/t/Y', strtotime('+1 month', strtotime($moveinDate)));
    $todayDate = $moveinDate;
    $todayNextMonthDate = date('m/01/Y', strtotime('+1 month', strtotime($moveinDate)));
    $todayNext2MonthDate = date('m/01/Y', strtotime('+2 month', strtotime($moveinDate)));
    $newRent = $prorateAmountData['rent'];
    $prorateAmount = $prorateAmountData['prorate'];
    $totalDeposit = $prorateAmountData['totalDeposit'];
    $firstField = ['glAcct'=>$gl607, 'title'=>$rGlChat[$gl607]['remark'], 'schedule'=>'S', 'amount'=>$totalDeposit, 'leaseStartDate'=>$moveinDate, 'moveOutDate'=>$noEndDate]; 
    
    $defaultValue = [
      'prorateCurrentMonth'=>[
        $firstField,
        ['glAcct'=>$gl602, 'title'=>$rGlChat[$gl602]['remark'], 'schedule'=>'S', 'amount'=>$prorateAmount, 'leaseStartDate'=>$todayDate, 'moveOutDate'=>$endMonthDate],
        ['glAcct'=>$gl602, 'title'=>$rGlChat[$gl602]['remark'], 'schedule'=>'M', 'amount'=>$newRent, 'leaseStartDate'=>$todayNextMonthDate, 'moveOutDate'=>$noEndDate, 'isInBilling'=>1]
      ],
      'prorateNextMonth'=>[
        $firstField,
        ['glAcct'=>$gl602, 'title'=>$rGlChat[$gl602]['remark'], 'schedule'=>'S', 'amount'=>$newRent, 'leaseStartDate'=>$todayDate, 'moveOutDate'=>$endMonthDate, 'isInBilling'=>0],
        ['glAcct'=>$gl602, 'title'=>$rGlChat[$gl602]['remark'], 'schedule'=>'S', 'amount'=>$prorateAmount, 'leaseStartDate'=>$todayNextMonthDate, 'moveOutDate'=>$endNextMonthDate, 'isInBilling'=>1],
        ['glAcct'=>$gl602, 'title'=>$rGlChat[$gl602]['remark'], 'schedule'=>'M', 'amount'=>$newRent, 'leaseStartDate'=>$todayNext2MonthDate, 'moveOutDate'=>$noEndDate, 'isInBilling'=>1]
      ],
      'noProrate'=>[
        $firstField,
        ['glAcct'=>$gl602, 'title'=>$rGlChat[$gl602]['remark'], 'schedule'=>'S', 'amount'=>$newRent, 'leaseStartDate'=>$todayDate, 'moveOutDate'=>$endMonthDate],
        ['glAcct'=>$gl602, 'title'=>$rGlChat[$gl602]['remark'], 'schedule'=>'M', 'amount'=>$newRent, 'leaseStartDate'=>$todayNextMonthDate, 'moveOutDate'=>$noEndDate, 'isInBilling'=>1]
      ],
      'managerMovein'=>[
        $firstField,
      ],
      'emptyField'=>[
        ['glAcct'=>$gl602, 'title'=>$rGlChat[$gl602]['remark'], 'schedule'=>'S', 'amount'=>'$0', 'leaseStartDate'=>$todayDate, 'moveOutDate'=>$endMonthDate],
      ]
    ];

    $data['signAgreement']  = [
      'prorateCurrentMonth'=>[
        ['amount'=>$totalDeposit],
        ['amount'=>$prorateAmount],
      ],
      'prorateNextMonth'=>[
        ['amount'=>$totalDeposit],
        ['amount'=>$newRent],
        ['amount'=>$prorateAmount],
      ],
      'noProrate'=>[
        ['amount'=>$totalDeposit],
        ['amount'=>$newRent],
      ],
    ];

    foreach($defaultValue as $prorateType=>$val){
      foreach ($val as $i=>$v) {
        $data[$prorateType] = !isset($data[$prorateType]) ? $_getField($prorateType, $i, $v) : $data[$prorateType] . $_getField($prorateType, $i, $v);
      }
    }
    $data['prorate'] = Form::getField([
      'id'=>'prorate', 
      'type'=>'option',
      'option'=>!empty($prorateOptions) ? $prorateOptions : ['prorateCurrentMonth'=>'Prorate Current Month','prorateNextMonth'=>'Prorate Next Month', 'noProrate'=>'No Prorate', 'managerMovein'=>'Manager MoveIn'],
      'value'=>'prorateNextMonth', 
      'includeLabel'=>0
    ]);
    return $data;
  }
//------------------------------------------------------------------------------
  public static function getProrateAmount($r, $moveinDate = ''){
    $moveinDate = !empty($moveinDate) ? $moveinDate : date('Y-m-d');
    $startDate = date('Y-m-d', strtotime($moveinDate));
    $endDate   = date('Y-m-t', strtotime($moveinDate));
        
    $amount = $r['amount'];
    $numDay = 30;
    $prorateDay = Helper::getDateDifference($startDate, $endDate) + 1;
    $prorate = ($prorateDay >= 30) ? $amount : ($prorateDay * $amount / $numDay);
    return [
      'prorate'       => Format::floatNumberSeperate($prorate),
      'proratePerDay' => Format::usMoney($amount / $numDay),
      'prorateDay'    => Format::floatNumberSeperate($prorateDay),
      'rent'          => Format::floatNumberSeperate($amount),
      'totalDeposit'  => Format::floatNumberSeperate($r['sec_deposit'] + $r['sec_deposit_add']),
    ];
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
}
