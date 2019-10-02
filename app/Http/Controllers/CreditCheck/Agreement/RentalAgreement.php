<?php
namespace App\Http\Controllers\CreditCheck\Agreement;
use App\Library\{File, Elastic,PDFMerge, Html, Format, TableName AS T, Helper};
use Storage;
use PDF;

class RentalAgreement {
  private static $_sigImgHeight   = '15';
  private static $_sigImgWidth    = '70';
  private static $_numChunks      = 13;
  private static $_numImages      = 16;
  private static $_imageDirPrefix = '/img/tenantRentalAgreement/image';
  private static $_moveInOpts     = ['N','S','O'];
  private static $_moveOutOpts    = ['S','O','D'];
  private static $_agentSig       = 6;
  private static $_agentFieldName = 'agent_signature_';
  private static $_numTntInitials = 14;
  public  static $numTntSig      = 15;
################################################################################
##########################    FONT MAPPINGS   ##################################
################################################################################
  //Some fonts have to have different file names so it can be used to TCPDF
  public static $fontFiles    = [
      'Source Sans Pro'      => 'Source Sans Pro',
      //'Aguafina Script'      => 'aguafinascripti',
      //'Cedarville Cursive'   => 'cedarville_cursive',
     //'League Script'        => 'league_script',
      'Meddon'               => 'meddon',
      //'Miss Fajardose'       => 'miss_fajardose',
      //'Miss Saint Delafield' => 'miss_saint_delafield',
      //'Mr De Haviland'       => 'mr_de_haviland',
      //'Pinyon Script'        => 'pinyon_script',
      'YellowTail'           => 'yellowtail',
    ];
  private static $_font         = 'times';
  private static $_fontSize     = 12;
################################################################################
##########################   TEXT FIELD COUNTS #################################
################################################################################
  public static $fieldCounts = [
    'management_name_'            => ['num' =>1,'readonly'=>'1'],
    'payments_to_'                => ['num' =>1,'readonly'=>'1'],
    'replace_appliances_'         => ['num' =>1],
    'included_pets_'              => ['num' =>1],
    'notice_address_'             => ['num' =>3,'readonly'=>'1'],
    'attn_'                       => ['num' =>1,'readonly'=>'1'],
    'notice_local_address_'       => ['num' =>3,'readonly'=>'1'],
    'landlord_initial_'           => ['num' =>3,'readonly'=>'1'],
    'landlord_signature_'         => ['num' =>7,'readonly'=>'1'],
    'guarantor_signature_'        => ['num' =>1],
    'prop_no_'                    => ['num' =>1,'readonly'=>'1'],
    'unit_no_'                    => ['num' =>2,'readonly'=>'1'],
    'premises_'                   => ['num' =>1,'readonly'=>'1'],
    'rent_payment_address_'       => ['num' =>4,'readonly'=>'1'],
    'sec_dep_return_address_'     => ['num' =>1],
    'other_service_'              => ['num' =>1],
    'other_reimburse_'            => ['num' =>1],
    'hoa_name_'                   => ['num' =>1],
    'other_addenda_'              => ['num' =>1],
    'prop_address_'               => ['num' =>1,'readonly'=>'1'],
    'full_prop_address_'          => ['num' =>8,'readonly'=>'1'],
    'num_occupants_'              => ['num' =>1,'readonly'=>'1'],
    'optional_address_'           => ['num' =>1],
    'pool_pay_to_'                => ['num' =>1],
    'submeter_address_'           => ['num' =>1],
    'water_service_name_'         => ['num' =>1],
    'water_service_address_'      => ['num' =>1],
    'water_service_day_other_'    => ['num' =>1],
    'water_fixture_name_'         => ['num' =>1],
    'water_fixture_address_'      => ['num' =>1],
    'submeter_location_'          => ['num' =>1],
    'water_service_hour_from_'    => ['num' =>1],
    'water_service_hour_to_'      => ['num' =>1],
    'submeter_name_'              => ['num' =>1],
  ];
################################################################################
##########################   PHONE  INPUT COUNTS ###############################
################################################################################
  public static $phoneFields = [
    'payment_phone_number_' => ['num' =>4,'readonly'=>'1'],
    'guarantor_phone_'      => ['num' =>1],
    'pool_vendor_phone_'    => ['num' =>1],
    'water_service_phone_'  => ['num' =>1],
    'water_fixture_phone_'  => ['num' =>1],
    'submeter_phone_'       => ['num' =>1],
  ];
################################################################################
##########################   DATE INPUT COUNTS #################################
################################################################################
  public static $dateCounts = [
    'rental_begin_date_'      => ['num' =>1,'readonly'=>'1'],
    'inspection_date_'        => ['num' =>1],
    'move_in_date_'           => ['num' =>1,'readonly'=>'1'],
    'move_out_date_'          => ['num' =>1],
    'submeter_date_'          => ['num' =>1],
  ];
  
################################################################################
##########################   EMAIL INPUT COUNTS ################################
################################################################################
  public static $emailCounts = [
    'guarantor_email_'     => ['num' =>1],
    'submeter_email_'      => ['num' =>1],
    'water_service_email_' => ['num' =>1],
    'water_fixture_email_' => ['num' =>1],
  ];
################################################################################
##########################   NUMERIC INPUT COUNTS ##############################
################################################################################
  public static $numberCounts = [
    'tenant_concessions_'       => ['num' =>1],
    'premise_keys_'             => ['num' =>1],
    'mailbox_keys_'             => ['num' =>1],
    'hoa_days_'                 => ['num' =>1],
    'water_service_bill_day_'   => ['num' =>1],
  ];
################################################################################
##########################   MONEY INPUT COUNTS ################################
################################################################################
  public static $moneyCounts = [
    'sec_dep_'               => ['num' =>2,'readonly'=>1],
    'monthly_rent_'          => ['num' =>2,'readonly'=>1],
    'next_month_rent_'       => ['num' =>1,'readonly'=>1],
    'garage_cost_'           => ['num' =>1],
    'trash_cost_'            => ['num' =>1],
    'water_cost_'            => ['num' =>1],
    'pets_cost_'             => ['num' =>1],
    'parking_cost_'          => ['num' =>2],
    'storage_cost_'          => ['num' =>2],
    'other_cost_'            => ['num' =>1],
    'total_cost_'            => ['num' =>1,'readonly'=>1],
    'pool_cost_'             => ['num' =>1],
    'submeter_cost_'         => ['num' =>1],
    'satellite_deposit_'     => ['num' =>1],
    'satellite_insurance_'   => ['num' =>1],
  ];
################################################################################
##########################   CHECKBOX COUNTS ###################################
################################################################################
  public static $checkBoxFields = [
    'electricity_service_'          => ['num' =>1],
    'gas_service_'                  => ['num' =>1],
    'water_service_'                => ['num' =>1],
    'garbage_service_'              => ['num' =>1],
    'electricity_reimburse_'        => ['num' =>1],
    'gas_reimburse_'                => ['num' =>1],
    'water_reimburse_'              => ['num' =>1],
    'garbage_reimburse_'            => ['num' =>1],
    'has_hazard_'                   => ['num' =>1],
    'water_service_day_monday_'     => ['num'=>1],
    'water_service_day_tuesday_'    => ['num'=>1],
    'water_service_day_wednesday_'  => ['num'=>1],
    'water_service_day_thursday_'   => ['num'=>1],
    'water_service_day_friday_'     => ['num'=>1],
    'water_service_day_saturday_'   => ['num'=>1],
    'water_service_day_sunday_'     => ['num'=>1],
  ];
################################################################################
##########################   TEXTAREAS  ########################################
################################################################################
  public static $textBoxCounts = [
    'included_properties_'     => ['num' =>1],
    'disclosed_infestations_'  => ['num' =>1],
    'tenant_lists_'            => ['num' =>1],
    'other_notes_'             => ['num' =>1],
    'property_additonal_info_' => ['num' =>1],
  ];
################################################################################
##########################   RADIO BUTTONS #####################################
################################################################################
  public static $radioFields = [
    'provide_parking_'                   => ['options'=>['include_parking','not_include_parking'],'default'=>'include_parking','count'=>1],
    'provide_storage_'                   => ['options'=>['include_storage','not_include_storage'],'default'=>'include_storage','count'=>1],
    'lead_paint_knowledge_'              => ['options' => ['known_lead_paint','not_known_lead_paint'],'default'=>'not_known_lead_paint','count'=>1],
    'contains_paint_record_'             => ['options'=>['has_paint_record','no_paint_record'],'default'=>'no_paint_record','count'=>1],
    'infestation_'                       => ['options'=>['no_infestation','previous_infestation'],'default'=>'no_infestation','count'=>1],
    'inspection_done_'                   => ['options'=>['inspection_prior','inspection_after'],'default'=>'inspection_prior','count'=>1],
    'provide_pets_'                      => ['options'=>['include_pets','not_include_pets'],'default'=>'not_include_pets','count'=>1],
    'bill_estimate_'                     => ['options'=>['average_bill','family_bill'],'default'=>'average_bill','count'=>1],  
    'require_satellite_insurance_'       => ['options'=>['require_insurance','not_require_insurance'],'default'=>'not_require_insurance','count'=>1],
    'require_satellite_deposit_'         => ['options'=>['require_satellites_deposit','not_require_satellite_deposit'],'default'=>'not_require_satellite_deposit','count'=>1],
  ];
################################################################################
##########################  ADDITIONAL INPUT CSS CLASSES #######################
################################################################################
  public static $fieldClasses = [
    'tenant_lease_full_name'     => 'form-control full-width',
    'tenant_occupant_full_name_' => 'form-control full-width',
    'full_prop_address_'         => 'form-control full-width',
    'premises_'                  => 'form-control full-width',
    'rent_payment_address_'      => 'form-control full-width',
    'sec_dep_return_address_'    => 'form-control full-width',
    'notice_address_'            => 'form-control full-width',
    'disclosed_infestations_'    => 'form-control full-width',
    'tenant_lists_'              => 'form-control full-width',
    'other_notes_'               => 'form-control full-width',
    'payments_to_'               => 'form-control full-width',
    'prop_address_'              => 'form-control full-width',
    'notice_local_address_'      => 'form-control full-width',
    'included_pets_'             => 'form-control full-width',
    'optional_address_'          => 'form-control full-width',
    'storage_cost_'              => 'decimal hide-on-load',
    'parking_cost_'              => 'decimal hide-on-load',
    'total_cost_'                => 'decimal',
    'electricity_service_'       => 'service-pay-checkbox-group',
    'gas_service_'               => 'service-pay-checkbox-group',
    'water_service_'             => 'service-pay-checkbox-group',
    'garbage_service_'           => 'service-pay-checkbox-group',
    'other_service_'             => 'service-pay-checkbox-group',
    'electricity_reimburse_'     => 'reimburse-pay-checkbox-group',
    'gas_reimburse_'             => 'reimburse-pay-checkbox-group',
    'water_reimburse_'           => 'reimburse-pay-checkbox-group',
    'garbage_reimburse_'         => 'reimburse-pay-checkbox-group',
    'other_reimburse_'           => 'reimburse-pay-checkbox-group',    
    'water_service_name_'        => 'form-control full-width',
    'water_service_address_'     => 'form-control full-width',
    'water_fixture_name_'        => 'form-control full-width',
    'water_fixture_address_'     => 'form-control full-width',
    'submeter_name_'             => 'form-control full-width',
    'submeter_address_'          => 'form-control full-width',
  ];
################################################################################
##########################   DYNAMIC TENANT INPUTS #############################
################################################################################
  public static $tenantFields = [
    'tenant_print_' => ['num'=>14,'classes'=>'','readonly'=>'1','label'=>'Printed Name','font'=>'Source Sans Pro','rule'=>'required|string'],
    'tenant_phone_' => ['num'=>2,'classes'=>'phone','label'=>'Phone','font'=>'Source Sans Pro','rule'=>'required'],
    'tenant_email_' => ['num'=>2,'classes'=>'email','label'=>'Email','font'=>'Source Sans Pro','rule'=>'nullable|string'],
  ];
################################################################################
##########################   INSPECTION HTML CODE  #############################
##########################        TEMPLATE         #############################
################################################################################
/*
 * Parameters needed to generate the webpage HTML and PDF HTML code
 * for the Move In / Move Out inspection section of the rental (Section 53.5)
 * 
 * Format:
 *  @example
 *    [
 *      //Name of category to inspection for move in and move out
 *      'category_name' => [
 *        'num' => 1, //Number of times this category appears in the document
 *        //Number of subcategories that have their own grouping of move in/move out checkboxes
 *        'fields' => [
 *          [
 *            'name'=>'field_name' //Prefix that will be added to the "name" attribue of the HTML inputs
 *            'title'=>'Field Title/Label' //Label of the subcategory that will appear on the document
 *          ],
 *        ],
 *        'header => 'Category Title' // Title /label in the document
 *        'inputHeader' => true //Whether there needs to be an additional text box for notes near the title
 *      ]
 *    ]
 */
  private static $_inspectionFields = [
    'front_yard_inspect_' => [
      'num' => 1,
      'fields' => [
        ['name'=>'landscaping','title'=>'Landscaping'],
        ['name'=>'fences','title'=>'Fences /Gates'],
        ['name'=>'sprinklers','title'=>'Sprinklers /Timers'],
        ['name'=>'walks','title'=>'Walks /Driveway'],
        ['name'=>'porches','title'=>'Porches /Stairs'],
        ['name'=>'mailbox','title'=>'Mailbox'],
        ['name'=>'light','title'=>'Light Fixtures'],
        ['name'=>'exterior','title'=>'Building Exterior'],
      ],
      'header' => 'Front Yard/Exterior',
      'inputHeader'=>false,
    ],
    'entry' => [
      'num'=> 1,
      'fields' => [
        ['name'=>'security','title'=>'Security /Screen Doors'],
        ['name'=>'doors','title'=>'Doors /Knobs /Locks'],
        ['name'=>'flooring','title'=>'Flooring /Baseboards'],
        ['name'=>'light','title'=>'Light Fixtures'],
        ['name'=>'switches','title'=>'Switches /Outlets'],
      ],
      'header' => 'Entry',
      'inputHeader'=>false
    ],
    'living_room' => [
      'num' => 1,
      'fields' => [
        ['name'=>'doors','title'=>'Doors /Knobs /Locks'],
        ['name'=>'flooring','title'=>'Flooring /Baseboards'],
        ['name'=>'walls','title'=>'Walls /Ceilings'],
        ['name'=>'screens','title'=>'Windows /Locks /Screens'],
        ['name'=>'light','title'=>'Light Fixtures'],
        ['name'=>'switches','title'=>'Switches /Outlets'],
        ['name'=>'fireplace','title'=>'Fireplace Equipment']
      ],
      'header' => 'Living Room',
      'inputHeader'=>false
    ],
    'other_room' => [
      'num'=>1,
      'fields' => [
        ['name'=>'doors','title'=>'Doors /Knobs /Locks'],
        ['name'=>'flooring','title'=>'Flooring /Baseboards'],
        ['name'=>'walls','title'=>'Walls /Ceilings'],
        ['name'=>'windows','title'=>'Window Coverings'],
        ['name'=>'light','title'=>'Light Fixtures'],
        ['name'=>'switches','title'=>'Switches /Outlets'],
      ],
      'header' => 'Other Room',
      'inputHeader'=>true
    ],
    'bedrooms' => [
      'num' => 3,
      'fields' => [
        ['name'=>'doors','title'=>'Doors /Knobs /Locks'],
        ['name'=>'flooring','title'=>'Flooring /Baseboards'],
        ['name'=>'walls','title'=>'Walls /Ceilings'],
        ['name'=>'windows','title'=>'Window Coverings'],
        ['name'=>'light','title'=>'Light Fixtures'],
        ['name'=>'switches','title'=>'Switches /Outlets'],
        ['name'=>'closets','title'=>'Closets /Doors /Tracks'],
      ],
      'header' => 'Bedroom #',
      'inputHeader' => true,
    ],
    'bath' => [
      'num' => 2,
      'fields' => [
        ['name'=>'doors','title'=>'Doors /Knobs /Locks'],
        ['name'=>'flooring','title'=>'Flooring /Baseboards'],
        ['name'=>'walls','title'=>'Walls/Ceilings'],
        ['name'=>'windows','title'=>'Window Coverings'],
        ['name'=>'screens','title'=>'Windows /Locks /Screens'],
        ['name'=>'light','title'=>'Light Fixtures'],
        ['name'=>'switches','title'=>'Switches /Outlets'],
        ['name'=>'toilet','title'=>'Toilet'],
        ['name'=>'tub','title'=>'Tub /Shower'],
        ['name'=>'sink','title'=>'Sink /Faucets'],
        ['name'=>'plumbing','title'=>'Plumbing /Drains'],
        ['name'=>'exhaust','title'=>'Exhaust Fan'],
        ['name'=>'towel','title'=>'Towel Rack(s)'],
        ['name'=>'paper_holder','title'=>'Toilet Paper Holder'],
        ['name'=>'cabinet','title'=>'Cabinet /Counters']
      ],
      'header'=>'Bath #',
      'inputHeader' => true,
    ],
    'kitchen' => [
      'num'=>1,
      'fields'=>[
        ['name'=>'doors','title'=>'Doors /Knobs /Locks'],
        ['name'=>'flooring','title'=>'Flooring /Baseboards'],
        ['name'=>'walls','title'=>'Walls/Ceilings'],
        ['name'=>'windows','title'=>'Window Coverings'],
        ['name'=>'screens','title'=>'Windows /Locks /Screens'],
        ['name'=>'light','title'=>'Light Fixtures'],
        ['name'=>'switches','title'=>'Switches /Outlets'],
        ['name'=>'range','title'=>'Range /Fan /Hood'],
        ['name'=>'oven','title'=>'Oven(s) Microwave'],
        ['name'=>'refrigerator','title'=>'Refrigerator'],
        ['name'=>'plumbing','title'=>'Plumbing /Drains'],
        ['name'=>'sink','title'=>'Sink /Disposal'],
        ['name'=>'faucet','title'=>'Faucet(s) Plumbing'],
        ['name'=>'cabinets','title'=>'Cabinets'],
        ['name'=>'counters','title'=>'Counters'],
      ],
      'header' => 'Kitchen',
      'inputHeader' => false,
    ],
    'laundry' => [
      'num'=>1,
      'fields'=>[
        ['name'=>'faucets','title'=>'Faucets /Valves'],
        ['name'=>'plumbing','title'=>'Plumbing /Drains'],
        ['name'=>'cabinet','title'=>'Cabinets /Counters'],
      ],
      'header'=>'Laundry',
      'inputHeader'=>true
    ],
    'systems'=>[
      'num'=>1,
      'fields'=>[
        ['name'=>'furnace','title'=>'Furnace /Thermostat'],
        ['name'=>'aircon','title'=>'Air Conditioner'],
        ['name'=>'heater','title'=>'Water Heater'],
        ['name'=>'softener','title'=>'Water Softener']
      ],
      'header'=>'Systems',
      'inputHeader'=>true
    ]
  ];
################################################################################
##########################   MAIN FUNCTION / LINK GENERATOR   ##################
################################################################################
  public static function generatePdfFile($r){
    try {
      //Generate directory and path for generated pdf
      $path          = storage_path('app/public/agreement/'); 

      //Get agreement data from database row
      $agreementData = isset($r['agreement']) ? json_decode($r['agreement'],true): [];
      $fileInfo      = self::_generateRentalFile($r['application_id']);
      //Generate pdf filename
      $file          = $fileInfo['file'];
      $uuid          = $fileInfo['uuid'];
      Storage::makeDirectory('public/agreement/' . $uuid . '/');
      $filePath      = $uuid . '/' . $file;
      //Get agreement data HTML
      $content       = !empty($agreementData) ? $agreementData['chunks'] : [];
      
      PDF::reset();
      PDF::SetTitle('Rental Agreement');
      PDF::setPageOrientation('P');
      # HEADER SETTING
      PDF::SetHeaderData('','0','Rental Agreement::Run on ' . date('F j, Y, g:i a'));
      PDF::setHeaderFont([self::$_font, '', self::$_fontSize]);
      PDF::SetHeaderMargin(3);
      PDF::AddPage();
      foreach($content as $k => $v){      
        # FOOTER SETTING
       // PDF::SetPrintFooter(false);
        PDF::SetFont(self::$_font, '',self::$_fontSize);
        PDF::setFooterFont([self::$_font, '', self::$_fontSize]);
        PDF::SetFooterMargin(5);
        
        # MARGIN SETTING
        PDF::SetMargins(10,15,10,true);
        PDF::SetAutoPageBreak(TRUE, 10);
        //Write HTML chunk to pdf file
        PDF::writeHTML($v,true,false,true,false,'');
//        PDF::Output($path . $tmpFilename,'F');
//        $paths[] = $path . $tmpFilename;
//        $files[] = 'public/tmp/' . $tmpFilename;
//        $firstPage = false;
      }
      PDF::Output(storage_path('app/public/agreement/' . $filePath),'F');
      $link       = \Storage::disk('public')->url('agreement/' . $filePath);
      $uploadLink = File::getLocation('CreditCheckUpload')['agreement'] . $filePath;
//      $mergeParams   = [	
//          'msg'       => 'Your Lease Agreement is now ready. Please click the link to download it',	
//          'paths'     => $paths,	
//          'files'     => $files,	
//          'fileName'  => 'public/tmp/' . $file,	
//          'href'      => $link	
//      ];	
//      $mergeR        = PDFMerge::mergeFiles($mergeParams);
      
      //Remove no longer needed images from server storage
      $imgRefs = !empty($agreementData['images']) ? $agreementData['images']  : [];
      foreach($imgRefs as $v){
          $valid = file_exists($v) ? unlink($v) : 0;
      }
      return [
          'application_id'=>$r['application_id'],
          'link'          =>$link,
          'fileName'      =>$file,
          'uuid'          =>$uuid,
          'fileUploadLink'=>$uploadLink,
          ];
    } catch (Exception $e){
      Helper::echoJsonError(Helper::unknowErrorMsg());
    }
  }
//------------------------------------------------------------------------------
  public static function addAgentSig($applicationId,$rawData){  
    $r          = Helper::getElasticResult(Elastic::searchMatch(T::$creditCheckView,['match'=>['application_id'=>$applicationId]]),1);
    $r          = !empty($r['_source']) ? $r['_source'] : [];
    
    $group      = !empty($r['group1']) ? preg_replace('/#/','',$r['group1']) : '';
    $supervisor = !empty($group) ? Helper::getElasticResult(Elastic::searchMatch(T::$accountView,['wildcard'=>['ownGroup'=>'*' . strtolower($group) . '*']]),1) : [];
    $supervisor = !empty($supervisor) ? $supervisor['_source'] : [];
    
    $formData   = !empty($rawData['formData']) ? $rawData['formData'] : [];
    $supervisorSig =  !empty($supervisor) ? title_case($supervisor['firstname'] . ' ' . $supervisor['lastname']) : '';
    for($i = 0; $i < self::$_agentSig; $i++){
        $formData[self::$_agentFieldName][$i] = $supervisorSig;
    }
    
    $formData  += !empty($rawData['uuid']) ? ['uuid'=>$rawData['uuid']] : [];
    $formData  += !empty($rawData['fileName']) ? ['fileName'=>$rawData['fileName']] : [];
    $formData['application_id'] = $applicationId;
    
    $newPdf     = self::generatePdfChunks($formData);
    return $newPdf;
  }
//------------------------------------------------------------------------------
  /*
   * @desc Generates PDF Html and JSON for rental agreement
   * @params {array} $data: Request data
   * @returns {array}
   */
  public static function generatePdfChunks($data){
    $appId             = $data['application_id']; //Credit Check Id
    $vData             = $data;
    $numTenantInitials = 14; //Number of tenant initial signatures from agreement form
    $numTenantSig      = self::$numTntSig; //Number of tenant signatures from agreement form

    $allData = $tenantEmails = [];
    
    //Obtain credit check application from Elastic search by application id
    $r                 = Elastic::searchMatch(T::$creditCheckView,['match'=>['application_id'=>$appId]]);
    $r                 = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    
    //Determine number of tenants from the credit check application
    $numTenants        = !empty($r) && !empty($r['application']) ? count($r['application']) : 1;
    
    //Get associated supervisor
    $supervisor        = self::_fetchSupervisor($r);
     
    //Determine if the user that submitted the form was the supervisor
    $isSupervisor      = !empty($vData['isSupervisor']) ? $vData['isSupervisor'] : false;

    //Get occupant HTML for the appropriate number of tenants
    $occupantHtml        = self::_generateOccupantHtml($numTenants,$vData);
    // Input id prefixes retrieved from the agreement form
    $prefixes = [
      'tnt_initial_'         => $numTenantInitials,
    ];
    
    //Generate tenant initial HTML data for the PDF report
    $tenantInitialData = self::_generateTenantInitials($prefixes,$vData,$r,$supervisor);
    
    $allData          += $tenantInitialData['data'];
    $imgFiles          = $tenantInitialData['images'];
    
    //Concatenate all the input fields and names to search for from the request data
    $allKeys           = array_merge(self::$fieldCounts,self::$dateCounts,self::$numberCounts,self::$moneyCounts,self::$checkBoxFields,self::$textBoxCounts,self::$emailCounts,self::$phoneFields);

    foreach($allKeys as $k => $v){
      for($i = 1; $i < $v['num'] + 1; $i++){
        if(isset(self::$checkBoxFields[$k])){
          //Generate response if input response was from a checkbox
          $allData[$k . $i] = isset($vData[$k][$i - 1]) ? Html::span(Html::b('X')) : Html::span(Html::b('_'));
        } else {
          if($k === 'tenant_email' && isset($vData[$k][$i - 1])){
            $tenantEmails[] = $vData[$k][$i-1];
          }
          //Underline and bold value if the input value was filled, leave it empty if it does not exist in the request data
          $value            = isset($allData[$k . $i]) ? $allData[$k . $i] : isset($vData[$k][$i-1]) ?  $vData[$k][$i-1] : '';
          $value            = !empty($value) && is_numeric($value) && in_array($k,array_keys(self::$moneyCounts)) ? Format::usMoney($value) : $value;
          $allData[$k . $i] = Html::span(Html::u(Html::b($value)));
        }
      }
    }
    
    $provideParking     = !empty($vData['provide_parking_']) ? $vData['provide_parking_'] : [self::$radioFields['provide_parking_']['default']];
    $provideStorage     = !empty($vData['provide_storage_']) ? $vData['provide_storage_'] : [self::$radioFields['provide_storage_']['default']];
    $providePet         = !empty($vData['provide_pets_']) ? $vData['provide_pets_'] :  [self::$radioFields['provide_pets_']['default']];
    
    $additionalCostArr       = [];
    $additionalCostTemplate  = [
        'parking_cost_' => ['label'=>'Parking Cost'],
        'storage_cost_' => ['label'=>'Storage Cost'],
        'pets_cost_'    => ['label'=>'Pet Cost'],
    ];
    
    $additionalCostHtml      = '';
    
    foreach($provideParking as $i => $v){
        $prefixStr                            = 'If not included in the Rent, the parking rental fee shall be an additional ';
        $suffixStr                            = ' per month.';
        $allData['parking_notes_' . ($i + 1)] = $v === 'not_include_parking' && !empty($vData['parking_cost_'][$i])  ? $prefixStr . Html::b(Html::u(($vData['parking_cost_'][$i]))) . $suffixStr : ''; 
    
        $additionalCostArr['parking_cost_'] = $v === 'not_include_parking' && !empty($vData['parking_cost_'][1])  ? ($vData['parking_cost_'][1]) : '';
    }
    
    foreach($provideStorage as $i => $v){
        $prefixStr                               = 'If not included in the Rent, the storage space fee shall be an additional ';
        $suffixStr                               = ' per month.';
        $allData['storage_notes_' . ($i + 1)]    = $v === 'not_include_storage' && !empty($vData['storage_cost_'][$i]) ? $prefixStr . Html::b(Html::u(($vData['storage_cost_'][$i]))) . $suffixStr : '';
 
        $additionalCostArr['storage_cost_']  = $v === 'not_include_storage' && !empty($vData['storage_cost_'][1])  ? ($vData['storage_cost_'][1]) : '';
    }
    
    foreach($providePet as $i => $v){
       $allData['included_pets_' . ($i + 1)]   = $v === 'include_pets' && !empty($vData['included_pets_'][$i]) ? Html::br() . Html::b(Html::u($vData['included_pets_'][$i])) : '';
       
       if($i == 0){
           $additionalCostArr['pets_cost_'] = $v === 'include_pets' && !empty($vData['pets_cost_'][$i]) ? ($vData['pets_cost_'][$i]) : '';
       }
    }
    
    foreach($additionalCostArr as $k => $v){
        $label   = !empty($additionalCostTemplate[$k]['label']) ? $additionalCostTemplate[$k]['label'] : '';
        if(!empty($v)){
            $trRow               =  '';
            $trRow              .= Html::td('') . Html::td('');
            $trRow              .= Html::td(Html::tag('p',$label),['width'=>'400']);
            $value               = Html::tag('p',Html::span(Html::b(Html::u(is_numeric($v) ? Format::usMoney($v) : '')),['class'=>'cost']));
            $trRow              .= Html::td($value);
            $additionalCostHtml .= Html::tr($trRow);
        }
    }
    
    $allData['additional_costs_1']  = $additionalCostHtml;
    //Determine font for tenant signatures
    $tenantSigFont = isset($vData['tnt-sig_font']) ? $vData['tnt-sig_font']  : 'Source Sans Pro';
    $tenantCounts  = array_merge(self::$tenantFields,['tnt_signature_'=>['num'=>$numTenantSig,'label'=>'Signature','font'=>$tenantSigFont]]);
    foreach($tenantCounts as $k => $v){
      //Generate tenant signature HTML responses for the report
      $resp        = self::_generateTenantSignatures($numTenants,$v,$vData,$k,$r);
      $imgFiles    = array_merge($imgFiles,$resp['images']);
      $allData[$k] = $resp['data'];
    }
    
    //Generate agent/manager signature HTML responses for the PDF report
    $allData['agent_signature_'] = self::_generateAgentSignatures($isSupervisor,self::$_agentSig,$vData);
    
    foreach(self::$radioFields as $k => $v){
      for($i = 1; $i < $v['count'] + 1; $i++){
        $selected = isset($vData[$k][$i-1]) ? $vData[$k][$i-1] : '';
        foreach($v['options'] as $opt){
          //If option is selected and is from a radio button
          $allData[$opt . '_' . $i] = ($opt === $selected && $selected != '') ? Html::span(Html::b('X')) : Html::span('_');
        }
      }
    }
    //Generate image table section of the PDF report
    $images         = self::_generateImageDiv();
    $prorateOpts    = [
      'prorateCurrentMonth'     => 'Prorate Current Month',
      'prorateNextMonth'        => 'Prorate Next Month',
      'noProrate'               => 'No Prorate'
    ];
    $allData['move_in_date']    = Html::b(Html::u($vData['move_in_date']));
    $allData['prorate']         = !empty($vData['prorate']) ? Html::b(Html::u(Helper::getValue($vData['prorate'],$prorateOpts))) : '';
    $allData['next_month_rent'] = !empty($vData['next_month_rent_'][0]) && is_numeric($vData['next_month_rent_'][0]) ? Html::tr(Html::td('') . Html::td('') . Html::td('Next Month\'s Rent',['width'=>400]) . Html::td(Html::p(Html::span(Html::b(Html::u(Format::usMoney($vData['next_month_rent_'][0]))),['class'=>'cost'])))) : '';
    //Generate inspection section from checkbox responses from the request data
    $inspectionData = self::_generateInspectionResponse($vData);
    $viewData       = [
                        'data'=>
                          [
                            'agreement'         =>$allData,
                            'occupantInfo'      =>$occupantHtml,
                            'occupantNames'     =>self::_generateOccupantNames($vData),
                            'inspectionResponse'=>$inspectionData,
                            'images'            =>$images
                          ]
                      ];
    
    
    //Get all chunk template file names
    $chunksParts  = array_map(function($v){return 'chunk' . $v;},range(1,self::$_numChunks));
    
    //Generate PDF HTML and JSON
    $pdfCode = self::_generatePDFHtml($chunksParts,$vData,$viewData,$imgFiles);
    return $pdfCode;
  }
################################################################################
##########################    PDF STORAGE / GENERATION FUNCTIONS   #############
################################################################################
//------------------------------------------------------------------------------
  /*
   * @desc Generate PDF file from HTML data retrieved from databsae
   * @params {array} $chunkIndexes: Array of template HTML file references
   * @params {array} $formData: Form Data from a submitted POST request
   * @params {array} $viewData: View data to pass to render HTML
   * @params {array} $imgRefs: Array of image file references to render input Jsignature images
   * @returns {string} : JSON-encoded string
   */
  private static function _generatePDFHtml($chunkIndexes,$formData,$viewData,$imgRefs=[]){
    $agreementJson = [
      'chunks'          => [],
      'formData'        => $formData,
      'images'          => $imgRefs,
      'application_id'  => $formData['application_id'],
      'uuid'            => 'Lease_Agreement_Dir_' . $formData['application_id'],
      'fileName'        => 'Tenant_Lease_Agreement_' . $formData['application_id'] . '.pdf',
    ];
   
    $templatePath = 'app/CreditCheck/agreement/';
    $templateView = \View::make($templatePath . 'editPdf',$viewData);
    $agreementJson['chunks']['chunk1'] = $templateView->render();
//    foreach($chunkIndexes as $k=>$v){
//      $templatePath = self::$_templateDir . $v; //Get template blade.php path
//      $templateView = \View::make($templatePath,$viewData); //Generate view
//      $agreementJson['chunks'][$v] = $templateView->render(); //Get view's HTML as string
//    }
    return json_encode($agreementJson,JSON_FORCE_OBJECT);
  }
################################################################################
##########################   PDF HTML CODE GENERATING ##########################
##########################         FUNCTIONS          ##########################
################################################################################
//------------------------------------------------------------------------------
  /*
   * @desc Generate section 1.1 of tenant agreement for PDF
   * @params {integer} $numTenants: Number of tenants in the rental agreement
   * @params {array}   $vData: Request data
   * @returns {string} : HTML string
   */
  private static function _generateOccupantHtml($numTenants,$vData){
    $occupantHtml = '';

    for($i = 1; $i < $numTenants + 1; $i++){
      //Get tenant name from section of request if it exists, leave blank otherwise
      $tntApplicantName = isset($vData['tenant_lease_full_name'][$i-1]) ? $vData['tenant_lease_full_name'][$i-1] : '';
      $tdData = Html::td('',['width'=>'15']) . Html::td('',['width'=>'30']) . Html::td(Html::tag('p',Html::b(Html::u($tntApplicantName))),['width'=>'450']);
      
      //Generate table row of this data
      $firstTr  = Html::tr($tdData);

      $occupantHtml .= ($firstTr);
    }

    return $occupantHtml;
  }
//------------------------------------------------------------------------------
  private static function _generateOccupantNames($vData){
      $occupants = !empty($vData['occupant_name']) ? $vData['occupant_name'] : [];
      $html      = '';
      foreach($occupants as $v){
          $tdData    = Html::td('',['width'=>'15']) . Html::td('',['width'=>'30']) . Html::td(Html::tag('p',Html::b(Html::u($v))),['width'=>'450']);
          $html     .= Html::tr($tdData);
      }
      return $html;
  }
//------------------------------------------------------------------------------
  /*
   * @desc Generate tenant initial response HTML from the submitted request
   * @params {array} $initialCounts: Prefix name to search in request data
   * @params {array} $vData        : Request data
   * @params {array} $r            : Elastic search document from Credit Check
   * @params {array} $supervisor   : Supervisor of agreement 
   * @returns {array} : Array of HTML string and image file references
   */
  private static function _generateTenantInitials($initialCounts,$vData,$r,$supervisor=[]){
    $allData  = $imgFiles = [];

    //Font's for submitted text inputs
    $sigFonts = [
      'tnt_initial_'         => isset($vData['tnt-initials_font']) ? $vData['tnt-initials_font'] : 'Source Sans Pro',
      'tnt_signature_'       => isset($vData['tnt-sig_font']) ? $vData['tnt-sig_font']  : 'Source Sans Pro',
    ];
    
    //Image file prefix
    $fileFirstName = !empty($r) && !empty($r['application']) ? $r['application'][0]['fname'] : !empty($supervisor) ? $supervisor['firstname'] : 'PAMA_MANAGEMENT';
    foreach($initialCounts as $k=>$v){
      
      for($i = 1; $i < $v + 1; $i++){
        
        if(!empty($vData[$k . 'hiddenSig'][$i - 1])){
          //Generate unique filename for signature image
          //$fileName   = $imgPath . $fileFirstName . '_CreditCheck_' . $vData['application_id'] . date('-Y-m-d-H-i-s') . $k . $i . '_hiddenSig';
          $fileName = storage_path('app/public/tmp/' . $fileFirstName . '_CreditCheck_' . $vData['application_id'] . date('-Y-m-d-H-i-s') . $k . $i . '_hiddenSig.png');
          //Retrieve signature image data
          $uriData    = $vData[$k . 'hiddenSig'][$i - 1];
          
          $imgFiles[] = $fileName;
          //Recreate and store image onto server
          self::_generateImage($fileName, $uriData);
          //Create an image tag using that image that can be rendered by the pdf
          $allData[$k . $i] = Html::img(['style'=>'display:inline-block;','height'=>self::$_sigImgHeight,'width'=>self::$_sigImgWidth,'src'=> self::_reformatToImageLink($fileName)]);
        } elseif(!empty($vData[$k . 'hiddenTxtSig'][$i - 1])) {
          $inputFont        = !empty(self::$fontFiles[$sigFonts[$k]]) ? self::$fontFiles[strval($sigFonts[$k])] : 'Source Sans Pro';
          
          $allData[$k . $i] = Html::span(Html::b(Html::u($vData[$k . 'hiddenTxtSig'][$i - 1])),['style'=>'font-family:' . $inputFont]);
        } else {
          //Otherwise use the user's text input and render it in the font they selected
          $allData[$k . $i] = isset($vData[$k][$i - 1]) ? Html::span(Html::b(Html::u($vData[$k][$i - 1]))) : '';
        }
      }
    }
    
    return [
      'data'   => $allData,
      'images' => $imgFiles,
    ];
  }
//------------------------------------------------------------------------------
  /*
   * @desc Generate tenant signature response HTML from the submitted request
   * @params {integer} $numTenants : Number of tenants for the rental agreement
   * @params {array}   $params     : Input HTML parameters
   * @params {array}   $vData      : Submitted and validated request data
   * @params {string}  $key        : Prefix key to search for in request data
   * @params {array} $r            : Elastic search document from Credit Check
   * @params {array} $supervisor   : Supervisor of agreement 
   * @returns {array} : Array of HTML string and image file references
   */
  private static function _generateTenantSignatures($numTenants,$params,$vData,$key,$r=[]){
    $responses = $imgPaths = [];
    //Directory for storing signature images
    //Determine filename prefix for generated images from Jsignature
    $filePrefix = !empty($r) && !empty($r['application']) ? $r['application'][0]['fname'] : $vData['account_first_name'];
   
    for($i = 1; $i < $params['num']+1; $i++){
      $html = '';
      for($j = 1; $j < $numTenants+1; $j++){
        //Generate label
        $html .= 'Tenant (' . ($j) . ')  ' . $params['label'] . ':&nbsp;&nbsp;&nbsp;';
        $imgKey = $key . $i . '_' . $j . '_hiddenSig';
        
        //If the submitted input was an image from Jsignature
        if(!empty($vData[$imgKey]) && !empty($vData[$imgKey])){
          //Generate filename for image
          //$fileName = $basePath . $filePrefix . '_CreditCheck_' . $vData['application_id'] . date('-Y-m-d-H-i-s') . $key . $i . '_' . $j . '_hiddenSig';
          $fileName = storage_path('app/public/tmp/' . $filePrefix . '_CreditCheck' . $vData['application_id'] . date('-Y-m-d-H-i-s') . '_' . $j . '_hiddenSig.png');
          //Get image bytes
          $uriData  = $vData[$key . $i . '_' . $j . '_hiddenSig'];
          $imgPaths[] = $fileName;
          
          //Generate image in /storage
          self::_generateImage($fileName,$uriData);
          
          //Create image tag with reference to signature image
          $html .= Html::img(['style'=>'display:inline-block','height'=>self::$_sigImgHeight,'width'=>self::$_sigImgWidth,'src'=>self::_reformatToImageLink($fileName)]) . Html::br();
        } else if(!empty($vData[$key . $i . '_' . $j . '_hiddenTxtSig'])){
          //If the submitted input was from a signature text input
          
          //Get font of text
          $font  =  isset(self::$fontFiles[$vData['fontSig'][$j-1]]) ? self::$fontFiles[$vData['fontSig'][$j-1]] : 'Source Sans Pro';
          
          //Generate response HTML with text value
          $html .=  Html::span(Html::b(Html::u($vData[$key . $i . '_' . $j . '_hiddenTxtSig'])),['style'=>'font-family:' . $font]) . Html::br();
        } else {
          $html .=  isset($vData[$key . $j . '_' . $i]) ? Html::span(Html::b(Html::u($vData[$key . $j . '_' . $i]))) : '';
          $html .= ($j < $numTenants) ? Html::br() : '';
        }
      }
      $responses[] = $html;
    }
    
    return ['data'=>$responses,'images'=>$imgPaths];
  }
//------------------------------------------------------------------------------
  /*
   * @desc Generate agent/manager signature response HTML from the submitted request
   * @params {bool}    $isSupervisor: Whether the user that submitted request was supervisor
   * @params {integer} $numSig:     Number of signature responses to generate
   * @params {array}   $vData      : Submitted and validated request data
   * @returns {array} : Array of HTML string and image file references
   */
  private static function _generateAgentSignatures($isSupervisor,$numSig,$vData){
    $agentSigHtml = [];
    
    for($i = 1; $i < $numSig + 1; $i++){
      $html = '';
      
      //If submitted user was not the supervisor
      if(!$isSupervisor){
        $html .= Html::b('Authorized Agent Signature:&nbsp;&nbsp;&nbsp;'); //Manager Signature Label
        //Get manager signature data
        $html .= (isset($vData['manager_signature'][$i-1]) ?  Html::span(Html::b(Html::u($vData['manager_signature'][$i-1]))) : '');
        //Manager Printed Name Label
        $html .= Html::br() . Html::b('Authorized Agent Printed Name:&nbsp;&nbsp;');
        //Get manager Printed Name data
        $html .= isset($vData['manager_print'][$i-1]) ? Html::span(Html::b(Html::u($vData['manager_print'][$i-1]))) : '';
        $html .= Html::br();
      }
   
      //Supervisor Signature Label
      $html   .=  Html::b('Supervisor Signature:&nbsp;&nbsp;');
      //Get supervisor signature data from request
      $html   .= isset($vData['agent_signature_'][$i-1]) ? Html::span(Html::b(Html::u($vData['agent_signature_'][$i-1]))) : '';
      //Supervisor Printed Name Label
      $html   .= Html::br()  . Html::b('Supervisor Print Name:&nbsp;&nbsp;');
      //Get supervisor Printed Name data from request
      $html   .= isset($vData['agent_print'][$i-1]) ? Html::span(Html::b(Html::u($vData['agent_print'][$i-1]))) : '';
      $agentSigHtml[] = $html; 
    }
    return $agentSigHtml;
  }
//------------------------------------------------------------------------------
  /*
   * @desc Generates Inspection Response HTML for PDF
   * @params {array} $vData : Request Data
   * @returns {string} : HTML string
   */
  private static function _generateInspectionResponse($vData){
    $html = '';
    
    //For every category / section for inspection
    foreach(self::$_inspectionFields as $k => $v){
      //Generate HTML response for each section
      for($i = 0; $i < $v['num']; $i++){
        $tArr  = [];
        //Get title of category
        $headerTxt  = $v['header'] . ' ';
        $headerKey  = $k . '_description';
        
        //Add title notes if present
        $headerTxt .= $v['inputHeader'] ? isset($vData[$headerKey][$i]) ? $vData[$headerKey][$i] : '' : '';
        
        $html .= Html::b($headerTxt) . Html::br();
        
        //For every submitted checkbox group / field for the section
        foreach($v['fields'] as $f){
          $moveIn = $td = $moveOut = '';
          //Add title of field
          $tRow   = [[
            'val' => $f['title'],
            'param'=>['width'=>'16%'],
            'header'=>[
              'val'=>'',
              'param'=>['width'=>'16%;']
            ]
          ]];
          foreach(self::$_moveInOpts as $val){
            $moveIn    .= $val;
            $checkKey   = $k . '_' . $f['name']  . '_movein';
            $checkVal   = isset($vData[$checkKey][$i]) ? $vData[$checkKey][$i] : '';
            //Process checkbox resposne
            $checkResp  = $val === $checkVal ? Html::b(' (X) ') : ' (_) ';
            $moveIn    .= $checkResp;
          }
          
          //Add checkbox field notes if submitted
          $moveIn .= (isset($vData[$k . '_' . $f['name'] . '_move_in_notes'][$i])) ? $vData[$k . '_' . $f['name'] . '_move_in_notes'][$i] : '';
          $tRow[]  = [
            'val' => $moveIn,
            'param'=>['width'=>'63%'],
            'header'=>[
              'val'=>'',
              'param'=>['width'=>'63%;']
            ]
          ];
          
          foreach(self::$_moveOutOpts as $val){
            $moveOut  .= $val;
            $checkKey  = $k .  '_' . $f['name'] . '_moveout';
            $checkVal  = isset($vData[$checkKey][$i]) ? $vData[$checkKey][$i] : '';
            //Process checkbox response
            $checkResp = $val === $checkVal ? Html::b(' (X) ') : ' (_) ';
            $moveOut  .= $checkResp;
          }
          
          //Add move out notes for field if present
          $moveOut .= (isset($vData[$k . '_' . $f['name'] . '_move_out_notes'][$i])) ? $vData[$k  . '_' . $f['name'] . '_move_out_notes'][$i] : '';
          $tRow[]   = [
            'val'=>$moveOut,
            'param' => ['width'=>'21%'],
            'header'=>[
              'val'=>'',
              'param'=>['width'=>'21%;']
            ]
          ];
          
          $tArr[]   = $tRow;
        }
        
        $html    .= Html::buildTable([
          'data' => $tArr,
          'tableParam'=>['class'=>'table-form'],
          'isHeader' => 0,
          'isOrderList'=>0,
        ]);
        //Add general notes for the section / category if they were submitted / present
        $html  .= (isset($vData[$k . '_generalNotes'][$i])) ? Html::tag('p',$vData[$k . '_generalNotes'][$i]) : '';
      }
    }
    
    return $html;
  }
//------------------------------------------------------------------------------
  private static function _generateImageDiv(){
    $tArr  = [];
    for($i = 1; $i < self::$_numImages + 1; $i+=2){
        $tRow    = [];
        $tRow[]  = [
          'val' =>Html::img(['src'=>public_path() . self::$_imageDirPrefix . $i . '.jpg','width'=>'250','height'=>'300']),
          'header' => [
            'val' => '',
            'param'=> []
          ]
        ];
        
        $tRow[]  = [
          'val' => Html::img(['src'=> public_path() . self::$_imageDirPrefix . ($i + 1) . '.jpg','width'=>'250','height'=>'300']),
          'header' => [
            'val' => '',
            'param'=>[]
          ]
        ];
        
        $tArr[]  = $tRow;
    }
    
    $images = Html::span(Html::buildTable(['data'=>$tArr,'tableParam'=>['align'=>'center'],'isHeader'=>0,'isOrderList'=>0]));
    
    return $images;
  }
################################################################################
########################## WEBPAGE HTML CODE GENERATING ########################
##########################          FUNCTIONS           ########################
################################################################################
//------------------------------------------------------------------------------
  /*
   * @desc Generates Inspection section for Rental Agreement page with Checkbox inputs etc.
   * @params {array} $agreementData : Any previously submitted form data
   * @params {integer} $disbaled : Whether inputs are disabled
   * @returns {string} : HTML string
   */
  public static function generateInspectionNotes($agreementData=[],$disabled=0){
    $html = '';
    $disabledParams = $disabled ? ['readonly'=>'1'] : [];
    
    //Iterate all categories / sections of inspection
    foreach(self::$_inspectionFields as $k => $v){
      //Generate HTML resposne for each section
      for($i = 0; $i < $v['num']; $i++){
        $name  = $k . '_description[' . $i . ']';
        $headerTxt  = $v['header'] . ' '; //Get title of section
        
        //Generate header input textbox if needed
        $headerTxt .= $v['inputHeader'] ? Html::input(isset($agreementData[$name]) ? $agreementData[$name] : '',['type'=>'text','name'=>$k . '_description[' . $i . ']','id'=>$k . '_' . $i . '_description'] + $disabledParams) : '';
        $html .= Html::div(Html::div(Html::b($headerTxt),['class'=>'col-sm-12']),['class'=>'row']);
        //$html .= Html::tag('p',Html::b($headerTxt));        
        
        //For every checkbox group of fields in the section
        foreach($v['fields'] as $f){
          $cols    = '';
          $moveOut = $moveIn = $td = '';
          $cols .= Html::div($f['title'],['class'=>'col-sm-2']);
          foreach(self::$_moveInOpts as $val){
            $prefix  = $k  . '_' . $f['name'] . '_movein';
            $name    = $k  . $f['name']  . '[' . $i . ']';
            //'S' for move in is checked by DEFAULT
           
            $checked = ((isset($agreementData[$prefix][$i]) && $agreementData[$prefix][$i] === $val ) || (!isset($agreementData[$prefix][$i]) && $val === 'S')) ? ['checked'=>'checked'] : [];
            //Create checkbox
            $btnHtml = Html::label(Html::input('',$checked + ['type'=>'radio','name'=>$k . '_' . $f['name']  . '_movein[' . $i . ']','value'=>$val] + $disabledParams) . ' ' . $val . ' ' ,['class'=>'checkbox-inline']);
            $moveIn .= Html::div($btnHtml,['class'=>'col-sm-2']);
            
          }
          
          $name    = $k  . '_' . $f['name'] . '_move_in_notes';
          
          //Generate additional note section beside checkbox group
          $moveIn .= Html::div(Html::input(isset($agreementData[$name][$i]) ? $agreementData[$name][$i] : 'SATISFACTORY/CLEAN',['type'=>'text','name'=>$k . '_' . $f['name'] . '_move_in_notes[' . $i . ']'] + $disabledParams),['class'=>'col-sm-6']);
          $cols   .= Html::div(Html::div($moveIn,['class'=>'row']),['class'=>'col-sm-5']);
          foreach(self::$_moveOutOpts as $val){
            $prefix   = $k . '_' . $f['name'] . '_moveout';
            $name     = $k . '_' . $f['name'] . $val . '[' . $i . ']';
            $checked  = isset($agreementData[$prefix][$i]) && $agreementData[$prefix][$i] === $val ? ['checked'=>'checked'] : [];
            //Generate move out checkbox input
            //$moveOut .= Html::label(Html::input('',$checked + ['type'=>'checkbox','name'=>$k  . '_' . $f['name'] . '_' . $val . '_moveout[' . $i . ']','value'=>'0'] + $disabledParams) . ' ' . $val . ' ',['class'=>'checkbox-inline']);
            $btnHtml  = Html::label(Html::input('',$checked + ['type'=>'radio','name'=>$k . '_' . $f['name'] . '_moveout[' . $i . ']','value'=>$val,'disabled'=>1]) . ' ' . $val . ' ',['class'=>'checkbox-inline']);
            $moveOut .= Html::div($btnHtml,['class'=>'col-sm-2']);
          }
          
          $name     = $k  . '_' . $f['name'] . '_move_out_notes';
          
          //Generate notes section input to be placed beside checkbox group
          $moveOut .=  Html::div(Html::input(isset($agreementData[$name][$i]) ? $agreementData[$name][$i] : '',['type'=>'text','name'=>$k . '_' . $f['name'] . '_move_out_notes[' . $i . ']','disabled'=>1]),['class'=>'col-sm-6']);
          $cols    .=  Html::div(Html::div($moveOut,['class'=>'row']),['class'=>'col-sm-5']);
          $html     .= Html::div($cols,['class'=>'row']);
        } 
        
        //Add general notes textarea section for the category
        $name     = $k  . '_generalNotes';
        $textArea = Html::tag('textarea',isset($agreementData[$name][$i]) ? $agreementData[$name][$i] : '',['name'=>$k . '_generalNotes[' . $i . ']','rows'=>2,'class'=>'table-form full-width','cols'=>50] + $disabledParams);
      
        $html    .= Html::div(Html::div($textArea,['class'=>'col-sm-12']),['class'=>'row']);
      }
    }
    $html = Html::div($html,['id'=>'inspectionNotesSection']);
    return $html;
  }
################################################################################
##########################    FILE STORAGE FUNCTIONS   #########################
################################################################################
//------------------------------------------------------------------------------
  private static function _generateImage($fileName,$data){
    //Remove 'data:image/png;base64,' character data from data URI
    $streamData = substr($data,strpos($data,',')+1);
    //Decode URI to binary data
    $binaryData = base64_decode($streamData);
    
    //Open/Create file and generate image 
    $fp         = fopen($fileName,'w');
    $oData      = fwrite($fp,$binaryData);
    fclose($fp); //Close file
    return $oData;
  }
################################################################################
##########################    HELPER FUNCTIONS    ##############################  
################################################################################
//------------------------------------------------------------------------------
  private static function _reformatToImageLink($fileName){
      return preg_replace('/\/storage\/app\/public\//','/public/storage/',$fileName);
  }
//------------------------------------------------------------------------------
  private static function _fetchSupervisor($r){
    $supervisor = [];
    
    if(!empty($r)){
      $group              = !empty($r) ? preg_replace('/#/','',$r['group1']) : '';
      $supervisor         = !empty($group) ? Elastic::searchMatch(T::$accountView,['wildcard'=>['ownGroup'=>'*' . strtolower($group) . '*']])['hits']['hits'][0]['_source'] : [];
    }
    
    return $supervisor;
  }
//------------------------------------------------------------------------------
  private static function _generateRentalFile($id){
      $dirPrefix = 'Lease_Agreement_Dir_';
      $filePrefix= 'Tenant_Lease_Agreement_';
      return [
          'uuid'   => $dirPrefix . $id,
          'file'   => $filePrefix . $id . '.pdf',
      ];    
  }
//------------------------------------------------------------------------------
  public static function fetchAgreementSupervisor($id){
    $supervisor = [];
    $r = Elastic::searchMatch(T::$creditCheckView,['match'=>['application_id'=>$id]]);
    $r = !empty($r['hits']['hits'][0]['_source']) ? $r['hits']['hits'][0]['_source'] : [];
    
    if(!empty($r)){
      $group      = preg_replace('/#/','',$r['group1']);
      $supervisor = !empty($group) ? Elastic::searchMatch(T::$accountView,['wildcard'=>['ownGroup'=>'*' . strtolower($group) . '*']]) : [];
      $supervisor = !empty($supervisor['hits']['hits'][0]['_source']) ? $supervisor['hits']['hits'][0]['_source'] : [];
    }
    
    return $supervisor;
  }
//------------------------------------------------------------------------------
  public static function isSupervisor($user,$supervisor){
    return !empty($user) && !empty($supervisor) && $user['account_id']  === $supervisor['account_id'] ? 1 : 0;
  }
}
