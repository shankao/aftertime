<?php
require_once 'include/log.php';
require_once 'include/template_log.php';
require_once 'include/config.php';
require_once 'include/db.php';

function translate_validate_flags($string) {
	$output = 0;
	foreach (explode('|', $string) as $filter_str) {
		$filter_str = trim($filter_str);
		switch($filter_str) {
			case 'FILTER_FLAG_STRIP_LOW': $filter = FILTER_FLAG_STRIP_LOW; break;
			case 'FILTER_FLAG_STRIP_HIGH': $filter = FILTER_FLAG_STRIP_HIGH; break;
			case 'FILTER_FLAG_ALLOW_FRACTION': $filter = FILTER_FLAG_ALLOW_FRACTION; break;
			case 'FILTER_FLAG_ALLOW_THOUSAND': $filter = FILTER_FLAG_ALLOW_THOUSAND; break;
			case 'FILTER_FLAG_ALLOW_SCIENTIFIC': $filter = FILTER_FLAG_ALLOW_SCIENTIFIC; break;
			case 'FILTER_FLAG_NO_ENCODE_QUOTES': $filter = FILTER_FLAG_NO_ENCODE_QUOTES; break;
			case 'FILTER_FLAG_ENCODE_LOW': $filter = FILTER_FLAG_ENCODE_LOW; break;
			case 'FILTER_FLAG_ENCODE_HIGH': $filter = FILTER_FLAG_ENCODE_HIGH; break;
			case 'FILTER_FLAG_ENCODE_AMP': $filter = FILTER_FLAG_ENCODE_AMP; break;
			case 'FILTER_NULL_ON_FAILURE': $filter = FILTER_NULL_ON_FAILURE; break;
			case 'FILTER_FLAG_ALLOW_OCTAL': $filter = FILTER_FLAG_ALLOW_OCTAL; break;
			case 'FILTER_FLAG_ALLOW_HEX': $filter = FILTER_FLAG_ALLOW_HEX; break;
			case 'FILTER_FLAG_IPV4': $filter = FILTER_FLAG_IPV4; break;
			case 'FILTER_FLAG_IPV6': $filter = FILTER_FLAG_IPV6; break;
			case 'FILTER_NO_PRIV_RANGE': $filter = FILTER_NO_PRIV_RANGE; break;
			case 'FILTER_NO_RES_RANGE': $filter = FILTER_NO_RES_RANGE; break;
			case 'FILTER_FLAG_PATH_REQUIRED': $filter = FILTER_FLAG_PATH_REQUIRED; break;
			case 'FILTER_FLAG_QUERY_REQUIRED': $filter = FILTER_FLAG_QUERY_REQUIRED; break;
			default: return false;
		}
		$output = $output | $filter;
	}
	return $output;
}

// Validation using PHP filter functions http://www.php.net/manual/en/ref.filter.php
function validate_page_params (array $page_params, array $request, &$errors) {
	$errors = array();
	$appclass = $request['app'];
	unset($request['app']);
	unset($request['page']);
	foreach ($page_params as $param_name => $param_conf) {
		$filter_type = isset($param_conf['filter'])? $param_conf['filter'] : null;
		if (!$filter_type) {
			log_entry("WARNING: no filters for '$param_name'");
		} else {
			$value = isset($request[$param_name])? $request[$param_name] : null;
			if (empty($value)) {
				$param_required = isset($param_conf['required'])? $param_conf['required'] : false;
				if ($param_required) {
					$errors[] = "PARAM_REQUIRED_$param_name";
					log_entry("ERROR: param '$param_name' is required");
				}
			} else {
				$options = array();
				if (isset($param_conf['filter_options'])) {
					$options['options'] = $param_conf['filter_options'];
					if (isset($options['options']['callback'])) {	// Don't allow methods out of the App's class
						$options['options'] = array($appclass, $options['options']['callback']);
						if (!is_callable($options['options'], $funcname)) {
							log_entry ("ERROR: cannot find validator function '$funcname'");
							return false;
						}
					}
				}
				if (isset($param_conf['filter_flags'])) {
					$flags = translate_validate_flags($param_conf['filter_flags']);
					if ($flags === false) {
						log_entry ("ERROR: Wrong flags: {$param_conf['filter_flags']}");
						return false;
					}
					$options['flags'] = $flags;
				}

				log_entry("Checking '$param_name' for filter '$filter_type'"); 
				$filter_id = filter_id($filter_type);
				if ($filter_id === false) {
					log_entry ("ERROR: Filter $filter_type does not exist");
					log_entry ('Available filter types: '.print_r(filter_list(), true));
					return false;
				} else {
					if (filter_var($value, $filter_id, $options) === false) {
						log_entry ("ERROR: Filter $filter_type failed for '$param_name'");
						$errors[] = "PARAM_INVALID_$param_name";
					}
				}
			}
		}
		unset($request[$param_name]);
	}
	if (count($request)) {
		log_entry("ERROR: unknown params: ".implode(', ', array_keys($request)));
		return false;
	}
	if (count($errors)) {
		return false;
	}
	return true;
}

