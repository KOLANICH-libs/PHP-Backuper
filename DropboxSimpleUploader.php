<?
include_once(__DIR__.'/../DropboxUploader/DropboxUploader.php');
/*!
	Uploads the backup to Dropbox using login (e-mail) and password
	it is insecure to store them on the server so it will be replaced with OAuth version
*/
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