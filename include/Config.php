<?php
namespace Aftertime;

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
				if (self::loadJSON($filename) === null) {
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

	private function loadJSON($file) {
		$fileconf = null;
		$error = false;
		if (is_readable($file)) {
			if (($contents = file_get_contents($file)) !== false) {
				$fileconf = json_decode($contents, true);
				if (!$fileconf) {
					if (function_exists('json_last_error_msg')) {	// PHP 5 >= 5.5.0
						$error = json_last_error_msg();
					} else {
						$error = 'Error in json_decode(): code '.json_last_error();
					}
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

	static public function initLog() {
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

	static public function printValues($prefix = '') {
		self::printConfigValues(self::$config, $prefix);
	}

	static private function printConfigValues ($config, $prefix = '') {
		if (empty($config)) {
			$prefix = substr($prefix, 0, -1);
			echo "$prefix=\n";
		} else {
			foreach ($config as $key => $value) {
				if (is_array($value)) {
					self::printConfigValues($value, "$prefix$key.");
				} else {
					echo "$prefix$key=$value\n";
				}
			}
		}
	}
}

