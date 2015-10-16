<?php
namespace Aftertime;

require_once __DIR__.'/log.php';

function log_entry ($text, $sizelimit = 2000) {
	return Log::log_entry ($text, $sizelimit);
}
?>
