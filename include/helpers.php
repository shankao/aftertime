<?php /* Helper functions */
require_once __DIR__.'/log.php';
// TODO: move where they better correspond
/*
 * XXX Deprecated
 */
function getParam ($source, $varname, $default=null) {
	log_entry('DEPRECATED: getParam()');
	if (!isset($source[$varname])) {
		return $default;
	} else {
		// Special case for boolean encoded as strings
		if ($source[$varname] == 'true')
			return true;
		elseif ($source[$varname] == 'false')
			return false;			
		else
			return ($source[$varname]);
	}		
}

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

function get_absolute_url ($url, $parent_url = null) {
	if (!isset($parent_url)) {
		return $url;
	}
	$parent_components = array();
	if (($parent_components = parse_url($parent_url)) === false) {
		log_entry("ERROR: malformed parent url");
		return false;
	}

	if (($url_components = parse_url($url)) === false) {
		log_entry("ERROR: malformed url");
		return false;
	}

	if (empty($url_components['host'])) {
		if ($url[0] === '/') {
			$url = substr($url, 1);
		}
		$url = $parent_components['scheme'] .'://'. $parent_components['host'].'/'.$url;
	}

	return filter_var($url, FILTER_VALIDATE_URL);
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
