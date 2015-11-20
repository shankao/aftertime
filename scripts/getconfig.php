<?php /*
Returns the site's configuration, given the config folder as param
*/
require_once __DIR__.'/../vendor/autoload.php';
$folder = empty($argv[1])? '.' : $argv[1];
if (Aftertime\Config::init($folder) === false) {
	echo Aftertime\Config::initLog();
	exit (-1);
}

Aftertime\Config::printValues();
exit (0);

