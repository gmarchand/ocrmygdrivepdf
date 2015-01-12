
<?php
require_once 'vendor/autoload.php';
require_once 'cli-framework/cli.php';
require_once 'googledrivehelper.class.php';

class ocrtodrivecli extends CLI{

  private $gid = "";
  private $gsecret = "";
  private $maxsize = 0;
  private $logger = null;


  function __construct($appname = null, $author = null, $copyright = null) {
    $this->logger = new \Monolog\Logger("GoogleDriveHelperClass");
    $this->logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());

    parent::__construct('OCR My Google Drive PDF', 'Guillaume Marchand', '(c) 2014');
  }


  public function main(){
    $tmpinputfname = "/tmp/ocrin_".date("Ymd_His").".pdf";
    $tmpoutputfname = "/tmp/ocrout_".date("Ymd_His").".pdf";

    $searchpdftoocr = "('me' in owners) and (mimeType = 'application/pdf')";
    $searchpdftoocr .= " and (trashed=false)";
    $searchpdftoocr .= " and (not properties has { key='ocrmypdf' and value='true' and visibility='PUBLIC' })";
    $searchpdftoocr .= " and (not properties has { key='ocrmypdf' and value='maxsize' and visibility='PUBLIC' })";
    $searchpdftoocr .= " and (not properties has { key='ocrmypdf' and value='error' and visibility='PUBLIC' })";

    $ocrparam = "-dcsv -l fra ".$tmpinputfname." ".$tmpoutputfname." 2>&1";


    // Delete old temp files
    array_map('unlink', glob("/tmp/ocr*.pdf"));

    $gdrive = new Google_Drive_Helper($this->gid, $this->gsecret);

    // Search file to OCR
    $result = $gdrive->findFiles($searchpdftoocr,1);

    // Save file to temp dir
    if (!is_array($result) or count($result) == 0) {
      $this->logger->addInfo("No File to OCR, exit");
      exit(0);
    }

    $file = $result[0];

    $this->logger->addInfo("File to OCR : ".$file->originalFilename." (id : $file->id)(Size : ".$file->fileSize.")");

    // If Filesize > maxfile, do nothing. Add property ocrmypdf = maxsize
    if($file->fileSize > $this->maxsize) {
      $this->logger->addInfo("File is too big. No OCR will be done. Max = ".$this->maxsize);
      $retprop = $gdrive->insertProperty( $file->id, "ocrmypdf", "maxsize", "PUBLIC");
      if($retprop == NULL) {

        $this->logger->addError("Update Google Drive File Property  Error, exit");
        exit(0);
      }
      exit(0);
    }
    $content = $gdrive->downloadFile($file->downloadUrl);

    if($content == null) {
      $this->logger->addError("Not possible to download file, exit");
      exit(0);
    }
    
    $handle = fopen($tmpinputfname, "w");
    fwrite($handle, $content);
    fclose($handle);
    $this->logger->addInfo("Downloaded File : ".$tmpinputfname);

    // Exec OCR
    $this->logger->addInfo("Begin OCR... ".$ocrparam);

    exec(dirname(__FILE__).'/../OCRmyPDF/OCRmyPDF.sh '.$ocrparam,$output,$retval);
    $this->logger->addInfo("OCR Output ", $output);
    $this->logger->addInfo("OCR return code : ".$retval);
    $this->logger->addInfo("Result Filename : ".$tmpoutputfname);

    if($retval != 0) {
      $this->logger->addError("OCR can't make the job, we exclude this file $file->originalFilename");
      $retprop = $gdrive->insertProperty( $file->id, "ocrmypdf", "error", "PUBLIC");
      exit(0);
    }

    // Update file to Google Drive
    $this->logger->addInfo("Upload new revision of file to Drive ");
    $retupdate = $gdrive->updateFile( $file->id, $tmpoutputfname, true) ;

    if($retupdate == NULL) {
      $this->logger->addError("Update Google Drive File Content Error, exit");
      exit(0);
    }


    $retprop = $gdrive->insertProperty( $file->id, "ocrmypdf", "true", "PUBLIC");
    if($retprop == NULL) {
      $this->logger->addError("Update Google Drive File Property  Error, exit");
      exit(0);
    }



  }


  public function option_gid($opt = null){
    if($opt == 'help'){
      return 'Google Account Id';
    }
    $this->gid = $opt;
  }



  public function option_gsecret($opt = null){
    if($opt == 'help'){
      return 'Google Account Secret';
    }

    $this->gsecret = $opt;

  }

  public function option_maxsize($opt = null){
    if($opt == 'help'){
      return 'Max Size (octet) of PDF to OCR, else do nothing';
    }

    $this->maxsize = $opt;

  }
}

/**
*IMPORTANT, instantiate your class! i.e. new Classname();
*/
new Ocrtodrivecli();
?>
