<?php
namespace App\Http\Controllers\CreditCheck\Agreement;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Library\{Html,V,Account,Helper,HelperMysql,Elastic,Mail,TableName as T,FullBilling};
use App\Http\Controllers\CreditCheck\Agreement\RentalAgreement as Agreement;
use App\Http\Models\{Model,CreditCheckModel as M};

class SignAgreementController extends Controller {
  private $_viewPath      = 'app/CreditCheck/agreement/';
  private $_viewTable     = '';
  private $_dbTable       = '';

  private $_imageHeight = '35';
  private $_imageWidth  = '400';
  //Number of each type of signature / initial type that should be in the agreement form
  private static $_emptyDefault  = 'Not Applicable';
  private static $_verifyKeys    = ['social_security','fname','lname'];
  
  private static $_signatureCounts = [
    'numTenantInitial'   => 14,
    'numTenantSig'       => 15,
    'numAgentSig'        => 6,
    'numLandlordInitial' => 3,
    'numLandlordSig'     => 7,
  ];
  
  //Which checkboxes in the agreement form that are checked by default
  private static $_checkedFields = [
    'electricity_service_' => ['num'=>1],
    'gas_service_' => ['num'=>1],
    'water_service_' => ['num'=>1],
    'garbage_service_' => ['num'=>1],
    'inspection_after_' => ['num'=>1],
    'inspection_prior_' => ['num'=>1],
    'no_infestation_'   => ['num'=>1],
    'disclosed_infestation_' => ['num'=>1],
  ];
  
