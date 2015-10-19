<?php
namespace Aftertime;

function log_entry ($text, $sizelimit = 2000) {
	return Log::log_entry ($text, $sizelimit);
}
?>
