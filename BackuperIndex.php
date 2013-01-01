<?

/*!
	can take either string or PDO object
	if it is not BackuperIndex it will take PDO object, BackuperIndex will take either PDO or string, most likely string
	@param PDO $base index database object
*/
interface IBackuperIndex{
	//function __construct($fileName);
	
	/*!
		checks wheither all tables are created, creates the ones that are missing
	*/
	function checkInitializedAndInitMissing();
	
	function load();
}
/*!
	A class for mantaining index.
	Now index is stored in SQLite database.
	Inherit this class to add your plugin a base where it can store info.
	base contains a set of tables, each plugin has its own tables but it is possible to use another plugin's tables
*/
class BackuperIndex implements IBackuperIndex{
	public $base=null;
	public $queries;
	const createTableQueryTempl='CREATE TABLE "%s" (%s);';
	const indexPrefix="";
	static $queriesTemplates=array();//!< contains the queries to make prepared statements
	static $baseStructureBuildQuery=array();//!< contains the databases' structure
	
	/*!
	@param string $base filename for sqlite file which will contain base
	*/
	function __construct(&$base){
		if(is_string($base)){
			$this->base=new PDO("sqlite:".$base,NULL,NULL,
				array(
					PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_STRINGIFY_FETCHES=>false,//!< @bug doesnt work by unknown reason
					PDO::ATTR_EMULATE_PREPARES=>false,//!< @bug disabling emulating prepared statements
				)
			);
			$this->fileName=$base;
		}else
			$this->base=&$base;
		
		$res=$this->checkInitializedAndInitMissing();
		static::prepareQueries();
		static::load();
		//var_dump($this);
	}
	
	/*!
	used to prepare queries to use them from plugins
	*/
	function prepareQueries(){
		$this->queries=new stdClass;
		if(empty(static::$queriesTemplates))return;
		foreach(static::$queriesTemplates as $queryName=>&$query){
			$this->queries->$queryName=$this->base->prepare(str_replace("%PR%",static::indexPrefix,$query));
		}
	}
	
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
			$tblName=static::indexPrefix.$tblName;
			if(empty($tables[$tblName])){
				$res1=$this->base->query(sprintf(self::createTableQueryTempl,$tblName,str_replace("%PR%",static::indexPrefix,$structure)));
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