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
?>
