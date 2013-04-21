<?php
require_once("BackuperIndex.php");

interface IBackuper{
	/*!
	initializes backuper plugin with prefs for it
	@param mixed $prefs can be anything your plugin will understand
	*/
	function __construct($prefs);
	/*!
	used to backup all informatin which needs backup
	@param ZipArchive &$arch where backups will be packed to
	
	usually a backuper creates some subfolders in the archive and strores backup data in them, for example /base/MySQL for MySQLBackuper  or /files and /patches for FileTreeBackuper
	@return array('comment'=>'string to be added to archive comment',...)
	*/
	function makeBackup(&$arch);
	/*!
	makes opperations needed for backup for example builds graph or looking for modified files
	takes database object to store index data
	usually you will want to use BackuperIndex subclass to mantain base
	@param PDO &$base opened db connection, don't close it (you can close it but must reopen .... may be in another base file, but it is strongly unrecommended to do so)
	*/
	function prepareForBackup(&$base);
	/*!
	determines wheither backup is needed for this plugin
	@returns boolean|integer does this plugin have work to do
	*/
	function needBackup();
};

interface IUploader{
	function __construct($prefs);
	/*!
	@param string $fileName pathname of the zip archive
	@param string &$as desired name of the zip archive on the target backup location, usually the same
	*/
	function upload($fileName,$as);
};

/*!
	Main backuper class.
	Manages plugins and resources and use them to make backups and uploads.
	It doesn't (and mustn't) implement IBackuper because it is not a plugin.
*/
class Backuper{
	public $index;
	static $backupsDir;//!< folder
	static $indexFileName="backupIndex.sqlite";//!< filename for database file (of course you can change it, don't forget to rename index file)
	
	public $arch=null;//!< PharData where everything will be packed
	public $archiveFormat;//!< archive format
	public $archiveCompression;//!< archive compression
	public $archiveFileExtension;//!< archive file extension
	const defaultArchiveFormat='zip.gz';
	
	public $plugins;//!< here plugins will be stored, look initPlugins
	
	/*!
	@param array $prefs preferences (auth information, some other, etc...) for plugins
		array("pluginName"=>array("pref1"=>"ololo","pref2"=>"trololo"),"archive"=>"tar,tar.gz,tar.bz2,zip or phar")
	*/
	function __construct($prefs){
		echo "consructing Backuper\n<br/>";
		$filename=static::$backupsDir.'/'.static::$indexFileName;//because by reference
		$this->index=new BackuperIndex($filename);
		static::initPlugins($prefs);
		
		if(isset($prefs['archive']))
			static::determineArchiveFormatByString($prefs['archive']);
		else static::determineArchiveFormatByString(static::defaultArchiveFormat);
	}
	
	/*!
		@param string $compStr in format "archiveType:compression"
		tar.gz means tarball compressed with gzip and generates a file with 'tar.gz' extension
		zip.gz means zip archive every file in it is compressed with gzip serparately
		zip means zip archive without compression
		see http://www.php.net/manual/en/phardata.compressfiles.php and http://php.net/manual/en/phar.compress.php for details
	*/
	
	
	function determineArchiveFormatByString($compStr){
		$arr=explode(".",strtolower($compStr));
		$exts=new StdClass();
		if(empty($arr[1]))$arr[1]="";
		switch($arr[0]){
			case "zip":
				$this->archiveFormat=Phar::ZIP;
			break;
			case "tar":
				$this->archiveFormat=Phar::TAR;
			break;
			case "phar":
				$this->archiveFormat=Phar::PHAR;
			break;
			default:
				throw new Exception('Unknown archive format :'.$arr[0]);
			break;
		}
		switch($arr[1]){
			case "":
			case "store":
				$this->archiveCompression=Phar::NONE;
			break;
			case "bz2":
				$this->archiveCompression=Phar::BZ2;
			break;
			case "gz":
				$this->archiveCompression=Phar::GZ;
			break;
			default:
				throw new Exception('Unknown compression :'.$arr[0]);
			break;
		}
		if(!Phar::canCompress($this->archiveCompression)){
			throw new Exception("Compression type is not supported");
		}
		$exts->extension=$arr[0];
		$exts->suffix=$this->archiveFormat!=Phar::ZIP?'.'.$arr[1]:'';
		$this->archiveFileExtension=$exts;
		new dBug($this->archiveFileExtension);
		return true;
	}
	
	
	/*!
	initializes the plugins (backupers and uploaders)
	*/
	function initPlugins($prefs){
		$this->plugins=new stdClass;
		foreach(array("upload","backup") as $pltype){
			if(isset($prefs[$pltype])&&is_array($prefs[$pltype])){
				$this->plugins->$pltype=array();
				foreach($prefs[$pltype] as $pluginName=>&$pluginPrefs){
					$plnm=$pluginName.ucfirst($pltype)."er";
					try{
						if(!include_once ($plnm.".php"))throw new Exception('Plugin '.$plnm.' was not found');
						$this->plugins->{$pltype}[$pluginName]=new $plnm($pluginPrefs);
					}
					catch(Exception $err){
						echo 'Plugin '.$plnm.' <font color="red">FAILED</font> to initialize. : '.get_class($err).' : '.$err->getCode().' : '.$err->getMessage()."\n<br/>";
						new dBug($err);
					}
				}
			}
		}
	}
	/*!
	adds index file to archive
	*/
	function backupIndex(){
		//$this->index->save();
		$this->arch->addFile($this->index->fileName,static::$indexFileName);
	}
	function needBackup(){
		if(empty($this->plugins->backup))return 0;
		foreach($this->plugins->backup as $bname => &$backuper){
			if(!$backuper->needBackup()){
				unset($this->plugins->backup[$bname]);
				echo "$bname : backup is not needed\n<br/>";
			}
		}
		//var_dump($this->plugins->backup);
		if(empty($this->plugins->backup))
			return 0;
		else
			return 1;
	}
	
	
	
