<?php
$time_start = microtime(true);
ini_set ('include_path', '.' . PATH_SEPARATOR . 'lib/pear/php');	// Add PEAR's folder. XXX This is needed before the includes just for their paths to be correct

require_once 'include/config.php';
if (aftertime_init() === false) {
	TemplateLog::render('templates/apperror.php');
} else {
	require_once 'include/log.php';
	require_once 'include/app.php';

	// Log the query that we get. Some request could have been filtered by the HTTP server already
	$url = $_SERVER['PHP_SELF'];
	$url .= !empty($_SERVER['QUERY_STRING'])? "?{$_SERVER['QUERY_STRING']}" : '';
	log_entry("REQUEST: $url");

	$app = appFactory::getApp($_REQUEST);
	if ($app) {
		if ($app->run() !== 'redirect') {
			$app->render_template();
		}
	} else {
		require_once 'include/template_log.php';
		TemplateLog::render('templates/apperror.php');
	}

	log_entry ('=== Page generation time was ' . (microtime(true) - $time_start) . ' ===');
}
?>
