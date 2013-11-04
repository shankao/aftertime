<?php
require_once 'include/log.php';
require_once 'include/template_log.php';
require_once 'include/config.php';
require_once 'include/db.php';

// Returns an App object chosen from the config and request params
final class appFactory {

	private function check_url() {
		// Log and check the request URL
		$url = $_SERVER['PHP_SELF'];
		$url .= !empty($_SERVER['QUERY_STRING'])? "?{$_SERVER['QUERY_STRING']}" : '';
		if (preg_match('/^\/(index\.php)(\?.*)?$/', $url) === 0) {
			log_entry("REQUEST FILTERED: $url");
			return false;
		} else {
			log_entry("REQUEST: $url");
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

		log_entry ("Creating app {$request['app']}");
		$app = new $request['app'];
		$app->params = $request;
		return $app;
	}
}

abstract class app {

	public $page;		// page to show after the method is run (used by 'default' template)
	public $template;	// XXX This is only the template name, not a TemplateLog object
	public $errors;		// Errors from the previous app
	public $params;		// Params accepted by the app
	public $user;		// User information. Not every site has it

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

	/*
	Functions for user authentication. 
	It requires a DB up and running, and DB_DataObject::factory('users') working
	*/
	private function do_login ($email, $password, $save_cookie=false, $password_is_encrypted=false) {

		log_entry ("Login user $email");

		$this->clean_login_cookies();

		$user = DB_DataObject::factory('users');
		$user->email = $email;
		if (!$user->find(true)) {
			$this->error_add('NO_USER_FOUND');
			log_entry ("User not found for email '$email'");
			return false;
		} else if (!$this->check_password($password, $user->password, $password_is_encrypted)) {
			$this->error_add('WRONG_PASSWD');
			log_entry ("Wrong password for email '$email'");
			return false;
		}

		$_SESSION['user'] = $user->toArray();
		if ($save_cookie) {	// Save for 10 days
			setcookie('us', $email, time()+864000, '/', null, false, true);
			setcookie('pw', $user->password, time()+864000, '/', null, false, true);
		}
		log_entry ("User logged in successfully: $email");
		return true;
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
			$this->error_add('DB_ERROR', $error);
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
                $app_config = Config::get($appname);
		$page_config = Config::get($appname, $pagename);
		if (!$page_config || !isset($page_config['params'])) {
			log_entry("WARNING: params not specified for '$pagename' page. Validation checks will not be performed");
		} else {
			require_once 'include/validate.php';
			$validator = new Validate;
			if ($validator->check_multiple($page_config['params'], $this->params) === false) {
				foreach ($validator->errors() as $error) {
					$this->error_add($error);
				}
				if (isset($page_config['params_error_page'])) {
					return $this->redirect($page_config['params_error_page']);
				}
			}
		}

		// fixme _COOKIE should be validated too
		if (init_db()) {
			if (!$this->is_user_logged() && isset($_COOKIE['us']) && isset($_COOKIE['pw'])) {	// Autologin from cookies
				$this->do_login($_COOKIE['us'], $_COOKIE['pw'], true, true);	// TODO check login result
			}
		}

		if ($this->check_http_auth() == false) {
			$this->error_add('HTTP_AUTH_ERROR');
			$this->template = 'apperror';
			return false;
		}

		// Run the page method
		Log::caller("$appname/$pagename");
		$this->page = $pagename;		// Used by 'default' template
		return $this->$pagename($this->params);	// TODO remove the param
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
			unset($_SESSION['user']);
			$this->clean_login_cookies();
		}
	}

	public function is_user_logged() {
		if (isset($_SESSION['user'])) {
			$this->user = $_SESSION['user'];
			return true;
		} else {
			return false;
		}
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
		$app_config = Config::get($this->params['app']);
		$page_config = Config::get($this->params['app'], $this->page);

		if ($page_config && isset($page_config['title'])) {
			$title_tag = "{$page_config['title']}";
			if ($app_config && isset($app_config['webtitle'])) {
				$title_tag .= " - {$app_config['webtitle']}";
			}

		} else if (isset($config['webtitle'])) {
			$title_tag = "{$config['webtitle']}";
		}
		return $title_tag;
	}

	public function redirect($dest) {
		if ($this->has_error()) {
			$_SESSION['errors'] = $this->get_all_errors();
		}
		redirect($dest);
		return 'redirect';
	}
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
