<?php
namespace App\Library;

class Mapping{
  
  public static function getMapping($table) { 
    $mapping = [
      'violation' => [
        ['field'=>'priority', 'mapping'=>['1'=>'Low', '2'=>'Medium', '3'=>'High', '4'=>'Highest']],
        ['field'=>'status',  'mapping'=>['O'=>'Open', 'P'=>'In Progress', 'DJ'=>'DJ',  'C'=>'Close']],
      ],
      'account' => [
        ['field'=>'isLocked', 'mapping'=>['0'=>'No', '1'=>'Yes']],
        ['field'=>'office',   'mapping'=>[''=>'Select Office','Covina Gardens'=>'Covina Gardens', 'El Monte'=>'El Monte','San Bernardino - Front Office'=>'San Bernardino - Front Office','San Bernardino - Back Office'=>'San Bernardino - Back Office','Montclair'=>'Montclair','Bakersfield'=>'Bakersfield','Hemet'=>'Hemet','Palmdale'=>'Palmdale','Riverside'=>'Riverside','Fontana'=>'Fontana','Indio'=>'Indio','Fresno'=>'Fresno','Arlington'=>'Arlington','Azusa'=>'Azusa', 'Chespeake'=>'Chespeake', 'Stockton'=>'Stockton','Sacramento'=>'Sacramento','Willows'=>'Willows']],
      ],
      'application'      => [
        ['field'=>'application_status','mapping'=>['Rejected'=>'Rejected','Approved'=>'Approved']],
      ],
      'application_info' => [
        ['field'=>'direct',     'mapping'=>[''=>'Select Direction','north'=>'North','south'=>'South','east'=>'East','west'=>'West','northeast'=>'Northeast','northwest'=>'Northwest','southeast'=>'Southeast','southwest'=>'Southwest']],
        ['field'=>'prop_type',  'mapping'=>[''=>'Select Type','Alley'=>'Alley','Avenue'=>'Avenue','Boulevard'=>'Boulevard','Building'=>'Building','Center'=>'Center','Circle'=>'Circle','Court'=>'Court','Crescent'=>'Crescent','Dale'=>'Dale','Drive'=>'Drive','Expresswa'=>'Expresswa','Freeway'=>'Freeway','Garden'=>'Garden','Grove'=>'Grove','Heights'=>'Heights','Highway'=>'Highway','Hill'=>'Hill','Knoll'=>'Knoll','Lane'=>'Lane','Loop'=>'Loop','Mall'=>'Mall','Oval'=>'Oval','Park'=>'Park','Parkway'=>'Parkway','Path'=>'Path','Pike'=>'Pike','Place'=>'Place','Plaza'=>'Plaza','Point'=>'Point','Road'=>'Road','Route'=>'Route','Row'=>'Row','Run'=>'Run','Ruralroute'=>'Ruralroute','Square'=>'Square','Street'=>'Street','Terrace'=>'Terrace','Thruway'=>'Thruway','Trail'=>'Trail','Turnpike'=>'Turnpike','Viaduct'=>'Viaduct','View'=>'View','Walk'=>'Walk','Way'=>'Way','Cove'=>'Cove']],
        ['field'=>'run_credit', 'mapping'=>['1'=>'Run Credit Check','0'=>'Manager Move In/Do not Run Credit Check']],
        ['field'=>'section8',   'mapping'=>['0'=>'No Section 8','1'=>'Section 8']],
        ['field'=>'states',     'mapping'=>['AL'=>'AL','AK'=>'AK','AS'=>'AS','AZ'=>'AZ','AR'=>'AR','CA'=>'CA','CO'=>'CO','CT'=>'CT','DE'=>'DE','DC'=>'DC','FM'=>'FM','FL'=>'FL','GA'=>'GA','GU'=>'GU','HI'=>'HI','ID'=>'ID','IL'=>'IL','IN'=>'IN','IA'=>'IA','KS'=>'KS','KY'=>'KY','LA'=>'LA','ME'=>'ME','MH'=>'MH','MD'=>'MD','MA'=>'MA','MI'=>'MI','MN'=>'MN','MS'=>'MS','MO'=>'MO','MT'=>'MT','NE'=>'NE','NV'=>'NV','NH'=>'NH','NJ'=>'NJ','NM'=>'NM','NY'=>'NY','NC'=>'NC','ND'=>'ND','MP'=>'MP','OH'=>'OH','OK'=>'OK','OR'=>'OR','PW'=>'PW','PA'=>'PA','PR'=>'PR','RI'=>'RI','SC'=>'SC','SD'=>'SD','TN'=>'TN','TX'=>'TX','UT'=>'UT','VT'=>'VT','VI'=>'VI','VA'=>'VA','WA'=>'WA','WV'=>'WV','WI'=>'WI','WY'=>'WY','AE'=>'AE','AA'=>'AA','AP'=>'AP']],
        ['field'=>'suffix',     'mapping'=>[''=>'None','Jr'=>'Jr','Sr'=>'Sr','I'=>'I','II'=>'II','III'=>'III','IV'=>'IV']]
      ],
      'bank' => [
        ['field'=>'print_bk_name',   'mapping'=>['Y'=>'(Y) - Print Name on Checks', 'N'=>'(N) - Do Not Print Name on Checks']],
        ['field'=>'print_prop_name', 'mapping'=>['Y'=>'(Y) - Print Property Address on Checks', 'N'=>'(N) - Do Not Print Property Address on Checks']],
        ['field'=>'two_sign',        'mapping'=>['Y'=>'Yes', 'N'=>'No']],
      ],
      'gl_chart' => [
        ['field'=>'acct_type', 'mapping'=>['A'=>'Assets', 'C'=>'Capital', 'E'=>'Expenses', 'I'=>'Income', 'L'=>'Liabilities', 'S'=>'S']],
        ['field'=>'type1099',  'mapping'=>['N'=>'No', 'Y'=>'Yes']],
        ['field'=>'no_post',  'mapping'=>['N'=>'No', 'Y'=>'Yes']],
      ],
      'prop' => [
        ['field'=>'isFreeClear', 'mapping'=>['Yes'=>'Yes', 'No'=>'No']],
        ['field'=>'mangtgroup','mapping'=>['RLS'=>'RLS','**'=>'**','**IERH'=>'**IERH','**MHP'=>'**MHP','**WEST'=>'**WEST','**PAMA'=>'**PAMA','**EAST'=>'**EAST']],
        ['field'=>'post_flg',    'mapping'=>['Y'=>'(Y) - Posting to this Prop', 'N'=>'(N) - Not Posting to this Prop']],
        ['field'=>'prop_class',  'mapping'=>[''=>'', 'A'=>'Accounting', 'C'=>'Consolidation', 'D'=>'Default', 'L'=>'Land', 'M'=>'Management', 'P'=>'Property', 'G'=>'Group','T'=>'Trust','X'=>'Inactive' ]],
        ['field'=>'prop_type',   'mapping'=>['A'=>'Apartment', 'C'=>'Condo', 'H'=>'House', 'I'=>'Inventory', 'M'=>'Mobile Home', 'N'=>'Convalescent Home', 'O'=>'Office', 'S'=>'Shopping Center', 'W'=>'Warehouse', '?'=>'Other']],
        ['field'=>'rent_type',   'mapping'=>['none' => 'None', 'rent_control' => 'Rent Control']],
      ],
      'prop_massive' => [
        ['field'=>'prop_class', 'mapping'=>[''=>'Leave as is', 'A'=>'Accounting', 'C'=>'Consolidation', 'D'=>'Default', 'L'=>'Land', 'M'=>'Management', 'P'=>'Property', 'G'=>'Group','T'=>'Trust','X'=>'Inactive' ]],
        ['field'=>'prop_type',  'mapping'=>[''=>'Leave as is', 'A'=>'Apartment', 'C'=>'Condo', 'H'=>'House', 'I'=>'Inventory', 'M'=>'Mobile Home', 'N'=>'Convalescent Home', 'O'=>'Office', 'S'=>'Shopping Center', 'W'=>'Warehouse', '?'=>'Other']],
      ],
      'remark_tnt'   => [
        ['field'=>'remark_code','mapping'=>[''=>'Select','EVI'=>'Eviction','T'=>'Termination','C'=>'Complaint','?'=>'Other']],
      ],
      'rent_raise'   => [
        ['field'=>'notice', 'mapping'=>['30'=>'30','60'=>'60','90'=>'90']],
        ['field'=>'bathroom','mapping'=>['0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10']],
        ['field'=>'bedroom',  'mapping'=>['0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6']],
        ['field'=>'prop_type','mapping'=>[''=>'Select Type','A'=>'Apartment', 'C'=>'Condo', 'H'=>'House', 'I'=>'Inventory', 'M'=>'Mobile Home', 'N'=>'Convalescent Home', 'O'=>'Office', 'S'=>'Shopping Center', 'W'=>'Warehouse', '?'=>'Other']],
        ['field'=>'is_printed','mapping'=>['0'=>'Ready to Print','1'=>'Printed']],
        ['field'=>'is_rent_raise_completed','mapping'=>['0'=>'No','1'=>'Yes']],
        ['field'=>'notice','mapping'=>['30'=>'30','60'=>'60','90'=>'90']],
        ['field'=>'rent_type',   'mapping'=>['none' => 'None', 'rent control' => 'Rent Control']],
        ['field'=>'unit_type', 'mapping'=>['A'=>'Apartment','B'=>'Commercial','C'=>'Condo','DW'=>'Double Wide','G'=>'Garage','H'=>'House','I'=>'Industrial','L'=>'Laundry', 'M'=>'Mobile Home','O'=>'Office','P'=>'Parking','PM'=>'Park Model','Q'=>'Space', 'S'=>'Storage','SW'=>'Single Wide', 'R'=>'Studio','T'=>'Trailer/RV','W'=>'Warehouse']],
        ['field'=>'isManager',   'mapping'=>['0'=>'No','1'=>'Yes']],
      ],
      'section_8' => [
        ['field'=>'status', 'mapping'=>[''=>'No Result','NE'=>'No Entry','F'=>'Failed','P'=>'Passed','I'=>'Inconclusive','R'=>'Reschedule','MO'=>'Move Out','NS'=>'No Show','?'=>'Other']],
        ['field'=>'status2','mapping'=>[''=>'No Result','NE'=>'No Entry','F'=>'Failed','P'=>'Passed','I'=>'Inconclusive','R'=>'Reschedule','MO'=>'Move Out','NS'=>'No Show','?'=>'Other']],
        ['field'=>'status3','mapping'=>[''=>'No Result','NE'=>'No Entry','F'=>'Failed','P'=>'Passed','I'=>'Inconclusive','R'=>'Reschedule','MO'=>'Move Out','NS'=>'No Show','?'=>'Other']],
      ],
      'service'   => [
        ['field'=>'schedule', 'mapping'=>['S'=>'Single', 'M'=>'Monthly']], 
        ['field'=>'tax_cd',   'mapping'=>['N'=>'No', 'Y'=>'Yes']], 
        ['field'=>'mangt_cd', 'mapping'=>['N'=>'No', 'Y'=>'Yes']], 
        ['field'=>'comm_cd',  'mapping'=>['N'=>'No', 'Y'=>'Yes']], 
      ],
      'tnt_eviction_event' => [
        ['field'=>'status', 'mapping'=>[''=>'Select', '0'=>'Start', '1'=>'Closed', '2'=>'Trial', '3'=>'Hearing', '4'=>'Default', '5'=>'Bankruptcy', '6'=>'Uncertain']],    
      ],
      'tnt_eviction_process' => [
        ['field'=>'process_status', 'mapping'=>[''=>'Select', '0'=>'Progress', '1'=>'Closed']],  
        ['field'=>'isFileuploadComplete', 'mapping'=>['0'=>'No', '1'=>'Yes']],
        ['field'=>'attorney', 'mapping'=>[''=>'None', 'Fast Evictions'=>'Fast Evictions - Intake@fastevict.com | FastEvict1@fastevict.com', 'Glenn Travis'=>'Glenn Travis - evictionglenn@gmail.com','Trevor Mirkes'=>'Trevor Mirkes - info@mirkeslaw.com', 'Jackie Mythen'=>'Jackie Mythen - mythenals@gmail.com', 'Eva Lopez'=>'Eva Lopez - elopez@huhemlaw.com', 'Renee Ewing'=>'Renee Ewing - rewing@Mcglynnclark.com', 'Rodney Benson'=>'Rodney Benson - rbenson@Mcglynnclark.com', 'Teresa Reyes'=>'Teresa Reyes - northparkfresno@sbcglobal.net']],
      ],
      'tnt_move_out_process' => [
        ['field'=>'status', 'mapping'=>['0'=>'No', '1'=>'Yes']],
        ['field'=>'isFileuploadComplete', 'mapping'=>['0'=>'No', '1'=>'Yes']]
      ],
      'unit' => [
        //['field'=>'bathrooms', 'mapping'=>['1.0'=>'1','1.25'=>'1.25','1.5'=>'1.5','1.75'=>'1.75','2.0'=>'2','2.25'=>'2.25','2.5'=>'2.5','2.75'=>'2.75','3.0'=>'3','3.25'=>'3.25','3.5'=>'3.5','3.75'=>'3.75','4.0'=>'4','4.25'=>'4.25','4.5'=>'4.5','4.75'=>'4.75','5.0'=>'5','5.25'=>'5.25','5.5'=>'5.5','5.75'=>'5.75','6.0'=>'6','6.25'=>'6.25','6.5'=>'6.5','6.75'=>'6.75','7.0'=>'7','7.25'=>'7.25','7.5'=>'7.5','7.75'=>'7.75','8.0'=>'8','8.25'=>'8.25','8.5'=>'8.5','8.75'=>'8.75','9.0'=>'9','9.25'=>'9.25','9.5'=>'9.5','9.75'=>'9.75','10.0'=>'10']],
        ['field'=>'bathrooms', 'mapping'=>['0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10']],
        ['field'=>'bedrooms',  'mapping'=>['0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6']],
        ['field'=>'mh_owner',  'mapping'=>['T'=>'Tenant Own Unit','P'=>'Park Own Unit','MJC'=>'Unit Paid by Tenant to MJC Capital Investment']],
        ['field'=>'status',    'mapping'=>[''=>'Select','C'=>'Current (C)','V'=>'Vacant (V)']],
        ['field'=>'status2',   'mapping'=>[''=>'Select','C'=>'Current (C)','P'=>'Past (P)']],
        ['field'=>'unit_type', 'mapping'=>['A'=>'Apartment','B'=>'Commercial','C'=>'Condo','DW'=>'Double Wide','G'=>'Garage','H'=>'House','I'=>'Industrial','L'=>'Laundry', 'M'=>'Mobile Home','O'=>'Office','P'=>'Parking','PM'=>'Park Model','Q'=>'Space', 'S'=>'Storage','SW'=>'Single Wide', 'R'=>'Studio','T'=>'Trailer/RV','W'=>'Warehouse']],
        ['field'=>'count_unit','mapping'=>['0'=>'Not Ready','1'=>'Ready']],   
        ['field'=>'style',     'mapping'=>['1'=>'1 Story','2'=>'2 Stories','3'=>'3 Stories']],
      ],
      'unit_hist' => [
        //['field'=>'bathrooms', 'mapping'=>['1.0'=>'1','1.25'=>'1.25','1.5'=>'1.5','1.75'=>'1.75','2.0'=>'2','2.25'=>'2.25','2.5'=>'2.5','2.75'=>'2.75','3.0'=>'3','3.25'=>'3.25','3.5'=>'3.5','3.75'=>'3.75','4.0'=>'4','4.25'=>'4.25','4.5'=>'4.5','4.75'=>'4.75','5.0'=>'5','5.25'=>'5.25','5.5'=>'5.5','5.75'=>'5.75','6.0'=>'6','6.25'=>'6.25','6.5'=>'6.5','6.75'=>'6.75','7.0'=>'7','7.25'=>'7.25','7.5'=>'7.5','7.75'=>'7.75','8.0'=>'8','8.25'=>'8.25','8.5'=>'8.5','8.75'=>'8.75','9.0'=>'9','9.25'=>'9.25','9.5'=>'9.5','9.75'=>'9.75','10.0'=>'10']],
        ['field'=>'bathrooms','mapping'=>['0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10']],
        ['field'=>'bedrooms',  'mapping'=>['0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6']],
        ['field'=>'status',    'mapping'=>[''=>'Select','C'=>'Current (C)','V'=>'Vacant (V)']],
        ['field'=>'status2',   'mapping'=>[''=>'Select','C'=>'Current (C)','P'=>'Past (P)']],
        ['field'=>'unit_type', 'mapping'=>['A'=>'Apartment','B'=>'Commercial','C'=>'Condo','G'=>'Garage','H'=>'House','I'=>'Industrial','L'=>'Laundry', 'M'=>'Mobile Home','O'=>'Office','P'=>'Parking','Q'=>'Space', 'S'=>'Storage', 'R'=>'Studio','T'=>'Trailer/RV','W'=>'Warehouse']],
        ['field'=>'count_unit','mapping'=>['0'=>'Not Ready','1'=>'Ready']],
        ['field'=>'style',     'mapping'=>['1'=>'1 Story','2'=>'2 Stories','3'=>'3 Stories']],
      ],
      'vendor' => [
        ['field'=>'flg_1099', 'mapping'=>['Y'=>'Yes', 'N'=>'No']],
        ['field'=>'vendor_type', 'mapping'=>[''=>'Select Type', 'T'=>'Tenant', 'C'=>'Contractor', 'M'=>'Maintenance', 'P'=>'Payroll', 'U'=>'Utility', 'O'=>'Other', 'G'=>'Government']]
      ],
      'vendor_gardenhoa' => [
        ['field'=>'stop_pay','mapping'=>['yes'=>'Yes','no'=>'No']],
      ],
      'vendor_insurance' => [
        ['field'=>'auto_renew',     'mapping'=>['yes'=>'Yes', 'no'=>'No']],
        ['field'=>'number_payment', 'mapping'=>['0'=>'0', '1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10','11'=>'11','12'=>'12']],
        ['field'=>'payer',          'mapping'=>['owner'=>'Owner', 'pama'=>'PAMA']],
        ['field'=>'occ',            'mapping'=>['APT'=>'APT','H'=>'H','1 Unit'=>'1 Unit','3 Unit'=>'3 Unit','1-4 Unit'=>'1-4 Unit','SFD'=>'SFD','SFR'=>'SFR','Land'=>'Land','Vacant Land'=>'Vacant Land','Industrial'=>'Industrial','Commercial'=>'Commercial','Retail'=>'Retail','Dwelling'=>'Dwelling','Duplex'=>'Duplex','Tri-Plex'=>'Tri-Plex','4-Plex'=>'4-Plex']],
      ],
      'vendor_mortgage' => [
        ['field'=>'paid_off_loan','mapping'=>['0'=>'No','1'=>'Yes']],
        ['field'=>'payment_option','mapping'=>['Interest Only'=>'Interest Only','Principal & Interest'=>'Principal & Interest']],
        ['field'=>'loan_type','mapping'=>['Secured'=>'Secured',"Mike's LC"=>"Mike's LC","Sabraj's LC"=>"Sabraj's LC","Michael's LC"=>"Michael's LC","Sanjeet's LC"=>"Sanjeet's LC","Mike's GL"=>"Mike's GL","Sanjeet's GL"=>"Sanjeet's GL","Michael's GL"=>"Sabraj's GL","Mike's GL"=>"Sabraj's GL","Grp XIII's GL"=>"Grp XIII's GL",'Credit Line'=>'Credit Line','Guidance Line'=>'Guidance Line','Construction'=>'Construction','Unsecured'=>'Unsecured']],
        ['field'=>'loan_option','mapping'=>['fixed'=>'Fixed','Variable'=>'Variable','Interest'=>'Interest','7yrs fixed'=>'7 Years Fixed','10 yr Fixed'=>'10 Years Fixed','15 year fix'=>'15 Years Fixed','5 year fix'=>'5 Years Fixed']],
        ['field'=>'payment_type','mapping'=>['Check'=>'Check','Wire'=>'Wire','ACH'=>'ACH']],
        ['field'=>'recourse','mapping'=>['Recourse'=>'Recourse','Non-Recourse'=>'Non-Recourse']],
      ],
      'vendor_payment' => [
        ['field'=>'high_bill', 'mapping'=>['0'=>'No', '1'=>'Yes']],
        ['field'=>'prop_class','mapping'=>[''=>'Leave as is', 'A'=>'Accounting', 'C'=>'Consolidation', 'D'=>'Default', 'L'=>'Land', 'M'=>'Management', 'P'=>'Property', 'G'=>'Group','T'=>'Trust','X'=>'Inactive' ]],
        ['field'=>'approve',   'mapping'=>['Approved'=>'Approved','Pending Submission'=>'Pending Submission','Rejected'=>'Rejected','Waiting For Approval'=>'Waiting For Approval']],
        ['field'=>'type',      'mapping'=>['pending_check'=>'Pending Check','insurance'=>'Insurance','managementfee'=>'Management Fee','mortgage'=>'Mortgage','gardenHoa'=>'Garden HOA','business_license'=>'Business License','util_payment'=>'Utility Payment']],
      ],
      'vendor_pending_check' => [
        ['field'=>'recurring', 'mapping'=>['yes'=>'Yes', 'no'=>'No']],
        ['field'=>'is_submitted', 'mapping'=>[''=>'Select Submission','yes'=>'Yes','no'=>'No']],
        ['field'=>'is_need_approved','mapping'=>['0'=>'No','1'=>'Yes']],
      ],
      'vendor_prop_tax' => [
        ['field'=>'payer', 'mapping'=>[''=>'Select Payer', 'LENDER'=>'LENDER', 'OWNER'=>'OWNER', 'PAMA'=>'PAMA', 'TENANT'=>'TENANT']],
      ],
      'vendor_util_payment' => [
        ['field'=>'active', 'mapping'=>['0'=>'Delete','1'=>'Active']],
        ['field'=>'pay_by', 'mapping'=>['owner'=>'Owner','tenant'=>'Tenant']],
        ['field'=>'mangtgroup','mapping'=>['RLS'=>'RLS','**'=>'**','**IERH'=>'**IERH','**MHP'=>'**MHP','**WEST'=>'**WEST','**PAMA'=>'**PAMA','**EAST'=>'**EAST']],
        ['field'=>'prop_type',   'mapping'=>['A'=>'Apartment', 'C'=>'Condo', 'H'=>'House', 'I'=>'Inventory', 'M'=>'Mobile Home', 'N'=>'Convalescent Home', 'O'=>'Office', 'S'=>'Shopping Center', 'W'=>'Warehouse', '?'=>'Other']],
      ],
      'tenant' => [
        ['field'=>'status', 'mapping'=>[''=>'Select Status','R'=>'Rent','E'=>'Evicted','T'=>'Termiated','F'=>'Future','C'=>'Current','P'=>'Past']],
        ['field'=>'spec_code', 'mapping'=>[''=>'Select Code','R'=>'Rent','L'=>'Late','E'=>'Eviction','T'=>'30 day notice','U'=>'Pending vacant','P'=>'Payment plan','O'=>'Other']],
        ['field'=>'bathrooms','mapping'=>['0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10']],
        ['field'=>'bedrooms',  'mapping'=>['0'=>'0','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6']],
        ['field'=>'isManager', 'mapping'=>['0'=>'No', '1'=>'Yes']]
      ], 
      'tnt_trans'=>[
        ['field'=>'tx_code', 'mapping'=>['P'=>'Payment','IN'=>'Invoice','S'=>'Sys', 'D'=>'D']],
      ]
    ];
    return $mapping[$table];  
  }
}
