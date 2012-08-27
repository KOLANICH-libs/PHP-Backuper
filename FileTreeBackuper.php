<?
/*
it seems that there are ready-to-use solutions
	http://phphack.ru/seescript/771/
	http://web4u.mirrors.phpclasses.org/package/4841-PHP-Manage-backup-copies-of-files-.html
	bash http://ilab.me/howto/bash-tar-vps-backup/
	http://tinyurl.com/7gsr9vb
	
	good links for article to xakep
*/

/*
TODO:
1 make root state be stored in base and arrays			<------------7
2 make classes process oneselves									 |
3 make processing of deleted items									 |
3 fix that items in root folder are always added (at first fix point 1)
*/
//require_once("BackuperIndex.php");
interface IFileTreeItem{
	function hash();
	function process();
	//public $inode,$mtime,$ctime,$size,$uid;
};
interface IFileTreeDir extends IFileTreeItem{
	function processChild(IFileTreeItem &$fChild);
	function expandChildren();
};



function convertNodeFromBase(&$row){
	$row->inode=(integer)$row->inode;
	$row->parent=(integer)$row->parent;
	$row->hash=(integer)$row->hash;
	$row->pathhash=(integer)$row->pathhash;
}

class FileTreeBackuperIndex extends BackuperIndex{
	public $base=null;
	//index
	//patch to current state
	public $moved=array(),$deleted=array(),$changed=array(),$added=array();
	//public $modified=array();
	public $ignores=array();
	static $queriesTemplates=array(
		"getTree"=>'SELECT * FROM `fileTree`;',
		"getChildren"=>"SELECT * FROM `fileTree` where `parent`=?;",
		"addFileToTree"=>"INSERT INTO `fileTree` VALUES (:inode, :parent, :hash, :pathhash, :name);",
		"deleteFileFromTree"=>"DELETE FROM `fileTree` WHERE `inode`=:inode;",
		"addCommit"=>"INSERT INTO commits (`timestamp`) VALUES (:time);",
	);
	static $baseStructureBuildQuery=array(
		'fileTree'=>'
			"inode"  INTEGER NOT NULL,
			"parent"  INTEGER NOT NULL,
			"hash"  INTEGER NOT NULL,
			"pathhash"  INTEGER NOT NULL,
			"name"  VARCHAR(256) NOT NULL,
			PRIMARY KEY ("inode") ON CONFLICT REPLACE,
			FOREIGN KEY ("parent") REFERENCES "fileTree" ("inode")
			UNIQUE ("inode") ON CONFLICT REPLACE,
			UNIQUE ("hash") ON CONFLICT REPLACE,
			UNIQUE ("pathhash") ON CONFLICT REPLACE',
		'ignores'=>'"ignore"  varchar(512) NOT NULL',
		'commits'=>'
			"id"  integer PRIMARY KEY AUTOINCREMENT NOT NULL,
			"timestamp"  TIMESTAMP NOT NULL'
	);
	public $inodes=array();
	function load(){
		$res=$this->queries->GetTree->execute();
		while($row=$this->queries->getChildren->fetchObject()){
			convertNodeFromBase($row);
			$this->inodes[$row->inode]=$row;// it looks like here i need & but id doesn't work by unknown reason
		}
		$this->queries->getTree->closeCursor();
	}
	function loadChildren(&$parent){
		$res=$this->queries->getChildren->execute(array($parent));
		$arr=array();
		while($row=$this->queries->getChildren->fetchObject()){
			convertNodeFromBase($row);
			$this->inodes[$row->inode]=$row;// it looks like here i need & (a reference) but id doesn't work by unknown reason
			$arr[$row->inode]=&$this->inodes[$row->inode];
		}
		$this->queries->getChildren->closeCursor();
		return $arr;
	}
	function needBackup(){
		return !(empty($this->added)&&empty($this->changed)&&empty($this->deleted)&&empty($this->moved));
	}
	function save($time=0){
		if(!$this->needBackup())return;
		$this->base->beginTransaction();
		try{
			$this->makeCommitRecord();
			$this->saveAdded();
			$this->saveChanged();
			$this->saveMoved();
			$this->saveDeleted();
			$this->base->commit();
		}
		catch(Exception $exc){
			$this->base->rollBack();
			//$this->base->commit();
			throw $exc;
		}
	}
	function saveAdded(){
		echo "saving added....\n<br/>";
		foreach($this->added as &$node){
			//new dBug(array('inode'=>$node->inode,'parent'=>$node->parent,'hash'=>$node->hash,'pathhash'=>$node->pathhash,'name'=>$node->name));
			$res=$this->queries->addFileToTree->execute(array($node->inode,$node->parent,$node->hash,$node->pathhash,$node->name));
		}
	}
	function saveChanged(){
		echo "saving changed....\n<br/>";
		foreach($this->changed as &$node){
			$res=$this->queries->addFileToTree->execute(array($node->inode,$node->parent,$node->hash,$node->pathhash,$node->name));
		}
	}
	function saveMoved(){
		echo "saving moved....\n<br/>";
		foreach($this->moved as &$node){
			$res=$this->queries->addFileToTree->execute(array($node->inode,$node->parent,$node->hash,$node->pathhash,$node->name));
		}
	}
	function saveDeleted(){
		echo "removing deleted....\n<br/>";
		foreach($this->deleted as &$node){
			$res=$this->queries->deleteFileFromTree->execute(array($node->inode));
		}
	}
	function makeCommitRecord($time=0){
		if(!$time)$time=time();
		$this->queries->addCommit->execute(array($time));
		//$this->queries->addCommit->execute();
		return $time;
	}
};
class FileTreeItem implements IFileTreeItem{
	public $name,$parent,$hash=0,$pathhash=0;
	static $attributesForHashing=array('inode','mtime','ctime','size','uid');
	//public $iter;
	function showAtrs(){
		$atrs=new stdClass;
		foreach(static::$attributesForHashing as $atrName)$atrs->$atrName=$this->$atrName;
		new dBug($atrs);
	}
	function __construct(RecursiveDirectoryIterator  &$file,IFileTreeDir &$parent=null){
		//echo "the dir is:".$dir."\n";
		//echo "the object path is:".$dir+"/"+$name."\n";
		echo "constructing ".$file->getPathname()."<br/>\n";
		//if(!$parent)$parent=&$this;
		if(!$parent)throw new Exception("file has no parent. Serious bug!!!");
		
		
		$this->name=$file->getFilename();
		//$this->iter=$file;
		$this->inode=$file->getInode();
		$this->mtime=$file->getMTime();
		$this->ctime=$file->getCTime();
		$this->size=$file->getSize();
		$this->uid=$file->getOwner();
		
		
		if(!$this->inode){
			//in windows $file->getInode() returns 0
			//so we have to make some bad kind of inode for debug purposes
			$this->inode=unpack('L',md5(
				$file->isDir().
				("|!".$file->isLink()).
				"|!".$file->getFilename().
				"|!".$this->ctime.
				($file->isDir()?"":
					//"|!".$file->getMTime().
					//"|!".$this->mtime.///why had i included it to inode?
					"|!".$file->getSize()
				)
			,1));
			$this->inode=$this->inode[1];
		}
		
		$this->showAtrs();
		
		$this->parent=$parent->inode;//it is here because the node may have yourself as parent
		$this->pathhash=crc32($file->getPathname());
		$this->hash=$this->hash();
		unset($file);
		$parent->inodes[$this->inode]=&$this;
		
	}
	function hash(){
		$hh=hash_init( "md5" );
		foreach(static::$attributesForHashing as $atrName)hash_update($hh,$this->$atrName);
		$h=unpack('L',hash_final( $hh ,1));
		$this->hash=$h[1];
		return $h[1];
	}
	/*function __call($name,$args){
		call_user_func_array(array($this->file,$name),$args)
	}*/
	function process(){}
};
class FileTreeDir extends FileTreeItem implements IFileTreeDir{
	protected $childrenIter=null;
	public $children=null;
	public $path="";
	function __construct(RecursiveDirectoryIterator  &$file,IFileTreeDir &$parent){
		parent::__construct($file,$parent);
		$this->path=$file->getPathname();
		new dBug($this->path);
		$this->childrenIter=$file->getChildren();
	}
	function process(){
		echo "processing folder $this->name ({$this->inode})<br/>\n";
		static::expandChildren();
	}
	function expandChildren(){
		foreach($this->childrenIter as $child){
			$fChild=null;
			if($child->isDir()){
				$fChild=new static($child,$this);
			}
			else{
				$fChild=new FileTreeItem($child,$this);
				//new dBug($child);
			}
			static::processChild($fChild);
		}
	}
	function processChild(IFileTreeItem &$fChild){
		
	}
};

