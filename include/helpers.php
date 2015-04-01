<?php
namespace Aftertime;

/* Helper functions */
require_once __DIR__.'/log.php';
// TODO: move where they better correspond

function html_value($var) {
        if (isset($var))
                return nl2br(htmlspecialchars($var));
        else
		return '';
}

// Like array_walk_recursive, but executes the function also on non-leafs
function walk_recursive (array $array, $function, $extra) {
	foreach ($array as $key => $value) {
		$function ($value, $key, $extra);
		if (is_array($value)) {
			walk_recursive ($value, $function, $extra);
		}
	}
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
		if ($is_folder && !@mkdir($filename, $mode, true)) {
			$result = false;
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

// Returns an array with the multi-value command line argument specified
// This allows to keep using getopt() for other regular options, but support bash expansion too
function get_multivalue_argv($option) {
        global $argv;
        $getting_param = false;
        $output = array();
        foreach ($argv as $index => $param) {
                if ($param == $option) {
                        $getting_param = true;
                } else if ($getting_param && $param[0] == '-') {        // This is a new param already
                        break;
                } else if ($getting_param) {
                        $output[] = $param; 
                }
        }
        return $output;
}
?>
