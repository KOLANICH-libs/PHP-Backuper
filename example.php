<?
require_once("Backuper.php");
$b=new Backuper(
	array(
		"upload"=>array(
			"WebDAV"=>array(
				"server"=>'https://webdav.yandex.ru/',
				"login"=>"test",
				"pass"=>"test",
				"dir"=>'/Backups'
			),
			'DropboxSimple'=>array(
				"login"=>"test@test.ru",
				"pass"=>'test',
				"dir"=>'/Backups'
			)
		),
		'backup'=>array(
			"FileTree"=>array(
				"."
			),
			"MySQL"=>array(
				"base"=>new PDO('mysql:dbname=test;host=127.0.0.1',"test"),
			),
			/*'SugarSync'=>array( //now this doesn't work
				"login"=>"test@test.ru",
				"pass"=>'test',
				"dir"=>'/Backups',
				"accessKeyId"=>"AAAAAAAAAAAAAAAAAAAAAAAAAAA",
				"privateAccessKey"=>"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA",
			),*/
		)
	)
);
$b->makeBackup();
?>