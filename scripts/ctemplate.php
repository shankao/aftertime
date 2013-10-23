<?php
require_once 'include/template.php';
require_once 'include/config.php';

$opts = getopt('t:');
if (!isset($opts['t'])) {
	echo "Please, specify the template to output with the -t option\n";
	exit(1);
}

$config = Config::init();
if (!$config) {
        echo Config::init_log();
        exit (1);
}
$result = Template::render($opts['t']);
exit($result? 0 : 1);
?>
