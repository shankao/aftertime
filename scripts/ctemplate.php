<?php
require_once __DIR__.'/../vendor/autoload.php';

$opts = getopt('t:');
if (!isset($opts['t'])) {
	echo "Please, specify the template to output with the -t option\n";
	exit(1);
}

if (!aftertime_init(false, '../..')) {
        echo Config::init_log();
        exit (1);
}
$config = Config::get();
$result = Aftertime\template_render($opts['t'], null, false);
exit($result? 0 : 1);
?>
