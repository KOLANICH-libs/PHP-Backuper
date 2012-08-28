<?
include_once __DIR__.'/../SugarSync/SugarSync.php';
/*!
	Uploads the backup to SugarSync using login (e-mail) and password
	also you will need
	accessKeyId and privateAccessKey which you can get from 
	it is insecure to store login and password on the server so it will be replaced with OAuth version
*/
class SugarSyncUploader implements IUploader{
	public $dir;
	public $sugar;
	/*!
	@param array $prefs
	
		array("login"=>"test@test.ru",
			"pass"=>'test',
			"dir"=>'/Backups',
			"accessKeyId"=>"AAAAAAAAAAAAAAAAAAAAAAAAAAA",
			"privateAccessKey"=>"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA",
		) 
	*/
	function __construct($prefs){
		extract($prefs);
		if(empty($login)||empty($pass)){
			throw new Exception("You had missed arguments about server");
		}
		$this->sugar=new SugarSync($login,$pass,$accessKeyId,$privateAccessKey);
		static::checkServerRequisites($dir);
	}
	function checkServerRequisites($dir){
		$this->sugar->chdir($dir,'/');
		$this->dir=$dir;
	}
	function upload($fileName,$as){
		//$this->sugar->chdir($dir,'/');
		$this->sugar->upload($fileName,$as);
	}
};


?>