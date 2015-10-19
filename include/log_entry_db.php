<?php
namespace Aftertime;

function log_entry_db ($class, $message, $logtype, $level) {
	return Log::log_entry_db ($class, $message, $logtype, $level);
}
?>
