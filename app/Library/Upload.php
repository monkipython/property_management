<?php
namespace App\Library;
use App\Library\{Html, Helper, V, File, TableName AS T, Util};

class Upload{
  private static $_allowedExtensions = [];
  private static $_sizeLimit = 	52428800;
  private static $_inputName = 'qqfile';
  private static $_chunksFolder = '/tmp/chunks';

  private static $_chunksCleanupProbability = 0.001; // Once in 1000 requests on avg
  private static $_chunksExpireIn = 604800; // One week
  protected static $_uploadName;
  
  public static function getName(){
    $req = \Request::all();
    if(isset($req['qqfilename'])){
      return $req['qqfilename'];
    }
    if (isset($_REQUEST['qqfilename'])){
      return $_REQUEST['qqfilename'];
    }
    if (isset($_FILES[self::$_inputName])){
      return $_FILES[self::$_inputName]['name'];
    }
  }

  public static function getInitialFiles() {
    $initialFiles = [];

    for ($i = 0; $i < 5000; $i++) {
      array_push($initialFiles, array("name" => "name" + $i, uuid => "uuid" + $i, thumbnailUrl => "/test/dev/handlers/vendor/fineuploader/php-traditional-server/fu.png"));
    }

    return $initialFiles;
  }

  /**
   * Get the name of the uploaded file
   */
  public static function getUploadName(){
    return self::$_uploadName;
  }

  public static function combineChunks($uploadDirectory, $name = null) {
    $uuid = $_POST['qquuid'];
    if ($name === null){
      $name = self::getName();
    }
    $targetFolder = self::$_chunksFolder.DIRECTORY_SEPARATOR.$uuid;
    $totalParts = isset($_REQUEST['qqtotalparts']) ? (int)$_REQUEST['qqtotalparts'] : 1;

    $targetPath = join(DIRECTORY_SEPARATOR, array($uploadDirectory, $uuid, $name));
    self::$_uploadName = $name;

    if (!file_exists($targetPath)){
      mkdir(dirname($targetPath));
    }
    $target = fopen($targetPath, 'wb');

    for ($i=0; $i<$totalParts; $i++){
      $chunk = fopen($targetFolder.DIRECTORY_SEPARATOR.$i, "rb");
      stream_copy_to_stream($chunk, $target);
      fclose($chunk);
    }

    // Success
    fclose($target);

    for ($i=0; $i<$totalParts; $i++){
      unlink($targetFolder.DIRECTORY_SEPARATOR.$i);
    }

    rmdir($targetFolder);

    if (!is_null(self::$_sizeLimit) && filesize($targetPath) > self::$_sizeLimit) {
      unlink($targetPath);
      http_response_code(413);
      return array("success" => false, "uuid" => $uuid, "preventRetry" => true);
    }

    return array("success" => true, "uuid" => $uuid);
  }

  /**
   * Process the upload.
   * @param string $uploadDirectory Target directory.
   * @param string $name Overwrites the name of the file.
   */
  public static function handleUpload($uploadDirectory, $allowedExtensions = [],  $name = null){
    self::$_allowedExtensions = !empty($allowedExtensions) ? $allowedExtensions : self::$_allowedExtensions;
    if (is_writable(self::$_chunksFolder) && 1 == mt_rand(1, 1/self::$_chunksCleanupProbability)){
      // Run garbage collection
      self::cleanupChunks();
    }

    // Check that the max upload size specified in class configuration does not
    // exceed size allowed by server config
    if (self::toBytes(ini_get('post_max_size')) < self::$_sizeLimit || self::toBytes(ini_get('upload_max_filesize')) < self::$_sizeLimit){
      $neededRequestSize = max(1, self::$_sizeLimit / 1024 / 1024) . 'M';
      return array('error'=>"Server error. Increase post_max_size and upload_max_filesize to ".$neededRequestSize);
    }

    if (self::isInaccessible($uploadDirectory)){
      return array('error' => "Server error. Uploads directory isn't writable");
    }

    $type = $_SERVER['CONTENT_TYPE'];
    if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
      $type = $_SERVER['HTTP_CONTENT_TYPE'];
    }

