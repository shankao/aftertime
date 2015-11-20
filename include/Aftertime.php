<?php
namespace Aftertime;

class Aftertime {

	private $time_start;

	public $debug = false;

	private function isWeb() {
		return php_sapi_name() === 'cli'? false : true;
	}

	public function __construct ($config_folder, $debug = null) {

		$this->time_start = microtime(true);

		$this->debug = $debug;
		$config = Config::init($config_folder);
		if ($config === false) {
			if ($this->isWeb() && $this->debug === false) {
				echo 'Config error';	// Don't output much on web
			} else {
				echo nl2br(Config::initLog());
			}
			throw new AftertimeException("Can't initialize config", AftertimeException::E_CONFIG_INIT);
		}

		if (!is_bool($debug)) {	// Gives prio to the passed $debug param
			if (isset($config['debug']) && is_bool($config['debug'])) {
				$this->debug = $config['debug'];
			}
		}

		if ($this->debug) {
			ini_set ('error_reporting', 'E_ALL');
		} else {
			ini_set ('error_reporting', 'E_ALL & ~E_STRICT');
		}

		if (isset($config['timezone'])) {
			ini_set ('date.timezone', $config['timezone']);
		}

		if ($this->initLog() === false) {
			if ($this->isWeb() && $this->debug === false) {
				echo 'Logging error';	// Don't output much on web
			} else {
				echo "No 'logs' key present in the config\n";
			}
			throw new AftertimeException("Can't initialize logs", AftertimeException::E_LOG_INIT);
		}
		if ($this->debug) {
			log_entry('Debug mode set');
			log_entry(Config::initLog());
		}

		if ($this->isWeb() && $this->initWeb() === false) {
			log_entry('ERROR: Cannot start session');
			throw new AftertimeException("Cannot start session", AftertimeException::E_SESSION_INIT);
		}
		$this->initPaths($config['site']);
	}

	private function initPaths($site) {
		// XXX Should remove the site folder here?
		ini_set ('include_path', $site);
	}

	private function initWeb() {
		ob_start(null, 4096);
		ini_set ('arg_separator.output', '&amp;');
		if (session_start() === false) {
			return false;
		}
		return true;
	}

	private function initLog() {
		// Set up logs folder from config
		$config = Config::get();
		if (!isset($config['logs'])) {
			return false;
		}
		if (Log::setFile($config['logs']) === false && $this->isWeb()) {
			return false;
		}

		ini_set ('error_log', Log::setFile());	// XXX function with 2 uses
		set_error_handler(array('Aftertime\Log', 'phpErrors'));
		set_exception_handler(array('Aftertime\Log', 'phpExceptions'));
		register_shutdown_function(array('Aftertime\Log', 'logShutdown'));
		return true;
	}

	public function __destruct () {
		if ($this->isWeb()) {
			// As of PHP 5.4.0, REQUEST_TIME_FLOAT is available in the $_SERVER superglobal array.
			// It contains the timestamp of the start of the request with microsecond precision.
			//	$time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
			log_entry ('=== Aftertime execution time was ' . (microtime(true) - $this->time_start) . ' ===');
		}
	}

	public function runApp() {
		$app_factory = new AppFactory;
		$this->app = $app_factory->build($_REQUEST);
		if ($this->app === null) {
			template_render(__DIR__.'/../templates/apperror.php');
			return false;
		}
		$this->app->db = $this->initDB();
		$this->app->debug($this->debug);
		if ($this->app->run() !== 'redirect') {
			return $this->app->renderTemplate();
		}
		return true;
	}

	// Returns a PDO instance initialized following Aftertime\Config
	public function initDB () {
		$config = Config::get();
		if (!isset($config['database'])) {
			log_entry('No database entry found in config');
			return false;
		}
		$dbconfig = $config['database'];

		// Note we force a UTF8 connection
		$dsn = "{$dbconfig['protocol']}:host={$dbconfig['host']};dbname={$dbconfig['dbname']};charset=utf8";

		try {
			$options = [
				\PDO::ATTR_EMULATE_PREPARES => false
			];
			if ($this->debug) {
				return new PDOLog($dsn, $dbconfig['user'], $dbconfig['password'], $options);
			} else {
				return new \PDO($dsn, $dbconfig['user'], $dbconfig['password'], $options);
			}
		} catch (\PDOException $e) {
			log_entry("PDO Exception: ".$e->getMessage());
			return false;
		}
	}
}

