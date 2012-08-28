<?
include_once __DIR__.'/../Dropbox/dropbox.php';
class EnchancedDropboxUploader implements IUploader{
	public $dir;
	public $dropbox;
	function __construct($prefs){
		extract($prefs);
		if($dir){
			$dir='/';
		}
		if(empty($key)||empty($secret)){
			throw new Exception("You had missed arguments about server");
		}
		$this->dropbox=new Dropbox($key,$secret);
		//static::checkServerRequisites($key,$secret,$dir);
	}
	function checkServerRequisites(){
	}
	function upload($fileName,$as){
		$this->dropbox->filesPost($this->dir.$as, $fileName,false);
	}
};
?>