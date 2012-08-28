<?
interface IBackuperIndex{
	//function __construct($fileName);
	function checkInitializedAndInitMissing();
	function load();
}
/*!
	A class for mantaining index.
	Now index is stored in SQLite database.
*/
class BackuperIndex implements IBackuperIndex{
	public $base=null;
	public $queries;
	const createTableQueryTempl='CREATE TABLE "%s" (%s);';
	static $queriesTemplates=array();//!< contains the queries to make prepared statements
	static $baseStructureBuildQuery=array();//!< contains the databases' structure
	function __construct(&$base){
		if(is_string($base)){
			$this->base=new PDO("sqlite:".$base,NULL,NULL,
				array(
					PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_STRINGIFY_FETCHES=>false,//doesnt work by unknown reason
					PDO::ATTR_EMULATE_PREPARES=>false,//disabling emulating prepared statements
				)
			);
			$this->fileName=$base;
		}else
			$this->base=&$base;
		
		$res=$this->checkInitializedAndInitMissing();
		$this->queries= new stdClass;
		if(empty(static::$baseStructureBuildQuery))return;
		foreach(static::$queriesTemplates as $queryName=>&$query){
			$this->queries->$queryName=$this->base->prepare($query);
		}
		//var_dump($this);
	}
	/*!
	 checks wheither all tables are created, creates the ones that are missing
	*/
	function checkInitializedAndInitMissing(){
		if(empty(static::$baseStructureBuildQuery))return;
		$res=$this->base->query("SELECT `name` FROM `sqlite_master` WHERE `type`='table';");
		$tables=array();
		if($res){
			while($r=$res->fetch(PDO::FETCH_NUM))
				$tables[$r[0]]=1;
			unset($r);
		}
		foreach(static::$baseStructureBuildQuery as $tblName=>&$structure){
			if(empty($tables[$tblName])){
				$res1=$this->base->query(sprintf(self::createTableQueryTempl,$tblName,$structure));
			}
		}
		unset($tables);
	}
	function load(){}

	function __destruct(){
		unset($this->base);//base will be closed automatically when the last reference to it will be removed
	}
};

?>