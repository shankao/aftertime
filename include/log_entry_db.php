<?php
namespace Aftertime;

require_once __DIR__.'/log.php';

function log_entry_db ($class, $message, $logtype, $level) {
	return Log::log_entry_db ($class, $message, $logtype, $level);
}
?>
