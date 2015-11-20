<?php
namespace Aftertime;

/*
   User management 
   It requires a PDO connection up and running with a table called 'users'
 */
class User extends PDOClass {
	const OK = 'OK';
	const NO_EMAIL_PASSWD = 'NO_EMAIL_PASSWD';
	const NO_USER_FOUND = 'NO_USER_FOUND';
	const WRONG_PASSWD = 'WRONG_PASSWD';

	const COOKIE_USER = '_us';
	const COOKIE_PASSWD = '_pw';
	const COOKIE_TIME = 864000;	// 10 days

	const SESSION_VARNAME = '_user';

	protected $_table = 'users';
	protected $_fields = ['user_id', 'email', 'password'];
	protected $_key = 'user_id';

	public $user_id, $email, $password;

	private function get_user_from_session() {
		$sdata = unserialize($_SESSION[User::SESSION_VARNAME]);
		foreach ($sdata as $name => $value) {
			$this->$name = $value;
		}
	}

	private function put_user_in_session() {
		foreach ($this->_fields as $field) {
			$user_array[$field] = $this->$field;
		}
		$_SESSION[User::SESSION_VARNAME] = serialize($user_array);
	}

	private function clean_user_session() {
		unset($_SESSION[User::SESSION_VARNAME]);
	}

	private function is_user_in_session() {
		return isset($_SESSION[User::SESSION_VARNAME]);
	}

	// Note that the one in the cookie is the encrypted password
	private function set_cookies() {
		// Keep for 10 days
		setcookie(User::COOKIE_USER, $this->email, time()+User::COOKIE_TIME, '/', null, false, true);
		setcookie(User::COOKIE_PASSWD, $this->password, time()+User::COOKIE_TIME, '/', null, false, true);
	}

	private function clean_cookies() {
		setcookie(User::COOKIE_USER, '', time()-3600, '/', null, false, true);
		setcookie(User::COOKIE_PASSWD, '', time()-3600, '/', null, false, true);
	}

	private function get_cookies() {
		if (isset($_COOKIE[User::COOKIE_USER]) && isset($_COOKIE[User::COOKIE_PASSWD])) {
			$result['email'] = $_COOKIE[User::COOKIE_USER];
			$result['password'] = $_COOKIE[User::COOKIE_PASSWD];
			return $result;
		} else {
			return false;
		}
	}

	// Basic auth. code taken from http://www.php.net/manual/en/features.http-auth.php
	private function check_http_auth() {
		$c = Config::get('apps.'.get_class($this));
		if (isset($c['user']) && isset($c['passwd'])) {
			// TODO Check better auth. ways instead of "Basic". Maybe autogenerating .htaccess and .htpasswd files
			// See http://pear.php.net/manual/en/package.filesystem.file-htaccess.intro.php

			$user = false;
			if (isset($_SERVER['PHP_AUTH_USER'])) {
				$user = $_SERVER['PHP_AUTH_USER'];
				$passwd = $_SERVER['PHP_AUTH_PW'];
			} else {
				// fixme This does not work when PHP is working as CGI/FastCGI
			}

			if ($user === $c['user'] && $passwd === $c['passwd']) {
				// HTTP auth OK
			} else {
				header('WWW-Authenticate: Basic realm="Login please"');
				header('HTTP/1.0 401 Unauthorized');
				return false;
			}
		}
		return true;
	}

	// This is the default. Note that apps. are free to override it. XXX Convert to interface?
	// TODO Check this (PHP 5.5+): http://www.php.net/manual/en/book.password.php
	public function encrypt_password ($password) {
		if (empty($password)) return null;

		$type = 'blowfish';
		$code = '2y'; 	// Blowfish code from php 5.3.7
		$cost = '11';	// range [04,31]
		for ($i=0, $salt=''; $i<22; $i++)		// [./0-9A-Za-z]{22} more chars are ignored
			$salt .= rand(0, 9);

		$combined_salt = "\$$code\$$cost\$$salt";
		$result = crypt($password, $combined_salt);
		return $result;
	}

	public function check_password ($input, $encrypted, $input_is_encrypted=false) {
		if (empty($input) || empty($encrypted)) return false;

		if ($input_is_encrypted) {
			$result = ($input === $encrypted)? true : false;
		} else {
			$result = (crypt($input, $encrypted) == $encrypted)? true : false;
		}
		return $result;
	}

	public function login ($email = '', $password = '', $set_cookies=false) {
		if ($this->is_user_logged()) {
			$this->get_user_from_session();
		} else {
			$password_is_encrypted = false;
			if (empty($email) || empty($password)) {	// Try cookies auth
				$cookies = $this->get_cookies();
				if ($cookies === false) { 
					return User::NO_EMAIL_PASSWD;	// No more methods to try
				}
				$email = $cookies['email'];
				$password = $cookies['password'];
				$password_is_encrypted = true;
				$set_cookies = true;	// Keep them
			}

			$this->clean_cookies();
			$this->email = $email;
			if ($this->select(true) === false) {
				return User::NO_USER_FOUND;
			}

			if (!$this->check_password($password, $this->password, $password_is_encrypted)) {
				return User::WRONG_PASSWD;
			}

			$this->put_user_in_session();
			if ($set_cookies) {	
				$this->set_cookies();
			}
		}
		return User::OK;
	}

	public function logout () {
		if ($this->is_user_logged()) {
			$this->clean_user_session();
			$this->clean_cookies();
		}
	}

	public function is_user_logged() {
		return $this->is_user_in_session();
	}
}

