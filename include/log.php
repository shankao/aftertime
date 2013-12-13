<?php
require_once 'include/aftertime.php';
require_once 'include/config.php';

function log_entry ($text, $sizelimit = 2000) {
	return Log::log_entry ($text, $sizelimit);
}

function log_entry_db ($class, $message, $logtype, $level) {
	return Log::log_entry_db ($class, $message, $logtype, $level);
}

final class Log {
	static private $caller = '';
	static private $slow_query_log = true, $slow_query_time = 2;	// In seconds
	static private $muted = false;

	public function out ($text, $commented = true) {
		$time = date('H:i:s');
		$text = "$time: $text";
		if ($commented) {
			$text = "<!-- $text -->";
		}
		echo "$text\n";
	}

	static function log_file () {
		static $logs_folder = null;
		static $filename = null;
		static $filedate = null;

		$date = date('Y-m-d');
		if (!$filename || $date != $filedate) {
			if (!isset($logs_folder)) {
				$config = Config::get();
				if ($config && isset($config['logs'])) {
					$logs_folder = $config['logs'];
				} else {
					$logs_folder = sys_get_temp_dir().'/logs';
				}
				if (!create_file($logs_folder, true, 0777)) {
					self::out("Cannot create the logs folder: $filename");
					self::$muted = true;
					return false;
				}
			}

			$filename = "$logs_folder/$date.log";
			$filedate = $date;

			if (!create_file($filename, false, 0666)) {
				self::out("Cannot write in the logs file: $filename");
				self::$muted = true;
				return false;
			}
		}
		return $filename;
	}

	static function caller ($caller = false) {
		if ($caller && is_string($caller)) {
			self::$caller = $caller;
		}
		return self::$caller;
	}

	static function mute($mute = null) {
		if ($mute !== null && is_bool($mute)) {
			if ($mute == true) {
				self::log_entry('Logs are muted');
	                        self::$muted = true;
			} else {
				self::$muted = false;
				self::log_entry('Logs are unmuted');
			}
                }
                return self::$muted;
	}

	static function log_entry ($text, $sizelimit = 2000) {
		if (strlen($text) > $sizelimit) {
			$text = substr($text, 0, $sizelimit/2) . '...(truncated)...' . substr($text, $sizelimit*-1/2);
		}
		$time = date('H:i:s');
		$caller = self::$caller? ' '.self::$caller : '';
		if (!self::$muted) {
			error_log ("$time$caller $text\n", 3, self::log_file());
		}
	}

	static function log_entry_db ($class, $message, $logtype, $level) {
		$message = str_replace("\n", '', $message);

		if (self::$slow_query_log && preg_match('/QUERY DONE IN  ([0-9.]+) seconds/', $message, $matches)) {
			if ($matches[1] > self::$slow_query_time) {
				self::log_entry('SLOW QUERY');
			}
		}

		if (in_array($logtype, array('CONNECT FAILED')) ||
			$level == 1 && in_array($logtype, array('QUERY', 'ERROR', 'query', 'Query Error'))
			) {
			self::log_entry("$class/$logtype: $message");
		}
	}

	static function log_backtrace() {
		$level = 0;
		foreach(debug_backtrace() as $bt) {
			if ($level > 2 && !empty($bt['file'])) {
				self::log_entry("at {$bt['file']}:{$bt['line']}");
			}
			$level++;
		}
	}

	static function php_errors ($errno, $errstr, $errfile, $errline) {
		if ($errno != E_STRICT) {
			self::log_entry("$errstr ($errfile:$errline)");
			self::log_backtrace();
		}
		return false;
	}

	static function log_shutdown () {
		$error = error_get_last();
		if ($error && in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
			self::log_entry("ERROR type {$error['type']}: {$error['message']} at {$error['file']}:{$error['line']}");
			self::log_entry('Ending script');
		}
	}

	static function slow_query_log ($status = null, $query_time = 2) {
		if ($status === null) {
			return self::$slow_query_log;
		}
		if (is_bool($status)) {
			self::$slow_query_log = $status;
		}
		if (is_numeric($query_time)) {
			self::$slow_query_time = $query_time;
		}
	}

	private function __construct() {
	}
}
?>
