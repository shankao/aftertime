<?php
namespace Aftertime;

// Returns an App object chosen from the config and request params
final class AppFactory {

	private function checkURL() {
		// Log and check the request URL
		$url = $_SERVER['PHP_SELF'];
		$url .= !empty($_SERVER['QUERY_STRING'])? "?{$_SERVER['QUERY_STRING']}" : '';
		$from = $_SERVER['REMOTE_ADDR'];
		if (preg_match('/^\/(index\.php)(\?.*)?$/', $url) === 0) {
			log_entry("REQUEST FILTERED: $url from $from");
			return false;
		} else {
			log_entry("REQUEST: $url from $from");
			return true;
		}
	}

	public function build($request) {
	
		if (self::checkURL() === false) {
			return null;
		}

		// app default and check
		$config = Config::get();
		if (empty($request['app'])) {
			if (!isset($config['init_app'])) {
				log_entry ("ERROR: init_app not set");
				return null;
			} else {
				$request['app'] = $config['init_app'];
			}
		}
                $app_config = Config::get("apps.{$request['app']}");
		if ($app_config === false) {
			log_entry("ERROR: application '{$request['app']}' invalid");
			return null;
		}
		log_entry("Checking 'app' for '{$request['app']}': OK");

		// page default and checks
		if (empty($request['page'])) {
			if (!isset($app_config['init_page'])) {
				log_entry ("ERROR: init_page not set");
				return null;
			} else {
				$request['page'] = $app_config['init_page'];
			}
		}
                if (!isset($app_config['pages'][$request['page']])) {
			log_entry ("ERROR: page '{$request['page']}' invalid");
                        return null;
                }
		if (strpos($request['page'], 'app_') === 0) {
			log_entry ("ERROR: page '{$request['page']}' name not allowed");
                        return null;
		}
		if (isset($app_config['class_location'])) {
			$app_code_file = "{$config['site']}/{$app_config['class_location']}";
		} else {
			$app_code_file = "{$config['site']}/{$request['app']}/code.php";
		}
		if (!is_readable($app_code_file)) {
			log_entry ("Cannot load the app's code at $app_code_file");
			return null;
		}
		require_once $app_code_file;
		if (isset($app_config['class_name'])) {
			$app_class_name = $app_config['class_name'];
		} else {
			$app_class_name = $request['app'];
		}
		log_entry("Checking 'page' for '{$request['page']}': OK");

		log_entry ("Creating app $app_class_name");
		$app = new $app_class_name;
		$app->params = $request;
		return $app;
	}
}

