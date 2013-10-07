<?php /*
Returns the site's configuration
*/

function print_config_values ($config, $prefix = '') {
	foreach ($config as $key => $value) {
		if (is_array($value)) {
			print_config_values($value, "$key.");
		} else {
			echo "$prefix$key=$value\n";
		}
	}
}

require_once 'include/config.php';

$config = Config::init();
if (!$config) {
	echo "ERROR loading config\n";
	exit (-1);
}

print_config_values($config);
exit (0);
?>
