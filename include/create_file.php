<?php
namespace Aftertime;

/* Helper functions */

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