    if(!isset($type)) {
      return array('error' => "No files were uploaded.");
    } else if (strpos(strtolower($type), 'multipart/') !== 0){
      return array('error' => "Server error. Not a multipart request. Please set forceMultipart to default value (true).");
    }

    // Get size and name
    $file = $_FILES[self::$_inputName];
    $size = $file['size'];
    if (isset($_REQUEST['qqtotalfilesize'])) {
      $size = $_REQUEST['qqtotalfilesize'];
    }

    if ($name === null){
      $name = self::getName();
    }

    // Validate name
    if ($name === null || $name === ''){
      return array('error' => 'File name empty.');
    }

    // Validate file size
    if ($size == 0){
      return array('error' => 'File is empty.');
    }

    if (!is_null(self::$_sizeLimit) && $size > self::$_sizeLimit) {
      return array('error' => 'File is too  large. Maximum allow is ' . Util::size_format(Self::$_sizeLimit, 2) . ' and your file size is : ' . Util::size_format($size, 2), 'preventRetry' => true);
    }

    // Validate file extension
    $pathinfo = pathinfo($name);
    $ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';

    if(self::$_allowedExtensions && !in_array(strtolower($ext), array_map("strtolower", self::$_allowedExtensions))){
      $these = implode(', ', self::$_allowedExtensions);
      return array('error' => 'File has an invalid extension, it should be one of '. $these . '.');
    }

    // Save a chunk
    $totalParts = isset($_REQUEST['qqtotalparts']) ? (int)$_REQUEST['qqtotalparts'] : 1;

    $uuid = $_REQUEST['qquuid'];
    if ($totalParts > 1){
      # chunked upload
      $chunksFolder = self::$_chunksFolder;
      $partIndex = (int)$_REQUEST['qqpartindex'];

      if (!is_writable($chunksFolder) && !is_executable($uploadDirectory)){
        return array('error' => 'Server error. Chunks directory is not writable or executable.');
      }

      $targetFolder = self::$_chunksFolder . DIRECTORY_SEPARATOR . $uuid;
      if (!file_exists($targetFolder)){
        mkdir($targetFolder);
      }

      $target = $targetFolder.'/'.$partIndex;
      $success = move_uploaded_file($_FILES[self::$_inputName]['tmp_name'], $target);

      return array("success" => true, "uuid" => $uuid);
    } else {
      # non-chunked upload
      $target = join(DIRECTORY_SEPARATOR, array($uploadDirectory, $uuid, $name));
      if ($target){
        self::$_uploadName = basename($target);
        if (!is_dir(dirname($target))){
          mkdir(dirname($target));
        }
        if (move_uploaded_file($file['tmp_name'], $target)){
          return array('success'=> true, "uuid" => $uuid);
        }
      }
      return array('error'=> 'Could not save uploaded file. The upload was cancelled, or server error encountered');
    }
  }

  /**
   * Process a delete.
   * @param string $uploadDirectory Target directory.
   * @params string $name Overwrites the name of the file.
   *
   */
  public static function handleDelete($uploadDirectory, $name=null){
    if (self::isInaccessible($uploadDirectory)) {
      return array('error' => 'Server error. Uploads directory is not writable' . (!self::isWindows() ? ' or executable.' : '.'));
    }

    $targetFolder = $uploadDirectory;
    $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $tokens = explode('/', $url);
    $uuid = $tokens[sizeof($tokens)-1];

    $target = join(DIRECTORY_SEPARATOR, array($targetFolder, $uuid));

    if (is_dir($target)){
      self::removeDir($target);
      return ["success" => true, "uuid" => $uuid];
    } 
    else {
      return [
      "success" => false,
      "error" => "File not found! Unable to delete.".$url,
      "path" => $uuid
      ];
    }
  }
