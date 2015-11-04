<?php
namespace Aftertime;

final class Log {
	static private $caller = '';
	static private $slow_query_log = true, $slow_query_time = 2;	// In seconds
	static private $muted = false;

	static public function log_file ($new_logsfolder = null) {
		static $logs_folder = null;
		static $filename = null;
		static $filedate = null;

		if ($new_logsfolder !== null) {
			$logs_folder = $new_logsfolder;
		}
		if (empty($logs_folder)) {
			return false;
		}

		$date = date('Y-m-d');
		if (!$filename || $date != $filedate) {
			$filedate = $date;
			$filename = "$logs_folder/$date.log";
		}
		return $filename;
	}

	static public function caller ($caller = false) {
		if (is_string($caller) || empty($caller)) {
			self::$caller = $caller;
		}
		return self::$caller;
	}

	static public function mute($mute = null) {
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

	static public function log_entry ($text, $sizelimit = 2000) {
		if (strlen($text) > $sizelimit) {
			$text = substr($text, 0, $sizelimit/2) . '...(truncated)...' . substr($text, $sizelimit*-1/2);
		}
		$time = date('H:i:s');
		$caller = self::$caller? ' '.self::$caller : '';
		if (!self::$muted) {
			$filename = self::log_file();	// Must be initialized before
			if ($filename === false) {
				echo "$time$caller $text\n";
			} else {
				error_log ("$time$caller $text\n", 3, $filename);
			} 
		}
	}

	static private function log_backtrace() {
		$level = 0;
		foreach(debug_backtrace() as $bt) {
			if ($level > 2 && !empty($bt['file'])) {
				self::log_entry("at {$bt['file']}:{$bt['line']}");
			}
			$level++;
		}
	}

	static public function php_errors ($errno, $errstr, $errfile, $errline) {
		self::log_entry("$errstr ($errfile:$errline)");
		self::log_backtrace();
		return false;
	}

	static public function php_exceptions(\Exception $ex) {
		self::log_entry('Exception: ' . $ex->getMessage());
		self::log_entry($ex->getTraceAsString());
	}

	static public function log_shutdown () {
		$error = error_get_last();
		if ($error && in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
			self::log_entry("ERROR type {$error['type']}: {$error['message']} at {$error['file']}:{$error['line']}");
			self::log_entry('Ending script');
		}
	}

	static public function slow_query_log ($status = null, $query_time = 2) {
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
