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
			$query = str_replace(array_keys($params), $params, $query);
		}
//		log_entry("PDO query: $query");		// XXX too verbose
		$result = parent::execute($params);
		if ($this->errorCode() != \PDO::ERR_NONE) {
                        log_entry('PDO ERROR: '.$this->errorInfo()[2].' running: '.$query);
                }
		return $result;
	}

	public function prepare (string $statement, array $driver_options = array()) {
		$result = parent::prepare($statement, $driver_options);
		if ($this->errorCode() != \PDO::ERR_NONE) {
                        log_entry('PDO ERROR: '.$this->errorInfo()[2].' preparing: '.$statement);
                }
		return $result;
	}

	public function bindValue ($parameter, $value, $data_type = \PDO::PARAM_STR) {
		$this->binds[$parameter] = $value;
		return parent::bindValue($parameter, $value, $data_type);
	}
}

// Limited O/R mapping for CRUD operations
// XXX unify select() and get()?
// TODO Add "...WHERE bla IN ()" support when sent an array of values
// TODO better support for queries with NULL values
// XXX Support for tables without a _key field?
class PDOClass {

	private $_statement;	// Last PDOStatement instance
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
			if (!$first) {
				$query_fields .= ', ';
				$query_values_place .= ', ';
				$update_values .= ', ';
			} else {
				$first = false;
			}
			$query_fields .= $var;
			$query_values_place .= ":$var";
			$query_values[":$var"] = $this->$var;
			$update_values .= "$var = :$var";
		}
		return array($query_fields, $query_values_place, $query_values, $update_values);
	}

	// Runs the given SQL binded with the variables in the array, and returns an associative array with the results
	// Used to extend select()
	protected function query($sql, array $vars = null, $remap_keys = false) {
		if (!$this->_pdo) {
                        return false;
                }
		$this->_statement = $this->_pdo->prepare($sql);
		if (!$this->_statement) {
			return false;
		}
		if ($vars) {
			foreach ($vars as $name => $value) {
				$this->_statement->bindValue(":$name", $value);
			}
		}
		if ($this->_statement->execute() === false) {
			return false;
		}
		$results = $this->_statement->fetchAll(\PDO::FETCH_CLASS, get_class($this), [$this->_pdo]);
		if ($remap_keys) {
			$results2 = array();
			foreach ($results as $result) {
				$key = $result->{$result->_key};
				$results2[$key] = $result;
			}
			$results = $results2;
		}
		return $results;
	}

	public function __construct(\PDO $pdo) {
		if (!is_string($this->_table) || !is_array($this->_fields) || !is_string($this->_key)) {
			return NULL;
		}
		$this->_pdo = $pdo;
	}

	public function toArray() {
		foreach ($this->_fields as $var) {
			if (isset($this->$var)) {
				$result[$var] = $this->$var;
			}
		}
		return $result;
	}
	
	public function insert() {
		if (!$this->_pdo) {
			return false;
		}
		list($query_fields, $query_values_place, $query_values) = $this->get_query_parts();
		$query = "INSERT INTO {$this->_table} ($query_fields) VALUES ($query_values_place)";
		$this->_statement = $this->_pdo->prepare($query);
		if (!$this->_statement) {
			return false;
		} else {
			if ($this->_statement->execute($query_values) === false) {
				return false;
			} else {
				if (!isset($this->{$this->_key})) {
					$this->{$this->_key} = $this->_pdo->lastInsertId();
				}
				return true;
			}
		}
	}

	public function update() {
		if (!$this->_pdo) {
			return false;
		}
		$key = $this->_key;
		if (!isset($this->$key)) {
			return false;
		}
		list($query_fields, $query_values_place, $query_values, $update_values) = $this->get_query_parts();
		$query = "UPDATE {$this->_table} SET $update_values WHERE {$this->_key} = :{$this->_key}";
		$this->_statement = $this->_pdo->prepare($query);
		if (!$this->_statement) {
			return false;
                }
		return $this->_statement->execute($query_values);
	}

	function delete($key_value = NULL) {
		if (!$this->_pdo) {
                        return false;
                }
		$keyname = $this->_key;
		if ($key_value === NULL) {
			if (!isset($this->$keyname)) {
				return null;
			}
			$key_value = $this->$keyname;
		}
		$query = "DELETE FROM {$this->_table} WHERE $keyname = :value";
		$this->_statement = $this->_pdo->prepare($query);
		if (!$this->_statement) {
			return false;
                }
		return $this->_statement->execute([':value' => $key_value]);
	}

	public function select($key = NULL, $value = NULL) {
		$query = "SELECT * FROM {$this->_table}";
		if (isset($key)) {
			if (!in_array($key, $this->_fields) || !isset($value)) {
				return false;
			}
			$query .= " WHERE $key = :value";
		}
		return $this->query($query, ["value" => $value]);
	}

	// Gets an element selected by the class' key
	public function get($id) {
		$objects = $this->select($this->_key, $id);
		if ($objects === false || count($objects) == 0) {
			return false;
		}
		$this->copy($objects[0]);
		return true;
	}

	public function copy($object) {
		foreach ($this->_fields as $var) {
			if (isset($object->$var)) {
				$this->$var = $object->$var;
			}
		}
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
		if ($debug) {
			return new PDOLog($dsn, $dbconfig['user'], $dbconfig['password']);
		} else {
			return new \PDO($dsn, $dbconfig['user'], $dbconfig['password']);
		}
	} catch (\PDOException $e) {
		log_entry("PDO Exception: ".$e->getMessage());
		return false;
	}
}
?>
