<?php
$opts = getopt('t:c:');
if (!isset($opts['t'])) {
	echo "Please, specify the template to output with the -t option\n";
	exit(1);
}
if (!isset($opts['c'])) {
	echo "Please, specify the config folder with the -c option\n";
	exit(1);
}

require_once __DIR__.'/../vendor/autoload.php';
$aftertime = new Aftertime\Aftertime($opts['c'], false);
$result = Aftertime\template_render($opts['t'], null, false);
exit($result? 0 : 1);

