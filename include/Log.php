<?php
namespace Aftertime;

final class Log {
	static private $caller = '';
	static private $slow_query_log = true, $slow_query_time = 2;	// In seconds
	static private $muted = false;

	static public function setFile ($new_logsfolder = null) {
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

	static public function mute ($mute = null) {
		if ($mute !== null && is_bool($mute)) {
			if ($mute == true) {
				self::logEntry('Logs are muted');
	                        self::$muted = true;
			} else {
				self::$muted = false;
				self::logEntry('Logs are unmuted');
			}
                }
                return self::$muted;
	}

	static public function logEntry ($text, $sizelimit = 2000) {
		if (strlen($text) > $sizelimit) {
			$text = substr($text, 0, $sizelimit/2) . '...(truncated)...' . substr($text, $sizelimit*-1/2);
		}
		$time = date('H:i:s');
		$caller = self::$caller? ' '.self::$caller : '';
		if (!self::$muted) {
			$filename = self::setFile();	// Must be initialized before
			if ($filename === false) {
				echo "$time$caller $text\n";
			} else {
				error_log ("$time$caller $text\n", 3, $filename);
			} 
		}
	}

	static private function logBacktrace() {
		$level = 0;
		foreach(debug_backtrace() as $bt) {
			if ($level > 2 && !empty($bt['file'])) {
				self::logEntry("at {$bt['file']}:{$bt['line']}");
			}
			$level++;
		}
	}

	static public function phpErrors ($errno, $errstr, $errfile, $errline) {
		self::logEntry("$errstr ($errfile:$errline)");
		self::logBacktrace();
		return false;
	}

	static public function phpExceptions(\Exception $ex) {
		self::logEntry('Exception: ' . $ex->getMessage());
		self::logEntry($ex->getTraceAsString());
	}

	static public function logShutdown () {
		$error = error_get_last();
		if ($error && in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
			self::logEntry("ERROR type {$error['type']}: {$error['message']} at {$error['file']}:{$error['line']}");
			self::logEntry('Ending script');
		}
	}

	static public function logSlowQuery ($status = null, $query_time = 2) {
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

