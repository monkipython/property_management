<?php
namespace App\Library;
use App\Library\{Html};
use Storage;
// https://github.com/hanneskod/libmergepdf
use iio\libmergepdf\{Merger, Pages};

class PDFMerge {

  /**
   * @desc it is used to merge multiple PDF files into one file.
   * @param {array} 
   * "paths"->all files location (Require)
   * "files"->all files name. For remove all temporary files after merge done.(Require)
   * "fileName"->output file name. (Require)
   * "href"->File download url (Require)
   * "msg"->Pop up message. (Optional)
   * @return {array} return pop up information.
  */
  public static function mergeFiles($data){
    $result       = [];
    $msg          = isset($data['msg']) ? $data['msg'] : 'Your file is ready. Please click the link here to download it';
    $isDeleteFile = isset($data['isDeleteFile']) ? $data['isDeleteFile'] : true;
    if(!empty($data) && isset($data['paths']) && isset($data['files']) && isset($data['fileName']) && isset($data['href'])){
      $href     = $data['href'];
      $merger   = new Merger;
      $first    = !empty($data['paths']) ? $data['paths'][0] : '';
      $merger->addFile($first);
      for($i = 1; $i < count($data['paths']); $i++){
        $fname = $data['paths'][$i];
        $merger->addFile($fname);   
      }

      $finalPdf = $merger->merge();
      Storage::put($data['fileName'],$finalPdf);
      // Remove all temporary files
      if($isDeleteFile){
        Storage::delete($data['files']);
      }
      
      $result = [
        'popupMsg'=>Html::a($msg, [
          'href'=>$href,
          'target'=>'_blank',
          'class'=>'downloadLink'
        ])
      ];
    }
    return $result;
  }
}