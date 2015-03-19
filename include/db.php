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

	protected function __construct($dbh) {
		$this->dbh = $dbh;
	}

	public function execute (array $params = NULL) {
		log_entry("PDOquery: {$this->queryString}");
		if ($params) {
			log_entry('PDOquery values: '.print_r($params, true));
		}
		parent::execute($params);
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
