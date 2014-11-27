<?php /* 
Handy DB related functions 
*/
require_once __DIR__.'/config.php';
require_once __DIR__.'/log.php';

function init_db($log_function = 'log_entry_db') {
        static $done = false;
        if (!$done) {
		$config = Config::get();
                if (!isset($config['database'])) {
                        return false;
                }

                // Note we force a UTF8 connection
                $dbconfig = $config['database'];
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
                        include_once "{$site}/db/db.php";
                        log_entry('No site specific DB config defined');
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
