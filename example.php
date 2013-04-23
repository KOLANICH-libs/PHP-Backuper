<?php
require_once("Backuper.php");
$b=new Backuper([
		"upload"=>[
			"WebDAV"=>[
				"server"=>'https://webdav.yandex.ru/',
				"login"=>"test",
				"pass"=>"test",
				"dir"=>'/Backups'
			],
			'DropboxSimple'=>[
				"login"=>"test@test.ru",
				"pass"=>'test',
				"dir"=>'/Backups'
			],
			/*'SugarSync'=>array( //now this doesn't work
				"login"=>"test@test.ru",
				"pass"=>'test',
				"dir"=>'/Backups',
				"accessKeyId"=>"AAAAAAAAAAAAAAAAAAAAAAAAAAA",
				"privateAccessKey"=>"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA",
			),*/
		],
		'backup'=>array(
			"FileTree"=>[
				"."
			],
			"MySQL"=>array(
				"base"=>new PDO('mysql:dbname=test;host=127.0.0.1',"test"),
			),
			//or "MySQL"=>new PDO('mysql:dbname=test;host=127.0.0.1',"root","",array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION)),
		)
]);
$b->makeBackup();

//or
$b=new Backuper(
	json_decode(file_get_contents("prefs.json"),1)
);
$b->makeBackup();
?>
