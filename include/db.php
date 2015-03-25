<?php /* 
Handy DB related functions 
*/
require_once __DIR__.'/config.php';
require_once __DIR__.'/log.php';

class PDOLog extends PDO {
	function __construct($dsn, $username='', $password='', $driver_options=array()) {
		parent::__construct ($dsn, $username, $password, $driver_options);
		$this->setAttribute (PDO::ATTR_STATEMENT_CLASS, array('PDOStatementLog', array($this)));
	}
}

class PDOStatementLog extends PDOStatement {
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
		log_entry("PDO query: $query");
		$result = parent::execute($params);
		if ($this->errorCode() != PDO::ERR_NONE) {
                        log_entry('PDO ERROR: '.$this->errorInfo()[2]);
                }
		return $result;
	}

	public function prepare (string $statement, array $driver_options = array()) {
		$result = parent::prepare($statement, $driver_options);
		if ($this->errorCode() != PDO::ERR_NONE) {
                        log_entry('PDO ERROR: '.$this->errorInfo()[2]);
                }
		return $result;
	}

	public function bindValue ($parameter, $value, $data_type = PDO::PARAM_STR) {
		$this->binds[$parameter] = $value;
		return parent::bindValue($parameter, $value, $data_type);
	}
}

// Limited O/R mapping
class PDOClass {

	protected $_pdo;	// PDO instance
	protected $_table;	// Override 
	protected $_fields;	// Override 
	protected $_key;	// Override 

	public function __construct(PDO $pdo) {
		if (!is_string($this->_table) || !is_array($this->_fields) || !is_string($this->_key)) {
			return NULL;
		}
		$this->_pdo = $pdo;
	}

	public function toArray() {
		foreach ($this->_fields as $var) {
			$result[$var] = $this->$var;
		}
		return $result;
	}

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

	public function insert() {
		list($query_fields, $query_values_place, $query_values) = $this->get_query_parts();
		$query = "INSERT INTO {$this->_table} ($query_fields) VALUES ($query_values_place)";
		$statement = $this->_pdo->prepare($query);
		if (!$statement) {
			return false;
		} else {
			if ($statement->execute($query_values) === false) {
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
		$key = $this->_key;
		if (!isset($this->$key)) {
			return false;
		}
		list($query_fields, $query_values_place, $query_values, $update_values) = $this->get_query_parts();
		$query = "UPDATE {$this->_table} SET $update_values WHERE {$this->_key} = :{$this->_key}";
		$statement = $this->_pdo->prepare($query);
		if (!$statement) {
			return false;
                }
		return $statement->execute($query_values);
	}

	function upsert() {
	}

	// TODO: allow id argument to delete directly
	function delete() {
		$keyname = $this->_key;
		if (!isset($this->$keyname)) {
			return null;
		}
		$query = "DELETE FROM {$this->_table} WHERE $keyname = :value";
		$statement = $this->_pdo->prepare($query);
		if (!$statement) {
			return false;
                }
		return $statement->execute([':value' => $this->$keyname]);
	}

	public function get($id) {
		$statement = $this->_pdo->prepare("SELECT * FROM {$this->_table} WHERE {$this->_key} = :id");
		if ($statement->execute(array(':id' => $id)) === false) {
			return false;
		} else {
			$data = $statement->fetch(PDO::FETCH_ASSOC);
			if ($data) {
				foreach ($this->_fields as $var) {
					$this->$var = $data[$var];
				}
			}
			return $data;
		}
	}

	// get() is a specific case of this method, with key hardcoded to this->_key and this returns an array of rows
	public function find($key, $value) {
                $statement = $this->_pdo->prepare("SELECT * FROM {$this->_table} WHERE $key = :value");
                if ($statement->execute([':value' => $value]) === false) {
			return false;
		} else {
			return $statement->fetchAll(PDO::FETCH_ASSOC);
		}
        }

	// TODO Unfiltered query: this is generally a bad idea, don't use
	function get_all () {
		$statement = $this->_pdo->prepare("SELECT * FROM {$this->_table}");
		if (!$statement) {
                        return false;
                }
                if ($statement->execute() === false) {
			return false;
                } else {
	                return $statement->fetchAll();
		}
	}
}

function init_db($log_function = 'log_entry_db') {
        static $done = false;
	if (!$done) {
		$config = Config::get();
		if (!isset($config['database'])) {
			return false;
		}

		$dbconfig = $config['database'];
		if ($dbconfig['use_pdo']) {
			// Note we force a UTF8 connection
			$dsn = "{$dbconfig['protocol']}:host={$dbconfig['host']};dbname={$dbconfig['dbname']};charset=utf8";
			try {
				$debug = true;	// XXX
				if ($debug) {
					return new PDOLog($dsn, $dbconfig['user'], $dbconfig['password']);
				} else {
					return new PDO($dsn, $dbconfig['user'], $dbconfig['password']);
				}
			} catch (PDOException $e) {
				log_entry("PDO Exception: ".$e->getMessage());
				return false;
			}
		} else {
			// Note we force a UTF8 connection
			$dsn = "{$dbconfig['protocol']}://{$dbconfig['user']}:{$dbconfig['password']}@{$dbconfig['host']}/{$dbconfig['dbname']}?charset=utf8";
			$options = &PEAR::getStaticProperty('DB_DataObject', 'options');
			$options = array(
					'database' => $dsn,
					'db_driver' => 'MDB2',
					'proxy' => 'full',
					'quote_identifiers' => 1,
					'class_prefix' => 'db_',
					'dont_die' => 1
					);

			if ($log_function) {
				DB_DataObject::debugLevel($log_function);
			}

			// Check/start the connection
			$d = new DB_DataObject;
			$c = $d->getDatabaseConnection();
			if (db_error()) {
				return false;
			}

			// Site specific DB config (like O/R mapping classes)
			$site = $config['site'];
			if (is_readable("{$site}/db/db.php")) {
				log_entry('DEPRECATED: site specific DB config defined');	// XXX
				include_once "{$site}/db/db.php";
			}
		}

                $done = true;
        }
        return $done;
}

function db_error() {
        $error = PEAR::getStaticProperty('DB_DataObject','lastError');
        if (PEAR::isError($error)) {
                if (preg_match('/\[Native message: (.*?)\]/m', $error->toString(), $matches)) {
                        $error_message = $matches[1];
                } else if (preg_match('/\message="(.*?)"/m', $error->toString(), $matches)) {
                        $error_message = $matches[1];
                } else {
                        $error_message = $error->toString();
                }
                log_entry("db_error(): $error_message");
                return $error_message;
        } else {
                return false;
        }
}

// Very simple multiquery implementation. Will have parsing problems.
function multiquery($sql_code) {
	$sql_code = explode(';', $sql_code);
	foreach ($sql_code as $statement) { 
		// Comment stripping supported when the only thing in line
		$sql = '';
		foreach (explode("\n", $statement) as $line) {
			if (strpos(ltrim($line), '--') === 0) {	// Comment line
				continue;
			}
			$sql .= "$line\n";
		}
		$sql = rtrim($sql, "\n");

		if (empty($sql)) {
			continue;
		}

		$db = new DB_DataObject;
		$db->query($sql);
		if (db_error()) {
			log_entry ("ERROR processing SQL statement: $sql");
			return false;
		}
	}
	return true;
}
?>
