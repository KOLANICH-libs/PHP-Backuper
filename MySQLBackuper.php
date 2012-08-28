<?
/*!
	Makes backup of MySQL database in format similar to mysqldump.
	The code was taken from sypex dumper v 1.
	Now backups only structure and data, no triggers and prepared statements are backuped.
	PHP script needs enough time and memory (and other resources) to finish.
*/

class MySQLBackuper implements IBackuper{
	public $db;
	const baseDir="base/mysql",baseName="base.sql";
	public $bases=null;
	/*!
		@param PDO $prefs
		@param array $prefs : either array( PDO $base1 ) or array( 'base'=>array( PDO $base1 ),'dumpStructure'=>1,'dumpData'=>1 )
	*/
	function __construct($prefs){
		if($prefs instanceof PDO){
			$prefs=array('base'=>$prefs);
		}
		if(is_array($prefs)){
			if(is_array($prefs["base"])){
				$this->bases=$prefs["base"];
			}
			else{
				$this->bases=array($prefs["base"]);
			}
			$this->dumpStructure=isset($prefs["dumpStructure"])?$prefs["dumpStructure"]:1;
			$this->dumpData=isset($prefs["dumpStructure"])?$prefs["dumpStructure"]:1;
		}else throw new Exception("Input must be either PDO object or array with prefs");
		
	}
	function prepareForBackup(&$base){
		
	}
	function makeBackup(&$zip){
		$result="";
		foreach($this->bases as &$base){
			$res=static::dumpDB($base);
			$result.=$res["structure"]."\n".$res["data"]."\n";
		}
		$zip->addFromString( static::baseDir.'/'.static::baseName, $result );
		return array("comment"=>"bases backed up");
	}
	function needBackup(){
		return 1;
	}
	function dumpDB(&$base){
		$res="";
		$structure="";
		$data="";
		$tables=$base->query("show full tables;");
		$tables->setFetchMode(PDO::FETCH_NUM);

		foreach($tables as $table){
			if($this->dumpStructure){
				$res=$base->query("show create table `{$table[0]}`;");
				$tblStrDump=$res->fetch();
				//new dBug($res);
				//new dBug($tblStrDump);
				$structure.="-- Table ".$tblStrDump[0]." - ".$table[1]."\n".$tblStrDump[1]."\n\n";
			}
			//////////////////////начинается чужой код
			if($this->dumpData){
				$data .= "--Dumping data of `{$table[0]}`\nINSERT INTO `{$table[0]}` VALUES \n";
				$notNum = array();
				$res = $base->query("SHOW COLUMNS FROM `{$table[0]}`");
				$fields = 0;
				foreach($res as $col) {
					// TODO: проверить типы SET, ENUM и BIT
					$notNum[$fields] = preg_match("/^(tinyint|smallint|mediumint|bigint|int|float|double|real|decimal|numeric|year)/", $col['Type']) ? 0 : 1; 
					$fields++;
				}
				$time_old = time();
				$z = 0;
				// Достаем данные
				//$res = mysql_unbuffered_query("SELECT * FROM `{$table[0]}`{$from}");
				$res = $base->query("SELECT * FROM `{$table[0]}`;");
				$res->setFetchMode(PDO::FETCH_NUM);
				foreach($res as $row) {
					for($k = 0; $k < $fields; $k++){
						if(!isset($row[$k])) {$row[$k] = '\N';}
						elseif($notNum[$k]) {$row[$k] =  '\'' . $base->quote($row[$k]) . '\'';}
					}
					$data .= '(' . implode(',', $row) . "),\n";
				
				}
				unset($row);
				//mysql_free_result($res);
				$data = substr_replace($data, "\t;\n\n",  -2, 2);
			}
		}
		
		return array('structure'=>$structure,'data'=>$data);
	}
}
?>