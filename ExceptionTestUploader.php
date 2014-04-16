<?php
	/*!
		This plugin is for testing exceptions in plugins initialization phase
	*/
	class ExceptionTestUploader implements IUploader{
	function __construct($prefs){
		throw new Exception("This plugin is for testing exceptions in plugins initialization phase");
	}
	function upload(string $fileName,string $as){
	}
};
?>