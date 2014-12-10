<?php
/**********************************
 * $ php sendfiletogdrive.php <FileToSend> <GDriveScanDirName>
 * With Google API Console. You need to create a "Client ID for native application" as credentials
 * TODO : Travaille de refactoring pour faire une classe avec m�thode
 */

require_once 'vendor/autoload.php';

$client = new Google_Client();


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
$service = new Google_Service_Drive($client);


// #### AUTH END

$searchpdftoocr = "(mimeType = 'application/pdf') and (not properties has { key='ocrmypdf' and value='true' and visibility='PRIVATE' })";

$scanFolderId = null;
// is SCAN folder exists
    try {
      $parameters = array();
      //$parameters['maxResults'] = 2;
      $parameters['q'] = $searchpdftoocr;
      //$parameters['fields'] = "items(id,title)";
      $files = $service->files->listFiles($parameters);

      $result = $files->getItems();
      $result = array_merge($result, $files->getItems());
	    print_r($result);
    } catch (Exception $e) {
      error_log ("An error occurred: " . $e->getMessage());
      exit(1);

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
    * @return Google_Property The inserted property. NULL is returned if an API
    error occurred.
    */
    function insertProperty($service, $fileId, $key, $value, $visibility) {
      $newProperty = new Google_Property();
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
