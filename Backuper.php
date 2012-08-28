<?
require_once("BackuperIndex.php");

interface IBackuper{
	/*!
	initializes backuper plugin with prefs
	$prefs can be anything your plugin will understand
	*/
	function __construct($prefs);
	/*!
	used to backup all informatin which needs backup
	@param ZipArchive &$zip where backups will be packed to
	
	usually a backuper creates some subfolders in the archive and strores backup data in them, for example /base/MySQL for MySQLBackuper  or /files and /patches for FileTreeBackuper
	@return array('comment'=>'string to be added to archive comment',...)
	*/
	function makeBackup(&$zip);
	/*!
	makes opperations needed for backup for example builds graph or looking for modified files
	takes database object to store index data
	usually you will want to use BackuperIndex subclass to mantain base
	@param PDO &$base
	*/
	function prepareForBackup(&$base);
	/*!
	determines wheither backup is needed for this plugin
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
	public $index, $roots=array();
	static $archiveTempDir;
	static $indexFileName="backupFilesIndex.sqlite";
	public $zip=null;
	public $plugins;
	function __construct($prefs){
		echo "consructing Backuper\n<br/>";
		$filename=static::$archiveTempDir.'/'.static::$indexFileName;//because by reference
		$this->index=new BackuperIndex($filename);
		static::initPlugins($prefs);
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
					//include_once(__DIR__.'/'.$plnm.".php");//maybe simple include?
					try{
						include_once($plnm.".php");
						$this->plugins->{$pltype}[$pluginName]=new $plnm($pluginPrefs);
					}
					catch(Exception $err){
						echo 'Plugin '.$plnm.'failed to initialize. : '.get_class($err).' : '.$err->getCode().' : '.$err->getMessage()."\n<br/>";
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
		$this->zip->addFile($this->index->fileName,static::$indexFileName);
	}
	function needBackup(){
		if(empty($this->plugins->backup))return 0;
		foreach($this->plugins->backup as $bname => &$backuper){
			if(!$backuper->needBackup()){
				unset($this->plugins->backup[$bname]);
				echo "$bname : backup is not needed\n<br/>";
			}
		}
		var_dump($this->plugins->backup);
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
		$this->zip=new ZipArchive;
		$this->zipFileShortName=$time.".zip";
		$this->zipFileName=static::$archiveTempDir.'/'.$this->zipFileShortName;
		new dBug($this->zipFileName);
		if(!$this->zip->open($this->zipFileName,ZIPARCHIVE::OVERWRITE))throw new Exception("Cannot create archive");
		new dBug($this->zip);
		
		$this->comment=$time."\n".date("g:i:s A D d.m.y",$time)."\n";
		static::makeBackups();
		$this->zip->setArchiveComment($this->comment);
		unset($this->comment);
		static::backupIndex();	
		if(!$this->zip->close())throw new Exception("Cannot close archive");
		$this->zip=null;
		echo 'saving backup archive...<br/>';
		static::save();
		echo 'backup archive saved<br/>';
		echo "<hr color='magenta'/>";
	}
	
	/*!
	prepares backups with backupers' method prepareForBackup
	*/
	function prepareForBackup(){
		if(empty($this->plugins->backup))return;
		foreach($this->plugins->backup as $name=>&$backuper){
			try{
				$backuper->prepareForBackup($this->index->base);
			}catch(Exception $err){
				echo 'preparing backuping plugin '.$name.' FAILED: '.$err."\n<br/>";
			}
			echo "preparing backuping plugin $name succeed\n<br/>";
		}
	}
	
	/*!
	make backups with backupers
	*/
	function makeBackups(){
		if(empty($this->plugins->backup))return;
		foreach($this->plugins->backup as $name=>&$backuper){
			try{
				$res=$backuper->makeBackup($this->zip);
				if(isset($res)){
					if(isset($res['comment']))$this->comment.=$res['comment'];
					
				}
			}catch(Exception $err){
				echo $name.' backuping plugin '.$name.' FAILED: '.$err->getMessage()."\n<br/>";
			}
			echo "backuping plugin $name succeed\n<br/>";
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
				$uploader->upload($this->zipFileName,$this->zipFileShortName);
			}catch(Exception $err){
				echo $uplnm.' uploading FAILED: '.get_class($err).':'.$err->getCode().':'.$err-getMessage()."\n<br/>";
				$uploadsFailed++;
			}
			echo $uplnm.' uploading suceed\n<br/>';
		}
		if($uploadsFailed!=$uploaders&&$uploaders!=0){
			unlink($this->zipFileName);
		}
		//parent::save();
	}
}
Backuper::$archiveTempDir=__DIR__;
?>