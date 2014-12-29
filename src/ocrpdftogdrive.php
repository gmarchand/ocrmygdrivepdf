<?php
/**********************************
 * $ php sendfiletogdrive.php <FileToSend> <GDriveScanDirName>
 * With Google API Console. You need to create a "Client ID for native application" as credentials
 * TODO : Travaille de refactoring pour faire une classe avec m�thode
 */

require_once 'vendor/autoload.php';

$logger = new \Monolog\Logger("log");
$logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());



// Google Authentication
$client = new Google_Client();

$apiConfig['use_objects'] = true;

$arguments = getopt("",array("gid:","gsecret:"));


foreach($arguments as $key=>$value){
    if($value == ''){
        error_log("Config variable ".$key." is not set");
        exit(1);
    } else {
        $$key = trim($value);
        //echo "KEY : *".$key."* : *".$$key."*\n";
    }
}
try {

    $client->setClientId($gid);
    $client->setClientSecret($gsecret);
    $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
    $client->setAccessType('offline');
    $client->setApprovalPrompt('force');
    $client->setScopes(array('https://www.googleapis.com/auth/drive'));

    $authUrl = $client->createAuthUrl();
} catch (Exception $e) {
    error_log('OAUTH auth exception : '.  $e->getMessage());
    exit(1);
}

// TODO : Find why Root Home Dir is not set
if (getenv("HOME") =='/') {
    error_log("PISCANTODRIVE: HOME Path set to '/'. Set to '/root'");
    putenv("HOME=/root");
}


// Path to save Oauth access token in JSON Format
$filename = getenv("HOME").'/piscantogdrive/credentials/accesstoken.json';
if(!file_exists(dirname($filename))){
    mkdir(dirname($filename),0700,true);
}

if(!file_exists($filename)){
    error_log("PISCANTODRIVE: No access token found in ".$filename.". Launch script by CLI");
    //Request authorization : Need to find an auth code in Web Browser
    print "Please visit:\n$authUrl\n\n";
    print "Please enter the auth code:\n";
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for access token
    $accessToken = $client->authenticate($authCode);
    //save access token
    file_put_contents($filename,$accessToken);
    $client->setAccessToken($accessToken);
} else {
    // Get access token
    $accessToken = file_get_contents($filename);
}

$client->setAccessToken($accessToken);



// #### AUTH END
// Search PDF to OCR

$tmpinputfname = "/tmp/ocrin_".date("Ymd_His").".pdf";
$tmpoutputfname = "/tmp/ocrout_".date("Ymd_His").".pdf";


$searchpdftoocr = "('me' in owners) and (mimeType = 'application/pdf') and (not properties has { key='ocrmypdf' and value='true' and visibility='PUBLIC' })";
$ocrparam = "-dcsv -l fra ".$tmpinputfname." ".$tmpoutputfname." 2>&1";

$service = new Google_Service_Drive($client);

// Search file to OCR
$result = findFiles($service, $searchpdftoocr,1);

// Save file to temp dir
if (!is_array($result) or count($result) == 0) {
  $logger->addInfo("No File to OCR, exit");
  exit(0);
}


$file = $result[0];

$logger->addInfo("File to OCR : ".$file->originalFilename);
$content = downloadFile($service,$file->downloadUrl,$client);

$handle = fopen($tmpinputfname, "w");
fwrite($handle, $content);
fclose($handle);
$logger->addInfo("Downloaded File : ".$tmpinputfname);



// Exec OCR
$logger->addInfo("Begin OCR... ".$ocrparam);

exec('../OCRmyPDF/OCRmyPDF.sh '.$ocrparam,$output,$retval);
$logger->addInfo("OCR Output ", $output);
$logger->addInfo("OCR return code : ".$retval);
$logger->addInfo("End OCR : ".$tmpoutputfname);

// Insert Property

if($retval != 0) {
  $logger->addInfo("OCR Error, exit");
  exit(0);
}

$logger->addInfo("Upload new revision of file to Drive ");
updateFile($service, $file->id, $tmpoutputfname, true) ;

$logger->addInfo("Add Property to Drive ");
insertProperty($service, $file->id, "ocrmypdf", "true", "PUBLIC");

// Update file to Google Drive

