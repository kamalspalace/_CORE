<?php
session_start();

require_once("helpers/sql_handler.php");
require_once("includes/view_manager.php");
require_once("includes/functions.php");
require_once("helpers/error_handler.php");

$_REQUEST['lang'] = "EN";

	function clean_string($string)
	{
  		$from = explode(',', "ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u,(,),[,],'");
  		$to = explode(',', 'c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u,,,,,,');

  		//Do the replacements, and convert all other non-alphanumeric characters to spaces
  		$value = preg_replace('~[^\w\d]+~', ' ', str_replace($from, $to, trim($string)));

  		return $value;
	}

?>
