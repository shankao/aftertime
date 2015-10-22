<?php
namespace Aftertime;

require_once __DIR__.'/../vendor/autoload.php';

class Aftertime {

	private $time_start;

	public $debug = false;
	public $is_valid = false;

	private function is_web() {
		return php_sapi_name() === 'cli'? false : true;
	}

	public function __construct ($config_folder, $debug = false) {

		$this->time_start = microtime(true);

		$this->debug = $debug;
		$config = Config::init($config_folder);
		if ($config === false) {
			if ($this->is_web() && $this->debug === false) {
				echo 'Config error';	// Don't output much on web
			} else {
				echo nl2br(Config::init_log());
			}
			return;
		}

		if (isset($config['debug']) && is_bool($config['debug'])) {
			$this->debug = $config['debug'];
		}

		if ($this->debug) {
			ini_set ('error_reporting', 'E_ALL');
		} else {
			ini_set ('error_reporting', 'E_ALL & ~E_STRICT');
		}

		if (isset($config['timezone'])) {
			ini_set ('date.timezone', $config['timezone']);
		}

		if ($this->init_log() === false) {
			if ($this->is_web() && $this->debug === false) {
				echo 'Logging error';	// Don't output much on web
			} else {
				echo "No 'logs' key present in the config\n";
			}
			return;
		}
		if ($this->debug) {
			log_entry('Debug mode set');
			log_entry(Config::init_log());
		}

		if ($this->is_web() && $this->init_web() === false) {
			log_entry('ERROR: Cannot start session');
			return;
		}
		$this->init_paths($config['site']);

		$this->is_valid = true;
	}

	private function init_paths($site) {
		// XXX Should remove the site folder here?
		ini_set ('include_path', $site);
	}

	private function init_web() {
		ob_start(null, 4096);
		ini_set ('arg_separator.output', '&amp;');
		if (session_start() === false) {
			return false;
		}
		return true;
	}

	private function init_log() {
		// Set up logs folder from config
		$config = Config::get();
		if (!isset($config['logs'])) {
			return false;
		}
		if (Log::log_file($config['logs']) === false && $this->is_web()) {
			return false;
		}

		ini_set ('error_log', Log::log_file());
		set_error_handler(array('Aftertime\Log', 'php_errors'));
		set_exception_handler(array('Aftertime\Log', 'php_exceptions'));
		register_shutdown_function(array('Aftertime\Log', 'log_shutdown'));
		return true;
	}

	public function __destruct () {
		if ($this->is_web()) {
			// As of PHP 5.4.0, REQUEST_TIME_FLOAT is available in the $_SERVER superglobal array.
			// It contains the timestamp of the start of the request with microsecond precision.
			//	$time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
			log_entry ('=== Aftertime execution time was ' . (microtime(true) - $this->time_start) . ' ===');
		}
	}

	public function run_app() {
		if (!$this->is_valid) {	// Something failed on the constructor
			return false;	
		}
		$app_factory = new AppFactory;
		$this->app = $app_factory->build($_REQUEST);
		if ($this->app === null) {
			template_render(__DIR__.'/../templates/apperror.php');
			return false;
		}
		$this->app->db = $this->init_db();
		$this->app->debug($this->debug);
		if ($this->app->run() !== 'redirect') {
			return $this->app->render_template();
		}
		return true;
	}

	// Returns a PDO instance initialized following Aftertime\Config
	public function init_db () {
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
?>
