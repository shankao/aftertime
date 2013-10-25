<?php
require_once 'include/log.php';
require_once 'include/template_log.php';
require_once 'include/config.php';
require_once 'include/db.php';
/*
	function filter(array $vars) {
		foreach ($vars as $var => $value) {
			$validator_fn = "validate_$var";
			if (!is_callable(array('Validator', $validator_fn))) {
				log_entry("ERROR: validator not found for '$var' param");
				$error = true;
				continue;
			}

			if ($this->$validator_fn($value) === false) {
				log_entry("Checking '$var' for '$value': ERROR");
			} else {
				log_entry("Checking '$var' for '$value': OK");
				$this->safe_vars[$var] = $value;
			}
		}
	}
*/
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
                if (!in_array($request['page'], $app_config['pages'])) {
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
			log_entry("WARNING: page has not method: '{$request['app']}::{$request['page']}'");
			return null;
		}
		log_entry("Checking 'page' for '{$request['page']}': OK");

		// TODO Validate method params now that we have loaded the config
		// ...then, as specified in the config, for each combination of app+a
		// Maybe http://www.php.net/manual/en/ref.filter.php

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
			$this->keep('user', $user->toArray());

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

	// App. redirection. Syntax is 'app/page'. You can ommit some (i.e. "/newpage", "newapp/")
	protected function redirect($dest) {

		log_entry("redirect($dest)");
		$parts = explode('/', $dest);

		$url = "index.php?app={$parts[0]}";
		if (isset($parts[1])) {
			$url .= "&page={$parts[1]}";
		}

		if (isset($this->error)) {
			$this->keep_once('error', $this->error);
		}
		if (isset($this->error_msg)) {
			$this->keep_once('error_msg', $this->error_msg);
		}

		log_entry ("HTTP redirecting to $url");
		header("Location: $url", true, 303);
		return 'redirect';
	}

	protected function keep_once($var, $value) {
		$_SESSION['keep_once'][$var] = $value;
	}

	protected function keep($var, $value) {
		$_SESSION['keep'][$var] = $value;
	}

	protected function keep_remove($var) {
		if (isset($_SESSION['keep_once'][$var])) {
			unset($_SESSION['keep_once'][$var]);
		}
		if (isset($_SESSION['keep'][$var])) {
			unset($_SESSION['keep'][$var]);
		}
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
		if (isset($_SESSION['keep'])) {
			foreach ($_SESSION['keep'] as $var => $value) {
				$this->$var = $value;
			}
		}
		if (isset($_SESSION['keep_once'])) {
			foreach ($_SESSION['keep_once'] as $var => $value) {
				$this->$var = $value;
			}
			unset ($_SESSION['keep_once']);
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
			$this->keep_remove('user');
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
}
?>
