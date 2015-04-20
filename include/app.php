<?php
namespace Aftertime;

require_once __DIR__.'/log.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/user.php';
require_once __DIR__.'/validate.php';
require_once __DIR__.'/template.php';

// Returns an App object chosen from the config and request params
final class appFactory {

	private function check_url() {
		// Log and check the request URL
		$url = $_SERVER['PHP_SELF'];
		$url .= !empty($_SERVER['QUERY_STRING'])? "?{$_SERVER['QUERY_STRING']}" : '';
		$from = "{$_SERVER['REMOTE_ADDR']}";
		if (preg_match('/^\/(index\.php)(\?.*)?$/', $url) === 0) {
			log_entry("REQUEST FILTERED: $url from $from");
			return false;
		} else {
			log_entry("REQUEST: $url from $from");
			return true;
		}
	}

	public function build($request) {
	
		if (self::check_url() === false) {
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

abstract class app {

	private $debug = false;

	public $errors = array();	// Errors from the previous app
	public $params;			// Params accepted by the app
	public $user;			// Instance of Aftertime\User. Not every site has it
	public $template;		// Rendering page
	public $db;			// Database connection

	protected function db_error() {
		$error = db_error();	// That's the function in include/helpers.php
		if ($error) {
			$this->error_add('DB_ERROR', $error);
			return true;
		} else {
			return false;
		}
	}

//------------------------ public
	
	public function run() {
		$appname = $this->params['app'];
		$pagename = $this->params['page'];
		Log::caller($appname);

		// Recover errors from the last App and remove them from the session
		if (isset($_SESSION['errors'])) {
			foreach ($_SESSION['errors'] as $error) {
				$this->error_add($error);
			}
			unset($_SESSION['errors']);
		}

		// Validate page parameters
                $app_config = Config::get("apps.$appname");
		$page_config = Config::get("apps.$appname.pages.$pagename");
		if (!$page_config || !isset($page_config['params'])) {
			log_entry("WARNING: params not specified for '$pagename' page. Validation checks will not be performed");
		} else {
			$validator = new Validate;
			$validator->check_array($this->params, $page_config['params']);
			if ($validator->has_errors()) {
				foreach ($validator->errors() as $error) {
					$this->error_add($error);
				}
				if (isset($page_config['params_error_page'])) {
					return $this->redirect($page_config['params_error_page']);
				}
			}
		}

		$this->db = init_db();
		if ($this->db) {
			$this->user = new User($this->db);
			$this->user->login();
		} else {
			log_entry('Error initializing DB');
		}
/*		// XXX Commented until fixed
		if ($this->check_http_auth() == false) {
			$this->error_add('HTTP_AUTH_ERROR');
			return false;	// redirect to a 505 page?
		}
*/

		$this->init_template();

		// Run the page method
		if (!is_callable(array($this, $pagename))) {
			log_entry("WARNING: no page method");
			$result = true;
		} else {
			Log::caller("$appname/$pagename");
			$result = $this->$pagename();
			Log::caller("$appname");
		}
		return $result;
	}
	
	private function init_template() {
		$page_config = Config::get("apps.{$this->params['app']}.pages.{$this->params['page']}");
		if (!isset($page_config['template'])) {
			log_entry('No template specified for this page');
			return false;
		}
		$page_template = $page_config['template'];
		$config = Config::get();

		switch ($page_template) {	// XXX TemplateLog types
			case 'default':
			case 'apperror':
				$template_filename = __DIR__."/../templates/$page_template.php";
				break;
			default:	// Local app template. XXX Maybe is worth to remove the appname here and let the app choose
				$appname = get_class($this);
				$template_filename = "{$config['site']}/$appname/$page_template.php";
				break;
		}

		if (isset($this->params)) {
			$vars['params'] = $this->params;
		}
		if (isset($this->errors)) {
			$vars['errors'] = $this->errors;
		}
		if ($this->user->is_user_logged()) {
			$vars['user'] = $this->user->toArray();
		}
		$vars['config'] = $config;
		$vars['debug'] = $this->debug();
		$this->template = new Template($template_filename, $vars);
		return true;
	}

	final public function render_template() {
		if (!$this->template) {
			return false;
		} else {
			return $this->template->render();
		}
	}

	// HTTP redirection. Syntax is 'app/page'. You can ommit some (i.e. "/newpage", "newapp/")
	public function redirect($dest, array $params = null, $response = 303) {
		if ($this->has_error()) {
			$_SESSION['errors'] = $this->get_all_errors();
		}
		
		$parts = explode('/', $dest);
		$params2['app'] = $parts[0];
		$params2['page'] = $parts[1];
		if ($params) {
			$params2 = array_merge($params2, $params);
		}
		$url = 'index.php?' . http_build_query($params2, '_', '&');

		log_entry ("HTTP redirecting to $url");
		header("Location: $url", true, $response);

		return 'redirect';
	}

	public function error_add($code) {
		$this->errors[] = $code;
	}

	public function has_error($code = null) {
		if ($code === null) {
			return count($this->errors);
		} else {
			return in_array($code, $this->errors)? true : false;
		}
	}

	public function get_all_errors() {
		return $this->errors;
	}
	
	public function debug($debug = null) {
                if ($debug !== null) {
                        $this->debug = $debug;
                }
                return $this->debug;
        }
}
?>