function drawHierarchy(&$inodelist){
	$arr=array();
	foreach($inodelist as $inode=>&$file){
		if(empty($arr[$inode])){
			$arr[$inode]=array();
			$arr[$inode]["name"]=$file->name;
			$arr[$inode]["parent"]=$file->parent;
		}
	}
	$transforms=1;
	while($transforms>0){
		$transforms=0;
		foreach($arr as $inode=>&$file){
			if(isset($arr[$file["parent"]])){
				$arr[$file["parent"]][$inode]=&$file;
				unset($file["parent"]);
				$transforms++;
			}
		}
	}
	foreach($arr as $inode=>&$file){
		if(empty($file["parent"])){
			unset($arr[$inode]);
		}
	}
	new dBug($arr);
}

class FileTreeBackupDir extends FileTreeDir{
	public $index,$childrenCache;
	public $root,$relPath;
	
	function __construct(RecursiveDirectoryIterator  $file,&$parent=null){
		//index is singleton
		echo "<hr/>";
		if($parent instanceof FileTreeBackuper){
			echo "We are a root of the backup\n<br/>";
			echo 'Root adress is '.$file->getPathname()."\n<br/>";
			$this->index=&$parent->index;
			echo "now index is of type ".get_class($this->index)."\n<br/>";
			parent::__construct($file,$this);//the root directory is parent of oneself
			$this->root=&$this;
			$this->pathLen=strlen($this->path);
			echo "root constructed\n<br/>";
		}else{
			$this->index=&$parent->index;
			$this->root=&$parent->root;
			//$this->index=&$this->inodes[$this->parent]->index;
		
			//echo "now index is of type ".get_class($this->index)."\n<br/>";
			parent::__construct($file,$parent);
			$this->relPath=static::getRelPath();
		}
	}
	function getRelPath(){//returns relative path beginning with /
		return substr($this->path,$this->root->pathLen);
		//return (this->index->inodes[$this->parent]->relPath.'/'.$this->name);
	}
	
