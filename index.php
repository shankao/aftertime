<?php
$time_start = microtime(true);
ini_set ('include_path', '.' . PATH_SEPARATOR . 'lib/pear/php');	// Add PEAR's folder. XXX This is needed before the includes just for their paths to be correct

require_once 'include/config.php';
require_once 'include/log.php';
require_once 'include/app.php';
require_once 'include/template_log.php';

if (aftertime_init() === false) {
	TemplateLog::render('templates/apperror.php');
	die;
}
$config = Config::get();
$request = $_REQUEST;	// TODO Validate here or in the app?
if (empty($request['app'])) {
	if (!isset($config['init_app'])) {
		log_entry ("ERROR: init_app not set");
	} else {
		$_SESSION['appname'] = $config['init_app'];
	}
} else {
	$_SESSION['appname'] = $request['app'];
}

$app_name = $_SESSION['appname'];
if (!$app_name || Config::get($app_name) == false) {
	log_entry("ERROR: application '$app_name' not defined");
} else {
	log_entry ("Loading app $app_name");
	$app_code_file = "sites/{$config['site']}/$app_name/code.php";
	if (!is_readable($app_code_file)) {
		log_entry ("Cannot load the app's code at $app_code_file");
	} else {
		require_once $app_code_file;
		if (!class_exists($app_name)) {
			log_entry ("No app class defined: $app_name");
		} else {
			$app = new $app_name($request);
			$result = $app->run();
			if ($result !== 'redirect') {
				$app->render_template();
			}
		}
	}
}

if (!isset($app)) {
	TemplateLog::render('templates/apperror.php');
}

log_entry ('=== Page generation time was ' . (microtime(true) - $time_start) . ' ===');
?>
