<?
include_once(__DIR__.'/../megacloud/MCManager.php');
class MegaCloudUploader implements IUploader{
	public $dir;
	public $mccon;
	
	/*!
		https://www.megacloud.com/developers
		@param array $prefs
			key
			secret
			dir
	*/
	
	function __construct($prefs){
		extract($prefs);
		if($dir){
			$dir='/';
		}
		if(empty($key)||empty($secret)){
			throw new Exception("You had missed arguments about server");
		}
		$this->mccon=MCManager::createConnection($app_key, $app_secret);
		$this->mccon->setTokenPair($tokenPair);
		$this->this->mccon->performAuth();
		//static::checkServerRequisites($key,$secret,$dir);
	}
	function checkServerRequisites(){
		/*$info=$this->mccon->userInfo();
		if(!$info){
			
		}*/
	}
	function upload($fileName,$as){
		$this->mccon->upload(Constant::ROOT_APP_FOLDER , $this->dir.$as, $fileName);
	}
};
?>