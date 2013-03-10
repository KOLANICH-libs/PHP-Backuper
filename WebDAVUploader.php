<?
//include_once __DIR__.'/../Sabre/autoload.php';
include_once __DIR__.'/../Sabre/DAV/Client.php';
include_once __DIR__.'/../Sabre/DAV/Exception.php';
/*!
	Uploads the backup to any WebDAV server using login and password
	it is insecure to store login and password on the server so oauth uploaders are preffered
	(though I think that it is possible to set server's prefs in same way that it will ask different credentials for uploading and downloading)
*/
class WebDAVUploader implements IUploader{
	public $dir;
	public $webDav;
	protected $prefs;
	//function __construct($server,$login,$pass,$dir='/'){
	function __construct($prefs){
		$this->prefs=$prefs;
		if(
			(empty($this->prefs->server)&&empty($this->prefs->baseUri)||
			(empty($this->prefs->login)&&empty($this->prefs->userName))||
			(empty($this->prefs->pass)&&empty($this->prefs->password))&&
		){
			throw new Exception("You had missed arguments about server");
		}
		$this->prefs->baseUri=&$this->prefs->server;
		$this->prefs->userName=$this->prefs->login;
		$this->prefs->password=&$this->prefs->pass;
		static::verifyServerAndInitConnection($dir);
	}
	function verifyServerAndInitConnection($dir){
		$webdav = new Sabre\DAV\Client($this->prefs);
		$str=sha1(rand());
		$webdav->put($str,$dir,'test.txt',1);
		$res=$webdav->request('GET', $dir.'test.txt');
		if($str!=$res["body"])throw new Exception("Hosting test was not passed - sent and received strings differ");
		$webdav->request('DELETE', $dir.'test.txt');
		$this->webdav=&$webdav;
		$this->dir=$dir;
	}
	function upload($fileName,$as){
		$this->webdav->put($fileName,$this->dir.'/',$as);
	}
};
?>