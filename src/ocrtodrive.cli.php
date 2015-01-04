
<?php
require_once 'vendor/autoload.php';
require_once 'cli-framework/cli.php';
require_once 'googledrivehelper.class.php';

class ocrtodrivecli extends CLI{

  private $gid = "";
  private $gsecret = "";

  private $logger = null;


  function __construct($appname = null, $author = null, $copyright = null) {
    $this->logger = new \Monolog\Logger("GoogleDriveHelperClass");
    $this->logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());

    parent::__construct('OCR My Google Drive PDF', 'Guillaume Marchand', '(c) 2014');
  }


  public function main(){
    $tmpinputfname = "/tmp/ocrin_".date("Ymd_His").".pdf";
    $tmpoutputfname = "/tmp/ocrout_".date("Ymd_His").".pdf";
    $searchpdftoocr = "('me' in owners) and (mimeType = 'application/pdf') and (not properties has { key='ocrmypdf' and value='true' and visibility='PUBLIC' })";
    $ocrparam = "-dcsv -l fra ".$tmpinputfname." ".$tmpoutputfname." 2>&1";

    $gdrive = new Google_Drive_Helper($this->gid, $this->gsecret);

    // Search file to OCR
    $result = $gdrive->findFiles($searchpdftoocr,1);

    // Save file to temp dir
    if (!is_array($result) or count($result) == 0) {
      $this->logger->addInfo("No File to OCR, exit");
      exit(0);
    }

    $file = $result[0];

    $this->logger->addInfo("File to OCR : ".$file->originalFilename." (Size : ".$file->fileSize.")");

    $content = $gdrive->downloadFile($file->downloadUrl);

    $handle = fopen($tmpinputfname, "w");
    fwrite($handle, $content);
    fclose($handle);
    $this->logger->addInfo("Downloaded File : ".$tmpinputfname);

    // Exec OCR
    $this->logger->addInfo("Begin OCR... ".$ocrparam);

    exec('../OCRmyPDF/OCRmyPDF.sh '.$ocrparam,$output,$retval);
    $this->logger->addInfo("OCR Output ", $output);
    $this->logger->addInfo("OCR return code : ".$retval);
    $this->logger->addInfo("End OCR : ".$tmpoutputfname);

    if($retval != 0) {
      $this->logger->addError("OCR Error, exit");
      exit(0);
    }

    // Update file to Google Drive
    $this->logger->addInfo("Upload new revision of file to Drive ");
    $retupdate = $gdrive->updateFile( $file->id, $tmpoutputfname, true) ;

    if($retupdate == NULL) {
      $this->logger->addError("Update Google Drive File Content Error, exit");
      exit(0);
    }

    $this->logger->addInfo("Add Property to Drive ");
    $retprop = $gdrive->insertProperty( $file->id, "ocrmypdf", "true", "PUBLIC");
    if($retprop == NULL) {
      $this->logger->addError("Update Google Drive File Property  Error, exit");
      exit(0);
    }

    unlink($tmpinputfname);
    unlink($tmpoutputfname);

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
}

/**
*IMPORTANT, instantiate your class! i.e. new Classname();
*/
new Ocrtodrivecli();
?>