final class appFactory {
	private function __construct () {
	}

	static public function getApp($request) {

		// Log and check the request URL
		$url = $_SERVER['PHP_SELF'];
		$url .= !empty($_SERVER['QUERY_STRING'])? "?{$_SERVER['QUERY_STRING']}" : '';
		if (preg_match('/^\/(index\.php)(\?.*)?$/', $url) === 0) {
			log_entry("REQUEST FILTERED: $url");
			return null;
		} else {
			log_entry("REQUEST: $url");
		}

		$config = Config::get();

		// app default and check
		if (empty($request['app'])) {
			if (!isset($config['init_app'])) {
				log_entry ("ERROR: init_app not set");
				return null;
			} else {
				$request['app'] = $config['init_app'];
			}
		}
                $app_config = Config::get($request['app']);
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
		$app_code_file = "sites/{$config['site']}/{$request['app']}/code.php";
		if (!is_readable($app_code_file)) {
			log_entry ("Cannot load the app's code at $app_code_file");
			return null;
		}
		require_once $app_code_file;
		if (!is_callable(array($request['app'], $request['page']))) {
			log_entry("ERROR: page has not method: '{$request['app']}::{$request['page']}'");
			return null;
		}
		log_entry("Checking 'page' for '{$request['page']}': OK");

		// XXX Consider if param validation should be done after creating the app object, so custom validators can use it too
		$page_config = $app_config['pages'][$request['page']];
		if (isset($page_config['params'])) {
			if (validate_page_params($page_config['params'], $request, $param_errors) === false) {
				if (isset($page_config['params_error'])) {
					$fn = array($request['app'], $page_config['params_error']);
					if (!is_callable($fn, $fn_name)) {
						log_entry("ERROR: cannot call the params_error function: '$fn_name'");
					} else {
						$fn($param_errors);	// XXX Should avoid the return null next?
					}
				}
				return null;
			}
		} else {
			log_entry("WARNING: params not specified for '{$request['page']}' page. Validation checks will not be performed");
		}

		log_entry ("Creating app {$request['app']}");
		return new $request['app']($request);
	}
}

class app {

	public $page;	// page to show after the method is run (used by 'default' template)

	public $template;	// XXX This is only the template name, not a TemplateLog object

	public $error;          // Error code
        public $error_msg;      // Error message

	public $params;		// Params accepted by the app
	public $user;		// User information. Not every site has it