//------------------------------------------------------------------------------
  public static function getHtml($id='fine-uploader'){
    return [
      'container'=>Html::div('', ['id'=>$id]) .  '<div id="uploadMsg"></div>',
      'hiddenForm'=>'<script type="text/template" id="qq-template">
        <div class="qq-uploader-selector qq-uploader" qq-drop-area-text="Drop files here">
          <div class="qq-total-progress-bar-container-selector qq-total-progress-bar-container">
            <div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-total-progress-bar-selector qq-progress-bar qq-total-progress-bar"></div>
          </div>
          <div class="qq-upload-drop-area-selector qq-upload-drop-area" qq-hide-dropzone>
            <span class="qq-upload-drop-area-text-selector"></span>
          </div>
          <div class="qq-upload-button-selector btn btn-info pull-right btn-sm">
            <div>Select files</div>
          </div>
          <span class="qq-drop-processing-selector qq-drop-processing">
            <span>Processing dropped files...</span>
            <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
          </span>
          <ul class="qq-upload-list-selector qq-upload-list" aria-live="polite" aria-relevant="additions removals">
            <li>
              <div class="qq-progress-bar-container-selector">
                <div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-progress-bar-selector qq-progress-bar"></div>
              </div>
              <span class="qq-upload-spinner-selector qq-upload-spinner"></span>
              <span class="qq-upload-file-selector qq-upload-file"></span>
              <span class="qq-edit-filename-icon-selector qq-edit-filename-icon" aria-label="Edit filename"></span>
              <input class="qq-edit-filename-selector qq-edit-filename" tabindex="0" type="text">
              <span class="qq-upload-size-selector qq-upload-size"></span>
              <button type="button" class="qq-btn qq-upload-cancel-selector qq-upload-cancel">Cancel</button>
              <button type="button" class="qq-btn qq-upload-retry-selector qq-upload-retry">Retry</button>
              <button type="button" class="qq-btn qq-upload-delete-selector qq-upload-delete pull-right">Delete</button>
              <span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>
            </li>
          </ul>

          <dialog class="qq-alert-dialog-selector">
            <div class="qq-dialog-message-selector"></div>
            <div class="qq-dialog-buttons">
              <button type="button" class="qq-cancel-button-selector">Close</button>
            </div>
          </dialog>

          <dialog class="qq-confirm-dialog-selector">
            <div class="qq-dialog-message-selector"></div>
            <div class="qq-dialog-buttons">
              <button type="button" class="qq-cancel-button-selector">No</button>
              <button type="button" class="qq-ok-button-selector">Yes</button>
            </div>
          </dialog>

          <dialog class="qq-prompt-dialog-selector">
            <div class="qq-dialog-message-selector"></div>
            <input type="text">
            <div class="qq-dialog-buttons">
              <button type="button" class="qq-cancel-button-selector">Cancel</button>
              <button type="button" class="qq-ok-button-selector">Ok</button>
            </div>
          </dialog>
        </div>
      </script>',
    
    ];
  }
//------------------------------------------------------------------------------
  public static function getListFile($r, $endPoint){
    $ul = [];
    foreach($r as $i=>$v){
      $active = ' active';
      $ul[] = [
        'value'=>Html::a(Html::span('', ['class'=>'fa fa-fw fa-file-pdf-o']) . ' ' . $v['name'], ['href'=>$endPoint . '/'. $v['uuid'], 'target'=>'_blank']),
        'param'=>['class'=>'eachPdf pointer' . $active, 'key'=>$v['uuid']]
      ];
    }
    $ul = Html::buildUl($ul, ['class'=>'nav nav-pills nav-stacked', 'id'=>'uploadList']);
    return Html::div($ul, ['class'=>'col-md-12']);
  }