	function expandChildren(){
		$children=$this->index->loadChildren($this->inode);
		
		/*echo "before\n<br/>";
		new dBug($children);*/
		
		foreach($this->childrenIter as $child){
			//if($child->isDot())continue;
			if($child->isDir()){
				$child=new static($child,$this);
			}
			else{
				$child=new FileTreeItem($child,$this);
				//new dBug($child);
			}
			
			if(isset($children[$child->inode])){
				echo "There is element of children with inode {$child->inode}\n<br/>";
				echo "hash is ".$child->hash." cached hash is {$children[$child->inode]->hash}\n<br/>";
				new dBug($this->index->inodes[$child->inode]);
				
				if($children[$child->inode]->hash!=$child->hash){
					//changed
					echo "file $child->name was <font color='orange'>changed</font>: {$children[$child->inode]->hash} != {$child->hash} \n<br/>\n";
					//$this->index->changed[$child->inode]=&$child;//TODO:why does the reference cause terrible errors in logic?
					$this->index->changed[$child->inode]=$child;
				}
				else{
					echo "file $child->name was <font color='blue'>not changed</font> or ????\n<br/>\n";
					//to prevent processing unchanged
					unset($children[$child->inode]);
					continue;
				}
				unset($children[$child->inode]);
				
			}
			else{
				//added
				echo "file {$child->name} ({$child->inode}) was <font color='green'>added</font> to folder $this->name ({$this->inode})\n<br/>\n";
				//$this->index->added[$child->inode]=&$child;//TODO:why does the reference cause terrible errors in logic?
				$this->index->added[$child->inode]=$child;
			}
			$this->index->inodes[$child->inode]=$child;
			static::processChild($child);
		}
		
		/*echo "after\n<br/>";
		new dBug($children);
		var_dump($this->index);
		var_dump($this->index->added);*/
		
		foreach($children as $inode=>$cachedChild){
			echo "file {$cachedChild->name} ({$cachedChild->inode}) was deleted\n<br/>\n";
			new dBug($cachedChild);
			//$this->index->deleted[$inode]=&$cachedChild;//TODO:why does the reference cause terrible errors in logic?
			$this->index->deleted[$inode]=$cachedChild;
		}
		echo "<hr color='green'/>";
	}

