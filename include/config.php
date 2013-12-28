<?php
// TODO Keep the config cached in memory between PHP calls somehow

// Initialization of the framework
function aftertime_init($web_init = true) {
	$config = Config::init();
	if ($config === false) {
		return false;
	}

	if (isset($config['timezone'])) {
		ini_set ('date.timezone', $config['timezone']);
	}
	ini_set ('include_path', '.' . PATH_SEPARATOR . 'lib/pear/php' . PATH_SEPARATOR . "sites/{$config['site']}");	// Adds the site folder
	ini_set ('error_reporting', 'E_ALL & ~E_STRICT');

	if (class_exists('Log')) {
		log_entry(Config::init_log());
		ini_set ('error_log', Log::log_file());
		set_error_handler(array('Log', 'php_errors'));
		set_exception_handler(array('Log', 'php_errors'));
		register_shutdown_function(array('Log', 'log_shutdown'));
	}

	if ($web_init) {
		ob_start(null, 4096);
		ini_set ('arg_separator.output', '&amp;');
		if (!session_start()) {
			log_entry('ERROR: Cannot start session');
			return false;
		}
	}

	return true;
}

final class Config {
	static private $config = null;
	static private $log = '';

	private function __construct() {
	}

	// Load everything that matches config/*.json
	static public function init() {
		self::log("Config::init()");
		$error = false;

		// Load build config
		$filename = 'config/aftertime.json';
		if (self::load_json($filename) === null) {
			$error = true;
		}

		// Get site's config files
		$site = self::$config['active_site'];
		$files = glob("sites/$site/config/*.json");
		$hostname_file = "sites/$site/config/hostname_".gethostname().'.json';
		if (($key = array_search($hostname_file, $files)) !== false) {	// Moves hostname.conf to the end
			unset($files[$key]);
			$files[] = $hostname_file;
		}

		// Load them
		foreach ($files as $filename) {
			if (strpos($filename, 'hostname_') !== false && $filename != $hostname_file) {
				self::log("Skip $filename: not current host");
				continue;	// Skip hostname config, if not for the current one
			}
			if (self::load_json($filename) === null) {
				$error = true;
			}
		}

		if ($error) {
			return false;
		} else {
			// Config initialized
			self::log('SUCCESS: all config loaded');
			unset(self::$config['active_site']);
			return self::$config;
		}
	}

	private function load_json($file) {
		$fileconf = null;
		$error = false;
		if (is_readable($file)) {
			if (($contents = file_get_contents($file)) !== false) {
				if (function_exists('json_decode')) {	// XXX i.e. Ubuntu needs php5-json package installed
					$fileconf = json_decode($contents, true);
					if (!$fileconf) {
						if (function_exists('json_last_error_msg')) {
							$error = json_last_error_msg();
						} else {
							$error = 'Error in json_decode()';
						}
					}
				} else {
					$error = 'json_decode() function is not available';
				}
			} else {
				$error = 'Cannot read file';
			}
		} else {
			$error = 'Cannot read file';
		}

		if ($error) {
			self::log("Loading '$file': ERROR ($error)");
		} else {
			if (self::$config === null) {
				self::$config = $fileconf;
			} else {
				self::$config = (array)array_merge_recursive_distinct(self::$config, $fileconf);
			}
			self::log("Loading '$file': OK");
		}
		return $fileconf;
	}

	// Private logging
	private function log($string) {
		self::$log .= "$string\n";
	}

	static public function init_log() {
		return self::$log;
	}

	// Get all the config or the one for the specified app
	static public function get($app_name = false, $page_name = false) {
		if (!self::$config) {
			return false;
		} else {
			$config = self::$config;
			if ($app_name === false) {
				return $config;
			} else {
				if (!isset($config['apps']) || !isset($config['apps'][$app_name])) {
					return false;
				} else {
					$app_config = $config['apps'][$app_name];
					if ($page_name === false) {
						return $app_config;
					} else {
						if (!isset($app_config['pages']) || !isset($app_config['pages'][$page_name])) {
                                        		return false;
                                		} else {
							$page_config = $app_config['pages'][$page_name];
							return $page_config;
						}
					}
				}
			}
		}
	}

	static public function set(array $config = null) {
		self::$config = $config;
	}
}

/**
 * array_merge_recursive does indeed merge arrays, but it converts values with duplicate
 * keys to arrays rather than overwriting the value in the first array with the duplicate
 * value in the second array, as array_merge does. I.e., with array_merge_recursive,
 * this happens (documented behavior):
 *
 * array_merge_recursive(array('key' => 'org value'), array('key' => 'new value'));
 *     => array('key' => array('org value', 'new value'));
 *
 * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
 * Matching keys' values in the second array overwrite those in the first array, as is the
 * case with array_merge, i.e.:
 *
 * array_merge_recursive_distinct(array('key' => 'org value'), array('key' => 'new value'));
 *     => array('key' => array('new value'));
 *
 * Parameters are passed by reference, though only for performance reasons. They're not
 * altered by this function.
 *
 * @param array $array1
 * @param array $array2
 * @return array
 * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
 * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
 */
function array_merge_recursive_distinct (array $array1, array $array2) {
	$merged = &$array1;
	foreach ($array2 as $key => $value) {
		if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
			$merged[$key] = array_merge_recursive_distinct($merged[$key],$value);
		} else {
			$merged[$key] = $value;
		}
	}
	return $merged;
}
?>