//------------------------------------------------------------------------------
  public static function getViewlistFile($r, $endPoint, $id = 'upload',$param=[]){
    $ul                = [];
    $isMobileIos       = preg_match('/(ipod|iphone|ipad|ios)/',strtolower($_SERVER['HTTP_USER_AGENT']));
    foreach($r as $i=>$v){
      $active = $i == 0 ? ' active' : '';
      
      $dirPrefix          = preg_replace('/(.*)\/storage\/app\/public\/(.*)/','$2',$v['path']);
      list($prefix,$temp) = explode('/',$dirPrefix);
      $href               = \Storage::disk('public')->url($prefix .'/'. $v['uuid'] . '/' . $v['file']);
      $pdfParam           = $isMobileIos && $v['ext'] === 'pdf' ? '' : 'eachPdf';
      $hrefParam          = $isMobileIos && $v['ext'] === 'pdf' ? ['href'=>$href,'target'=>'_blank','title'=>'Click to View File'] : [];
      $deleteIcon         = !empty($param['includeDeleteIcon']) ? Html::i('',['class'=>'deleteFile fa fa-fw fa-trash text-red','uuid'=>$v['uuid'],'data-type'=>$v['type']]) . '   ' : '';
      $ul[]               = [
          'value'   => Html::a($deleteIcon . Html::span('',['class'=>'fa fa-fw fa-file-pdf-o']) . ' ' . $v['name'],$hrefParam),
          'param'   => ['class'=>$pdfParam . ' pointer ' . $active,'uuid'=>$v['uuid']],
      ];
    }
    $ul = Html::buildUl($ul, ['class'=>'nav nav-pills nav-stacked', 'id'=>$id . 'List']);
    $script = "<script> 
      $('.eachPdf').unbind('click').on('click', function(){
        var self = $(this);
        var uuid = $(this).attr('uuid');
        $('.eachPdf').removeClass('active');
        $(this).addClass('active');

        AJAX({
          url: '" . $endPoint . "/' + uuid,
          data: {size: 200}, 
          success: function(ajaxData){
            $('#".$id."View').html(ajaxData.html);
            
            // DELETE UPLOAD FILE
            UPLOADDELETE(self, uuid, '" . $endPoint . "','#gridTable');
          }
        });
      });
      $('.eachPdf').first().trigger('click');
    </script>";

    return Html::div(
      Html::div($ul, ['class'=>'col-md-3']) . Html::div('', ['class'=>'col-md-9', 'id'=>$id . 'View']), 
      ['class'=>'row']
    ). $script;
  }
//------------------------------------------------------------------------------
  public static function getViewContainerType($path, $height= '600', $width = '100%'){
    $larger = Html::a('View Larger', ['class'=>'btn btn-info pull-left btn-sm viewLarger', 'target'=>'_blank', 'href'=>$path]);
    $delete = Html::span('Delete', [
      'class'=>'btn btn-danger pull-right btn-sm deleteFile', 
      'data-type'=>'application',
    ]);
    
    if(preg_match('/pdf$/i', $path)){
      return ['html'=>Html::tag('iframe', '', ['src'=>$path, 'width'=>$width, 'height'=>$height]), 'type'=>'pdf', 'path'=>$path];
    } else{
      return ['html'=>Html::img(['src'=>$path, 'width'=>$width, 'height'=>$height]), 'type'=>'img', 'path'=>$path];
    }
  }
