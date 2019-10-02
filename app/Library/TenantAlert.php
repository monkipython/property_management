<?php
namespace App\Library;
class TenantAlert{
  private static $_password;
  private static $_user = 'pama2api';
  private static $_url;
  private static $_packageID;
  private static $_email = 'apiorder@pamamgt.com';
  private static $_instance; 

  public function __construct() {
    self::$_password  = (env('APP_ENV') == 'production') ? '353ddab02a5f9071c0d13482d36e9ab0' : '5bc30ad46567ff7a4ee61e3b32a9c186';
    self::$_url       = (env('APP_ENV') == 'production') ? 'https://api.tenantalert.com/api' : 'https://api-dev.tenantalert.com/api';
    self::$_packageID = (env('APP_ENV') == 'production') ? 139 : 20;
  }
//------------------------------------------------------------------------------
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }
//------------------------------------------------------------------------------
  public static function processCredit($v){
    // Build XML for submitting to Credit Check company
    $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    $xml .= "<xml>";
    $xml .= "  <action>NewApplication</action>";
    $xml .= "  <user>".self::$_user."</user>";
    $xml .= "  <email>".self::$_email."</email>";
    $xml .= "  <password>".self::$_password."</password>";
    $xml .= "  <request>";
    $xml .= "    <end_user_name>".self::$_user."</end_user_name>";
    $xml .= "    <package_id>".self::$_packageID."</package_id>";
    $xml .= "    <applicant>";
    $xml .= "      <fname>". $v['fname'] ."</fname>";
    $xml .= "	     <mname>". $v['mname'] ."</mname>";
    $xml .= "	     <lname>". $v['lname'] ."</lname>";
    $xml .= "        <suffix>". $v['suffix'] ."</suffix>";
    $xml .= "	     <dob>". date('m/d/Y', strtotime($v['dob'])) ."</dob>";
    $xml .= "	     <ssn>". preg_replace('/\-+/', '', $v['social_security']) ."</ssn>";
    $xml .= "      <address>";
    $xml .= "        <street_number>". $v['street_num'] ."</street_number>";
    $xml .= "        <street_direction>north</street_direction>";
    $xml .= "        <street_name>". $v['street_name'] ."</street_name>";
    $xml .= " 		   <street_type>ally</street_type>";
    $xml .= " 		   <suite>". $v['tnt_unit'] ."</suite>";
    $xml .= "        <city>". $v['city'] ."</city>";
    $xml .= "        <state>". $v['state'] ."</state>";
    $xml .= "        <zip>". $v['zipcode'] ."</zip>";
    $xml .= "      </address>";
    $xml .= "      <extended_fields>";
    $xml .= "        <limelyte_drivers_license>";
    $xml .= "          <drivers_license_number>". $v['driverlicense'] ."</drivers_license_number>";
    $xml .= "          <license_state>". $v['driverlicensestate'] ."</license_state>";
    $xml .= "        </limelyte_drivers_license>";
    $xml .= "      </extended_fields>";
    $xml .= "    </applicant>";
    $xml .= "  </request>";
    $xml .= "</xml>";
    // Processs the credit check by sending the data in XML using CURL
    return self::_processCurl($xml);
  }
//------------------------------------------------------------------------------
  public static function viewCreditReport($applicationId){
    $response = array();
    // evicted 199833
    // Build XML for submitting to Credit Check company
    $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    $xml .= "<xml>";
    $xml .= "  <action>GetApplication</action>";
    $xml .= "  <user>".self::$_user."</user>";
    $xml .= "  <email>".self::$_email."</email>";
    $xml .= "  <password>".self::$_password."</password>";
    $xml .= "  <request>";
    $xml .= "    <response_format>detailed</response_format>";
    $xml .= "      <application_id>" . $applicationId . "</application_id>";
    $xml .= " 	   <render_applicant_extended_fields>";
    $xml .= "        <ari_criminal_info/>";
    $xml .= "        <ari_evictions_info/>";
    $xml .= "        <limelyte_education/>";
    $xml .= "        <limelyte_tenancy/>";
    $xml .= "        <limelyte_previous_tenancy/>";
    $xml .= "        <limelyte_professional_license/>";
    $xml .= "        <limelyte_employment/>";
    $xml .= "        <limelyte_drivers_license/>";
    $xml .= "      </render_applicant_extended_fields>";
    $xml .= "  </request>";
    $xml .= "</xml>";
    // Processs the credit check by sending the data in XML using CURL
    $result = self::_processCurl($xml);
    // Validating the data from Tenant Alert 
    if(isset($result->status) && $result->status == "OK" && isset($result->result) && isset($result->result->application) && isset($result->result->application->status) ){
      if($result->result->application->status == 'Completed'){
        $response['status'] = 'ok';
        $response['result'] = $result;
      } else if($result->result->application->status == 'Processing'){
        $response['status'] = 'processing';
      } else{ // Error from TenantAlert
        $response['status'] = 'error';
      }
    } else{// Error from TenantAlert
      $response['status'] = 'error';
    }
    return $response;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################  
  private static function _processCurl($xml){
    $response = '';
    $ch = curl_init(self::$_url);
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array("Content-Type: application/xml"));
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt( $ch, CURLOPT_POST, true );
    $response = curl_exec($ch);
    $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $response = simplexml_load_string($response);
    return $response;
  }
}