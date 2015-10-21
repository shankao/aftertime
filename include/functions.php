<?php
namespace Aftertime;

// Stand-alone functions

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

function log_entry ($text, $sizelimit = 2000) {
	return Log::log_entry ($text, $sizelimit);
}

function log_entry_db ($class, $message, $logtype, $level) {
	return Log::log_entry_db ($class, $message, $logtype, $level);
}

// One-liner for lazyness
function template_render($filename, array $vars = null, $logging = true) {
	$t = new Template($filename, $vars, $logging);
	return $t->render();
}

// Checks write permissions if the file exists, or creates it of it doesn't
// Same thing for folders
function create_file($filename, $is_folder = false, $mode = false) {
	$result = true;
	if (is_writeable($filename) === false) {
		if (file_exists($filename)) {
			$result = false;	// permissions problem?
		}

		$oldumask = umask(0);
		if ($is_folder) { 
			$result = mkdir($filename, $mode, true);
		} else if (!@touch($filename)) {
			$result = false;
		} else {	// No folder, touch() OK
			if ($result && $mode) {
				$result = chmod($filename, $mode);
			}
		}
		if ($result === true) {
			log_entry ("$filename created");
		}
		umask($oldumask);
	}
	return $result;
}
?>
