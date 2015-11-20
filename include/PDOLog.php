<?php
namespace Aftertime;

class PDOLog extends \PDO {
	function __construct($dsn, $username='', $password='', $driver_options=array()) {
		parent::__construct ($dsn, $username, $password, $driver_options);
		$this->setAttribute (\PDO::ATTR_STATEMENT_CLASS, array('Aftertime\PDOStatementLog', array($this)));
	}
}