	/*
	Functions for user authentication. 
	It requires a DB up and running, and DB_DataObject::factory('users') working
	*/
	private function do_login ($email, $password, $save_cookie=false, $password_is_encrypted=false) {

		log_entry ("Login user $email");

		$this->clean_login_cookies();

		$user = DB_DataObject::factory('users');
		$user->email = $email;
		if (!$user->find(true))
			$this->error = 'NO_USER_FOUND';
		else if (!$this->check_password($password, $user->password, $password_is_encrypted)) {
			$this->error = 'WRONG_PASSWD';
		} else {
			keep('user', $user->toArray());

			if ($save_cookie) {	// Save for 10 days
				setcookie('us', $email, time()+864000, '/', null, false, true);
				setcookie('pw', $user->password, time()+864000, '/', null, false, true);
			}
		}

		if (isset($this->error)) {
			log_entry ("Login error for user $email: {$this->error}");
		} else {
			log_entry ("User logged in successfully: $email");
		}
	}

	private function clean_login_cookies() {
		setcookie('us', '', time()-3600, '/', null, false, true);
		setcookie('pw', '', time()-3600, '/', null, false, true);
	}

//------------------------ protected

	// This is the default. Note that apps. are free to override it. XXX Convert to interface?
	protected function encrypt_password ($password) {
		if (empty($password)) return null;

		if (CRYPT_BLOWFISH) {
			$type = 'blowfish';
			$code = '2y'; 	// Blowfish code from php 5.3.7
			$cost = '05';	// range [04,31]
			for ($i=0, $salt=''; $i<22; $i++)		// [./0-9A-Za-z]{22} more chars are ignored
				$salt .= rand(0, 9);

			$combined_salt = "\$$code\$$cost\$$salt";
			$result = crypt($password, $combined_salt);
		} else {
			$type = 'sha1';
			$result = sha1($password);
		}

		log_entry ("Encrypted password $result using $type");
		return $result;
	}

	protected function check_password ($input, $encrypted, $input_is_encrypted=false) {

		log_entry("check_password (XXX, $encrypted, $input_is_encrypted)");	
		if (empty($input) || empty($encrypted)) return false;

		if ($input_is_encrypted) {
			$result = ($input === $encrypted)? true : false;
		} else {
			if (CRYPT_BLOWFISH) {
				$result = (crypt($input, $encrypted) == $encrypted)? true : false;
			} else {
				$result = (sha1($input) == $encrypted)? true : false;
			}
		}

                return $result;
	}

	protected function db_error() {
		$error = db_error();	// That's the function in include/aftertime.php
		if ($error) {
			$this->error = 'DB ERROR';
			$this->error_msg = $error;
			return true;
		} else {
			return false;
		}
	}

	// Basic auth. code taken from http://www.php.net/manual/en/features.http-auth.php
	private function check_http_auth() {
		$c = Config::get(get_class($this));
		if (isset($c['user']) && isset($c['passwd'])) {
			// TODO Check better auth. ways instead of "Basic". Maybe autogenerating .htaccess and .htpasswd files
			// See http://pear.php.net/manual/en/package.filesystem.file-htaccess.intro.php
log_entry(print_r($_SERVER, true), 20000);

			$user = false;
			if (isset($_SERVER['PHP_AUTH_USER'])) {
				$user = $_SERVER['PHP_AUTH_USER'];
				$passwd = $_SERVER['PHP_AUTH_PW'];
			} else {
				// fixme This does not work when PHP is working as CGI/FastCGI
			}

			if ($user === $c['user'] && $passwd === $c['passwd']) {
				log_entry("HTTP auth OK");
			} else {
				header('WWW-Authenticate: Basic realm="Login please"');
				header('HTTP/1.0 401 Unauthorized');
				log_entry("HTTP auth failed");
				return false;
			}
		}
		return true;
	}

	public function run() {
		Log::caller($this->params['app']);
		if (isset($this->params['page'])) {	// TODO Move to when the method is running
			Log::caller("{$this->params['app']}/{$this->params['page']}");
		}

		$this->page = $this->params['page'];	// Used by 'default' template

		if (init_db()) {
			if (!$this->is_user_logged() && isset($_COOKIE['us']) && isset($_COOKIE['pw'])) {	// Autologin from cookies
				$this->do_login($_COOKIE['us'], $_COOKIE['pw'], true, true);
			}
		}

		if ($this->check_http_auth() == false) {
			$this->error = 'HTTP_AUTH_ERROR';
			$this->template = 'apperror';
			return false;
		}

		// Run the page method
		$method = $this->params['page'];
		return $this->$method($this->params);	// TODO remove the param
	}