////------------------------------------------------------------------------------
//  public static function store($v, $extensionAllow){
//    $op = $v['op'];
//    $type = $v['data']['type'];
//    
//    $location = !empty(File::getLocation($op)[$type]) ? File::getLocation($op)[$type] : '';
//    if(!empty($location) && !file_exists($location)){
//      mkdir($location, '0755');
//    }
//    $defaultError = 'Fail to upload.';
//    
//    if(!empty($location)){
//      $vData = $v['data'];
//      $fileInfo = pathinfo($vData['qqfilename']);
//      $v['data']['ext']  = $fileInfo['extension'];
//      $v['data']['file'] = hash('ripemd160', $fileInfo['filename'] . rand(1, 10000)) . '.' . $fileInfo['extension'];
//      $uploadR = self::handleUpload($location, $extensionAllow, $v['data']['file']);
//      if(empty($uploadR['error']) && $uploadR['success']){
//        $uploadR['name'] = $vData['qqfilename'];
//        $uploadR['file'] = $v['data']['file'];
//        
//        $cls = '\App\Http\Controllers\Upload\ProcessUpload\\' . $op ;
//        $method = 'store';
//        return ($cls::$method($v, $location, Helper::mysqlDate())) ? $uploadR : Helper::echoJsonError($defaultError);
//      } else{
//        Helper::echoJsonError($uploadR['error']);
//      }
//    } else{
//      Helper::echoJsonError('Location does not exist.');
//    }
//  }
//------------------------------------------------------------------------------
  public static function startUpload($v, $extensionAllow){
    $op = $v['op'];
    $type = $v['data']['type'];
    $location = File::getLocation($op);
    $location = !empty($location[$type]) ? $location[$type] : '';
    if(!empty($location) && !file_exists($location)){
      mkdir($location, '0755');
    }
    if(!empty($location)){
      $vData = $v['data'];
      $fileInfo = pathinfo($vData['qqfilename']);
      $fileInfo['extension']    = strtolower($fileInfo['extension']);
      $v['data']['ext']         = $fileInfo['extension'];
      $v['data']['file']        = hash('ripemd160', $fileInfo['filename'] . rand(1, 10000)) . '.' . $fileInfo['extension'];
      $v['data']['qqfilename']  = self::strtolowerExtension($v['data']['qqfilename']);
      
      $uploadR = self::handleUpload($location, $extensionAllow, $v['data']['file']);
      if(empty($uploadR['error']) && $uploadR['success']){
        $uploadR['name'] = self::strtolowerExtension($vData['qqfilename']);
        $uploadR['file'] = $v['data']['file'];
        return ['location'=>$location, 'data'=>$v, 'uploadData'=>$uploadR];
      } else{
        Helper::echoJsonError(Html::errMsg($uploadR['error']), 'uploadMsg');
      }
    } else{
      Helper::echoJsonError(Html::errMsg('Location does not exist.'), 'uploadMsg');
    }
  }  
//------------------------------------------------------------------------------
  public static function getOrderField(){
    return ['qquuid', 'qqfilename', 'qqtotalfilesize', 'type'];
  }
//------------------------------------------------------------------------------
   public static function getRule($orderField = ''){
    $data = [
      'qqfile'=>'nullable',
      'qqfilename'=>'required|string|between:1,1000000',
      'qqpath'=>'required|string|between:1,1000000',
      'qquuid'=>'required|string|between:1,1000000',
      'qqtotalfilesize'=>'required',
      'qqpartindex'=>'nullable',
      'qqchunksize'=>'nullable',
      'qqpartbyteoffset'=>'nullable',
      'qqtotalparts'=>'nullable|integer',
      'done'=>'nullable|string|between:1,1000000',
      'op'=>'required|string|between:1,100',
      'type'=>'required|string|between:2,100',
      'foreign_id'=>'nullable|integer'
      
    ];
    return !empty($orderField) ? [$orderField=>$data[$orderField]] : $data;
  }
