<?
include_once __DIR__.'/../SugarSync/SugarSync.php';

class SugarSyncUploader implements IUploader{
	public $dir;
	public $sugar;
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