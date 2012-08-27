<?
include_once(__DIR__.'/../DropboxUploader/DropboxUploader.php');
class DropboxSimpleUploader implements IUploader{
	public $dir;
	public $dropbox;
	function __construct($settings){
		extract($settings);
		if(empty($login)||empty($pass)){
			throw new Exception("You had missed arguments about server");
		}
		static::checkServerRequisites($login,$pass,$dir);
	}
	function checkServerRequisites($mail,$pass,$dir){
		$this->dropbox = new DropboxUploader($mail, $pass);
		$this->dropbox->login();
		//$this->dropbox->uploadBuffer("test",$dir,"test");
		$this->dir=$dir;
	}
	function upload($fileName,$as){
		$this->dropbox->upload($fileName,$this->dir,$as);
	}
};
?>