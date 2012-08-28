<?
//this is not working because google's OAuth 2 is horrible shit
include_once 'backuper.php';
include_once __DIR__.'/../google-api/apiClient.php';
include_once __DIR__.'/../google-api/contrib/apiOauth2Service.php';
include_once __DIR__.'/../google-api/contrib/apiDriveService.php';

class GoogleDriveUploader implements IUploader{
	public $dir;
	public $gdr;
	public $clientId,$clientSecret,$redirectUri;
	function __construct($prefs){
		extract($prefs);
		if(empty($clientSecret)||empty($clientId)){
			throw new Exception("You had missed arguments about server");
		}
	}
	function initCloudConnection($mail,$pass,$dir){
		$client = new apiClient();
		$client->setUseObjects(true);
		$client->setAuthClass('apiOAuth2');
		$client->setScopes(array('https://www.googleapis.com/auth/drive.file'));
		$client->setClientId($this->$clientId);
		$client->setClientSecret($this->clientSecret);
		$client->setRedirectUri($this->redirectUri);
		//$client->setDeveloperKey('insert_your_developer_key');
	}
	function save(){
		$this->gdr->upload($this->zipFileName,$this->zipFileShortName);
	}
};

//google drive credentials are setted up in config.php in google drive directory


// Visit https://code.google.com/apis/console?api=plus to generate your
// client id, client secret, and to register your redirect uri.







?>