<?php
// TODO Keep the config cached in memory between PHP calls somehow
final class Config {
	static private $config = null;
	static private $log = '';

	private function __construct() {
	}

	// Loads all the config files in the folder
	static public function load($folder) {
		// Get config files' list
		$files = glob("$folder/*.json");
		$host_config = "$folder/hostname_".gethostname().'.json';
		if (($key = array_search($host_config, $files)) !== false) {
			// Moves host config to the end for overriding
			unset($files[$key]);
			$files[] = $host_config;
		}

		// Load them
		$return = true;
		if (count($files)) {
			foreach ($files as $filename) {
				if (strpos($filename, 'hostname_') !== false && $filename != $host_config) {
					// Skip hostname config, if not for the current one
					self::log("Skip $filename: not current host");
					continue;
				}
				if (self::load_json($filename) === null) {
					$return = false;
				}
			}
		} else {
			self::log("ERROR: no config files found in folder: $folder");
			$return = false;
		}

		return $return;
	}

	static public function init($root_folder = false) {
		self::log("Config::init()");
		$root_folder = $root_folder? $root_folder : '.';

		// Load aftertime config
		if (self::load(__DIR__.'/../config') === false) {
			return false;
		}

		// Get indicated config
		if (self::load($root_folder) === false) {
			return false;
		}

		self::log('SUCCESS: all config loaded');
		return self::$config;
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

	static public function get($leaf = null) {
		if (!self::$config) {
			return false;
		}
		$root = self::$config;
		if (isset($leaf)) {
			foreach (explode('.', $leaf) as $leaf_part) {
				if (!isset($root[$leaf_part])) {
					return null;
				}
				$root = $root[$leaf_part];
			}
		}
		return $root;
	}

	static public function set(array $config = null) {
		self::$config = $config;
	}

	static public function print_values($prefix = '') {
		self::print_config_values(self::$config, $prefix);
	}

	static private function print_config_values ($config, $prefix = '') {
		if (empty($config)) {
			$prefix = substr($prefix, 0, -1);
			echo "$prefix=\n";
		} else {
			foreach ($config as $key => $value) {
				if (is_array($value)) {
					self::print_config_values($value, "$prefix$key.");
				} else {
					echo "$prefix$key=$value\n";
				}
			}
		}
	}

	// For all the PEAR require_* hell, that got worse with composer not following the usual PEAR folder structure
        static public function pear_paths() {
                $pear_composer = __DIR__ . '/../vendor/pear-pear.php.net/';
                foreach (glob($pear_composer.'*', GLOB_ONLYDIR) as $folder) {
                        $paths[] = $folder;
                }
                $paths[] = $pear_composer . 'MDB2_Driver_mysqli/MDB2_Driver_mysqli-1.5.0b4';    // Crap
                return implode(PATH_SEPARATOR, $paths);
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