  private static $_uncheckedFields = [
    'has_hazard_' => ['num'=>1],
    'water_service_day_monday_'    => ['num'=>1],
    'water_service_day_tuesday_'   => ['num'=>1],
    'water_service_day_wednesday_' => ['num'=>1],
    'water_service_day_thursday_'  => ['num'=>1],
    'water_service_day_friday_'    => ['num'=>1],
    'water_service_day_saturday_'  => ['num'=>1],
    'water_service_day_sunday_'    => ['num'=>1],
    'electricity_reimburse_' => ['num'=>1],
    'gas_reimburse_' => ['num'=>1],
    'water_reimburse_' => ['num'=>1],
    'garbage_reimburse_' => ['num'=>1],
  ];
  
  
//------------------------------------------------------------------------------
  public function ___construct(){
    $this->_viewTable = T::$creditCheckView;
    $this->_dbTable   = T::$rentalAgreement;
  }
//------------------------------------------------------------------------------  
  public function index(Request $req){
    $req->merge(['application_id'=>!empty($req['application_id']) ? $req['application_id'] : '0']);
    $req->merge(['foreign_id'=>$req['application_id']]);
    $valid  = V::startValidate([
      'rawReq'    => $req->all(),
      'tablez'    => $this->_getTable(__FUNCTION__),
      'setting'   => $this->_getSetting(__FUNCTION__),
      'validateDatabase' => [
        'mustExist'  => [
          T::$application . '|application_id',
          T::$fileUpload  . '|foreign_id',
          T::$applicationInfo . '|application_id',
        ],
      ]
    ]);
    $vData  = $valid['data'];
    $op     = $valid['op'];
    switch($op){
      default:
        case 'sendEmail': return $this->_sendEmail($vData);
        case 'submitEmail' : return $this->_submitEmail($vData);
        case 'signTemplate': return $this->_createSignTemplate($vData);
        case 'doubleTemplate': return $this->_createDoubleTemplate($vData);
        case 'createAdditionalSigForm' : return $this->_createAdditionalSigForm($vData);
    }
  }
//------------------------------------------------------------------------------  
  public function edit($id, Request $req) {
    $page = $this->_viewPath . 'edit';
    $tntName          = $propAddress = $premises = $supervisorSig = $supervisorInitials = $fullPropAddress = $tntHtml = $group = $tntInitials = '';
    $oldRent          = $newRent = 0;
    $req->merge(['application_id'=>$id]);
    $req->merge(['foreign_id'=>$id]);
    
    if(!($this->_verifyRequest($req->all()))){
      return abort(403);
    }
    
    $valid = V::startValidate([
      'rawReq'    => $req->all(),
      'tablez'    => $this->_getTable(__FUNCTION__),
      'setting'   => $this->_getSetting(__FUNCTION__,$req),
      'includeCdate' => 0,
      'validateDatabase' => [
        'mustExist' => [
          T::$application . '|application_id',
          T::$fileUpload  . '|foreign_id',
          T::$applicationInfo . '|application_id',
        ],
      ],
    ]);
    $defaultValues = $formValues = $additionalFields = [];
    
    //Get and concatenate all the names of every tenant associated with credit check application
    $_getTenants = function($app,$delimiter = ', '){
      $names = '';
      if(!empty($app['application'])){
        $app = $app['application'];
        //Get a formatted version of each tenants name
        $nameList = array_map(function($v) use(&$delimiter){return title_case(trim($v['fname'])) . $delimiter . title_case(trim($v['lname']));},$app);
        $names = implode('; ',$nameList);
      }
      return $names;
    };
    
    //Get every tenants contact information (default is cell and email)
    $_getTenantContact = function($app,$keys=['cell','email']){
      $contacts = array_map(function($v){return [];},array_flip($keys));
      
      if(!empty($app['application'])){
        $app = $app['application'];
        foreach($app as $v){
          foreach($keys as $k){
            $contacts[$k][] = $v[$k];
          }
        }
      }
      
      //Output is formatted as key-value array with the values being arrays
      /*
        @example
       * [
       *  'cell'=> ['111-111-1111','222-222-2222'],
       *  'email'=>['james@gmail.com','john@gmail.com'],
       * ];
       */
      return $contacts;
    };
    
    
    $dbForm   = [];
    
    //Get credit check information from Elastic search
    $r    = Helper::getElasticResult(Elastic::searchMatch(T::$creditCheckView,['match'=>['application_id'=>$id]]));
    $r    = !empty($r) ? $r[0]['_source'] : [];
    
    $checkedFields    = self::$_checkedFields;
    $uncheckedFields  = self::$_uncheckedFields;
    
    $rUnit            = !empty($r['prop']) ? Helper::keyFieldNameElastic(Elastic::searchQuery([
        'index'    => T::$unitView,
        'size'     => 5000,
        '_source'  => ['prop.prop','unit','unit_type'],
        'query'    => [
            'must' => ['prop.prop'=>$r['prop']],
        ]
    ]),['prop.prop','unit']) : [];
    $unitCount        = count($rUnit);
    $unitType         = !empty($rUnit[$r['prop'] . $r['unit']]['unit_type']) ?  $rUnit[$r['prop'] . $r['unit']]['unit_type'] : '';
    if($unitCount > 4 && $unitType !== 'H'){
        $unsetKeys = ['water_service_','garbage_service_','garbage_reimburse_','water_reimburse_'];
        foreach($checkedFields as $k => $v){
            if(in_array($k,$unsetKeys)){
                $uncheckedFields[$k] = $v;
                unset($checkedFields[$k]);
            }
        }
    }
    //Input fields for the agreement along with some optional parameters
    //to set for those types of inputs
    //i.e. Additional classes, placeholders, default values, etc. for HTML input
    $fieldParams      = [
      [
        //Setting for numerical inputs
        'fields'=>Agreement::$numberCounts,
        'params'=>['inputType'=>'text','attr'=>['min'=>'0'],'classes'=>'number-touchspin']
      ],
        //Settings for decimal / money inputs
      [
        'fields'=>Agreement::$moneyCounts,
        'params'=>['classes'=>'decimal','attr'=>['placeholder'=>'$0.00']],
      ],
        //Settings for email inputs
      [
        'fields'=>Agreement::$emailCounts,
        'params'=>['classes'=>'email'],
      ],
        //Settings for telephone inputs
      [
        'fields'=>Agreement::$phoneFields,
        'params'=>['classes'=>'phone'],
      ],
        //Settings for checkbox fields that are CHECKED by default
      [
        'fields'=>$checkedFields,
        'params'=>['inputType'=>'checkbox','checked'=>'checked'],
      ],
      [
        'fields'=>$uncheckedFields,
        'params'=>['inputType'=>'checkbox'],
      ],
        //Settings for datepicker inputs
      [
        'fields'=>Agreement::$dateCounts,
        'params'=>['classes'=>'date']
      ],
    ];
        
    //Get landlord information from the logged in account
    $accountInfo = $req['ACCOUNT'];
    
    //Get landlord's name from the logged in user
    $landlordName     = trim($accountInfo['firstname'] . ' ' . $accountInfo['lastname']);
    
    //Any application info to be used as default values for the agreement
    //will be gathered from the first application from the creditcheck document
    $appInfo     = !empty($r['application']) ? $r['application'][0] : [];    
    
    //Get number of tenants associated with rental
    $numTenants  = !empty($r['application']) ? count($r['application']) : 1; 
    
    if(!empty($appInfo)){
      //Retrieve tenant's name and generate initials
      $tntName      = trim($appInfo['fname'] . ' ' . $appInfo['lname']); 
      $full         = $appInfo['fname'] . ' ' . $appInfo['mname'] . ' ' . $appInfo['lname'];
      $tntInitials  = $this->_generateInitials($full);

      //Get default old and new rent for the rental unit
      $oldRent      = $appInfo['old_rent'];
      $newRent      = $appInfo['new_rent'];
    }
    
    //Get property associated with rental unit
    $property           = !empty($r) ? Elastic::searchMatch(T::$propView,['match'=>['prop'=>$r['prop']]]) : [];
    $property           = !empty($property['hits']['hits']) ? $property['hits']['hits'][0]['_source'] : [];
    
    //Generate tenant name information
    $tntHtml            = $this->_generateTableRows($numTenants,$r);
    //Get credit check group
    $group              = !empty($r) ? preg_replace('/#/','',$r['group1']) : '';
    $groupR             = !empty($group) ? Helper::getElasticResult(Elastic::searchMatch(T::$groupView,['match'=>['prop'=>$group]])) : [];
    $groupR             = !empty($groupR) ? $groupR[0]['_source'] : $groupR;
    //Get supervisor (user who is the OWNER of the group)
    $supervisor         = !empty($group) ? Elastic::searchMatch(T::$accountView,['wildcard'=>['ownGroup'=>'*' . strtolower($group) . '*']]) : [];
    $supervisor         = !empty($supervisor['hits']['hits']) ? $supervisor['hits']['hits'][0]['_source'] : [];
   
    $supervisorStreet   = !empty($groupR) ? implode(', ',Helper::selectData(['street','city','state','zip'],$groupR)) : '';
    //Retrieve supervisor's name and initials
    $supervisorSig      = !empty($supervisor) ? title_case($supervisor['firstname']) . ' ' . title_case($supervisor['lastname']) : '';
    $supervisorInitials = !empty($supervisor) ? $this->_generateInitials(implode(' ',[$supervisor['firstname'],$supervisor['middlename'],$supervisor['lastname']])) : '';
    
    //Determine if logged-in user is the supervisor
    $supervisor['account_id'] = isset($supervisor['account_id']) ? $supervisor['account_id'] : '';
    $isSupervisor = $accountInfo['account_id'] === $supervisor['account_id'];
    
    //Generate default values for supervisor input HTML template
    $agentParams  = ['manager'=>$landlordName,'supervisor'=>$supervisorSig];
    
    //Generate default values for certain inputs the web form
    //A single value can belong to multiple inputs as they represent the same information
    //(Tenant Print Name)
    //This can be seen with the key_prefix (the array keys) of the $formValues variable

    //Get initial total cost
    $deposit     = !empty($r['sec_deposit']) ? $r['sec_deposit'] : 0;
    $totalCost   = $deposit + (!empty($r['new_rent']) ? $r['new_rent'] : 0);

    $mgt         = !empty($property) ? $property['mangtgroup'] : '';
    
    $companyR    = Helper::getElasticResult(Elastic::searchQuery([
        'index'     => T::$companyView,
        'size'      => 1,
        'query'     => [
            'must'   => [
                'match' => [
                    'company_code' => preg_replace('/\*/','',$mgt)
                ]
            ]
        ]
    ]));
    $companyR         = !empty($companyR) ? $companyR[0]['_source'] : [];
    $companyName      = !empty($companyR['company_name']) ? title_case($companyR['company_name']) : 'Pama Management';
    
    $newRent          = !empty($r['new_rent']) ? $r['new_rent'] : 0;
    $applicationUnit  = M::getUnitElastic(['prop.prop.keyword'=>$r['prop'],'unit.keyword'=>$r['unit']],['prop','unit','street']);
    $unitProp         = Helper::getValue(0,Helper::getValue(T::$prop,$applicationUnit,[]),[]);
    $unitStreet       = Helper::getValue('street',$applicationUnit);
    //$premises         = !empty($r) ? implode(', ',[$r['street'],$r['city'],$r['state'],$r['zip']])  : self::$_emptyDefault;
    $premises         = !empty($r) ? implode(', ',[$unitStreet,$unitProp['city'],$unitProp['state'],$unitProp['zip']]) : self::$_emptyDefault;
    $fullPropAddress  = !empty($r['prop']) && !empty($r['unit']) ? 'Property #: ' . $r['prop'] . ' and Unit #: ' . $r['unit'] : self::$_emptyDefault;
    $fullPropAddress  = !empty($property) ? implode(', ',array_merge([$unitStreet],array_values(Helper::selectData(['city','state','zip'],$property)))) . '. ' . $fullPropAddress : $fullPropAddress;
    $propAddress      = !empty($r) ? implode(', ',[$unitStreet,$r['city'],$r['state'],$r['zip']]) : self::$_emptyDefault;
    
    $noticeAddress    = !empty($companyR) ? implode(', ',Helper::selectData(['street','city','state','zip'],$companyR)) : self::$_emptyDefault;
    $prop        = !empty($r['prop']) ? $r['prop'] : '';
    $unit        = !empty($r['unit']) ? $r['unit'] : '';
    //Get converted manager name from Elastic search
    $paymentsTo  = implode(', ',[$companyName,'Property #: ' . $prop,'Unit #: ' . $unit]);
    //Get all tenants' contact information
    $contactInfo = $_getTenantContact($r);
 
    //Set form's default values
    $formValues  = [
      'prop_no_'                   => $prop,
      'unit_no_'                   => $unit,
      'premises_'                  => $premises,
      'sec_dep_'                   => $deposit,
      'tenant_print_'              => explode('; ',$_getTenants($r,' ')),
      'tenant_phone_'              => !empty($contactInfo['cell']) ? $contactInfo['cell'] : '',
      'tenant_email_'              => !empty($contactInfo['email']) ? $contactInfo['email'] : '',
      'prop_address_'              => $propAddress,
      'full_prop_address_'         => $fullPropAddress,
      'monthly_rent_'              => Helper::getValue('new_rent',$r,0),
      'next_month_rent_'           => Helper::getValue('new_rent',$r,0),
      'total_cost_'                => $totalCost,
      'num_occupants_'             => $numTenants,
      'payments_to_'               => $paymentsTo,
      'tenant_lists_'              => $_getTenants($r),
      'management_name_'           => $companyName,
      'agent_print_'               => $supervisorSig,
      'attn_'                      => $supervisorSig,
      'rental_begin_date_'         => date('m/d/Y'),
      'move_in_date_'              => date('m/d/Y'),
      'move_out_date_'             => '12/31/9999',
      'premise_keys_'              => 1,
      'mailbox_keys_'              => 1,
      'payment_phone_number_'      => Helper::getValue('phone',$companyR,self::$_emptyDefault),
      'rent_payment_address_'      => title_case($noticeAddress),
      'hoa_days_'                  => 30,
      'notice_address_'            => title_case($noticeAddress),
      'notice_local_address_'      => title_case($supervisorStreet),
      'pets_cost_'                 => 300,
      'water_service_hour_from_'   => '6:00 AM',
      'water_service_hour_to_'     => '10:00 PM',
      'water_fixture_name_'        => $paymentsTo,
      'water_fixture_address_'     => title_case($noticeAddress),
      'water_fixture_phone_'       => !empty($supervisor['cellphone']) ? $supervisor['cellphone'] : self::$_emptyDefault,
      'submeter_name_'             => $paymentsTo,
      'submeter_address_'          => title_case($noticeAddress),
      'submeter_phone_'            => !empty($supervisor['cellphone']) ? $supervisor['cellphone'] : self::$_emptyDefault,
      'water_service_bill_day_'    => 1,
    ];
    //Parameters for landlord initial inputs, landlord signature inputs, and tenant initial inputs
    //Format of parameter
    /*
     * @example
     *      'num'=>4, //Number of inputs related to this field in the form
     *      'val'=>'ABC', //Default value of input
     *      'isFont'=>false, //Allows for font of input to be toggled
     *      'isSignature'=>false,//If input is a signature input and therefore needs additional CSS classes
     *      'classes'=>'col-sm-6',//Additional CSS classes to add to the input
     */    
    $prefixes = [
      'landlord_initial_'    => ['num'=>self::$_signatureCounts['numLandlordInitial'],'val'=>$supervisorInitials,'isSignature'=>true,'readonly'=>'1'],
      'landlord_signature_'  => ['num'=>self::$_signatureCounts['numLandlordSig'],'val'=>$companyName,'isSignature'=>true,'readonly'=>'1'],
      'tnt_initial_'         => ['num'=>self::$_signatureCounts['numTenantInitial'],'val'=>'','useFont'=>true,'isSignature'=>true,'classes'=>'tnt-initials'],
    ];

    foreach(Agreement::$fieldCounts as $k => $v){
      //Generate input HTML generating parameters for generic input text fields
      $entry                = ['num'=>$v['num'],'val'=>isset($formValues[$k]) ? $formValues[$k] : self::$_emptyDefault,'classes' => isset(Agreement::$fieldClasses[$k]) ? Agreement::$fieldClasses[$k] : ''];
      $entry               += !empty($v['readonly']) ? ['readonly'=>$v['readonly']] : [];
      $additionalFields[$k] = $entry;
    }
    
    
    //Generate input HTML generate paramters for the other types of inputs specified in $fieldParams
    foreach($fieldParams as $v){
      foreach($v['fields'] as $k=>$val){
        $classes                = !empty($v['params']['classes']) ? $v['params']['classes'] : '';
        $addClass               = !empty(Agreement::$fieldClasses[$k]) ? Agreement::$fieldClasses[$k] : $classes;
        $v['params']['classes'] = $addClass;
        $entry                  = ['num'=>$val['num'],'val'=>isset($formValues[$k]) ? $formValues[$k] : self::$_emptyDefault] + $v['params'];
        $entry                 += !empty($val['readonly']) ? ['readonly'=>$val['readonly']] : [];
        
        $additionalFields[$k] = $entry;
      }
    }
   
    //Array to function-call mapping
    $fieldInfo = [
      ['fields'=>Agreement::$radioFields,'method'=>'_generateRadioGroup'],
      ['fields'=>Agreement::$textBoxCounts,'method'=>'_generateTextBox'],
      ['fields'=>Agreement::$tenantFields,'method'=>'_generateTenantField'],
      ['fields'=>array_merge($additionalFields,$prefixes),'method'=>'_generateSigningOptions']
    ];

    foreach($fieldInfo as $f){
      foreach($f['fields'] as $k => $v){
        $arguments = [$k,$v,$dbForm]; //Default HTML generating arguments (key,value,defaultData)
        if($f['method'] === '_generateTextBox'){
          $val       = isset($formValues[$k]) ? $formValues[$k] : self::$_emptyDefault; //If empty make default value for textbox N/A
          $cls       = isset(Agreement::$fieldClasses[$k]) ? Agreement::$fieldClasses[$k] : ''; //Get HTML classes
          $arguments = [$k,$v,$val,$cls,$dbForm];
        } else if($f['method'] === '_generateTenantField'){
          $arguments = [$k,$numTenants,$v,$formValues[$k],$dbForm];//Include number of tenants when generating tenant related input HTML
        }
        //Generate HTML
        $defaultValues[$k] = call_user_func_array([$this,$f['method']],$arguments);
      }
    }
    
    //Generate Tenant Signature HTML inputs (includes hidden inputs and JSignature buttons)
    $defaultValues['tnt_signature_']   = $this->_generateTenantSignatureInpt('tnt_signature_', self::$_signatureCounts['numTenantSig'], $numTenants,$dbForm);
    
    //Generate Supervisor/Agent Signature HTML inputs
    $defaultValues['agent_signature_'] = $this->_generateAgentSignatures('agent_signature_',$isSupervisor, self::$_signatureCounts['numAgentSig'], $agentParams,$dbForm);
    
    //Add Inspection Notes HTML with Checkboxes and Textareas for notes
    $inspectionNotes                   = Agreement::generateInspectionNotes($dbForm);
    
    //Submission parameters
    $btn                               = Html::div(Html::button('Agree to Rental',['class'=>'btn btn-danger btn-lg reportDoc','title'=>'Accept Agreement','type'=>'button','data-type'=>'AgreementReport']),['class'=>'text-center']);

    //Render view with parameters
    return view($page,['data'=>[
        'nav'            => $req['NAV'],
        'account'        => Account::getHtmlInfo($req['ACCOUNT']),
        'application_id' => $id,
        'tnt_inputs'     => $tntHtml,
        'hiddenInputs'   => $this->_generateHiddenInputs($id),
        'defaultValues'  => $defaultValues,
        'inspectionNotes'=> $inspectionNotes,
        'toggleSig'      => $this->_generateToggleNav($r),
        'toolbar'        => $this->_generateDownloadBar(),
        'occupantForm'   => $this->_generateOccupantInput(),
        'prorate'        => $this->_getProrateSection($r),
        'submit'         => $btn,
      ]
    ]);
  }
//------------------------------------------------------------------------------
/*
 * @desc PUT Method is used for processing signatures submitted by the user using
 *  a text input or JSignature
 */  
  public function update($id, Request $req){
    $response = [];
    $formId   = !empty($req['formId']) ? $req['formId'] : 'initialForm';
    //Get form processing rules
    $settings = $this->_getSetting(__FUNCTION__ . $formId);
    $keys = ['initial_tenant_initials','initial_tenant_signature'];
    
    foreach($keys as $k){
      $adjustedKey = !empty($req['formIndex']) ? $k . '_' . $req['formIndex'] : $k;
      //Adjust rules if the JSignature pads are used instead of text inputs
      $settings['rule'][$adjustedKey] = !empty($req[$adjustedKey . '_hiddenSig']) ? 'nullable|string' : $settings['rule'][$k];
    }

    try {
      //Validate inputs
      $valid = V::startValidate([
        'rawReq' => $req->all(),
        'setting'=> $settings,
      ]);
    
      $response['mainMsg'] = $this->_getSuccessMsg(__FUNCTION__);
    } catch (Exception $e) {
      $response['error']['mainMsg'] = $this->_getErrorMsg(__FUNCTION__);
    }
    
    return $response;
  }
//------------------------------------------------------------------------------
/*
 * @desc Used for processing form submissions and generating Rental Agreement PDF Code
 */  
  public function store(Request $req){
    
    $appId    = isset($req['application_id']) ? $req['application_id'] : 0;
    $req->merge(['foreign_id'=>$req['application_id']]);
    $r        = Helper::getElasticResult(Elastic::searchMatch($this->_viewTable,['match'=>['application_id'=>$appId]]));
    $r        = !empty($r) ? $r[0]['_source'] : [];
    unset($req['limit']);
    
    $op       = !empty($req['op']) ? $req['op'] : '';
    
    //Validate
    $valid = V::startValidate([
      'rawReq' =>$req->all(),
      'tablez' =>$this->_getTable(__FUNCTION__),
      'setting'=>$this->_getSetting(__FUNCTION__ . $op,$req,$r),
      'validateDatabase' => [
        'mustExist' => [
          T::$application . '|application_id',
          T::$fileUpload  . '|foreign_id',
          T::$applicationInfo . '|application_id',
        ],
      ],
    ]);
    $op    = $valid['op'];
    $vData = $valid['data'];
    
    switch($op){
        case 'AgreementReport' : return $this->_submitRentalAgreement($vData,$req);
        case 'preview'         : return $this->_generatePreviewPdf($vData,$req);
    }
  }
//------------------------------------------------------------------------------
  private function _submitRentalAgreement($vData,$req=[]){
    $response         = $elastic = $updateData = $success = [];
    $appId            = $vData['application_id'];
    //Get rental agreement from database if application_id exists
    //$row              = M::getRentalAgreement(Model::buildWhere(['application_id'=>$appId]));
    $oldApp           = M::getTableData(T::$application,Model::buildWhere(['application_id'=>$appId]),['application_id','raw_agreement'],1);
    $oldContent       = !empty($oldApp['raw_agreement']) ? json_decode($oldApp['raw_agreement'],true) : [];
    $oldUuid          = !empty($oldContent['uuid']) ? $oldContent['uuid'] : '';
    $row              = !empty($oldUuid) ? M::getApplicationUpload(Model::buildWhere(['foreign_id'=>$appId,'uuid'=>$oldUuid,'type'=>'agreement'])) : [];
    //Get associated supervisor
    $supervisor       = Agreement::fetchAgreementSupervisor($appId);
    $supervisorId     = !empty($supervisor['account_id']) ? $supervisor['account_id'] : 0;    
    //Determine if logged user is the supervisor
    $isSupervisor     = Agreement::isSupervisor(!empty($req['ACCOUNT']) ? $req['ACCOUNT'] : [],$supervisor);
    
    //Add supervisor information to the validated data
    $vData           += ['account_first_name' => !empty($supervisor) ? $supervisor['firstname'] : 'PAMA_MANAGEMENT','isSupervisor'=>$isSupervisor];
    
    //Generate PDF HTML
    $pdfContent       = Agreement::generatePdfChunks($vData);
    
    $insertData = [
      'application_id' => $appId,
      'account_id'     => $supervisorId,
      'isApprove'      => $isSupervisor,
      'agreement'      => $pdfContent,
    ];
    
    $linkData              = Agreement::generatePdfFile($insertData);
    $link                  = $linkData['link'];
    $usid                  = Helper::getUsid($req);
 
    $dbInsertData       = [
      'foreign_id'     => $appId,
      'name'           => $linkData['fileName'],
      'file'           => $linkData['fileName'],
      'ext'            => 'pdf',
      'uuid'           => $linkData['uuid'],
      'path'           => $linkData['fileUploadLink'],
      'type'           => 'agreement',
      'usid'           => $usid,
      'cdate'          => Helper::mysqlDate(),
      'active'         => 1
    ];
    $updateData         = [
      T::$application  => [
          'whereData'   => ['application_id'=>$appId],
          'updateData'  => ['raw_agreement' =>$pdfContent,'is_upload_agreement'=>1],
      ],
    ];
    
    if(!empty($row)){
        $updateData[T::$fileUpload] = [
            'whereData'   => ['foreign_id'=>$appId,'type'=>'agreement'],
            'updateData'  => [  
                'usid'          => $usid,
                'active'        => 1,
                'name'          => $linkData['fileName'],
                'file'          => $linkData['fileName'],
                'ext'           => 'pdf',
                'path'          => $linkData['fileUploadLink'],
                'uuid'          => $linkData['uuid']
            ]
        ];
    }
    ############### DATABASE SECTION ######################
    DB::beginTransaction();
    try {
      $success          += empty($row) ? Model::insert([T::$fileUpload=>$dbInsertData]) : [];
      $success          += Model::update($updateData);
        
      $elastic           = [
          'insert'   => [
            T::$creditCheckView => ['a.application_id'=>[$appId]],
          ]
      ];
      Model::commit([
        'success'=>$success,
        'elastic'=>$elastic,
      ]);
      
      $redirectLink             = action('CreditCheck\CreditCheckController@index',['application_id'=>$appId]);
      $response['link']         = $link;
      $response['contentMsg']   = 'Your agreement has been submitted for approval';
      $response['redirectLink'] = $redirectLink;
    } catch (Exception $e) {
      $response['error']['mainMsg'] = Model::rollback($e);
    }
    return $response;
  }
//------------------------------------------------------------------------------
  private function _generatePreviewPdf($vData,$req=[]){
    $response         = $elastic = $updateData = $success = [];
    $appId            = $vData['application_id'];
    //Get rental agreement from database if application_id exists
    
    //Get associated supervisor
    $supervisor       = Agreement::fetchAgreementSupervisor($appId);
    $supervisorId     = !empty($supervisor['account_id']) ? $supervisor['account_id'] : 0;    
    //Determine if logged user is the supervisor
    $isSupervisor     = Agreement::isSupervisor(!empty($req['ACCOUNT']) ? $req['ACCOUNT'] : [],$supervisor);
    
    //Add supervisor information to the validated data
    $vData           += ['account_first_name' => !empty($supervisor) ? $supervisor['firstname'] : 'PAMA_MANAGEMENT','isSupervisor'=>$isSupervisor];
    
    try {
        //Generate PDF HTML
        $pdfContent       = Agreement::generatePdfChunks($vData);

        $insertData = [
          'application_id' => $appId,
          'account_id'     => $supervisorId,
          'isApprove'      => $isSupervisor,
          'agreement'      => $pdfContent,
        ];

        $linkData              = Agreement::generatePdfFile($insertData);
        $link                  = $linkData['link'];
        
        $response['mainMsg']   = $this->_getSuccessMsg(__FUNCTION__);
        $response['link']      = $link;
    } catch (Exception $e) {
        $response['error']['mainMsg'] = $this->_getErrorMsg(__FUNCTION__);
    }
    return $response;
  }
//------------------------------------------------------------------------------
/**
   * @desc Generate html for a signature modal which includes:
    * Text box to enter signature
    * Select box to toggle the font
    * An optional button to replace the text with an image generated from jSignature
   * @params {array}  $vData: Request Data
   * @return {array}
   */
   private function _createSignTemplate($vData){
    $signatureType = $vData['signatureType']; //Is tenant signature or tenant initials
    $appId         = $vData['application_id'];//CreditCheck application_id

    //Get tenant information from Elastic search
    $r    = Helper::getElasticResult(Elastic::searchMatch($this->_viewTable,['match'=>['application_id'=>$appId]]));
    $r    = !empty($r) ? $r[0]['_source'] : [];  

    $tntName          = $inputHtml = $tntInitials = '';
    $appInfo = !(empty($r['application'])) ? $r['application'][0] : [];
    if(!empty($appInfo)){
      //Retrieve tenant's name and generate initials
      $tntName      = trim($appInfo['fname'] . ' ' . $appInfo['lname']); 
      $full         = $appInfo['fname'] . ' ' . $appInfo['mname'] . ' ' . $appInfo['lname'];
      $tntInitials  = $this->_generateInitials($full);
    }

    $initialValues = [
      'tenant_initial'   => $tntInitials,
      'tenant_signature' => $tntName,
    ];

    $name      = $signatureType . '_all'; //Name or id of the input element

    $inputOpts = [
      'type'  => 'text',
      'class' => 'signature ',
      'name'  => $name,
      'id'    => $name,
    ];

    $innerDiv1         = Html::input(isset($initialValues[$signatureType]) ? $initialValues[$signatureType] : '',$inputOpts);
    $innerDiv1        .= Html::img(['src'=>'','id'=>$name . '_sigImg','name'=>$name.'_sigImg','style'=>'display:none','class'=>'signature-img ','width'=>$this->_imgWidth,'height'=>$this->_imgHeight]);
    $innerDiv1        .= Html::input('',['type'=>'hidden','class'=>'signature-img-data ','name'=>$name.'_hiddenSig']);
    $innerDiv1        .= Html::input('',['type'=>'hidden','class'=>'signature-txt-data ','name'=>$name]);
    
    $innerLabel1       = Html::div(Html::label('Signature',['class'=>'control-label','style'=>'text-align:center;']),['class'=>'col-sm-6']);
    $innerLabel2       = Html::div(Html::label('Style',['class'=>'control-label','style'=>'text-align:center;']),['class'=>'col-sm-6']);
    $label1            = Html::div(Html::div($innerLabel1 . $innerLabel2,['class'=>'row']),['class'=>'col-sm-6']);
    $label2            = Html::div(Html::div(Html::div(Html::label('Click to Apply Signature',['class'=>'control-label','style'=>'text-align:center;']),['class'=>'col-sm-6 col-sm-offset-3']),['class'=>'row']),['class'=>'col-sm-6']);
    $labelRow          = Html::div($label1 . $label2,['class'=>'row']);
    $fontName          = $name . '_fonts'; //Name of dropdown menu for the corresponding input element
    $selectHtml        = $this->_generateFontOptions($fontName,'',true); //Generate font toggler

    $innerDiv2         = Html::div($selectHtml,['class'=>'col-sm-6']);
    $div1              = Html::div(Html::div(Html::div($innerDiv1,['class'=>'col-sm-6']) . $innerDiv2,['class'=>'row']),['class'=>'col-sm-6']);
    $div2              = '&nbsp;&nbsp;' . Html::button('Use Signature Pad ' . Html::i('',['class'=>'fa fa-pencil','title'=>'Use Signature Pad']),['class'=>'sign-prompt']);
    $div2             .= Html::button(Html::i('&nbsp;Type',['class'=>'fa fa-keyboard-o']),['class'=>'keyboard-prompt','style'=>'display:none;']);
    $div2              = Html::div(Html::div(Html::div($div2,['class'=>'col-sm-6 col-sm-offset-3']),['class'=>'row']),['class'=>'col-sm-6']);
    
    $inputHtml    = Html::div($labelRow . Html::div($div1 . $div2,['class'=>'row']),['class'=>'sign-window-block']);
    $html = $tabs = $tabContent = '';
    $tabs .= Html::li(Html::a('Type Manually',['href'=>'#tab_1','data-toggle'=>'tab']),['class'=>'active']);
    $tabs .= Html::li(Html::a('Sign Electronically',['href'=>'#tab_2','data-toggle'=>'tab']));
    $tabs  = Html::ul($tabs,['class'=>'nav nav-tabs']);
    
    $tabContent .= Html::div($inputHtml,['id'=>'tab_1','class'=>'tab-pane active']);
    $tabContent .= Html::div('Sign with Pad',['id'=>'tab_2','class'=>'tab-pane']);
    $tabContent  = Html::div($tabContent,['class'=>'tab-content']);
    
    $html        = Html::div($tabs .$tabContent,['class'=>'nav-tabs-custom']);
    return [
     'html'=>$html,
    ];
   }
#################################################################################
########################## FORM PROCESSING METHODS   ############################
#################################################################################
   private function _getTable($fn){
     $tablez  = [
       'store'   => [T::$application,T::$fileUpload,T::$applicationInfo],
       'show'    => [T::$application,T::$fileUpload,T::$applicationInfo],
       'edit'    => [T::$application,T::$fileUpload,T::$applicationInfo],
       'update'  => [T::$application,T::$fileUpload,T::$applicationInfo],
       'index'   => [T::$application,T::$fileUpload,T::$applicationInfo],
     ];
     return $tablez[$fn];
   }
//------------------------------------------------------------------------------
   private function _getSetting($fn,$req=[],$default=[]){
     $perm    = Helper::getPermission($req);
     $setting = [
       'index' => [
         'field' => [
           
         ],
         'rule'  => [
           'email'          => 'nullable|string',
           'application_id' => 'required|string',
           'formsLeft'      => 'nullable|integer',
           'index'          => 'nullable|integer',
           'link'           => 'nullable',
         ],
       ],
       'edit'  => [
         'field'=> [
           
         ],
         'rule' => [
           'application_id' => 'required|string',
           'foreign_id'     => 'required|string',
         ],
       ],
       'show'  => [
         'field' => [
           
         ],
       ],
       'updateesignatureForm'        => [
           'rule'      => [
               'formIndex'                             => 'nullable|string',
               'formId'                                => 'required|string',
               'initial_tenant_initials'               => 'nullable|string',
               'initial_tenant_signature'              => 'nullable|string',
               'initial_tenant_initials_hiddenSig'     => 'required|string',
               'initial_tenant_signature_hiddenSig'    => 'required|string',
               'font_toggle'                           => 'nullable|string',
           ],
       ],
       'updateinitialForm'           =>[
         'rule'=>[
           'formIndex'                          => 'nullable|string',
           'initial_tenant_initials'            => 'required|string',
           'formId'                             => 'required|string',
           'font_toggle'                        => 'nullable|string',
           'initial_tenant_signature'           => 'required|string',
         ]
       ],
       'storepreview' => [
          'rule' => [
            'provide_pets_'     => 'nullable',
            'tnt-initials_font' => 'nullable|string',
            'application_id'    => 'nullable',
            'occupants'         => 'nullable|string',
            'tnt_initials_font' => 'nullable|string',
            'tnt-sig_font'      => 'nullable|string',
            'tenant_lease_full_name' => 'nullable|string',
            'tnt_initials_font' => 'nullable|string',
            'management_name_'  => 'nullable|string',
            'contains_paint_record_' => 'nullable',
            'property_additonal_info_' => 'nullable',
            'lead_paint_knowledge' => 'nullable',
            'prop_no_'          => 'nullable',
            'inspection_date_'  => 'nullable',
            'unit_no_'          => 'nullable',
            'num_occupants_'     => 'nullable',
            'rental_begin_date_'    =>'nullable',
            'monthly_rent_'         =>'nullable',
            'payments_to_'          =>'nullable|string',
            'payment_phone_number_' =>'nullable|string',
            'rent_payment_address_' =>'nullable|string',
            'sec_dep_'              =>'nullable',
            'total_cost_'           =>'nullable',
            'premise_keys_'        =>'nullable',
            'mailbox_keys_'        =>'nullable',
            'attn_'                =>'nullable',
            'tenant_concessions_'    =>'nullable',
            'hoa_days_'              =>'nullable',
            'landlord_initial_'    =>'nullable',
            'full_prop_address_'   =>'nullable',
            'landlord_signature_'  =>'nullable',
            'prop_address_'       =>'nullable',
            'move_in_date_'        =>'nullable',
            'move_out_date_'       =>'nullable',
            'agent_signature_'     =>'nullable',
            'agent_print'         =>'nullable|string',
            'agent_title'         =>'nullable|string',
            'manager_signature'   =>'nullable|string',
            'manager_print'       =>'nullable|string',
            'has_paint_record_'    =>'nullable',
            'no_paint_record_'    =>'nullable',
            'include_storage_'    =>'nullable',
            'not_include_storage_'    =>'nullable',
            'include_parking_'       =>'nullable',
            'not_include_parking_'   =>'nullable',
            'provide_parking_'       =>'nullable',
             'provide_storage_'      =>'nullable',
            'known_lead_paint_'      =>'nullable',
            'not_known_lead_paint_'  =>'nullable',
            'included_properties_'   =>'nullable',
            'disclosed_infestation_' =>'nullable',
            'disclosed_infestations_'=>'nullable',
            'infestation_'           =>'nullable',
            'tenant_lists_'          =>'nullable',
            'other_notes_'           =>'nullable',
            'next_month_rent_'       =>'nullable|numeric',
            'garage_cost_'           =>'nullable',
            'trash_cost_'            =>'nullable',
            'water_cost_'            =>'nullable',
            'pets_cost_'             =>'nullable',
            'parking_cost_'          =>'nullable',
            'storage_cost_'          =>'nullable',
            'other_cost_'            =>'nullable',
            'pool_cost_'             =>'nullable',
            'replace_appliances_'  =>'nullable',
            'included_pets_'       =>'nullable',
            'notice_address_'      =>'nullable',
            'notice_local_address_' =>'nullable',
            'premises_' =>'nullable',
            'rent_payment_address_' =>'nullable',
            'sec_dep_return_address_' =>'nullable',
            'other_service_' =>'nullable',
            'other_reimburse_' =>'nullable',
            'hoa_name_' =>'nullable',
            'other_addenda_' =>'nullable',
            'pool_pay_to_'     =>'nullable',
            'electricity_service_' =>'nullable',
            'gas_service_' =>'nullable',
            'water_service_' =>'nullable',
            'garbage_service_' =>'nullable',
            'electricity_reimburse_' =>'nullable',
            'gas_reimburse_' =>'nullable',
            'water_reimburse_' =>'nullable',
            'garbage_reimburse_' =>'nullable',
            'inspection_done_'   => 'nullable',
            'payment_phone_number_' =>'nullable',
            'pool_vendor_phone_'    =>'nullable',
            'has_hazard_'           =>'nullable',
            'lead_paint_knowledge_' => 'nullable',
            'front_yard_inspect__landscaping_movein'=> 'nullable',
            'front_yard_inspect__landscaping_move_in_notes' => 'nullable',
            'front_yard_inspect__landscaping_moveout' => 'nullable',
            'front_yard_inspect__landscaping_move_out_notes'=> 'nullable',
            'front_yard_inspect__fences_movein' => 'nullable',
            'front_yard_inspect__fences_move_in_notes'=> 'nullable',
            'front_yard_inspect__fences_moveout'=> 'nullable',
            'front_yard_inspect__fences_move_out_notes' => 'nullable',
            'front_yard_inspect__sprinklers_movein' => 'nullable',
            'front_yard_inspect__sprinklers_move_in_notes'=> 'nullable',
            'front_yard_inspect__sprinklers_moveout'=> 'nullable',
            'front_yard_inspect__sprinklers_move_out_notes' => 'nullable',
            'front_yard_inspect__walks_movein'=> 'nullable',
            'front_yard_inspect__walks_move_in_notes'=> 'nullable',
            'front_yard_inspect__walks_moveout' => 'nullable',
            'front_yard_inspect__walks_move_out_notes'=> 'nullable',
            'front_yard_inspect__porches_movein'=> 'nullable',
            'front_yard_inspect__porches_move_in_notes'=> 'nullable',
            'front_yard_inspect__porches_moveout' => 'nullable',
            'front_yard_inspect__porches_move_out_notes'=> 'nullable',
            'front_yard_inspect__mailbox_movein'=> 'nullable',
            'front_yard_inspect__mailbox_move_in_notes'=> 'nullable',
            'front_yard_inspect__mailbox_moveout' => 'nullable',
            'front_yard_inspect__mailbox_move_out_notes'=> 'nullable',
            'front_yard_inspect__light_movein'=> 'nullable',
            'front_yard_inspect__light_move_in_notes'=> 'nullable',
            'front_yard_inspect__light_moveout'=> 'nullable',
            'front_yard_inspect__light_move_out_notes'=> 'nullable',
            'front_yard_inspect__exterior_movein'=> 'nullable',
            'front_yard_inspect__exterior_move_in_notes'=> 'nullable',
            'front_yard_inspect__exterior_moveout'=> 'nullable',
            'front_yard_inspect__exterior_move_out_notes'=> 'nullable',
            'front_yard_inspect__generalNotes'=> 'nullable',
            'entry_security_movein'=> 'nullable',
            'entry_security_move_in_notes'=> 'nullable',
            'entry_security_moveout'=> 'nullable',
            'entry_security_move_out_notes'=> 'nullable',
            'entry_doors_movein'=> 'nullable',
            'entry_doors_move_in_notes'=> 'nullable',
            'entry_doors_moveout'=> 'nullable',
            'entry_doors_move_out_notes'=> 'nullable',
            'entry_flooring_movein'=> 'nullable',
            'entry_flooring_move_in_notes'=> 'nullable',
            'entry_flooring_moveout'=> 'nullable',
            'entry_flooring_move_out_notes'=> 'nullable',
            'entry_light_movein'=> 'nullable',
            'entry_light_move_in_notes'=> 'nullable',
            'entry_light_moveout'=> 'nullable',
            'entry_light_move_out_notes'=> 'nullable',
            'entry_switches_movein'=> 'nullable',
            'entry_switches_move_in_notes'=> 'nullable',
            'entry_switches_moveout'=> 'nullable',
            'entry_switches_move_out_notes'=> 'nullable',
            'entry_generalNotes'=> 'nullable',
            'living_room_doors_movein'=> 'nullable',
            'living_room_doors_move_in_notes'=> 'nullable',
            'living_room_doors_moveout'=> 'nullable',
            'living_room_doors_move_out_notes'=> 'nullable',
            'living_room_flooring_movein'=> 'nullable',
            'living_room_flooring_move_in_notes'=> 'nullable',
            'living_room_flooring_moveout'=> 'nullable',
            'living_room_flooring_move_out_notes'=> 'nullable',
            'living_room_walls_movein'=> 'nullable',
            'living_room_walls_move_in_notes'=> 'nullable',
            'living_room_walls_moveout'=> 'nullable',
            'living_room_walls_move_out_notes'=> 'nullable',
            'living_room_screens_movein'=> 'nullable',
            'living_room_screens_move_in_notes'=> 'nullable',
            'living_room_screens_moveout'=> 'nullable',
            'living_room_screens_move_out_notes'=> 'nullable',
            'living_room_light_movein'=> 'nullable',
            'living_room_light_move_in_notes'=> 'nullable',
            'living_room_light_moveout'=> 'nullable',
            'living_room_light_move_out_notes'=> 'nullable',
            'living_room_switches_movein'=> 'nullable',
            'living_room_switches_move_in_notes'=> 'nullable',
            'living_room_switches_moveout'=> 'nullable',
            'living_room_switches_move_out_notes'=> 'nullable',
            'living_room_fireplace_movein'=> 'nullable',
            'living_room_fireplace_move_in_notes'=> 'nullable',
            'living_room_fireplace_moveout'=> 'nullable',
            'living_room_fireplace_move_out_notes'=> 'nullable',
            'living_room_generalNotes'=> 'nullable',
            'other_room_description'=> 'nullable',
            'other_room_doors_movein'=> 'nullable',
            'other_room_doors_move_in_notes'=> 'nullable',
            'other_room_doors_moveout'=> 'nullable',
            'other_room_doors_move_out_notes'=> 'nullable',
            'other_room_flooring_movein'=> 'nullable',
            'other_room_flooring_move_in_notes'=> 'nullable',
            'other_room_flooring_moveout'=> 'nullable',
            'other_room_flooring_move_out_notes'=> 'nullable',
            'other_room_walls_movein'=> 'nullable',
            'other_room_walls_move_in_notes'=> 'nullable',
            'other_room_walls_moveout'=> 'nullable',
            'other_room_walls_move_out_notes'=> 'nullable',
            'other_room_windows_movein'=> 'nullable',
            'other_room_windows_move_in_notes'=> 'nullable',
            'other_room_windows_moveout'=> 'nullable',
            'other_room_windows_move_out_notes'=> 'nullable',
            'other_room_light_movein'=> 'nullable',
            'other_room_light_move_in_notes'=> 'nullable',
            'other_room_light_moveout'=> 'nullable',
            'other_room_light_move_out_notes'=> 'nullable',
            'other_room_switches_movein'=> 'nullable',
            'other_room_switches_move_in_notes'=> 'nullable',
            'other_room_switches_moveout'=> 'nullable',
            'other_room_switches_move_out_notes'=> 'nullable',
            'other_room_generalNotes'=> 'nullable',
            'bedrooms_description'=> 'nullable',
            'bedrooms_doors_movein'=> 'nullable',
            'bedrooms_doors_move_in_notes'=> 'nullable',
            'bedrooms_doors_moveout'=> 'nullable',
            'bedrooms_doors_move_out_notes'=> 'nullable',
            'bedrooms_flooring_movein'=> 'nullable',
            'bedrooms_flooring_move_in_notes'=> 'nullable',
            'bedrooms_flooring_moveout'=> 'nullable',
            'bedrooms_flooring_move_out_notes'=> 'nullable',
            'bedrooms_walls_movein'=> 'nullable',
            'bedrooms_walls_move_in_notes'=> 'nullable',
            'bedrooms_walls_moveout'=> 'nullable',
            'bedrooms_walls_move_out_notes'=> 'nullable',
            'bedrooms_windows_movein'=> 'nullable',
            'bedrooms_windows_move_in_notes'=> 'nullable',
            'bedrooms_windows_moveout'=> 'nullable',
            'bedrooms_windows_move_out_notes'=> 'nullable',
            'bedrooms_light_movein'=> 'nullable',
            'bedrooms_light_move_in_notes'=> 'nullable',
            'bedrooms_light_moveout'=> 'nullable',
            'bedrooms_light_move_out_notes'=> 'nullable',
            'bedrooms_switches_movein'=> 'nullable',
            'bedrooms_switches_move_in_notes'=> 'nullable',
            'bedrooms_switches_moveout'=> 'nullable',
            'bedrooms_switches_move_out_notes'=> 'nullable',
            'bedrooms_closets_movein'=> 'nullable',
            'bedrooms_closets_move_in_notes'=> 'nullable',
            'bedrooms_closets_moveout'=> 'nullable',
            'bedrooms_closets_move_out_notes'=> 'nullable',
            'bedrooms_generalNotes'=> 'nullable',
            'bath_description'=> 'nullable',
            'bath_doors_movein'=> 'nullable',
            'bath_doors_move_in_notes'=> 'nullable',
            'bath_doors_moveout'=> 'nullable',
            'bath_doors_move_out_notes'=> 'nullable',
            'bath_flooring_movein'=> 'nullable',
            'bath_flooring_move_in_notes'=> 'nullable',
            'bath_flooring_moveout'=> 'nullable',
            'bath_flooring_move_out_notes'=> 'nullable',
            'bath_walls_movein'=> 'nullable',
            'bath_walls_move_in_notes'=> 'nullable',
            'bath_walls_moveout'=> 'nullable',
            'bath_walls_move_out_notes'=> 'nullable',
            'bath_windows_movein'=> 'nullable',
            'bath_windows_move_in_notes'=> 'nullable',
            'bath_windows_moveout'=> 'nullable',
            'bath_windows_move_out_notes'=> 'nullable',
            'bath_screens_movein'=> 'nullable',
            'bath_screens_move_in_notes'=> 'nullable',
            'bath_screens_moveout'=> 'nullable',
            'bath_screens_move_out_notes'=> 'nullable',
            'bath_light_movein'=> 'nullable',
            'bath_light_move_in_notes'=> 'nullable',
            'bath_light_moveout'=> 'nullable',
            'bath_light_move_out_notes'=> 'nullable',
            'bath_switches_movein'=> 'nullable',
            'bath_switches_move_in_notes'=> 'nullable',
            'bath_switches_moveout'=> 'nullable',
            'bath_switches_move_out_notes'=> 'nullable',
            'bath_toilet_movein'=> 'nullable',
            'bath_toilet_move_in_notes'=> 'nullable',
            'bath_toilet_moveout'=> 'nullable',
            'bath_toilet_move_out_notes'=> 'nullable',
            'bath_tub_movein'=> 'nullable',
            'bath_tub_move_in_notes'=> 'nullable',
            'bath_tub_moveout'=> 'nullable',
            'bath_tub_move_out_notes'=> 'nullable',
            'bath_sink_movein'=> 'nullable',
            'bath_sink_move_in_notes'=> 'nullable',
            'bath_sink_moveout'=> 'nullable',
            'bath_sink_move_out_notes'=> 'nullable',
            'bath_plumbing_movein'=> 'nullable',
            'bath_plumbing_move_in_notes'=> 'nullable',
            'bath_plumbing_moveout'=> 'nullable',
            'bath_plumbing_move_out_notes'=> 'nullable',
            'bath_exhaust_movein'=> 'nullable',
            'bath_exhaust_move_in_notes'=> 'nullable',
            'bath_exhaust_moveout'=> 'nullable',
            'bath_exhaust_move_out_notes'=> 'nullable',
            'bath_towel_movein'=> 'nullable',
            'bath_towel_move_in_notes'=> 'nullable',
            'bath_towel_moveout'=> 'nullable',
            'bath_towel_move_out_notes'=> 'nullable',
            'bath_paper_holder_movein'=> 'nullable',
            'bath_paper_holder_move_in_notes'=> 'nullable',
            'bath_paper_holder_moveout'=> 'nullable',
            'bath_paper_holder_move_out_notes'=> 'nullable',
            'bath_cabinet_movein'=> 'nullable',
            'bath_cabinet_move_in_notes'=> 'nullable',
            'bath_cabinet_moveout'=> 'nullable',
            'bath_cabinet_move_out_notes'=> 'nullable',
            'bath_generalNotes'=> 'nullable',
            'kitchen_doors_movein'=> 'nullable',
            'kitchen_doors_move_in_notes'=> 'nullable',
            'kitchen_doors_moveout'=> 'nullable',
            'kitchen_doors_move_out_notes'=> 'nullable',
            'kitchen_flooring_movein'=> 'nullable',
            'kitchen_flooring_move_in_notes'=> 'nullable',
            'kitchen_flooring_moveout'=> 'nullable',
            'kitchen_flooring_move_out_notes'=> 'nullable',
            'kitchen_walls_movein'=> 'nullable',
            'kitchen_walls_move_in_notes'=> 'nullable',
            'kitchen_walls_moveout'=> 'nullable',
            'kitchen_walls_move_out_notes'=> 'nullable',
            'kitchen_windows_movein'=> 'nullable',
            'kitchen_windows_move_in_notes'=> 'nullable',
            'kitchen_windows_moveout'=> 'nullable',
            'kitchen_windows_move_out_notes'=> 'nullable',
            'kitchen_screens_movein'=> 'nullable',
            'kitchen_screens_move_in_notes'=> 'nullable',
            'kitchen_screens_moveout'=> 'nullable',
            'kitchen_screens_move_out_notes'=> 'nullable',
            'kitchen_light_movein'=> 'nullable',
            'kitchen_light_move_in_notes'=> 'nullable',
            'kitchen_light_moveout'=> 'nullable',
            'kitchen_light_move_out_notes'=> 'nullable',
            'kitchen_switches_movein'=> 'nullable',
            'kitchen_switches_move_in_notes'=> 'nullable',
            'kitchen_switches_moveout'=> 'nullable',
            'kitchen_switches_move_out_notes'=> 'nullable',
            'kitchen_range_movein'=> 'nullable',
            'kitchen_range_move_in_notes'=> 'nullable',
            'kitchen_range_moveout'=> 'nullable',
            'kitchen_range_move_out_notes'=> 'nullable',
            'kitchen_oven_movein'=> 'nullable',
            'kitchen_oven_move_in_notes'=> 'nullable',
            'kitchen_oven_moveout'=> 'nullable',
            'kitchen_oven_move_out_notes'=> 'nullable',
            'kitchen_refrigerator_movein'=> 'nullable',
            'kitchen_refrigerator_move_in_notes'=> 'nullable',
            'kitchen_refrigerator_moveout'=> 'nullable',
            'kitchen_refrigerator_move_out_notes'=> 'nullable',
            'kitchen_plumbing_movein'=> 'nullable',
            'kitchen_plumbing_move_in_notes'=> 'nullable',
            'kitchen_plumbing_moveout'=> 'nullable',
            'kitchen_plumbing_move_out_notes'=> 'nullable',
            'kitchen_sink_movein'=> 'nullable',
            'kitchen_sink_move_in_notes'=> 'nullable',
            'kitchen_sink_moveout'=> 'nullable',
            'kitchen_sink_move_out_notes'=> 'nullable',
            'kitchen_faucet_movein'=> 'nullable',
            'kitchen_faucet_move_in_notes'=> 'nullable',
            'kitchen_faucet_moveout'=> 'nullable',
            'kitchen_faucet_move_out_notes'=> 'nullable',
            'kitchen_cabinets_movein'=> 'nullable',
            'kitchen_cabinets_move_in_notes'=> 'nullable',
            'kitchen_cabinets_moveout'=> 'nullable',
            'kitchen_cabinets_move_out_notes'=> 'nullable',
            'kitchen_counters_movein'=> 'nullable',
            'kitchen_counters_move_in_notes'=> 'nullable',
            'kitchen_counters_moveout'=> 'nullable',
            'kitchen_counters_move_out_notes'=> 'nullable',
            'kitchen_generalNotes'=> 'nullable',
            'laundry_description'=> 'nullable',
            'laundry_faucets_movein'=> 'nullable',
            'laundry_faucets_move_in_notes'=> 'nullable',
            'laundry_faucets_moveout'=> 'nullable',
            'laundry_faucets_move_out_notes'=> 'nullable',
            'laundry_plumbing_movein'=> 'nullable',
            'laundry_plumbing_move_in_notes'=> 'nullable',
            'laundry_plumbing_moveout'=> 'nullable',
            'laundry_plumbing_move_out_notes'=> 'nullable',
            'laundry_cabinet_movein'=> 'nullable',
            'laundry_cabinet_move_in_notes'=> 'nullable',
            'laundry_cabinet_moveout'=> 'nullable',
            'laundry_cabinet_move_out_notes'=> 'nullable',
            'laundry_generalNotes'=> 'nullable',
            'systems_description'=> 'nullable',
            'systems_furnace_movein'=> 'nullable',
            'systems_furnace_move_in_notes'=> 'nullable',
            'systems_furnace_moveout'=> 'nullable',
            'systems_furnace_move_out_notes'=> 'nullable',
            'systems_aircon_movein'=> 'nullable',
            'systems_aircon_move_in_notes'=> 'nullable',
            'systems_aircon_moveout'=> 'nullable',
            'systems_aircon_move_out_notes'=> 'nullable',
            'systems_heater_movein' => 'nullable',
            'systems_heater_move_in_notes'=> 'nullable',
            'systems_heater_moveout'=> 'nullable',
            'systems_heater_move_out_notes' => 'nullable',
            'systems_softener_movein' => 'nullable',
            'systems_softener_move_in_notes'=> 'nullable',
            'systems_softener_moveout'=> 'nullable',
            'systems_softener_move_out_notes'=> 'nullable',
            'systems_generalNotes'=> 'nullable',
            'tnt_initial_hiddenTxtSig' => 'nullable',
            'tnt_initial_hiddenSig'    => 'nullable',
            'fontSig'                  => 'nullable',
            'water_service_name_'      => 'nullable',
            'water_service_address_'   => 'nullable',
            'submeter_email_'          => 'nullable',
            'submeter_phone_'          => 'nullable',
            'water_service_phone_'     => 'nullable',
            'water_service_email_'     => 'nullable',
            'water_service_day_other_' => 'nullable',
            'submeter_cost_'           => 'nullable',
            'water_fixture_name_'      => 'nullable',
            'water_fixture_email_'     => 'nullable',
            'water_fixture_phone_'     => 'nullable',
            'water_fixture_address_'   => 'nullable',
            'water_service_hour_from_' => 'nullable',
            'water_service_hour_to_'   => 'nullable',
            'water_service_day_monday_'      => 'nullable',
            'water_service_day_tuesday_'     => 'nullable',
            'water_service_day_wednesday_'   => 'nullable',
            'water_service_day_thursday_'    => 'nullable',
            'water_service_day_friday_'      => 'nullable',
            'water_service_day_saturday_'    => 'nullable',
            'water_service_day_sunday_'      => 'nullable',
            'submeter_location_'             => 'nullable',
            'submeter_date_'                 => 'nullable',
            'bill_estimate_'                 => 'nullable',
            'satellite_deposit_'             => 'nullable',
            'satellite_insurance_'           => 'nullable',
            'require_satellite_deposit_'     => 'nullable',
            'require_satellite_insurance_'   => 'nullable',
            'submeter_address_'              => 'nullable',
            'occupant_name'                  => 'nullable',
            'submeter_name_'                 => 'nullable',
            'water_service_bill_day_'        => 'nullable',
            'move_in_date'                   => 'nullable',
            'prorate'                        => 'nullable',
            'optional_address_'              => 'nullable',
         ]
       ],
       'storeAgreementReport' => [
         'rule' => [
           'provide_pets_'     => 'nullable',
           'tnt-initials_font' => 'nullable|string',
           'application_id'    => 'required',
           'occupants'         => 'required|string',
           'tnt_initials_font' => 'nullable|string',
           'tnt-sig_font'      => 'nullable|string',
           'tenant_lease_full_name' => 'required|string',
           'tnt_initials_font' => 'nullable|string',
           'management_name_'  => 'required|string',
           'contains_paint_record_' => 'nullable',
           'lead_paint_knowledge' => 'nullable',
           'prop_no_'          => 'required',
           'inspection_date_'  => 'required|date',
           'unit_no_'          => 'required',
           'num_occupants_'     => 'required|integer',
           'rental_begin_date_'    =>'required|date',
           'monthly_rent_'         =>'required',
           'payments_to_'          =>'required|string',
           'payment_phone_number_' =>'required|string',
           'rent_payment_address_' =>'required|string',
           'sec_dep_'              =>'required',
           'total_cost_'           =>'required',
           'premise_keys_'        =>'required',
           'mailbox_keys_'        =>'required',
           'attn_'                =>'required',
           'tenant_concessions_'    =>'nullable|numeric',
           'hoa_days_'              =>'nullable|numeric',
           'landlord_initial_'    =>'required',
           'full_prop_address_'   =>'required',
           'landlord_signature_'  =>'required',
           'prop_address_'       =>'required',
           'move_in_date_'        =>'required|date',
           'move_out_date_'       =>'required|date',
           'agent_signature_'     =>'nullable|string',
           'agent_print'         =>'nullable|string',
           'agent_title'         =>'nullable|string',
           'manager_signature'   =>'nullable|string',
           'manager_print'       =>'nullable|string',
           'has_paint_record_'    =>'nullable',
           'no_paint_record_'    =>'nullable',
            'include_storage_'    =>'nullable',
            'not_include_storage_'    =>'nullable',
            'include_parking_'       =>'nullable',
            'not_include_parking_'   =>'nullable',
            'provide_parking_'       =>'nullable',
             'provide_storage_'      =>'nullable',
            'known_lead_paint_'      =>'nullable',
            'not_known_lead_paint_'  =>'nullable',
            'included_properties_'   =>'nullable',
            'disclosed_infestation_' =>'nullable',
            'disclosed_infestations_'=>'nullable',
            'infestation_'           =>'nullable',
            'tenant_lists_'          =>'required',
            'other_notes_'           =>'required',
            'next_month_rent_'       =>'nullable|numeric',
            'garage_cost_'           =>'nullable|numeric',
            'trash_cost_'            =>'nullable|numeric',
            'water_cost_'            =>'nullable|numeric',
            'pets_cost_'             =>'nullable|numeric',
            'parking_cost_'          =>'nullable|numeric',
            'storage_cost_'          =>'nullable|numeric',
            'other_cost_'            =>'nullable|numeric',
            'pool_cost_'             =>'nullable|numeric',
            'replace_appliances_'  =>'nullable',
            'included_pets_'       =>'nullable',
            'notice_address_'      =>'nullable',
            'notice_local_address_' =>'nullable',
            'premises_' =>'nullable',
            'rent_payment_address_' =>'nullable',
            'sec_dep_return_address_' =>'nullable',
            'other_service_' =>'nullable',
            'other_reimburse_' =>'nullable',
            'hoa_name_' =>'nullable',
            'other_addenda_' =>'nullable',
            'pool_pay_to_'     =>'nullable',
            'electricity_service_' =>'nullable',
            'gas_service_' =>'nullable',
            'water_service_' =>'nullable',
            'garbage_service_' =>'nullable',
            'electricity_reimburse_' =>'nullable',
            'gas_reimburse_' =>'nullable',
            'water_reimburse_' =>'nullable',
            'garbage_reimburse_' =>'nullable',
            'inspection_done_'   => 'nullable',
            'payment_phone_number_' =>'required',
            'pool_vendor_phone_'    =>'nullable',
            'has_hazard_'           =>'nullable',
            'lead_paint_knowledge_' => 'nullable',
            'front_yard_inspect__landscaping_movein'=> 'nullable',
            'front_yard_inspect__landscaping_move_in_notes' => 'nullable',
            'front_yard_inspect__landscaping_moveout' => 'nullable',
            'front_yard_inspect__landscaping_move_out_notes'=> 'nullable',
            'front_yard_inspect__fences_movein' => 'nullable',
            'front_yard_inspect__fences_move_in_notes'=> 'nullable',
            'front_yard_inspect__fences_moveout'=> 'nullable',
            'front_yard_inspect__fences_move_out_notes' => 'nullable',
            'front_yard_inspect__sprinklers_movein' => 'nullable',
            'front_yard_inspect__sprinklers_move_in_notes'=> 'nullable',
            'front_yard_inspect__sprinklers_moveout'=> 'nullable',
            'front_yard_inspect__sprinklers_move_out_notes' => 'nullable',
            'front_yard_inspect__walks_movein'=> 'nullable',
            'front_yard_inspect__walks_move_in_notes'=> 'nullable',
            'front_yard_inspect__walks_moveout' => 'nullable',
            'front_yard_inspect__walks_move_out_notes'=> 'nullable',
            'front_yard_inspect__porches_movein'=> 'nullable',
            'front_yard_inspect__porches_move_in_notes'=> 'nullable',
            'front_yard_inspect__porches_moveout' => 'nullable',
            'front_yard_inspect__porches_move_out_notes'=> 'nullable',
            'front_yard_inspect__mailbox_movein'=> 'nullable',
            'front_yard_inspect__mailbox_move_in_notes'=> 'nullable',
            'front_yard_inspect__mailbox_moveout' => 'nullable',
            'front_yard_inspect__mailbox_move_out_notes'=> 'nullable',
            'front_yard_inspect__light_movein'=> 'nullable',
            'front_yard_inspect__light_move_in_notes'=> 'nullable',
            'front_yard_inspect__light_moveout'=> 'nullable',
            'front_yard_inspect__light_move_out_notes'=> 'nullable',
            'front_yard_inspect__exterior_movein'=> 'nullable',
            'front_yard_inspect__exterior_move_in_notes'=> 'nullable',
            'front_yard_inspect__exterior_moveout'=> 'nullable',
            'front_yard_inspect__exterior_move_out_notes'=> 'nullable',
            'front_yard_inspect__generalNotes'=> 'nullable',
            'entry_security_movein'=> 'nullable',
            'entry_security_move_in_notes'=> 'nullable',
            'entry_security_moveout'=> 'nullable',
            'entry_security_move_out_notes'=> 'nullable',
            'entry_doors_movein'=> 'nullable',
            'entry_doors_move_in_notes'=> 'nullable',
            'entry_doors_moveout'=> 'nullable',
            'entry_doors_move_out_notes'=> 'nullable',
            'entry_flooring_movein'=> 'nullable',
            'entry_flooring_move_in_notes'=> 'nullable',
            'entry_flooring_moveout'=> 'nullable',
            'entry_flooring_move_out_notes'=> 'nullable',
            'entry_light_movein'=> 'nullable',
            'entry_light_move_in_notes'=> 'nullable',
            'entry_light_moveout'=> 'nullable',
            'entry_light_move_out_notes'=> 'nullable',
            'entry_switches_movein'=> 'nullable',
            'entry_switches_move_in_notes'=> 'nullable',
            'entry_switches_moveout'=> 'nullable',
            'entry_switches_move_out_notes'=> 'nullable',
            'entry_generalNotes'=> 'nullable',
            'living_room_doors_movein'=> 'nullable',
            'living_room_doors_move_in_notes'=> 'nullable',
            'living_room_doors_moveout'=> 'nullable',
            'living_room_doors_move_out_notes'=> 'nullable',
            'living_room_flooring_movein'=> 'nullable',
            'living_room_flooring_move_in_notes'=> 'nullable',
            'living_room_flooring_moveout'=> 'nullable',
            'living_room_flooring_move_out_notes'=> 'nullable',
            'living_room_walls_movein'=> 'nullable',
            'living_room_walls_move_in_notes'=> 'nullable',
            'living_room_walls_moveout'=> 'nullable',
            'living_room_walls_move_out_notes'=> 'nullable',
            'living_room_screens_movein'=> 'nullable',
            'living_room_screens_move_in_notes'=> 'nullable',
            'living_room_screens_moveout'=> 'nullable',
            'living_room_screens_move_out_notes'=> 'nullable',
            'living_room_light_movein'=> 'nullable',
            'living_room_light_move_in_notes'=> 'nullable',
            'living_room_light_moveout'=> 'nullable',
            'living_room_light_move_out_notes'=> 'nullable',
            'living_room_switches_movein'=> 'nullable',
            'living_room_switches_move_in_notes'=> 'nullable',
            'living_room_switches_moveout'=> 'nullable',
            'living_room_switches_move_out_notes'=> 'nullable',
            'living_room_fireplace_movein'=> 'nullable',
            'living_room_fireplace_move_in_notes'=> 'nullable',
            'living_room_fireplace_moveout'=> 'nullable',
            'living_room_fireplace_move_out_notes'=> 'nullable',
            'living_room_generalNotes'=> 'nullable',
            'other_room_description'=> 'nullable',
            'other_room_doors_movein'=> 'nullable',
            'other_room_doors_move_in_notes'=> 'nullable',
            'other_room_doors_moveout'=> 'nullable',
            'other_room_doors_move_out_notes'=> 'nullable',
            'other_room_flooring_movein'=> 'nullable',
            'other_room_flooring_move_in_notes'=> 'nullable',
            'other_room_flooring_moveout'=> 'nullable',
            'other_room_flooring_move_out_notes'=> 'nullable',
            'other_room_walls_movein'=> 'nullable',
            'other_room_walls_move_in_notes'=> 'nullable',
            'other_room_walls_moveout'=> 'nullable',
            'other_room_walls_move_out_notes'=> 'nullable',
            'other_room_windows_movein'=> 'nullable',
            'other_room_windows_move_in_notes'=> 'nullable',
            'other_room_windows_moveout'=> 'nullable',
            'other_room_windows_move_out_notes'=> 'nullable',
            'other_room_light_movein'=> 'nullable',
            'other_room_light_move_in_notes'=> 'nullable',
            'other_room_light_moveout'=> 'nullable',
            'other_room_light_move_out_notes'=> 'nullable',
            'other_room_switches_movein'=> 'nullable',
            'other_room_switches_move_in_notes'=> 'nullable',
            'other_room_switches_moveout'=> 'nullable',
            'other_room_switches_move_out_notes'=> 'nullable',
            'other_room_generalNotes'=> 'nullable',
            'bedrooms_description'=> 'nullable',
            'bedrooms_doors_movein'=> 'nullable',
            'bedrooms_doors_move_in_notes'=> 'nullable',
            'bedrooms_doors_moveout'=> 'nullable',
            'bedrooms_doors_move_out_notes'=> 'nullable',
            'bedrooms_flooring_movein'=> 'nullable',
            'bedrooms_flooring_move_in_notes'=> 'nullable',
            'bedrooms_flooring_moveout'=> 'nullable',
            'bedrooms_flooring_move_out_notes'=> 'nullable',
            'bedrooms_walls_movein'=> 'nullable',
            'bedrooms_walls_move_in_notes'=> 'nullable',
            'bedrooms_walls_moveout'=> 'nullable',
            'bedrooms_walls_move_out_notes'=> 'nullable',
            'bedrooms_windows_movein'=> 'nullable',
            'bedrooms_windows_move_in_notes'=> 'nullable',
            'bedrooms_windows_moveout'=> 'nullable',
            'bedrooms_windows_move_out_notes'=> 'nullable',
            'bedrooms_light_movein'=> 'nullable',
            'bedrooms_light_move_in_notes'=> 'nullable',
            'bedrooms_light_moveout'=> 'nullable',
            'bedrooms_light_move_out_notes'=> 'nullable',
            'bedrooms_switches_movein'=> 'nullable',
            'bedrooms_switches_move_in_notes'=> 'nullable',
            'bedrooms_switches_moveout'=> 'nullable',
            'bedrooms_switches_move_out_notes'=> 'nullable',
            'bedrooms_closets_movein'=> 'nullable',
            'bedrooms_closets_move_in_notes'=> 'nullable',
            'bedrooms_closets_moveout'=> 'nullable',
            'bedrooms_closets_move_out_notes'=> 'nullable',
            'bedrooms_generalNotes'=> 'nullable',
            'bath_description'=> 'nullable',
            'bath_doors_movein'=> 'nullable',
            'bath_doors_move_in_notes'=> 'nullable',
            'bath_doors_moveout'=> 'nullable',
            'bath_doors_move_out_notes'=> 'nullable',
            'bath_flooring_movein'=> 'nullable',
            'bath_flooring_move_in_notes'=> 'nullable',
            'bath_flooring_moveout'=> 'nullable',
            'bath_flooring_move_out_notes'=> 'nullable',
            'bath_walls_movein'=> 'nullable',
            'bath_walls_move_in_notes'=> 'nullable',
            'bath_walls_moveout'=> 'nullable',
            'bath_walls_move_out_notes'=> 'nullable',
            'bath_windows_movein'=> 'nullable',
            'bath_windows_move_in_notes'=> 'nullable',
            'bath_windows_moveout'=> 'nullable',
            'bath_windows_move_out_notes'=> 'nullable',
            'bath_screens_movein'=> 'nullable',
            'bath_screens_move_in_notes'=> 'nullable',
            'bath_screens_moveout'=> 'nullable',
            'bath_screens_move_out_notes'=> 'nullable',
            'bath_light_movein'=> 'nullable',
            'bath_light_move_in_notes'=> 'nullable',
            'bath_light_moveout'=> 'nullable',
            'bath_light_move_out_notes'=> 'nullable',
            'bath_switches_movein'=> 'nullable',
            'bath_switches_move_in_notes'=> 'nullable',
            'bath_switches_moveout'=> 'nullable',
            'bath_switches_move_out_notes'=> 'nullable',
            'bath_toilet_movein'=> 'nullable',
            'bath_toilet_move_in_notes'=> 'nullable',
            'bath_toilet_moveout'=> 'nullable',
            'bath_toilet_move_out_notes'=> 'nullable',
            'bath_tub_movein'=> 'nullable',
            'bath_tub_move_in_notes'=> 'nullable',
            'bath_tub_moveout'=> 'nullable',
            'bath_tub_move_out_notes'=> 'nullable',
            'bath_sink_movein'=> 'nullable',
            'bath_sink_move_in_notes'=> 'nullable',
            'bath_sink_moveout'=> 'nullable',
            'bath_sink_move_out_notes'=> 'nullable',
            'bath_plumbing_movein'=> 'nullable',
            'bath_plumbing_move_in_notes'=> 'nullable',
            'bath_plumbing_moveout'=> 'nullable',
            'bath_plumbing_move_out_notes'=> 'nullable',
            'bath_exhaust_movein'=> 'nullable',
            'bath_exhaust_move_in_notes'=> 'nullable',
            'bath_exhaust_moveout'=> 'nullable',
            'bath_exhaust_move_out_notes'=> 'nullable',
            'bath_towel_movein'=> 'nullable',
            'bath_towel_move_in_notes'=> 'nullable',
            'bath_towel_moveout'=> 'nullable',
            'bath_towel_move_out_notes'=> 'nullable',
            'bath_paper_holder_movein'=> 'nullable',
            'bath_paper_holder_move_in_notes'=> 'nullable',
            'bath_paper_holder_moveout'=> 'nullable',
            'bath_paper_holder_move_out_notes'=> 'nullable',
            'bath_cabinet_movein'=> 'nullable',
            'bath_cabinet_move_in_notes'=> 'nullable',
            'bath_cabinet_moveout'=> 'nullable',
            'bath_cabinet_move_out_notes'=> 'nullable',
            'bath_generalNotes'=> 'nullable',
            'kitchen_doors_movein'=> 'nullable',
            'kitchen_doors_move_in_notes'=> 'nullable',
            'kitchen_doors_moveout'=> 'nullable',
            'kitchen_doors_move_out_notes'=> 'nullable',
            'kitchen_flooring_movein'=> 'nullable',
            'kitchen_flooring_move_in_notes'=> 'nullable',
            'kitchen_flooring_moveout'=> 'nullable',
            'kitchen_flooring_move_out_notes'=> 'nullable',
            'kitchen_walls_movein'=> 'nullable',
            'kitchen_walls_move_in_notes'=> 'nullable',
            'kitchen_walls_moveout'=> 'nullable',
            'kitchen_walls_move_out_notes'=> 'nullable',
            'kitchen_windows_movein'=> 'nullable',
            'kitchen_windows_move_in_notes'=> 'nullable',
            'kitchen_windows_moveout'=> 'nullable',
            'kitchen_windows_move_out_notes'=> 'nullable',
            'kitchen_screens_movein'=> 'nullable',
            'kitchen_screens_move_in_notes'=> 'nullable',
            'kitchen_screens_moveout'=> 'nullable',
            'kitchen_screens_move_out_notes'=> 'nullable',
            'kitchen_light_movein'=> 'nullable',
            'kitchen_light_move_in_notes'=> 'nullable',
            'kitchen_light_moveout'=> 'nullable',
            'kitchen_light_move_out_notes'=> 'nullable',
            'kitchen_switches_movein'=> 'nullable',
            'kitchen_switches_move_in_notes'=> 'nullable',
            'kitchen_switches_moveout'=> 'nullable',
            'kitchen_switches_move_out_notes'=> 'nullable',
            'kitchen_range_movein'=> 'nullable',
            'kitchen_range_move_in_notes'=> 'nullable',
            'kitchen_range_moveout'=> 'nullable',
            'kitchen_range_move_out_notes'=> 'nullable',
            'kitchen_oven_movein'=> 'nullable',
            'kitchen_oven_move_in_notes'=> 'nullable',
            'kitchen_oven_moveout'=> 'nullable',
            'kitchen_oven_move_out_notes'=> 'nullable',
            'kitchen_refrigerator_movein'=> 'nullable',
            'kitchen_refrigerator_move_in_notes'=> 'nullable',
            'kitchen_refrigerator_moveout'=> 'nullable',
            'kitchen_refrigerator_move_out_notes'=> 'nullable',
            'kitchen_plumbing_movein'=> 'nullable',
            'kitchen_plumbing_move_in_notes'=> 'nullable',
            'kitchen_plumbing_moveout'=> 'nullable',
            'kitchen_plumbing_move_out_notes'=> 'nullable',
            'kitchen_sink_movein'=> 'nullable',
            'kitchen_sink_move_in_notes'=> 'nullable',
            'kitchen_sink_moveout'=> 'nullable',
            'kitchen_sink_move_out_notes'=> 'nullable',
            'kitchen_faucet_movein'=> 'nullable',
            'kitchen_faucet_move_in_notes'=> 'nullable',
            'kitchen_faucet_moveout'=> 'nullable',
            'kitchen_faucet_move_out_notes'=> 'nullable',
            'kitchen_cabinets_movein'=> 'nullable',
            'kitchen_cabinets_move_in_notes'=> 'nullable',
            'kitchen_cabinets_moveout'=> 'nullable',
            'kitchen_cabinets_move_out_notes'=> 'nullable',
            'kitchen_counters_movein'=> 'nullable',
            'kitchen_counters_move_in_notes'=> 'nullable',
            'kitchen_counters_moveout'=> 'nullable',
            'kitchen_counters_move_out_notes'=> 'nullable',
            'kitchen_generalNotes'=> 'nullable',
            'laundry_description'=> 'nullable',
            'laundry_faucets_movein'=> 'nullable',
            'laundry_faucets_move_in_notes'=> 'nullable',
            'laundry_faucets_moveout'=> 'nullable',
            'laundry_faucets_move_out_notes'=> 'nullable',
            'laundry_plumbing_movein'=> 'nullable',
            'laundry_plumbing_move_in_notes'=> 'nullable',
            'laundry_plumbing_moveout'=> 'nullable',
            'laundry_plumbing_move_out_notes'=> 'nullable',
            'laundry_cabinet_movein'=> 'nullable',
            'laundry_cabinet_move_in_notes'=> 'nullable',
            'laundry_cabinet_moveout'=> 'nullable',
            'laundry_cabinet_move_out_notes'=> 'nullable',
            'laundry_generalNotes'=> 'nullable',
            'systems_description'=> 'nullable',
            'systems_furnace_movein'=> 'nullable',
            'systems_furnace_move_in_notes'=> 'nullable',
            'systems_furnace_moveout'=> 'nullable',
            'systems_furnace_move_out_notes'=> 'nullable',
            'systems_aircon_movein'=> 'nullable',
            'systems_aircon_move_in_notes'=> 'nullable',
            'systems_aircon_moveout'=> 'nullable',
            'systems_aircon_move_out_notes'=> 'nullable',
            'systems_heater_movein' => 'nullable',
            'systems_heater_move_in_notes'=> 'nullable',
            'systems_heater_moveout'=> 'nullable',
            'systems_heater_move_out_notes' => 'nullable',
            'systems_softener_movein' => 'nullable',
            'systems_softener_move_in_notes'=> 'nullable',
            'systems_softener_moveout'=> 'nullable',
            'systems_softener_move_out_notes'=> 'nullable',
            'systems_generalNotes'=> 'nullable',
            'tnt_initial_hiddenTxtSig' => 'nullable',
            'tnt_initial_hiddenSig'    => 'nullable',
            'fontSig'                  => 'nullable',
            'water_service_name_'      => 'nullable',
            'water_service_address_'   => 'nullable',
            'submeter_email_'          => 'nullable',
            'submeter_phone_'          => 'nullable',
            'water_service_phone_'     => 'nullable',
            'water_service_email_'     => 'nullable',
            'water_service_day_other_' => 'nullable',
            'submeter_cost_'           => 'nullable',
            'water_fixture_name_'      => 'nullable',
            'water_fixture_email_'     => 'nullable',
            'water_fixture_phone_'     => 'nullable',
            'water_fixture_address_'   => 'nullable',
            'water_service_hour_from_' => 'nullable',
            'water_service_hour_to_'   => 'nullable',
            'water_service_day_monday_'      => 'nullable',
            'water_service_day_tuesday_'     => 'nullable',
            'water_service_day_wednesday_'   => 'nullable',
            'water_service_day_thursday_'    => 'nullable',
            'water_service_day_friday_'      => 'nullable',
            'water_service_day_saturday_'    => 'nullable',
            'water_service_day_sunday_'      => 'nullable',
            'submeter_location_'             => 'nullable',
            'submeter_date_'                 => 'nullable',
            'bill_estimate_'                 => 'nullable',
            'satellite_deposit_'             => 'nullable',
            'satellite_insurance_'           => 'nullable',
            'require_satellite_deposit_'     => 'nullable',
            'require_satellite_insurance_'   => 'nullable',
            'submeter_address_'              => 'nullable',
            'occupant_name'                  => 'nullable',
            'submeter_name_'                 => 'nullable',
            'water_service_bill_day_'        => 'nullable',
            'move_in_date'                   => 'required|string',
            'prorate'                        => 'nullable',
            'property_additonal_info_'       => 'nullable',
            'optional_address_'              => 'nullable|string',
         ]
       ]
     ];
     
     if(!empty($req['formId'])){
         $setting['updateinitialForm']['rule']['initial_tenant_initials']                = 'nullable|string';
         $setting['updateesignatureForm']['rule']['initial_tenant_initials_hiddenSig']   = 'nullable|string';
     }
     
     $tntSigRules = Agreement::$tenantFields + ['tnt_signature_'=>['num'=>Agreement::$numTntSig,'rule'=>'nullable']]; 
     $count       = !empty($default['application']) ? count($default['application']) : 1;

     foreach($tntSigRules as $k => $v){
       for($i = 1; $i < $v['num'] + 1; $i++){
         for($j = 1; $j < $count + 1; $j++){
           if($k === 'tnt_signature_'){
             $key                                                                      = $k . $i . '_' . $j;
             $setting['storeAgreementReport']['rule'][$key . '_hiddenTxtSig']          = $v['rule'];
             $setting['storeAgreementReport']['rule'][$key . '_hiddenSig']             = $v['rule'];
             
             $setting['storepreview']['rule'][$key . '_hiddenTxtSig']                  = 'nullable';
             $setting['storepreview']['rule'][$key . '_hiddenSig']                     = 'nullable';
           } else {
             $key                                                                      = $k . $j . '_' . $i;
             $setting['storeAgreementReport']['rule'][$key]                            = $v['rule'];
             $setting['storepreview']['rule'][$key]                                    = 'nullable';
           }
         }
       }
     }
     return $setting[$fn];
   }
//------------------------------------------------------------------------------
/**
   * @desc Generate html for the initial signature modal on startup which includes:
    * Text box to enter signature and/or initials
    * Select box to toggle the font
    * An optional button to replace the text with an image generated from jSignature
   * @params {array}  $vData: Request Data
   * @return {array}
   */
   private function _createDoubleTemplate($vData){
     $fontToggleHtml = $this->_generateFontOptions('font_toggle','form-control font-toggle',true);
     //Generate a font selector to toggle the fonts of text inputs for initials and signatures
     //$fontToggleHtml = Html::div(Html::div($fontToggleHtml,['class'=>'col-sm-6 col-sm-offset-4']),['class'=>'row']);
     $appId = $vData['application_id'];
     $r     = Helper::getElasticResult(Elastic::searchMatch(T::$creditCheckView,['match'=>['application_id'=>$appId]]));
     $r     = !empty($r) ? $r[0]['_source'] : [];
     $additionalForm = !empty($r['application']) ? count($r['application']) - 1: 0;
     $name1 = 'initial_tenant_initials';
     
     //Generate signature div for tenant initials
     //Includes space for placing JSignature image and text input   
     //Generate signature div for tenant signature
     //Includes space for placing JSignature image and text input
     $name2 = 'initial_tenant_signature';
     
     $div1                 = $div2 = '';
     $div1                .= Html::h3('Type Signature and Select Font',['class'=>'text-center']);
     $sigRow               = Html::label('Signature',['class'=>'control-label','for'=>$name2]) . Html::input('',['type'=>'text','class'=>'form-control signature','name'=>$name2]);
     $div1                .= Html::div($sigRow,['class'=>'form-group']);
     $initialRow           = Html::label('Initials',['class'=>'control-label','for'=>$name1]) . Html::input('',['type'=>'text','class'=>'form-control signature','name'=>$name1]);
     
     $div1                .= Html::div($initialRow,['class'=>'form-group']);
     $fontRow              = Html::label('Font Type',['class'=>'control-label','for'=>'font-toggle'])  . $fontToggleHtml;
     $div1                .= Html::div($fontRow,['class'=>'form-group']);

     $row                  = Html::div(Html::div($div1,['class'=>'col-sm-12']),['class'=>'row']);
     $form1                = Html::tag('form',$row,['id'=>'initialForm']);
     
     $div2                 = Html::h3('Use Signature Pad');
     $div2                .= Html::button('Use Signature Pad for Signature&nbsp;&nbsp;' . Html::i('',['class'=>'fa fa-pencil','title'=>'Use Signature Pad']),['id'=>'signature_button_signature','class'=>'btn btn-lg btn-block signature-prompt','canvas-target'=>'signature_pad_signature']);
     $hiddenImg1           = Html::img(['src'=>'','id'=>$name2 . '_sigImg','style'=>'display:none;','class'=>'signature-img','width'=>$this->_imageWidth,'height'=>$this->_imageHeight]);
     $hiddenImg1          .= Html::input('',['type'=>'hidden','class'=>'form-control signature-img-data','name'=>$name2 . '_hiddenSig']);
     $div2                .= $hiddenImg1;
     $div2                .= Html::div('',['class'=>'signature-container','id'=>'signature_pad_signature','data-target'=>$name2 . '_hiddenSig']);
     $div2                .= Html::button('Use Signature Pad for Initials&nbsp;&nbsp;' . Html::i('',['class'=>'fa fa-pencil','title'=>'Use Signature Pad']),['id'=>'signature_button_initials','class'=>'btn btn-lg btn-block signature-prompt','canvas-target'=>'signature_pad_initials']);
     $hiddenImg2           = Html::img(['src'=>'','id'=>$name1 . '_sigImg','style'=>'display:none;','class'=>'form-control signature-img','width'=>$this->_imageWidth,'height'=>$this->_imageHeight]);
     $hiddenImg2          .= Html::input('',['type'=>'hidden','class'=>'form-control signature-img-data','name'=>$name1 . '_hiddenSig']);
     $div2                .= $hiddenImg2;
     $div2                .= Html::div('',['class'=>'signature-container','id'=>'signature_pad_initials','data-target'=>$name1 . '_hiddenSig']);
     
     $row2                 = Html::div(Html::div($div2,['class'=>'col-sm-12'],['class'=>'row']));
     $form2                = Html::tag('form',$row2,['id'=>'esignatureForm']);
     $tabs                 = $tabContent = '';
     $tabs                .= Html::li(Html::a('Type Manually',['href'=>'#tab_1','data-toggle'=>'tab']),['class'=>'active']);
     $tabs                .= Html::li(Html::a('Sign Electronically',['href'=>'#tab_2','data-toggle'=>'tab']));
     $tabs                 = Html::ul($tabs,['class'=>'nav nav-tabs']);

     $tabContent          .= Html::div($form1,['id'=>'tab_1','class'=>'tab-pane active']);
     $tabContent          .= Html::div($form2,['id'=>'tab_2','class'=>'tab-pane']);
     $tabContent           = Html::div($tabContent,['class'=>'tab-content']);

     $html                 = Html::div($tabs .$tabContent,['class'=>'nav-tabs-custom','id'=>'tab_form']);
     return [
       'html' => $html,
       'additionalForms'=>$additionalForm,
     ];
   }
//------------------------------------------------------------------------------
   private function _createAdditionalSigForm($vData){
     $fontToggleHtml = $this->_generateFontOptions('font_toggle','form-control font-toggle',true);
     $appId           = $vData['application_id'];
     $additionalCalls = $vData['formsLeft'];
     $index           = $vData['index'];
     $r               = Helper::getElasticResult(Elastic::searchMatch(T::$creditCheckView,['match'=>['application_id'=>$appId]]));
     $r               = !empty($r) ? $r[0]['_source'] : [];
     
     $name            = 'initial_tenant_signature_' . $index;
     
     $div1                 = $div2 = '';
     $div1                .= Html::h3('Type Signature and Select Font',['class'=>'text-center']);
     $sigRow               = Html::label('Signature',['class'=>'control-label','for'=>$name]) . Html::input('',['type'=>'text','class'=>'form-control signature','name'=>$name]);
     $div1                .= Html::div($sigRow,['class'=>'form-group']);
     
     $fontRow              = Html::label('Font Type',['class'=>'control-label','for'=>'font-toggle'])  . $fontToggleHtml;
     $div1                .= Html::div($fontRow,['class'=>'form-group']);
     
     
     $div2                 = Html::h3('Use Signature Pad');
     $div2                .= Html::button('Use Signature Pad&nbsp;&nbsp;' . Html::i('',['class'=>'fa fa-pencil','title'=>'Use Signature Pad']),['id'=>'signature_button_signature','canvas-target'=>'signature_pad_signature','class'=>'btn btn-lg btn-block signature-prompt']);
     $div2                .= Html::div('',['class'=>'signature-container','id'=>'signature_pad_signature','data-target'=>$name . '_hiddenSig']);
     
     $hiddenImg1           = Html::img(['src'=>'','id'=>$name . '_sigImg','style'=>'display:none;','class'=>'signature-img','width'=>$this->_imageWidth,'height'=>$this->_imageHeight]);
     $hiddenImg1          .= Html::input('',['type'=>'hidden','class'=>'form-control signature-img-data','name'=>$name . '_hiddenSig']);
     $div2                .= $hiddenImg1;
     
     $row                  = Html::div(Html::div($div1,['class'=>'col-sm-12']),['class'=>'row']);
     $form1                = Html::tag('form',$row,['id'=>'initialForm']);
     
     $row2                 = Html::div(Html::div($div2,['class'=>'col-sm-12']),['class'=>'row']);
     $form2                = Html::tag('form',$row2,['id'=>'esignatureForm']);
     $tabs                 = $tabContent = '';
     $tabs                .= Html::li(Html::a('Type Manually',['href'=>'#tab_1','data-toggle'=>'tab']),['class'=>'active']);
     $tabs                .= Html::li(Html::a('Sign Electronically',['href'=>'#tab_2','data-toggle'=>'tab']));

     $tabContent          .= Html::div($form1,['id'=>'tab_1','class'=>'tab-pane active']);
     $tabContent          .= Html::div($form2,['id'=>'tab_2','class'=>'tab-pane']);

     $html                 = Html::div(Html::ul($tabs,['class'=>'nav nav-tabs']) . Html::div($tabContent,['class'=>'tab-content']),['class'=>'nav-tabs-custom','id'=>'tab_form']);
     return [
       'html' => $html,
       'additionalForms' => $additionalCalls,
     ];
   }
################################################################################
##########################    HTML CODE FUNCTIONS    ###########################  
################################################################################
//------------------------------------------------------------------------------
  private function _sendEmail($vData){
    $input      = Html::div(Html::input('',['type'=>'text','class'=>'form-control email','name'=>'email[0]','id'=>'email[0]']),['class'=>'col-sm-9']);
    $group      = Html::div(Html::label('Email (1): ',['for'=>'email[0]','class'=>'control-label col-sm-3']) . $input,['class'=>'form-group']);
    $form       = Html::tag('form',$group,['id'=>'emailForm','class'=>'form-horizontal']);
    $btns       = '';
    $btns      .= Html::button(Html::i('',['class'=>'fa fa-fw fa-minus-square']),['id'=>'removeEmail','class'=>'btn btn-info pull-left']);
    $btns      .= Html::button(Html::i('',['class'=>'fa fa-fw fa-plus-square']),['id'=>'addEmail','class'=>'btn btn-info pull-right']);
    $btnRow     = Html::div(Html::div($btns,['class'=>'col-md-12']),['class'=>'row']);
    $html       = $btnRow . Html::tag('p','&nbsp;') . Html::div(Html::div($form,['class'=>'col-md-12']),['class'=>'row']);
    return [
      'html' => $html,
    ];
  }
//------------------------------------------------------------------------------
  private function _submitEmail($vData){
    $response    = [];
    $email       = $vData['email']; 
    try {
      foreach($email as $v){
        $msg = [
          'to'           => $v, 
          'cc'           => '',  
          'bcc'          => '',  
          'from'         => $v,  
          'subject'      => 'Rental Agreement with PAMA for Approval/Confirmation',  
          'msg'          => "Below is a link to your new rental agreement. " . Html::br() . 'Follow the link to download ' .
            Html::a('here',['href'=>$vData['link'],'target'=>'_blank','title'=>'Completed Rental Agreement']),
        ];

        Mail::send($msg);
      }
      $response['msg']          = $this->_getSuccessMsg('emailPdf');
    } catch(Exception $e){
      $response['error']['mainMsg'] = $this->_getErrorMsg('emailPdf');
    }
    return $response;
  }
//------------------------------------------------------------------------------
   /**
   * @desc Generate html for section 1.1 of Rental Agreement depending on number of tenants
   * @params {integer} $numTenants: Number of tenants in the application
   * @params {array}   $r:        Credit Check Applicaton Document from Elastic Search
   * @params {integer|bool} $disabled: Whether the input is disabled or not
   * @return {string}  HTML string
   */
  private function _generateTableRows($numTenants,$r,$disabled=0){
    $tntHtml = '';

    for($i = 0; $i < $numTenants; $i++){
      //Get every tenant name for each application from the credit check
      $tntApplicantName = !empty($r['application']) ? trim($r['application'][$i]['fname']) . ', ' . trim($r['application'][$i]['lname']): '';
      $idx = $i + 1;
      $tntInputName     = 'tenant_lease_full_name[' . $i . ']'; //Html input ["name"]
      //Generate table data
      $inptParams       = ['type'=>'text','id'=>$tntInputName,'class'=>'form-control occupant-name full-width','name'=>$tntInputName,'readonly'=>'1'];
      $tField = Html::td(Html::tag('p',Html::input($tntApplicantName,$inptParams)));
      $tdData  = implode('',[Html::td(''),Html::td(''),$tField]);
      
      //Generate table row
      $firstTr  = Html::tr($tdData);
     
      //Generate label
      $trList   = [Html::td('',['width'=>'15']),Html::td('',['width'=>'20']),Html::td( Html::tag('p','Full Name (First Name, Last Name)'),['width'=>'500'])];
      $secondTr = Html::tr(implode('',$trList));
      
      $tntHtml .= ($firstTr . $secondTr);
    }

    return $tntHtml;
  }
################################################################################
##########################    HTML INPUT GENERATING FUNCTIONS    ###############  
################################################################################
//------------------------------------------------------------------------------
  /**
   * @desc Generate input Html for a storing a tenant's signature
   * @params {string}  $prefix: Name prefix for the inputs
   * @params {integer} $numSignatures: Number of signatures to generate
   * @params {numTenants} $numTenants: Number of tenants that need their own set of inputs
   * @params {array}   $agreementData: Previous form data
   * @params {integer} $disabled: Whether the input is disabled or not
   * @return {array}   Array of HTML string
   */
  private function _generateTenantSignatureInpt($prefix,$numSignatures,$numTenants,$agreementData=[],$disabled=0){
    $signatures = [];
    $disabledParam = $disabled ? ['readonly'=>'1'] : [];
    for($i = 1; $i < $numSignatures+1; $i++){
      $inputHtml = '';
      for($j = 1; $j < $numTenants+1; $j++){
       $sigClass   = $j == 1 ? 'tnt-sig' : 'tnt-sig-' . ($j-1);
       $name       = $prefix . ($i) . '_' . ($j); //Name prefix for input HTML tag
       $inputHtml .= Html::label('Tenant (' . ($j) . ') Signature:&nbsp',['class'=>'control-label','for'=>$name]); //Input Label
       
       
       //a tag used for navigation that stores text values
       $aHtml      = $disabled ? '' : Html::tag('a',isset($agreementData[$name . '_hiddenTxtSig']) ? $agreementData[$name . '_hiddenTxtSig'] : '',['class'=>'signature','id'=>$name,'href'=>'#','style'=>isset($agreementData[$name . '_hiddenSig']) ? 'display:none' : 'display:inline-block' ] + $disabledParam);
       $inputHtml .=  $aHtml;
       //Hidden input for storing signature text
       $inputHtml .= $disabled ? '' : Html::input(isset($agreementData[$name . '_hiddenSig']) ? $agreementData[$name . '_hiddenSig'] : '',['type'=>'hidden','class'=>'form-control signature-txt-data ' . $sigClass,'name'=>$name . '_hiddenTxtSig'] + $disabledParam);
       
       //Hidden image for displaying signatures created by jSignature
       $inputHtml .=  Html::img(['src'=>isset($agreementData[$name . '_hiddenSig']) ? $agreementData[$name . '_hiddenSig'] : '','id'=>$name . '_sigImg','name'=>$name.'_sigImg','style'=>isset($agreementData[$name . '_hiddenSig']) ? 'display:inline-block' : 'display:none','class'=>'signature-img ' . $sigClass,'width'=>$this->_imageWidth,'height'=>$this->_imageHeight]);
       //Hidden input for storing corresponding image's data URI
       $inputHtml .=  $disabled ? '' : Html::input(isset($agreementData[$name . '_hiddenSig']) ? $agreementData[$name . '_hiddenSig'] : '',['type'=>'hidden','class'=>'form-control signature-img-data ' . $sigClass,'name'=>$name.'_hiddenSig']);
       //Button to trigger jSignature modal
       $inputHtml .= $disabled ? '' : '&nbsp;&nbsp;' . Html::button('Click Here to Sign ' . Html::i('',['class'=>'fa fa-pencil','title'=>'Use Signature Pad']),['class'=>'apply-sig ' . $sigClass]);
       $inputHtml .= Html::br();
      }
      $signatures[] = Html::span($inputHtml,['class'=>'sign-box']);
    }
    
    return $signatures;
  }
//------------------------------------------------------------------------------
  /**
   * @desc Generate input Html for a storing a tenant input field
   * @params {string}  $prefix: Name prefix for the inputs
   * @params {integer} $numTenants: Number of tenants that need their own set of inputs
   * @params {array}   $params:  Input HTML parameters and attributes
   * @params {array}   $default: Default values
   * @params {array}   $agreementData: Previous form data
   * @params {integer} $disabled: Whether the input is disabled or not
   * @return {array}   Array of HTML string
   */
private function _generateTenantField($prefix,$numTenants,$params,$default=[],$agreementData=[],$disabled=0){
  $fieldData = [];
  $params['num']  = !empty($params['num']) ? $params['num'] : 1;
  for($i = 1; $i < $params['num'] + 1; $i++){
    $inputHtml = '';
    for($j = 1; $j < $numTenants + 1; $j++){
      //Input HTML ["name"] field
      $name       = $prefix . $j . '_' . $i;
      //Generate input label
      $inputHtml .= Html::label('Tenant (' . ($j) . ') ' . $params['label'] . ':&nbsp;&nbsp;',['class'=>'control-label','for'=>$name]);

      //Determine HTML input value
      $val  = isset($agreementData[$name]) ? $agreementData[$name] : self::$_emptyDefault;
      $val  = (!empty($default[$j - 1]) && ($val === self::$_emptyDefault || empty($val))) ? $default[$j-1] : $val;
      
      $disabled   = !empty($params['readonly']) ? ['readonly'=>$params['readonly']] : [];
      //Generate input HTML tag
      $inputHtml .= Html::input($val,['type'=>'text','class'=>'form-control ' . $params['classes'],'name'=>$name,'id'=>$name] + $disabled);
    
      $inputHtml .= Html::br();
    }
    $fieldData[]  = $inputHtml;
  }
  
  return $fieldData;
}
//------------------------------------------------------------------------------
/**
   * @desc Generate input Html for a storing a tenant input field
   * @params {string}  $prefix: Name prefix for the inputs
   * @params {bool}    $isSupervisor: Whether the logged in user is the supervisor or not
   * @params {integer} $numSignatures: Number of signature inputs to generate for the whole form
   * @params {array}   $data: Default values
   * @params {array}   $agreementData: Previous form data
   * @params {integer} $disabled: Whether the input is disabled or not
   * @return {array}   Array of HTML string
   */
  private function _generateAgentSignatures($prefix,$isSupervisor,$numSignatures,$data,$agreementData=[],$disabled=0){
    $signatureHtml = [];
    
    $disabledParams = $disabled ? ['readonly'=>'1'] : []; //Set disabled parameter
    
    for($i = 1; $i < $numSignatures+1; $i++){
      $inputHtml = '';
      
      if(!$isSupervisor){
        $inputPrefix= 'manager_signature';
        $name       = 'manager_signature[' . ($i-1) . ']'; //Input name prefix for authorized agents
        $inputHtml .= Html::label(Html::b('Authorized Agent Signature: &nbsp;&nbsp;'),['class'=>'control-label','for'=>$name]); //Input label
        
        //Generate signature input HTML
        $inputHtml .= Html::input(isset($agreementData[$inputPrefix][$i-1]) ? $agreementData[$inputPrefix][$i-1] : $data['manager'],['type'=>'text','class'=>'form-control signature','name'=>$name,'id'=>$name,'readonly'=>'1']);
        
        //Generate manager print input label
        $inputPrefix= 'manager_print';
        $name       = 'manager_print[' . ($i-1) . ']';
        $inputHtml .= Html::br() .  Html::label(Html::b('Authorized Agent Printed Name:&nbsp;&nbsp;'),['class'=>'control-label','for'=>$name]);
        
        
        //Generate manager print input HTML
        $inputHtml .= Html::input(isset($agreementData[$inputPrefix][$i-1]) ? $agreementData[$inputPrefix][$i-1] : $data['manager'],['type'=>'text','name'=>$name,'id'=>$name,'class'=>'form-control','readonly'=>'1']) . Html::br();
      } 
      
      $name         = $prefix . '[' . ($i-1) . ']'; //["name"] prefix for input HTML
      $inputHtml   .= Html::label(Html::b('Supervisor Signature:&nbsp;&nbsp;'),['class'=>'control-label','for'=>$name]); //Label for input HTML for signatures
      
      //Generate input HTML tag for supervisor signature
      $inputHtml   .= Html::input('',['type'=>'text','class'=>'form-control signature','name'=>$name,'id'=>$name,'readonly'=>'1']);
      $inputPrefix  = 'agent_print';
      $name         = 'agent_print[' . ($i-1) . ']';
      
      //Label for supervisor print
      $inputHtml   .= Html::br() . Html::label(Html::b('Supervisor Print Name:&nbsp;&nbsp;'),['class'=>'control','for'=>$name]);
      
      //Generate input HTML tag for supervisor print
      $inputHtml   .= Html::input(isset($agreementData[$inputPrefix][$i-1]) ? $agreementData[$inputPrefix][$i-1] : $data['supervisor'],['type'=>'text','name'=>$name,'id'=>$name,'class'=>'form-control','readonly'=>'1']);
      
      //Generate supervisor title label
      $name         = 'agent_title[' . ($i - 1) . ']';
      //Generate supervisor title input HTML
      $signatureHtml[] = $inputHtml;
    }
    
    return $signatureHtml;
  }
//------------------------------------------------------------------------------  
/**
   * @desc Generate fixed top elements for navigating agreement form
   * @return {string}
   */ 
  private function _generateDownloadBar(){
    $html      = '';
    
    $resetBtns  = Html::button('Reset Initials',['class'=>'btn btn-secondary btn-sm','type'=>'button','id'=>'resetInitials']);
    $resetBtns .= Html::button('Reset Signature',['class'=>'btn  btn-secondary btn-sm','type'=>'button','id'=>'resetSignature']);

    $html   .= Html::div($resetBtns,['class'=>'btn-group-vertical reset-group','role'=>'group']);
    return $html;
  }
//------------------------------------------------------------------------------  
/**
   * @desc Generate fixed top elements for navigating through the signature inputs of agreement form
   * @return {string}
   */ 
  private function _generateToggleNav($r = []){
    $html              = '';
    $agreementComplete = isset($r['raw_agreement']) ? $r['raw_agreement'] : 1;
    $buttonTxt         = $agreementComplete == 0 ? 'Agree to Rental' . Html::repeatChar('&nbsp;',4) . Html::i('',['class'=>'fa fa-fw fa-check']) : 'Agreement Complete';
    $buttonCls         = $agreementComplete == 0 ? 'reportDoc' : '';

    $resetSig= Html::div(Html::button('Reset Signature(s)',['class'=>'btn btn-secondary btn-lg btn-block','type'=>'button','id'=>'resetSignature']),['style'=>'margin:0;padding:0;','class'=>'col-sm-3 text-center']);
    $btn     = Html::div(Html::button($buttonTxt,['class'=>'btn btn-success btn-lg btn-block ' . $buttonCls,'title'=>'Accept Agreement','type'=>'button','data-type'=>'AgreementReport']),['style'=>'margin:0;padding:0;','class'=>'col-sm-2 text-center']);
    $prevBtn = Html::div(Html::button('Print Preview' . Html::repeatChar('&nbsp;',4) . Html::i('',['class'=>'fa fa-fw fa-print']),['class'=>'btn btn-secondary btn-lg btn-block','type'=>'button','id'=>'generateAgreementPreview']),['style'=>'margin:0;padding:0;','class'=>'col-sm-2 text-center']);
    $upBtn   = Html::div(Html::button(Html::i('',['class'=>'glyphicon glyphicon-chevron-up']),['class'=>'btn btn-primary btn-lg prev-sig text-center','style'=>'width:100%;','type'=>'button']),['style'=>'margin:0;padding:0;','class'=>'col-sm-2']);
    $downBtn = Html::div(Html::button(Html::i('',['class'=>'glyphicon glyphicon-chevron-down']),['class'=>'btn btn-primary btn-lg next-sig text-center','style'=>'width:100%;','type'=>'button']),['style'=>'margin:0;padding:0;','class'=>'col-sm-3']);   
    $html   .= Html::div($upBtn . $resetSig . $btn . $prevBtn . $downBtn,['class'=>'row']);
    return $html;
  }

//------------------------------------------------------------------------------
/**
   * @desc Generate dropdown menu so a user can toggle the font of the signature they type
   * @params {string} $name: Name/#id of the select html
   * @return {string}
   */  
  private function _generateFontOptions($name = '',$classes='',$visible=false){
    //Available options that a user can toggle between
    //$fonts       = ['Select Font','Cedarville Cursive','League Script','Meddon','Miss Fajardose','Miss Saint Delafield','Mr De Haviland','Pinyon Script','YellowTail'];
    $fonts       = array_merge(['Select Font'],array_keys(Agreement::$fontFiles));
    $options     = '';
    
    foreach($fonts as $i => $f){
      //By default the first font will be the default
      $slt  = $i == 0 ? ['selected'=>'selected'] : [];
      $font = $f == 'Select Font' ? 'Source Sans Pro' : $f;
      //Have option text in the font it corresponds to
      $options .= Html::tag('option',$f,$slt +['style'=>'font-family:' . $font . ';','value'=>$font]);
    }
    //Create dropdown menu '<select></select>'
    $html  =  Html::tag('select',$options,['style'=>'display:' . $visible ? 'inline-block' : 'none' . ';font-family:' . 'Source Sans Pro','class'=>'fontPicker ' . $classes,'name'=>$name,'id'=>$name,'value'=>'Aguafina Script']);
    return $html;
  }
  
//------------------------------------------------------------------------------
/**
   * @desc Generate html for a signature option which includes:
    * Text box to enter signature
    * Select box to toggle the font
    * An optional button to replace the text with an image generated from jSignature
   * @params {string} $prefix: Prefix or name of the input element that will be used in the form
   * @params {array}  $options: Array of options that includes how many inputs will be generated, default values, and whether 
    * to include a font toggle option or jSignature pad
   * @return {array}
   */
  private function _generateSigningOptions($prefix,$options,$agreementData=[],$disabled=0){
    $signatureHtml = [];
    $disabledParam = !empty($options['readonly']) || $disabled ? ['readonly'=>'1'] : [];
    $options['num']= !empty($options['num']) ? $options['num'] : 1;
    
    for($i = 1; $i < $options['num'] + 1; $i++){
      
      $inputHtml = '';
      $name      = $prefix . '[' . ($i-1) . ']'; //Name or id of the input element
      
      $additionalCls = isset($options['classes']) ? $options['classes'] : '';
      
      //Input HTML tag parameters
      $inputOpts = [
        'type'  => isset($options['inputType']) ? $options['inputType'] : 'text',
        'class' => isset($options['isSignature']) && $options['isSignature'] ? 'signature ' . $additionalCls : $additionalCls,
        'name'  => $name,
        'id'    => $name,
      ];
      
      $inputOpts   += $disabledParam;
      
      //Set checked parameter to checked if input is a checkbox and is set to be
      //checked by default
      if(isset($options['checked']) || isset($agreementData[$prefix][$i])){
        $inputOpts['checked'] = 'checked';
        $options['val']       = 'checked';
      }

      $spanClass  = '';
      
      $inputOpts += isset($options['attr']) ? $options['attr'] : [];
      if(isset($options['useFont']) && $options['useFont']){
       $name       = $prefix;
       $aHtml      =  Html::tag('a','',['class'=>'signature','id'=>$name,'href'=>'#']);
       $inputHtml .=  $aHtml;
       //Hidden input for storing signature text
       $inputHtml .=  $disabled ? '' : Html::input('',['type'=>'hidden','class'=>'signature-txt-data ' . $additionalCls,'name'=>$name . 'hiddenTxtSig[' . ($i - 1) . ']']);
       
       //Hidden image for displaying signatures created by jSignature
       $inputHtml .=  Html::img(['src'=>'','id'=>$name . '_sigImg','name'=>$name.'_sigImg','style'=>'display:none','class'=>'signature-img ' . $additionalCls,'width'=>$this->_imageWidth,'height'=>$this->_imageHeight]);

       //Hidden input for storing corresponding image's data URI
       $inputHtml .=  $disabled ? '' : Html::input('',['type'=>'hidden','class'=>'signature-img-data ' . $additionalCls,'name'=>$name.'hiddenSig[' . ($i - 1) . ']']);
       //Button to trigger jSignature modal
       $inputHtml .=  $disabled ? '' : '&nbsp;&nbsp;' . Html::button('Click Here to Sign ' . Html::i('',['class'=>'fa fa-pencil','title'=>'Use Signature Pad']),['class'=>'apply-sig ' . $additionalCls]);
       
       $spanClass  = 'sign-box';
      } else {
        $value      = isset($agreementData[$prefix][$i-1]) ? $agreementData[$prefix][$i-1]: $options['val'];
        
        $inputHtml .= Html::input($value,$inputOpts);
        $spanClass .= !empty(preg_match('/number\-touchspin/',$additionalCls)) ?  ' inline-number-box ' : '';

      }
      //Group all elements in a span so they can be easily selected with JQuery
      $signatureHtml[] = Html::span($inputHtml,['class'=>$spanClass]);
      
    }
    
   
    return $signatureHtml;
  }
//------------------------------------------------------------------------------
/**
   * @desc Generate textarea html
   * @params {string} $name: Prefix to set the "name" attribute of the textarea
   * @params {integer}$num : Number of textareas to generate
   * @params {string} $value: Default value of textarea
   * @params {string} $cls: Additional CSS Classes
   * @params {array}  $agreementData: Request data from possible previous form (empty by default)
   * @params {integer} $rows: Number of rows for the textarea
   * @params {integer} $disabled: Whether the input is enabled or not
   * @return {array}  Array of html
   */
   private function _generateTextBox($name,$settings=['num'=>1],$value='',$cls='',$agreementData=[],$rows=1,$disabled=0){
     $textBoxes        = [];
     $settings['num']  = !empty($settings['num']) ? $settings['num'] : 1;
     for($i = 0; $i < $settings['num']; $i++){
       //Textarea value defaults to default value if no value is set in the form
       $val    = isset($agreementData[$name][$i]) ? $agreementData[$name][$i] : $value;
       //Generate textarea HTML tag
       $textBoxes[] = Html::tag('textarea',$val,['placeholder'=>self::$_emptyDefault,'name'=>$name . '[' . $i . ']','rows'=>$rows,'cols'=>'50','class'=>'form-control ' . $cls] + ($disabled ? ['readonly'=>'1'] : []));
     }
     return $textBoxes;
   }
//------------------------------------------------------------------------------
/**
   * @desc Generate radio button html
   * @params {string} $prefix: Prefix to set the "name" attribute of the textarea
   * @params {array}  $options : Input parameters
   * @params {array}  $agreementData: Previous form data
   * @params {integer} $disabled: Whether the input is enabled or not
   * @return {array}  Array of html
   */
   private function _generateRadioGroup($prefix,$options,$agreementData=[],$disabled=0){
     $signatureHtml = [];
     
    $disabledParam = $disabled ? ['readonly'=>'1'] : [];
    for($i = 1; $i < $options['count'] + 1; $i++){
      $name      = $prefix . '[' . ($i - 1) . ']'; //Name or id of the input element
      $defaultOpt= !empty($options['default']) ? $options['default'] : '';
      
      $additionalCls = isset($options['classes']) ? $options['classes'] : ''; //Input classes
      $inputOpts = [
        'type'  => 'radio',
        'class' => isset($options['isSignature']) && $options['isSignature'] ? 'signature ' . $additionalCls : $additionalCls,
        'name'  => $name,
        'id'    => $name
      ];
      
      $inputOpts += $disabledParam;
      
      //If the button is checked or in the previous form
      //check the radio button
      if(isset($agreementData[$prefix][$i-1])){
        $inputOpts['checked'] ='1';
      } 
      
      $optionsHtml = [];
      foreach($options['options'] as $v){
        $checked       = $v === $defaultOpt ? ['checked'=>'checked'] : [];
        //Create radio button
        $optionsHtml[] = Html::input($v,$inputOpts + $checked);
      }
       
      //Group all elements in a span so they can be easily selected with JQuery
      $signatureHtml[] = $optionsHtml;
    }
    return $signatureHtml;
   }
//------------------------------------------------------------------------------
   private function _generateOccupantInput(){
      $btns       = Html::button(Html::i('',['class'=>'fa fa-fw fa-minus-square']),['id'=>'removeOccupant','class'=>'btn btn-info pull-left']);
      $btns      .= Html::button(Html::i('',['class'=>'fa fa-fw fa-plus-square']),['id'=>'addOccupant','class'=>'btn btn-info','style'=>'margin-left: 85%;']);
      $btnRow     = Html::tr(Html::td('',['width'=>'15']) . Html::td('',['width'=>'20']) . Html::td(Html::tag('p',$btns),['width'=>'500']));      
      $input      = Html::input('',['type'=>'text','class'=>'form-control full-width','name'=>'occupant_name[0]','id'=>'occupant_name[0]','placeholder'=>self::$_emptyDefault]);     
      $labelRow   = Html::span('Full Name (First Name, Last Name)',['class'=>'full-width']);
      $inputRow   = Html::tr(Html::td('',['width'=>'15']) . Html::td('',['width'=>'20']) . Html::td(Html::tag('p',Html::span($input . Html::br() . $labelRow . Html::br(),['id'=>'occupant_row[0]']),['id'=>'occupantContainer']),['width'=>'500']));
      $html       = $btnRow . $inputRow;
      return $html;
   }
//------------------------------------------------------------------------------
   /**
   * @desc Generate hidden inputs
   * @params {string} $id: Credit Check Application Id
   * @return {string} html string
   */
   private function _generateHiddenInputs($id){
     $html = '';
     
     //Hidden input for storing application id
     $html .= Html::input($id,['name'=>'application_id','id'=>'application_id','type'=>'hidden','readonly'=>1]);
     
     //Signature fonts hidden inputs
     $html .= Html::input('Source Sans Pro',['name'=>'tnt-sig_font','id'=>'tnt-sig_font','type'=>'hidden']);
     $html .= Html::input('Source Sans Pro',['name'=>'tnt-initials_font','id'=>'tnt-initials_font','type'=>'hidden']);
     return $html;
   }
//------------------------------------------------------------------------------
   private function _getProrateSection($r){
     $rGlChat            = Helper::keyFieldName(HelperMysql::getService(Model::buildWhere(['prop'=>$r['prop']]), ['remark', 'service']), 'service');
     $label              = Html::label(Html::b('Move In Date: '),['for'=>'move_in_date']);
     $input              = Html::input(date('m/d/Y'),['type'=>'text','class'=>'form-control date','name'=>'move_in_date','id'=>'move_in_date']);
     $r['amount']        = $r['new_rent'];
     $prorateAmount      = FullBilling::getProrateAmount($r);
     $prorateLabel       = Html::label(Html::b('Prorate: '),['for'=>'prorate']);
     $fullBillingField   = FullBilling::getFullBillingField($prorateAmount, $rGlChat, date('m/d/Y'),['prorateCurrentMonth'=>'Prorate Current Month','prorateNextMonth'=>'Prorate Next Month', 'noProrate'=>'No Prorate']);
     //$html               = $label . $input . Html::repeatChar('&nbsp;',5) . $fullBillingField['prorate'];
     $html               = Html::div(Html::div($label  . $input,['class'=>'form-group']) . Html::div(Html::div($prorateLabel . $fullBillingField['prorate']),['class'=>'form-group','style'=>'margin-left:3.5%;']),['class'=>'form-inline']);
     return $html;
   }
#################################################################################
########################## FORM PROCESSING METHODS   ############################
#################################################################################
 //------------------------------------------------------------------------------ 
   private function _getSuccessMsg($name,$vData=[]){  
     $data = [  
       'emailPdf'            => Html::sucMsg('Your Agreement has been Successfully Emailed'),
       'update'              => Html::sucMsg('Signature and Initials Successfully Saved'),
       'store'               => Html::sucMsg('Your Rental Agreement has been Successfully Received'),
        '_generatePreviewPdf'=> Html::sucMsg('Your Agreement Preview is Ready'),
     ]; 
     return $data[$name]; 
   }  
 //------------------------------------------------------------------------------ 
   private function _getErrorMsg($name,$vData=[]){  
     $data = [  
       '_generatePreviewPdf' => Html::errMsg('Error Generating Agreement Preview'),
       'emailPdf'            => Html::errMsg('An error occurred, the email was unable to be sent.'),
       'update'              => Html::errMsg('You are missing a signature or initial'),
       'store'               => Html::errMsg('There was an error processing your agreement'),
     ]; 
     return $data[$name]; 
   }
################################################################################
############################ HELPER FUNCTIONS ##################################
################################################################################
//------------------------------------------------------------------------------  
  private function _generateInitials($fullName){
    $names     = explode(' ',$fullName);
    $initials  = implode(array_map(function($v){return !empty($v) ? ucfirst($v)[0] : ''; },$names),'');
    return $initials;
  }
//------------------------------------------------------------------------------
  private function _verifyRequest($reqData){
    foreach(self::$_verifyKeys as $k => $v){
      if(empty($reqData[$v])){
        return false;
      }
    }
    return true;
  }
}