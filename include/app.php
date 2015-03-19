<?php
require_once __DIR__.'/log.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/helpers.php';

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
		$app_code_file = "{$config['site']}/{$request['app']}/code.php";
		if (!is_readable($app_code_file)) {
			log_entry ("Cannot load the app's code at $app_code_file");
			return null;
		}
		log_entry("Checking 'page' for '{$request['page']}': OK");

		log_entry ("Creating app {$request['app']}");
		require_once $app_code_file;
		$app = new $request['app'];
		$app->params = $request;
		return $app;
	}
}

abstract class app {

	private $debug = false;
	private $use_pdo = false;

	public $errors;		// Errors from the previous app
	public $params;		// Params accepted by the app
	public $user;		// User information. Not every site has it
	public $template;	// Rendering page
	public $db;		// Database connection

	/*
	Functions for user authentication. 
	It requires a DB up and running, and DB_DataObject::factory('users') working
	*/
	private function do_login ($email, $password, $save_cookie=false, $password_is_encrypted=false) {

		log_entry ("Login user $email");

		$this->clean_login_cookies();

		// Get user for the given email
		if (is_a($this->db, 'PDO')) {
			$db_get_mail = $this->db->prepare('SELECT * FROM users WHERE email = :email');
			$db_get_mail->execute(array('email' => $email));
			$user = $db_get_mail->fetch();
		} else {
			$user = DB_DataObject::factory('users');
			$user->email = $email;
			$user->find(true);
			$user = $user->toArray();
		}

		if (!$user) {
			$this->error_add('NO_USER_FOUND');
			log_entry ("User not found for email '$email'");
			return false;
		} else if (!$this->check_password($password, $user['password'], $password_is_encrypted)) {
			$this->error_add('WRONG_PASSWD');
			log_entry ("Wrong password for email '$email'");
			return false;
		}

		$_SESSION['user'] = $user;
		if ($save_cookie) {	// Save for 10 days
			setcookie('us', $email, time()+864000, '/', null, false, true);
			setcookie('pw', $user['password'], time()+864000, '/', null, false, true);
		}
		log_entry ("User logged in successfully: $email");
		return true;
	}

	private function clean_login_cookies() {
		setcookie('us', '', time()-3600, '/', null, false, true);
		setcookie('pw', '', time()-3600, '/', null, false, true);
	}

	// Basic auth. code taken from http://www.php.net/manual/en/features.http-auth.php
	private function check_http_auth() {
		$c = Config::get('apps.'.get_class($this));
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

//------------------------ protected

	// This is the default. Note that apps. are free to override it. XXX Convert to interface?
	// TODO Check this (PHP 5.5+): http://www.php.net/manual/en/book.password.php
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
			// TODO To be removed. Needs an upgrade path to safer algorithms:
			// 1st, not to generate new passwords like this. Then, use your own legacy check_password function
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
			} else {	// TODO to go
				$result = (sha1($input) == $encrypted)? true : false;
			}
		}

                return $result;
	}

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
			require_once __DIR__.'/validate.php';
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
			if (!$this->is_user_logged() && isset($_COOKIE['us']) && isset($_COOKIE['pw'])) {	// Autologin from cookies
				$this->do_login($_COOKIE['us'], $_COOKIE['pw'], true, true);	// TODO check login result
			}
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

		require_once __DIR__.'/template.php';
		if (isset($this->params)) {
			$vars['params'] = $this->params;
		}
		if (isset($this->errors)) {
			$vars['errors'] = $this->errors;
		}
		if ($this->is_user_logged()) {
			$vars['user'] = $this->user;
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

	public function redirect($dest) {
		if ($this->has_error()) {
			$_SESSION['errors'] = $this->get_all_errors();
		}
		redirect($dest);
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
