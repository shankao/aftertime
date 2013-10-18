<?php
require_once 'include/session_object.class.php';
require_once 'include/log.php';
require_once 'include/template_log.php';
require_once 'include/config.php';
require_once 'include/db.php';

// XXX Check why app extends the session object instead of include it inside
class app extends session_object {

	protected $action;	// Requested app action
	protected $page;	// Requested page to show after the action is run

	public $template;	// XXX This is only the template name, not a TemplateLog object
	public $error;		// Error code
	public $error_msg;	// Error message

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
			$this->set('user', $user->toArray());

			if ($save_cookie) {	// Save for 10 days
				setcookie('us', $email, time()+864000, '/', null, false, true);
				setcookie('pw', $user->password, time()+864000, '/', null, false, true);
			}
		}

		if ($this->error)
			log_entry ("Login error for user $email: {$this->error}");
		else
			log_entry ("User logged in successfully: $email");
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

	// App. redirection. Syntax is 'app/page/action'. You can ommit some (i.e. "//newaction"
	protected function redirect($dest, $http_redirect = false) {

		log_entry("redirect($dest, $http_redirect)");
		$parts = explode('/', $dest);

		if (isset($parts[0])) {
			$_SESSION['appname'] = $parts[0];
		} else {
			log_enty("WARNING: doing a redirect to the same app. You don't need app redirection for this");
		}
		$url = "index.php?app={$_SESSION['appname']}";

		if (isset($parts[1])) {
			$this->set('page', $parts[1]);
			$url .= "&page={$parts[1]}";
		} else {
			$this->reset_var('page');
		}

		if (isset($parts[2])) {
			$this->set('action', $parts[2]);
			$url .= "&a={$parts[2]}";
		} else {
			$this->reset_var('action');
		}

		if ($http_redirect) {
                        log_entry ("HTTP redirecting to {$_SESSION['appname']}");
			header("Location: $url", true, 303);
			return 'http redirect';
		} else {
                        log_entry ("Internal redirecting to {$_SESSION['appname']}");
			return 'redirect';
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

	protected function default_action() {
		$this->template = 'default';
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
		$app_name = get_class($this);
		Log::caller($app_name);

		// XXX check different between set() and setting directly
		$this->set('action', isset($this->params['a'])? $this->params['a'] : NULL);
		$this->reset_var('actions');

		if ($this->action) {
			Log::caller("$app_name/{$this->action}");
		}

		if (init_db()) {
			if (!$this->is_user_logged() && isset($_COOKIE['us']) && isset($_COOKIE['pw'])) {
				// Try auth. automatically by cookies
				$this->do_login($_COOKIE['us'], $_COOKIE['pw'], true, true);
			}
		}

		// XXX check me
		if (empty($this->params['section']) && empty($this->params['app']) && !empty($_SESSION['appname']) && !empty($this->params['page'])) {
			// Kept the same app
                } else {
			// Changed app
			$this->reset_var('page');
		}

		if (empty($this->page)) { 
			if (empty($this->params['page'])) {
				$app_config = Config::get($app_name);
				if (!isset($app_config['init_page'])) {
					log_entry ('WARNING: init_page not set');
				} else {
					$this->set('page', $app_config['init_page']);
				}
			} else {
				$this->set('page', $this->params['page']);
			}
		}

		if ($this->check_http_auth() == false) {
			$this->error = 'HTTP_AUTH_ERROR';
			$this->template = 'apperror';
			return false;
		}

		$method = false;
		$action_name = $this->action;
		if (empty($action_name)) {	// XXX Should an action be tried even if there's not action specified?
			$method = 'default_action';
		} else {
			if (!isset($this->actions) || (isset($this->actions) && !in_array($action_name, $this->actions) && !array_key_exists($action_name, $this->actions))) {
				log_entry("Unsupported action: '$action_name'");
			} else {
				if (isset($this->actions[$action_name])) 
					$method = $this->actions[$action_name];
				else
					$method = $action_name;
			}
		}

		$result = null;
		if ($method) {
			if (!method_exists($this, $method)) {
				log_entry("Unexistent method: $method");
			} else {
				// TODO Validate method params
				// Maybe http://www.php.net/manual/en/ref.filter.php
				$result = $this->$method($this->params);
				if ($result === 'http redirect') {
					unset($this->template);  // Don't output, we are going to redirect the user
				}
			}
		}

		// XXX After each action should be a HTTP redirect?
		if (isset($this->error)) {
			$logline = "ERROR: {$this->error}";
			if ($this->error_msg) {
				$logline .= " ({$this->error_msg})";
			}
			log_entry($logline);
		}

		return $result;
	}

	public function __construct(array $request) {
		// XXX Check / validate?
		parent::__construct($request);
		$this->params = $request;
	}

	public function current_page() {
		return isset($this->page)? $this->page : null;
	}

	public function login ($email, $password, $save_cookie=false) {
		return $this->do_login ($email, $password, $save_cookie);
	}

	public function logout () {
		if ($this->is_user_logged()) {
			log_entry ("Logout user {$this->user['email']}");

			$this->reset();
			$config = Config::get();
			if (!isset($config['init_app'])) {
				log_entry ("ERROR: init_app is not set");
			} else {
				$_SESSION['appname'] = $config['init_app'];
			}
			
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
					$template_filename = 'templates/default.php';
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