	/*!
	the method to be called by user to make backups
	starts backup process
	*/
	function makeBackup(){
		$time=time();
		static::prepareForBackup();
		echo "<hr color='magenta'/>";
		if(!$this->needBackup()){
			echo "backup is not needed <br/>";
			return 0;
		}
		
		$this->archFileShortName=$time.'.'.$this->archiveFileExtension->extension;
		$this->archFileName=static::$backupsDir.'/'.$this->archFileShortName;
		new dBug($this->archFileName);
		
		$this->arch=new PharData($this->archFileName,0,$this->archiveFormat);
		
		
		
		
		//$archOpeningRes=$this->arch->open($this->archFileName,ZIPARCHIVE::OVERWRITE|ZIPARCHIVE::CREATE|ZIPARCHIVE::CHECKCONS);
		//if($archOpeningRes !== TRUE)throw new Exception('Cannot create archive : '.$this->arch->getStatusString());
		//unset($archOpeningRes);
		//new dBug($this->arch);
		//new dBug($this->arch);
		
		$this->comment=$time."\n".date('g:i:s A D d.m.y',$time)."\n";
		
		//! calling plugins
		static::makeBackups();
		
		$this->arch->setMetadata($this->comment);
		//$this->arch->setArchiveComment($this->comment);
		unset($this->comment);
		static::backupIndex();	
		
		if($this->archiveFormat==Phar::ZIP){
			$this->arch->compressFiles($this->archiveCompression);
		}else{
			$this->arch->compress($this->archiveCompression);
		}
		
		$this->arch=null;
		if($this->archiveFormat!=Phar::ZIP){
			unlink($this->archFileName);
			$this->archFileName.=$this->archiveFileExtension->suffix;
		}
		
		
		echo 'saving backup archive...<br/>';
		static::save();
		echo 'backup archive saved<br/>';
		echo '<hr color="magenta"/>';
	}
	
	/*!
	prepares backups with backupers' method prepareForBackup
	*/
	function prepareForBackup(){
		if(empty($this->plugins->backup))return;
		foreach($this->plugins->backup as $name=>&$backuper){
			try{
				$backuper->prepareForBackup($this->index->base);
				echo 'preparing backuping plugin '.$name." <font color='green'>succeed</font>\n<br/>";
			}catch(Exception $err){
				echo 'preparing backuping plugin '.$name.' <font color="red">FAILED</font>: '.$err."\n<br/>";
				new dBug($err);
			}

		}
	}
	
	/*!
	make backups with backupers
	*/
	function makeBackups(){
		if(empty($this->plugins->backup))return;
		foreach($this->plugins->backup as $name=>&$backuper){
			try{
				$res=$backuper->makeBackup($this->arch);
				if(isset($res)){
					if(isset($res['comment']))$this->comment.="\n".$res['comment'];
				}
				echo 'backuping plugin '.$name." <font color='green'>succeed</font>\n<br/>";
			}catch(Exception $err){
				echo $name.' backuping plugin '.$name.' <font color="red">FAILED</font>: '.$err->getMessage()."\n<br/>";
				new dBug($err);
			}
		}
	}
	
	/*!
	uploads the archive to different servers and services and deletes it from local disk
	if no servers were specified or none of the uploads succeeded the zip file will not be deleted
	*/
	function save(){
		if(empty($this->plugins->upload))return;
		$uploadsFailed=0;
		$uploaders=0;
		foreach($this->plugins->upload as $uplnm=>&$uploader){
			$uploaders++;
			try{
				$uploader->upload($this->archFileName,$this->archFileShortName);
			}catch(Exception $err){
				echo $uplnm.' uploading <font color="red">FAILED</font>: '.get_class($err).':'.$err->getCode().':'.$err-getMessage()."\n<br/>";
				new dBug($err);
				$uploadsFailed++;
			}
			echo $uplnm.' uploading <font color="green">succeed</font>'."\n<br/>";
		}
		if($uploadsFailed!=$uploaders&&$uploaders!=0){
			unlink($this->archFileName);
		}
		//parent::save();
	}
}
Backuper::$backupsDir=__DIR__;
?>