//unlink($tmpinputfname);
//unlink($tmpoutputfname);
/**
* Download a file's content.
*
* @param Google_DriveService $service Drive API service instance.
* @param File $file Drive File instance.
* @return String The file's content if successful, null otherwise.
*/
function downloadFile($service, $downloadUrl,$client) {

  if ($downloadUrl) {
    $request = new Google_Http_Request($downloadUrl, 'GET', null, null);

    $SignhttpRequest = $client->getAuth()->sign($request);
    $httpRequest = $client->getIo()->makeRequest($SignhttpRequest);

    if ($httpRequest->getResponseHttpCode() == 200) {
      return $httpRequest->getResponseBody();
    } else {
      // An error occurred.
      return null;
    }
  } else {
    // The file doesn't have any content stored on Drive.
    return null;
  }
}

    /**
    * Insert a new custom file property.
    *
    * @param Google_DriveService $service Drive API service instance.
    * @param String $fileId ID of the file to insert property for.
    * @param String $key ID of the property.
    * @param String $value Property value.
    * @param String $visibility 'PUBLIC' to make the property visible by all apps,
    *                           or 'PRIVATE' to make it only available to the app that created it.
    * @return Google_Property The inserted property. NULL is returned if an API error occurred.
    */
    function insertProperty($service, $fileId, $key, $value, $visibility) {
      $newProperty = new Google_Service_Drive_Property();
      $newProperty->setKey($key);
      $newProperty->setValue($value);
      $newProperty->setVisibility($visibility);
      try {
        return $service->properties->insert($fileId, $newProperty);
      } catch (Exception $e) {
        print "An error occurred: " . $e->getMessage();
      }
      return NULL;
    }

    function findFiles($service, $query, $limit=10 ) {

      try {
        $parameters = array();
        $parameters['maxResults'] = $limit;
        $parameters['q'] = $query;
        $files = $service->files->listFiles($parameters);

        return $result = $files->getItems();

        //return array_merge($result, $files->getItems());

      } catch (Exception $e) {
        error_log ("An error occurred: " . $e->getMessage());

      }

      return NULL;

    }


    /**
    * Update an existing file's metadata and content.
    *
    * @param Google_DriveService $service Drive API service instance.
    * @param string $fileId ID of the file to update.
    * @param string $newTitle New title for the file.
    * @param string $newDescription New description for the file.
    * @param string $newMimeType New MIME type for the file.
    * @param string $newFilename Filename of the new content to upload.
    * @param bool $newRevision Whether or not to create a new revision for this file.
    * @return Google_DriveFile The updated file. NULL is returned if an API error occurred.
    */
    function updateFile($service, $fileId, $newFileName, $newRevision) {
      try {
        // First retrieve the file from the API.
        $file = $service->files->get($fileId);

        // File's new content.
        $data = file_get_contents($newFileName);

        $additionalParams = array(
          'newRevision' => $newRevision,
          'data' => $data,
          'uploadType' => 'multipart',
        );

        // Send the request to the API.
        $updatedFile = $service->files->update($fileId, $file, $additionalParams);
        return $updatedFile;
      } catch (Exception $e) {
        print "An error occurred: " . $e->getMessage();
      }
    }

/*
// Insert File
$file = new Google_Service_Drive_DriveFile();

$file->setTitle('piscan_document_'.date("Y-m-d_His").'.pdf');
$file->setDescription('Scan Document '. date("Y-m-d H:i:s"));
$file->setMimeType('application/pdf');

// Set the parent folder : SCAN folder
if ($scanFolderId != null) {
    $parent = new Google_Service_Drive_ParentReference();
    $parent->setId($scanFolderId);
    $file->setParents(array($parent));
}
$data = file_get_contents($filein);
try {
    // Post File
    $createdFile = $service->files->insert($file, array(
            'convert' => false,
            'ocr' => false,
            'ocrLanguage' => 'fr',
            'useContentAsIndexableText' => false,
            'data' => $data,
            'mimeType' => 'application/pdf',
            'uploadType'=>'multipart'
        ));
    file_put_contents($filename,$accessToken);
    echo ( "Document uploaded to Google Drive");
    exit(0);
} catch (Google_ServiceException $e) {
    error_log('Exception Message : '.  $e->getMessage());
    exit(1);
}
*/
?>
