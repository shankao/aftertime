<?php
namespace Aftertime;

/* 
Handy DB related functions 
*/
require_once __DIR__.'/config.php';
require_once __DIR__.'/log.php';

class PDOLog extends \PDO {
	function __construct($dsn, $username='', $password='', $driver_options=array()) {
		parent::__construct ($dsn, $username, $password, $driver_options);
		$this->setAttribute (\PDO::ATTR_STATEMENT_CLASS, array('Aftertime\PDOStatementLog', array($this)));
	}
}

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

// Limited O/R mapping for CRUD operations
// XXX Support for tables without a _key field? I.e. m-n relations tables do have more than one key
class PDOClass {

	protected $_statement;	// Last PDOStatement instance
	protected $_pdo;	// PDO instance

	protected $_table;	// Override 
	protected $_fields;	// Override 
	protected $_key;	// Override 

	private function get_query_parts() {
		$first = true;
		$query_fields = $query_values_place = $update_values = '';
		$query_values = array();
		foreach ($this->_fields as $var) {
			if (!isset($this->$var)) {
				continue;
			}
			if ($this->$var === 'NULL') {
				$this->$var = null;
			}
			if (!$first) {
				$query_fields .= ', ';
				$query_values_place .= ', ';
				$update_values .= ', ';
			} else {
				$first = false;
			}
			$query_fields .= $var;
			$query_values_place .= ":$var";
			$query_values["$var"] = $this->$var;
			$update_values .= "$var = :$var";
		}
		return array($query_fields, $query_values_place, $query_values, $update_values);
	}

	private function copy($object) {
		foreach ($this->_fields as $var) {
			if (isset($object->$var)) {
				$this->$var = $object->$var;
			}
		}
	}

	private function get_pdo_type($value) {
		if (is_null($value)) {
			return \PDO::PARAM_NULL;
		} else if (is_bool($value)) {
			return \PDO::PARAM_BOOL;
		} else if (is_integer($value)) {
			return \PDO::PARAM_INT;
		} else {
			return \PDO::PARAM_STR;
		}
	}

	// Runs the given SQL binded with the variables in the array, and returns an associative array with the results
	protected function query($sql, array $vars = null, $do_fetch = true) {
		if (!$this->_pdo) {
			return false;
		}
		$this->_statement = $this->_pdo->prepare($sql);
		if (!$this->_statement) {
			if ($this->_pdo->errorCode() != \PDO::ERR_NONE) {
				log_entry('PDO ERROR: '.$this->_pdo->errorInfo()[2].' preparing: '.$sql);
			}
			return false;
		}
		if ($vars) {
			foreach ($vars as $name => $value) {
				if (is_array($value)) {
					if (count($value) == 0) {
						continue;
					}
					$i = 0;
					foreach ($value as $val) {
						$this->_statement->bindValue(":$name"."_$i", $val, $this->get_pdo_type($val));
						$i++;
					}
				} else {
					$this->_statement->bindValue(":$name", $value, $this->get_pdo_type($value));
				}
			}
		}
		if ($this->_statement->execute() === false) {
			return false;
		}
		if ($do_fetch) {	
			return $this->_statement->fetchAll(\PDO::FETCH_CLASS, get_class($this), [$this->_pdo]);
		} else {
			return true;
		}
	}

	public function __construct(\PDO $pdo) {
		if (!is_string($this->_table) || !is_array($this->_fields) || !is_string($this->_key)) {
			return NULL;
		}
		$this->_pdo = $pdo;
	}

	public function insert() {
		if (!$this->_pdo) {
			return false;
		}
		list($query_fields, $query_values_place, $query_values) = $this->get_query_parts();
		$sql = "INSERT INTO {$this->_table} ($query_fields) VALUES ($query_values_place)";
		if ($this->query($sql, $query_values, false) === false) {
			return false;
		}
		if (!isset($this->{$this->_key})) {
			$this->{$this->_key} = $this->_pdo->lastInsertId();
		}
		return true;
	}

	public function update() {
		if (!$this->_pdo) {
			return false;
		}
		$key_name = $this->_key;
		if (!isset($this->$key_name)) {
			return false;
		}
		list($query_fields, $query_values_place, $query_values, $update_values) = $this->get_query_parts();
		$sql = "UPDATE {$this->_table} SET $update_values WHERE $key_name = :key_$key_name";
		$query_values["key_$key_name"] = $this->$key_name;
		return $this->query($sql, $query_values, false);
	}

	public function delete() {
		if (!$this->_pdo) {
                        return false;
                }
		$key_name = $this->_key;
		if (!isset($this->$key_name)) {
			return null;
		}
		$sql = "DELETE FROM {$this->_table} WHERE $key_name = :$key_name";
		return $this->query($sql, [$key_name => $this->$key_name], false);
	}

	// Does a SELECT with the fields of the object, combined by an AND operator
	// TODO deal with values that can be NULL
	public function select($fetch_first = false) {
		$query = "SELECT * FROM {$this->_table}";
		$first_field = true;
		$query_values = array();
		foreach ($this->_fields as $field) {
			if (isset($this->$field)) {
				if ($first_field) {
					$query .= ' WHERE';
					$first_field = false;
				} else {
					$query .= ' AND';
				}
				$query .= " $field";
				if (is_array($this->$field)) {
					if (count($this->$field) == 0) {
						continue;
					}
					$query .= ' IN (';
					for ($i = 0; $i < count($this->$field); $i++) {
						if ($i > 0) {
							$query .= ', ';
						}
						$query .= ":$field"."_$i";
					}
					$query .= ')';
				} else {
					$query .= " = :$field";
				}
				$query_values[$field] = $this->$field;
			}
		}
		$results = $this->query($query, $query_values);
		if ($results === false) {
			return false;
		} 
		if ($fetch_first) {
			if (count($results) > 0) {
				$this->copy($results[0]);
			} else {	
				return false;	// Asked to fetch first, but no results
			}
		}
		return $results;
	}

	public function errorInfo() {
		return $this->_statement->errorInfo();
	}
}

function init_db() {
	$config = Config::get();
	if (!isset($config['database'])) {
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
