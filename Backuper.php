<?
require_once("BackuperIndex.php");



interface IBackuper{
	function __construct($prefs);
	function makeBackup(&$zip);
	function prepareForBackup(&$base);
	function needBackup();
};

interface IUploader{
	function __construct($prefs);
	function upload($fileName,$as);
};


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
	function initPlugins($prefs){
		$this->plugins=new stdClass;
		foreach(array("upload","backup") as $pltype){
			if(isset($prefs[$pltype])&&is_array($prefs[$pltype])){
				$this->plugins->$pltype=array();
				foreach($prefs[$pltype] as $pluginName=>&$pluginPrefs){
					$plnm=$pluginName.ucfirst($pltype)."er";
					//include_once(__DIR__.'/'.$plnm.".php");//maybe simple include?
					include_once($plnm.".php");
					$this->plugins->{$pltype}[$pluginName]=new $plnm($pluginPrefs);
				}
			}
		}
	}
	
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
	
	function makeBackups(){//function for performing additional backups
		if(empty($this->plugins->backup))return;
		foreach($this->plugins->backup as $name=>&$backuper){
			try{
				$res=$backuper->makeBackup($this->zip);
				if(isset($res)){
					if(isset($res['comment']))$this->comment.=$res['comment'];
					
				}
			}catch(Exception $err){
				echo $name.' backuping plugin '.$name.' FAILED: '.$err."\n<br/>";
			}
			echo "backuping plugin $name succeed\n<br/>";
		}
	}
	
	
	function save(){
		if(empty($this->plugins->upload))return;
		$uploadsFailed=0;
		$uploaders=0;
		foreach($this->plugins->upload as $uplnm=>&$uploader){
			$uploaders++;
			try{
				$uploader->upload($this->zipFileName,$this->zipFileShortName);
			}catch(Exception $err){
				echo $uplnm.' uploading FAILED: '.$err."\n<br/>";
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