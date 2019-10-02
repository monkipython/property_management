<?php
namespace App\Library;
class Mail{
  private static $devEmail = 'sean@pamamgt.com,jonathan@dataworkers.com,joshua@dataworkers.com';
  public static function emailList($cls){
    $cls = preg_replace('/Controller/', '', last(explode('\\', $cls)));
    $data = [
      'CreditCheck'=>[
      ],
      'debug'=>'sean@pamamgt.com'
    ];
    return $data[$cls];
  }
//------------------------------------------------------------------------------
//  public static function send($dt){
//    $to        = (strtolower(env('APP_ENV')) == 'production') ? $dt['to'] : self::$devEmail;
//    $cc        = (!empty($dt['cc'])) ? "CC: " . $dt['cc']  . "\r\n": "";
//    $bcc       = (!empty($dt['bcc'])) ? "Bcc: " . $dt['bcc']  . ",sean@pamamgt.com\r\n": "Bcc: sean@pamamgt.com" . "\r\n";
//    $fileName  = (!empty($dt['filename'])) ? $dt['filename']  . "\r\n": '';
//    
////    $from      = "From: =?utf-8?b?" . base64_encode($dt['from']) . "?= <gtac-www.plm@siemens.com>";
//    $from      = $dt['from'];
//    $subject   = "=?utf-8?b?" . base64_encode($dt['subject']) . "?=";
//    $msg       = $dt['msg'];  
//    
//
//    //read from the uploaded file & base64_encode content for the mail
//    //$file_tmp_name = '/var/www/test.pdf';
//    //Attachement
//    if(!empty($fileName)){
//      $pathFilename = $dt['pathFilename'];
//      $handle = fopen($pathFilename, "r"); 
//      $content = fread($handle, filesize($pathFilename));
//      fclose($handle);
//      $encoded_content = chunk_split(base64_encode($content));
//    }
//
//    $boundary = md5("sanwebe"); 
//    //header
//    $headers = "MIME-Version: 1.0\r\n"; 
//    $headers .= "From: $from\r\n";
//    $headers .= "Reply-To: $from\r\n";
//    $headers .= $cc;
//    $headers .= $bcc;
//    $headers .= "Content-Type: multipart/mixed; boundary = $boundary\r\n\r\n"; 
//
//    //plain text 
//    $body = "--$boundary\r\n";
//    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
//    $body .= "Content-Transfer-Encoding: base64\r\n\r\n"; 
//    $body .= chunk_split(base64_encode($msg)); 
//
//    //attachment
//    if(!empty($fileName)){
//      $body .= "--$boundary\r\n";
//      $body .="Content-Type: application/pdf; name=$fileName\r\n";
//      $body .="Content-Disposition: attachment; filename=$fileName\r\n";
//      $body .="Content-Transfer-Encoding: base64\r\n";
//      $body .="X-Attachment-Id: ".rand(1000,99999)."\r\n\r\n"; 
//      $body .= $encoded_content; 
//    }
//    return mail($to, $subject, $body, $headers);
//  }
//------------------------------------------------------------------------------
  public static function send($dt){
    $to        = (strtolower(env('APP_ENV')) == 'production') ? $dt['to'] : self::$devEmail;
    $cc        = (!empty($dt['cc'])) ? "CC: " . $dt['cc']  . "\r\n": "";
    $bcc       = (!empty($dt['bcc'])) ? "Bcc: " . $dt['bcc']  . ",sean@pamamgt.com\r\n": "Bcc: sean@pamamgt.com" . "\r\n";
    $from      = $dt['from'];
    $subject   = $dt['subject']; 
    $msg       = $dt['msg'];  
    $filename  = (isset($dt['filename'])) ? $dt['filename'] : '';
    
    //read from the uploaded file & base64_encode content for the mail
    //$file_tmp_name = '/var/www/test.pdf';
    //Attachement
    $boundary = md5("sanwebe"); 
    //header
    $headers = "MIME-Version: 1.0\r\n"; 
    $headers .= "From: $from\r\n";        
    $headers .= "Reply-To: $from\r\n";
    $headers .= $cc;
    $headers .= $bcc;
    $headers .= "Content-Type: multipart/mixed; boundary = $boundary\r\n\r\n"; 

    //plain text 
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n"; 
    $body .= chunk_split(base64_encode($msg)); 

    //attachment
    if(!empty($filename)){
      if(!is_array($filename)){
        $filename = [$filename];
        $dt['pathFilename'] = [$dt['pathFilename']];
      }
      
      foreach($filename as $i=>$file){
        $file_tmp_name = $dt['pathFilename'][$i];

        $handle = fopen($file_tmp_name, "r"); 
        $content = fread($handle, filesize($file_tmp_name));
        fclose($handle);
        $encoded_content = chunk_split(base64_encode($content));
        
        $body .= "--$boundary\r\n";
        $body .="Content-Type: application/pdf; name=$file\r\n";
        $body .="Content-Disposition: attachment; filename=$file\r\n";
        $body .="Content-Transfer-Encoding: base64\r\n";
        $body .="X-Attachment-Id: ".rand(1000,99999)."\r\n\r\n"; 
        $body .= $encoded_content; 
      }
    }
    return mail($to, $subject, $body, $headers);
  }
}