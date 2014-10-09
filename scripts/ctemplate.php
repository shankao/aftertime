<?php
require_once __DIR__.'/../include/template.php';
require_once __DIR__.'/../include/config.php';

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
$result = Template::render($opts['t']);
exit($result? 0 : 1);
?>
