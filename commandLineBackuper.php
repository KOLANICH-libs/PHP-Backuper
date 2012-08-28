<?

abstract class CommandLineBackuper implements IBackuper{
	const command="";
	const innerPath="";
	static $temp="";
	public $prefs;
	function __construct($prefs){
		$this->prefs=&$prefs;
	}
	function prepareForBackup(&$base){
		$cmd=vsprintf(static::command,$prefs);
	}
	function makeBackup(&$zip){
		$result="";
		foreach($this->bases as &$base){
			$res=static::dumpDB($base);
			$result.=$res["structure"]."\n".$res["data"]."\n";
		}
		$zip->addFromString( static::innerPath.'/'.static::baseName, $result );
		return array("comment"=>"bases backed up");
	}
	function needBackup(){
		return 1;
	}
}
?>