	function processChild(IFileTreeItem &$fChild){
		echo "processing child {$fChild->name} ({$fChild->inode}) of {$this->name} ({$this->inode})\n<br/>\n";
		
		/*echo 'var_dump($this->index->inodes)';
		var_dump($this->index->inodes);
		echo 'var_dump($this->index)';
		var_dump($this->index);*/
		drawHierarchy($this->index->added);
		
		
		
		$fChild->process();
		echo "<hr color='red'/>";
	}
	function checkDeletedChildren(){
		
	}
};

class FileTreeBackuper implements IBackuper{
	public $index, $roots=array();
	const filesDir="files",patchesDir="patches";
	public $zip=null;
	function __construct($roots){
		$this->roots=&$roots;
	}
	function prepareForBackup(&$base){
		$this->index=new FileTreeBackuperIndex($base);
		$roots=array();
		foreach($this->roots as $root){
			echo "Creating iterator for root: address is $root   .\n<br/>";
			$flags=FilesystemIterator::CURRENT_AS_SELF;
			$root=new RecursiveDirectoryIterator($root,$flags);
			$root->setFlags($flags|FilesystemIterator::SKIP_DOTS);
			echo "Iterator created: address is ".$root->getPathname()."   .\n<br/>";
			$root=new FileTreeBackupDir($root,$this);
			$roots[$root->inode]=&$root;
			$this->index->inodes[$root->inode]=&$root;
		}
		$this->roots=&$roots;
		foreach($this->roots as &$root){
			$root->process();
		}
		drawHierarchy($this->index->added);
		echo "<hr color='purple'/>";
		static::detectMoved();
		echo "<hr color='magenta'/>";
	}
	function makeBackup(&$zip){
		$this->zip=&$zip;
		echo "archivation of changed...<br/>";
		static::archivateChanged();
		echo "archivating added...<br/>";
		static::archivateAdded();
		//todo: add processing of deleted
		
		$this->index->save();
		return array('comment'=>("+ ".count($this->index->added)
			."\n* ".count($this->index->changed)
			."\n-> ".count($this->index->moved)
			."\n- ".count($this->index->deleted)));
	}
	
	
	
	private function detectMoved(){
		echo "<hr color='lemonchiffon'/>Detecting moved....<br/>";
		foreach($this->index->deleted as $inode=>&$node){
			new dBug($node);
			if(isset($this->index->added[$inode])){
				var_dump($this->index->added[$inode]);
				var_dump($this->index->inodes[$inode]);
				var_dump($node);
				if($this->index->added[$inode]->pathhash!=$node->pathhash){
					//moved
					//var_dump($this->index->changed[$node->parent]);
					echo "file {$node->name} ({$node->inode}) was moved\n<br/>\n";
					//$this->index->moved[$node->inode]=&$node;//TODO:why does the reference cause terrible errors in logic?
					$this->index->moved[$inode]=$node;
					unset($this->index->added[$inode]);
					unset($this->index->deleted[$inode]);
				}else{
					//error
				}
			}
		}
		echo "<hr color='lemonchiffon'/>";
	}
	function needBackup(){
		return $this->index->needBackup();
	}
	
	private function archivateAdded(){
		foreach($this->index->added as $inode=>&$node){
			echo "archivating ".$this->index->inodes[$node->parent]->path.'/'.$node->name.' as '.static::filesDir.$this->index->inodes[$node->parent]->relPath.'/'.$node->name.'</br>';
			if($node instanceof IFileTreeDir){
				$this->zip->addEmptyDir(static::filesDir.$node->relPath);
				continue;
			}
			var_dump($node);
			//var_dump($this->index->inodes[$node->parent]);
			if(!$this->zip->addFile(
				$this->index->inodes[$node->parent]->path.'/'.$node->name,
				static::filesDir.$this->index->inodes[$node->parent]->relPath.'/'.$node->name)
			)throw new Exception("Cannot add file to archive"); 
			
		}
	}
	private function archivateChanged(){
		static::archivateAdded();//temporary, later will be replaced with saving patches for source code files
	}
}
?>