////------------------------------------------------------------------------------
//  public static function destroy($v){
////    $v = V::startValidate([
////      'rawReq'=>['uuid'=>$id] + $req->all(),
////      'tablez'=>[T::$fileUpload],
////      'orderField'=>['uuid', 'foreign_id', 'type'],
////      'validateDatabase'=>[
////        'mustExist'=>[
////          T::$fileUpload . '|uuid', 
////        ]
////      ]
////    ]);
//    if(!empty($v['op'])){
//      $cls = '\App\Http\Controllers\Upload\ProcessUpload\\' . $v['op'] ;
//      $method = 'destroy';
//      return $cls::$method($v);
//    }
//  }
####################################################################################################
#####################################     HELPER FUNCTION      #####################################
####################################################################################################
  
  /**
   * Returns a path to use with this upload. Check that the name does not exist,
   * and appends a suffix otherwise.
   * @param string $uploadDirectory Target directory
   * @param string $filename The name of the file to use.
   */
  protected static function getUniqueTargetPath($uploadDirectory, $filename){
    // Allow only one process at the time to get a unique file name, otherwise
    // if multiple people would upload a file with the same name at the same time
    // only the latest would be saved.

    if (function_exists('sem_acquire')){
      $lock = sem_get(ftok(__FILE__, 'u'));
      sem_acquire($lock);
    }

    $pathinfo = pathinfo($filename);
    $base = $pathinfo['filename'];
    $ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
    $ext = $ext == '' ? $ext : '.' . $ext;

    $unique = $base;
    $suffix = 0;

    // Get unique file name for the file, by appending random suffix.

    while (file_exists($uploadDirectory . DIRECTORY_SEPARATOR . $unique . $ext)){
      $suffix += rand(1, 999);
      $unique = $base.'-'.$suffix;
    }

    $result =  $uploadDirectory . DIRECTORY_SEPARATOR . $unique . $ext;

    // Create an empty target file
    if (!touch($result)){
      // Failed
      $result = false;
    }

    if (function_exists('sem_acquire')){
      sem_release($lock);
    }

    return $result;
  }

  /**
   * Deletes all file parts in the chunks folder for files uploaded
   * more than chunksExpireIn seconds ago
   */
  protected static function cleanupChunks(){
    foreach (scandir(self::$_chunksFolder) as $item){
      if ($item == "." || $item == "..")
      continue;

      $path = self::$_chunksFolder.DIRECTORY_SEPARATOR.$item;

      if (!is_dir($path))
      continue;

      if (time() - filemtime($path) > self::$_chunksExpireIn){
      self::removeDir($path);
      }
    }
  }

  /**
   * Removes a directory and all files contained inside
   * @param string $dir
   */
  protected static function removeDir($dir){
    foreach (scandir($dir) as $item){
      if ($item == "." || $item == "..")
      continue;

      if (is_dir($item)){
      self::removeDir($item);
      } else {
      unlink(join(DIRECTORY_SEPARATOR, array($dir, $item)));
      }
    }
    rmdir($dir);
  }

  /**
   * Converts a given size with units to bytes.
   * @param string $str
   */
  protected static function toBytes($str){
    $val = trim($str);
      //Debug Test
      $val = substr($val,0,-1);

    $last = strtolower($str[strlen($str)-1]);
    switch($last) {
      case 'g': $val *= 1024;
      case 'm': $val *= 1024;
      case 'k': $val *= 1024;
    }
    return $val;
  }

  /**
   * Determines whether a directory can be accessed.
   *
   * is_executable() is not reliable on Windows prior PHP 5.0.0
   *  (http://www.php.net/manual/en/function.is-executable.php)
   * The following tests if the current OS is Windows and if so, merely
   * checks if the folder is writable;
   * otherwise, it checks additionally for executable status (like before).
   *
   * @param string $directory The target directory to test access
   */
  protected static function isInaccessible($directory) {
    $isWin = self::isWindows();
    $folderInaccessible = ($isWin) ? !is_writable($directory) : ( !is_writable($directory) && !is_executable($directory) );
    return $folderInaccessible;
  }
  
  /**
   * Determines is the OS is Windows or not
   *
   * @return boolean
   */
  protected static function isWindows() {
    $isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    return $isWin;
  }
  /**
   * Lower case all file extension handles in a file name
   *
   * @return string
   */
  protected static function strtolowerExtension($file){
    $tokens = explode('.',$file);
    foreach($tokens as $i => $v){
      $tokens[$i] = $i >= 1 ? strtolower($v) : $v;
    }
    return implode('.',$tokens);
  }
}
