<?php
namespace Aftertime;

require_once __DIR__.'/../vendor/autoload.php';

class Aftertime {

	private $time_start;
	private $debug = false;
	private $is_ready = false;

	public function debug($debug = null) {
		if ($debug !== null) {
			$this->debug = $debug;
		}
		return $this->debug;
	}

	public function __construct ($config_folder) {
		$this->time_start = microtime(true);
		if ($this->debug()) {
			ini_set ('error_reporting', 'E_ALL');
		} else {
			ini_set ('error_reporting', 'E_ALL & ~E_STRICT');
		}

		$config = Config::init($config_folder);
		if ($config === false) {
			if ($this->debug()) {
				echo nl2br(Config::init_log());
			}
			return;
		} else {
			log_entry(Config::init_log());
			if (isset($config['timezone'])) {
				ini_set ('date.timezone', $config['timezone']);
			}
			if (is_bool($config['debug'])) {
				$this->debug($config['debug']);
				log_entry("Debug mode: {$this->debug()}");
			}

			// Log initialization
			ini_set ('error_log', Log::log_file());
			set_error_handler(array('Aftertime\Log', 'php_errors'));
			set_exception_handler(array('Aftertime\Log', 'php_exceptions'));
			register_shutdown_function(array('Aftertime\Log', 'log_shutdown'));

			ob_start(null, 4096);
			ini_set ('arg_separator.output', '&amp;');
			if (session_start() === false) {
				log_entry('ERROR: Cannot start session');
				return;
			}

			// XXX Should remove the site folder here?
			ini_set ('include_path', $config['site']);
		}
		$this->is_ready = true;
	}

	public function __destruct () {
		// As of PHP 5.4.0, REQUEST_TIME_FLOAT is available in the $_SERVER superglobal array.
		// It contains the timestamp of the start of the request with microsecond precision.
		//	$time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
		log_entry ('=== Page generation time was ' . (microtime(true) - $this->time_start) . ' ===');
	}

	public function run_app() {
		if (!$this->is_ready) {	// Something failed on the constructor
			return false;	
		}
		$app_factory = new AppFactory;
		$this->app = $app_factory->build($_REQUEST);
		if ($this->app) {
			$this->app->debug($this->debug());
			if ($this->app->run() !== 'redirect') {
				return $this->app->render_template();
			}
		} else {
			template_render(__DIR__.'/../templates/apperror.php');
			return false;
		}
		return true;
	}
}
?>
