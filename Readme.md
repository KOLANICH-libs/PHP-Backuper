PHP Backuper
=============
This is a framework which will help you making (incremental) backups of your site.
The framework is written in pure php an can be used on free hostings with supported PHP version ( 5.4+) and was designed as extensible.

Requirements and modules folder structure
-------------------------------------
PHP 5.4+
sqlite
ZipArchive
PDO + mysql for MySQLBackuper
dBug - https://github.com/KOLANICH/dBug - for debug output
SabreDAV - https://github.com/KOLANICH/SabreDAV - for WebDAVUploader
DropboxUploader - https://github.com/KOLANICH/DropboxUploader - for DropboxSimpleUploader


Backup main workflow
--------------------

Look into example.php .
To make backup you'll need:
1. to create a Backuper object
2. to call makeBackup() method

### Creating object

	new Backuper(
		array(
			"upload"=>array(
				/*here should be a list of upload plugins (further uploaders)*/
			),
			'backup'=>array(
				/*here should be a list of backup pluguns (further backupers)*/
			)
		)
	);

Backupers are responsible for extracting data, packing them to archive and mantaining index.
Uploaders are responsible for upload resulting archive to different services such as DropBox, SugarSync, Google drive, etc.

Uploaders
-----------
Each uploader must implement IUploader interface.

Backupers
------------
Each backuper must implement IBackuper interface.