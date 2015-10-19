<?php
namespace Aftertime;

/* 
Handy DB related functions 
*/

function init_db() {
	$config = Config::get();
	if (!isset($config['database'])) {
		log_entry('No database entry found in config');
		return false;
	}
	$dbconfig = $config['database'];

	// Note we force a UTF8 connection
	$dsn = "{$dbconfig['protocol']}:host={$dbconfig['host']};dbname={$dbconfig['dbname']};charset=utf8";

	try {
		$debug = true;	// XXX
		$options = [
			\PDO::ATTR_EMULATE_PREPARES => false
		];
		if ($debug) {
			return new PDOLog($dsn, $dbconfig['user'], $dbconfig['password'], $options);
		} else {
			return new \PDO($dsn, $dbconfig['user'], $dbconfig['password'], $options);
		}
	} catch (\PDOException $e) {
		log_entry("PDO Exception: ".$e->getMessage());
		return false;
	}
}
?>
