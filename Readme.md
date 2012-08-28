PHP Backuper	{#mainpage}
===
This is a framework which will help you making (incremental) backups of your site.
The framework is written in pure php an can be used on free hostings with supported PHP version ( 5.4+) and was designed as extensible.
Feel free to fork and modify it.

Requirements
---
PHP 5.4+

SQLite + its PDO driver

dBug - https://github.com/KOLANICH/dBug - for debug output

ZipArchive

MySQL + its PDO driver for MySQLBackuper

SabreDAV - https://github.com/KOLANICH/SabreDAV - for WebDAVUploader

DropboxUploader - https://github.com/KOLANICH/DropboxUploader - for DropboxSimpleUploader

Modules folder structure
---
There is a folder, where we store the modules, somewhere in the server. Let's call it 'modules' for example.
Then the subtree will be such:
~~~
modules
   |
   |---Backuper_______
   |            |    |
   |           ...  Backuper.php
   |
   |---Sabre________
   |         |     |
   |         ...  Dav___
   |               |   |
   |              ...  Client.php
   |
   |---DropboxUploader______
   |                  |    |
   ...               ...  DropboxUploader.php
~~~

Backup main workflow
---

Look into example.php .
To make backup you'll need:
1. to create a Backuper instance
2. to call makeBackup() method

### Creating instance
~~~{.php}
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
~~~



### Making backup
You need to call Backuper::makeBackup method.


Backupers
---
Backupers are responsible for extracting data, packing them to archive and mantaining index.
Each backuper must implement IBackuper interface.


Uploaders
---
Uploaders are responsible for upload resulting archive to different services such as DropBox, SugarSync, Google Drive, Yandex.Disk, etc.
Each uploader must implement IUploader interface.

