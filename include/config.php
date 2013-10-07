<?php
// TODO Keep the config cached in memory between PHP calls somehow
// TODO This needs some proper logging that does not depend in the config (stderr? stdout?)

// Initialization of the framework
function aftertime_init($web_init = true) {
	$config = Config::init();
	if ($config === false) {
		log_entry('ERROR: bad config');
		return false;
	}

	// Init the rest (needs config)
	ini_set ('date.timezone', $config['timezone']);
	ini_set ('include_path', '.' . PATH_SEPARATOR . 'lib/pear/php' . PATH_SEPARATOR . "sites/{$config['site']}");	// Adds the site folder
	ini_set ('error_reporting', 'E_ALL & ~E_STRICT');

	if (class_exists('Log')) {
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

	private function __construct() {
	}

	// Load everything that matches config/*.json
	static public function init() {
		// Load aftertime config
		$conf = self::load_json('config/aftertime.json');
		if (empty($conf)) {
			return false;
		}

		// Get site's config files
		$files = glob("sites/{$conf['active_site']}/config/*.json");
		$hostname_file = "sites/{$conf['active_site']}/config/hostname_".gethostname().'.json';
		if (($key = array_search($hostname_file, $files)) !== false) {	// Moves hostname.conf to the end
			unset($files[$key]);
			$files[] = $hostname_file;
		}

		// Load them
		foreach ($files as $filename) {
			if (strpos($filename, 'hostname_') !== false && $filename != $hostname_file) {
				continue;	// Avoid hostname config, but the current one
			}

			$fileconf = (array)self::load_json($filename);
			if (!$fileconf) {	// Fail as soon as one of the files cannot be loaded
				if (function_exists('json_last_error_msg')) {
					$error = json_last_error_msg();
				}
//				echo "$filename wrong $error";
				return false;
			}
			$conf = array_merge_recursive_distinct($conf, $fileconf);
//			echo "$filename loaded\n";
		}

		unset($conf['active_site']);
		self::$config = $conf;
		return self::$config;
	}

	private function load_json($file) {
		if (!is_readable($file)) {
			return false;
		}
		if (($contents = file_get_contents($file)) === false) {
			return false;
		} else {
			if (!function_exists('json_decode')) {
				// XXX i.e. Ubuntu needs php5-json package installed
				return false;
			}
			return json_decode($contents, true);
		}
	}

/* Outdated
	static public function store($filename) {
		if (!create_file($filename)) {
			return false;
		} else if (file_put_contents($filename, self::$config, LOCK_EX) === false) {
			return false;
		}
		return true;
	}
*/

	// Get all the config or the one for the specified app
	static public function get($app_name = false) {
		if (!self::$config) {
			return false;
		}
		if ($app_name) {
			if (!isset(self::$config['apps'])) {
				return false;
			}
			if (!isset(self::$config['apps'][$app_name])) {
				return false;
			}
			return self::$config['apps'][$app_name];
		}
		return self::$config;
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
