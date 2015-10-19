<?php
namespace Aftertime;

// TODO add optional per-query logging
// TODO add optional per-query benchmarking
class PDOStatementLog extends \PDOStatement {
	protected $dbh;
	private $binds = array();

	protected function __construct($dbh) {
		$this->dbh = $dbh;
	}

	public function execute (array $params = NULL) {
		$query = $this->queryString;
		if (!$params && !empty($this->binds)) {
			$params = $this->binds;
		}
		if ($params) {
			$params_print = array_map(
				function($value) {
					if (is_null($value)) {
						return 'NULL';
					} else if ($value === false) {
						return 'false';
					} else if ($value === true) {
						return 'true';
					} else {
						return $value;
					}
				},
				$params
			);
			$query = str_replace(array_keys($params_print), $params_print, $query);
		}
//		log_entry("PDO query: $query");		// XXX too verbose
		$result = parent::execute($params);
		if ($this->errorCode() != \PDO::ERR_NONE) {
                        log_entry('PDO ERROR: '.$this->errorInfo()[2].' running: '.$query);
                }
		return $result;
	}

	public function bindValue ($parameter, $value, $data_type = \PDO::PARAM_STR) {
		$this->binds[$parameter] = $value;
		return parent::bindValue($parameter, $value, $data_type);
	}
}
?>
