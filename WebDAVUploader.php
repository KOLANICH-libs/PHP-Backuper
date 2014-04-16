<?php
//include_once __DIR__.'/../Sabre/autoload.php';
include_once __DIR__.'/../Sabre/DAV/Client.php';
include_once __DIR__.'/../Sabre/DAV/Exception.php';
/*!
	Uploads the backup to any WebDAV server using login and password
	it is insecure to store login and password on the server so oauth uploaders are preffered
	(though I think that it is possible to set server's prefs in same way that it will ask different credentials for uploading and downloading)
*/


/**
* Puts a file or buffer to server.
* If you wanna put a file, $mode must be 0 (it is by default), $file should contain filename.
* If you wanna put a binary string, you must set $mode into 1 and $remoteName also must be set
* @param string $url
* @param string $file
* @param string $remoteName
* @param integer $mode
* @return array
*/
function sabrePut(&$sabre,$file, $url='/', $remoteName='', $mode=0){
	switch ($mode){
		case 0:
			if(!file_exists($file)){
				throw new Exception('Upload Error : file ' . $file . ' doesnt exist');
			}
			if(!$remoteName)$remoteName=basename($file);
			//new dBug($url.$remoteName);
			return $sabre->request('PUT', $url.$remoteName, array("file"=>'@'.$file));
			break;
		case 1:
			if(!$remoteName)throw new Exception('You MUST specify $remoteName if you upload blob');
			//new dBug($url.$remoteName);
			return $sabre->request('PUT', $url.$remoteName, $file);
		break;
		default:
			throw new Exception('Bad mode value');
		break;
	}
}

class WebDAVUploader implements IUploader{
	public $dir;
	public $webDav;
	protected $prefs;
	//function __construct($server,$login,$pass,$dir='/'){
	function __construct($prefs){
		$this->prefs=$prefs;
		if(
			(empty($this->prefs['server'])&&empty($this->prefs['baseUri']))||
			(empty($this->prefs['login'])&&empty($this->prefs['userName']))||
			(empty($this->prefs['pass'])&&empty($this->prefs['password']))
		){
			throw new Exception("You had missed arguments about server");
		}
		$this->prefs['baseUri']=&$this->prefs['server'];
		$this->prefs['userName']=&$this->prefs['login'];
		$this->prefs['password']=&$this->prefs['pass'];
		static::verifyServerAndInitConnection($this->prefs['dir']);
	}
	function verifyServerAndInitConnection($dir){
		$webdav = new Sabre\DAV\Client($this->prefs);
		$str=sha1(rand());
		sabrePut($webdav,$str,$dir,'test.txt',1);
		sabrePut($webdav,$str,$dir,'test.txt',1);
		$res=$webdav->request('GET', $dir.'test.txt');
		if($str!=$res["body"])throw new Exception("Hosting test was not passed - sent and received strings differ");
		$webdav->request('DELETE', $dir.'test.txt');
		$this->webdav=&$webdav;
		$this->dir=$dir;
	}
	function upload(string $fileName,string $as){
		return sabrePut($this->webdav,$fileName,$this->dir.'/',$as);
	}
};
?>