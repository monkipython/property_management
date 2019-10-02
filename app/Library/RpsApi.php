<?php
namespace App\Library;
use App\Library\{Helper, TableName as T};
use \SoapClient;
class RpsApi {
  private $_soapVersion  = 1;
  private $_trace        = 1;
  private $_client;
  private static $_instance;
  
  public function __construct(){
    $url           = env('SOAP_PROTOCOL') . '://' . env('SOAP_HOST') . ':' . env('SOAP_PORT') . env('SOAP_ENDPOINT') . '?wsdl';
    $this->_client = new SoapClient($url,['trace'=>$this->_trace,'soap_version'=>$this->_soapVersion]);
  }
/**
 * @desc this getInstance is important because to activate __contract we need to call getInstance() first
 */
  public static function getInstance(){
    if ( is_null( self::$_instance ) ){
      self::$_instance = new self();
    }
    return self::$_instance;
  }
//------------------------------------------------------------------------------
  public function getAvailableTypes(){
    return $this->_client->__getTypes();
  }
//------------------------------------------------------------------------------
  public function getAvailableFunctions(){
    return $this->_client->__getFunctions();
  }
//------------------------------------------------------------------------------
  public function getLastXmlRequest(){
    return $this->_client->__getLastRequest();
  }
//------------------------------------------------------------------------------
  public function getDocumentData($drn){
    return $this->_client->GetDocument(['drn'=>$drn]);
  }
//------------------------------------------------------------------------------
  public function getRelatedDocs($drn){
    $imageDrn    = $data = [];
    $doc         = $this->_client->GetDocument(['drn'=>$drn])->GetDocumentResult->Document;
    $transaction = !empty($doc) ? $this->_client->GetTransaction(['jobKey'=>$doc->JobKey,'transId'=>$doc->TransId]) : [];
    $transaction = !empty($transaction) ? $transaction->GetTransactionResult->Documents->Doc : [];
    
    foreach($transaction as $v){
      $imageDrn[] = $v->Drn;
    }
    
    foreach($imageDrn as $i => $v){
      $r                  = $this->_client->GetImage(['drn'=>$v])->GetImageResult->Image;
      $data[$i]['check']['front']  = $this->_createImageTag(!empty($r) ? $r->FrontImage : '');
      $data[$i]['check']['rear']   = $this->_createImageTag(!empty($r) ? $r->RearImage : '');
    }
    return $data;
  }
################################################################################
##########################   HELPER FUNCTION   #################################  
################################################################################ 
/*
  Install ImageMagick onto Ubuntu Server / VM with:

  $ sudo apt-get update && sudo apt-get install -y php-imagick
 * 
 * Then restart your server
 */
//------------------------------------------------------------------------------
  private function _createImageTag($rawData=''){
    $id        = uniqid();
    $tiffName  = 'check_image_' . $id . '.tiff';
    $pngName   = 'check_image_' . $id . '.png';
    $encoded   = base64_encode('');
    if(!empty($rawData)){
      file_put_contents($tiffName,$rawData);
      exec('convert ' . $tiffName . ' ' . $pngName);
      $encoded = base64_encode(file_get_contents($pngName));
      unlink($tiffName);
      unlink($pngName);
    }

    $image = Html::img(['src'=>'data:image/png;base64,' . $encoded]);
    return $image;
  }
}