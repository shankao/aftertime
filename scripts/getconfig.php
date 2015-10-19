<?php /*
Returns the site's configuration
*/
require_once __DIR__.'/../vendor/autoload.php';
if (aftertime_init(false, '../..') === false) {
	echo Config::init_log();
	exit (-1);
}

Config::print_values();
exit (0);
?>