	public function __construct(array $request) {
		foreach (keep_get_all() as $name => $value) {
			$this->$name = $value;
		}
		$this->params = $request;
	}

	// Used by 'default' template
	public function current_page() {
		return $this->params['page'];
	}

	public function login ($email, $password, $save_cookie=false) {
		return $this->do_login ($email, $password, $save_cookie);
	}

	public function logout () {
		if ($this->is_user_logged()) {
			log_entry ("Logging out user {$this->user['email']}");
			keep_remove('user');
			$this->clean_login_cookies();
		}
	}

	public function is_user_logged() {
		return isset($this->user)? true : false;
	}

	public function get_css() {
		static $css = null;
		return $css != null? $css : $this->get_app_file('css');
	}

	public function get_js() {
		static $js = null;
		return $js != null? $js : $this->get_app_file('js');
	}

	private function get_app_file($type) {
		$config = Config::get();
		$sitename = $config['site'];
		$appname = get_class($this);
		$filename = "sites/$sitename/$appname/$appname.$type";	// XXX Investigate if sites/$sitename -> smth returned from function
		if (is_readable($filename)) {
			return "$filename?{$config['code_revision']}";
		} else {
			return false;
		}
	}

	public function render_template() {
		if (isset($this->template) && $this->template != false) {
			switch ($this->template) {	// XXX TemplateLog types
				case 'default':
				case 'apperror':
					$template_filename = "templates/{$this->template}.php";
					break;
				default:	// Local app template. XXX Maybe is worth to remove the appname here and let the app choose
					$config = Config::get();
					$appname = get_class($this);
					$template_filename = "sites/{$config['site']}/$appname/{$this->template}.php";
					break;
			}
			return TemplateLog::render($template_filename);
		} else {
			log_entry('No template defined');
			return false;
		}
	}

	public function get_title_tag() {
		$config = Config::get();
		$app_config = $config['apps'][$this->params['app']];
		$page_config = $app_config['pages'][$this->page];

		if (isset($page_config['title'])) {
			$title_tag = "{$page_config['title']}";
			if (isset($app_config['webtitle']))
				$title_tag .= " - {$config['webtitle']}";

		} else if (isset($config['webtitle'])) {
			$title_tag = "{$config['webtitle']}";
		}
		return $title_tag;
	}

	protected function redirect($dest) {
		if (isset($this->error)) {
			keep_once('error', $this->error);
		}
		if (isset($this->error_msg)) {
			keep_once('error_msg', $this->error_msg);
		}
		redirect($dest);
		return 'redirect';
	}
}



function keep_once($var, $value) {
	$_SESSION['keep_once'][$var] = $value;
}

function keep($var, $value) {
	$_SESSION['keep'][$var] = $value;
}

function keep_remove($var) {
	if (isset($_SESSION['keep_once'][$var])) {
		unset($_SESSION['keep_once'][$var]);
	}
	if (isset($_SESSION['keep'][$var])) {
		unset($_SESSION['keep'][$var]);
	}
}

function keep_get_all() {
	$vars = array();
	if (isset($_SESSION['keep'])) {
		$vars = $_SESSION['keep'];
	}
	if (isset($_SESSION['keep_once'])) {
		$vars = array_merge($vars, $_SESSION['keep_once']);
		unset ($_SESSION['keep_once']);
	}
	return $vars;
}

// HTTP redirection. Syntax is 'app/page'. You can ommit some (i.e. "/newpage", "newapp/")
function redirect($dest, $response = 303) {
	$parts = explode('/', $dest);

	$url = "index.php?app={$parts[0]}";
	if (isset($parts[1])) {
		$url .= "&page={$parts[1]}";
	}

	log_entry ("HTTP redirecting to $url");
	header("Location: $url", true, $response);
}

?>
