<?php

require_once 'vendor/autoload.php';

// Google Drive API Helper
Class Google_Drive_Helper {

  private $client;
  private $logger;
  private $service;


  function __construct($gid,$gsecret)
  {
    $this->logger = new \Monolog\Logger("GoogleDriveHelperClass");
    $this->logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());


    $this->auth($gid,$gsecret);

    $this->service = new Google_Service_Drive($this->client);


  }

  // Manage Google OAUTH autentication and save access token to ~/gdrive/credentials/accesstoken.json
  private function auth($gid,$gsecret) {
    try {

      $this->client = new Google_Client();
      $this->client->setClientId($gid);
      $this->client->setClientSecret($gsecret);
      $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
      $this->client->setAccessType('offline');
      $this->client->setApprovalPrompt('force');
      $this->client->setScopes(array('https://www.googleapis.com/auth/drive'));

      $authUrl = $this->client->createAuthUrl();
    } catch (Exception $e) {
      $this->logger->addError('OAUTH auth exception : '.  $e->getMessage());
      exit(1);
    }

    // TODO : Find why Root Home Dir is not set
    if (getenv("HOME") =='/') {
      $this->logger->addWarning("Home path set to '/'. Set to '/root'");
      putenv("HOME=/root");
    }

    // Path to save Oauth access token in JSON Format
    $filename = getenv("HOME").'/gdrive/credentials/accesstoken.json';
    if(!file_exists(dirname($filename))){
      mkdir(dirname($filename),0700,true);
    }

    if(!file_exists($filename)){
      $this->logger->addAlert("No access token found in ".$filename.". Launch script manually by CLI to initiate");
      //Request authorization : Need to find an auth code in Web Browser
      print "Please visit:\n$authUrl\n\n";
      print "Please enter the auth code:\n";
      $authCode = trim(fgets(STDIN));

      // Exchange authorization code for access token
      $accessToken = $this->client->authenticate($authCode);
      //save access token
      file_put_contents($filename,$accessToken);
      $this->client->setAccessToken($accessToken);
    } else {
      // Get access token
      $accessToken = file_get_contents($filename);
    }

    $this->client->setAccessToken($accessToken);

  }



  /**
  * Download a file's content.
  *
  * @param File $file Drive File instance.
  * @return String The file's content if successful, null otherwise.
  */
  function downloadFile($downloadUrl) {
    $this->logger->addInfo("File begins to download");

    if ($downloadUrl) {
      $request = new Google_Http_Request($downloadUrl, 'GET', null, null);

      $SignhttpRequest = $this->client->getAuth()->sign($request);
      $httpRequest = $this->client->getIo()->makeRequest($SignhttpRequest);

      if ($httpRequest->getResponseHttpCode() == 200) {
        return $httpRequest->getResponseBody();
      } else {
        // An error occurred.
        $this->logger->addError("Impossible to download File. Google Response Code : ".$httpRequest->getResponseHttpCode());
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
  * @param String $fileId ID of the file to insert property for.
  * @param String $key ID of the property.
  * @param String $value Property value.
  * @param String $visibility 'PUBLIC' to make the property visible by all apps,
  *                           or 'PRIVATE' to make it only available to the app that created it.
  * @return Google_Property The inserted property. NULL is returned if an API error occurred.
  */
  function insertProperty($fileId, $key, $value, $visibility) {
    $this->logger->addInfo("Add Property $key:$value to Drive ");
    $newProperty = new Google_Service_Drive_Property();
    $newProperty->setKey($key);
    $newProperty->setValue($value);
    $newProperty->setVisibility($visibility);
    try {
      return $this->service->properties->insert($fileId, $newProperty);
    } catch (Exception $e) {
      $this->logger->addError("An error occurred: " . $e->getMessage());
    }
    return NULL;
  }


  function findFiles($query, $limit=10 ) {

    try {
      $parameters = array();
      $parameters['maxResults'] = $limit;
      $parameters['q'] = $query;
      $files = $this->service->files->listFiles($parameters);

      return $result = $files->getItems();

      //return array_merge($result, $files->getItems());

    } catch (Exception $e) {
      $this->logger->addError("An error occurred: " . $e->getMessage());
    }

    return NULL;

  }


  /**
  * Update an existing file's metadata and content.
  *
  * @param string $fileId ID of the file to update.
  * @param string $newFilename Filename of the new content to upload.
  * @param bool $newRevision Whether or not to create a new revision for this file.
  * @return Google_DriveFile The updated file. NULL is returned if an API error occurred.
  */
  function updateFile($fileId, $newFileName, $newRevision) {
    try {
      // First retrieve the file from the API.
      $file = $this->service->files->get($fileId);

      // File's new content.
      $data = file_get_contents($newFileName);

      $additionalParams = array(
      'newRevision' => $newRevision,
      'data' => $data,
      'uploadType' => 'resumable',
      );

      // Send the request to the API.
      $updatedFile = $this->service->files->update($fileId, $file, $additionalParams);
      return $updatedFile;
    } catch (Exception $e) {
      $this->logger->addError("An error occurred: " . $e->getMessage());
    }
  }




}
