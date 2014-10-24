<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/log.php';

class Aftertime {

	private $time_start;
	private $debug = true;
	private $is_ready = false;

	public function __construct ($config_folder) {
		$this->time_start = microtime(true);
	
		ini_set ('error_reporting', 'E_ALL & ~E_STRICT');

		$config = Config::init($config_folder);
		if ($config === false) {
			if ($this->debug) {
				echo nl2br(Config::init_log());
			}
			return;
		} else {
			if (isset($config['timezone'])) {
				ini_set ('date.timezone', $config['timezone']);
			}

			// Log initialization
			log_entry(Config::init_log());
			ini_set ('error_log', Log::log_file());
			set_error_handler(array('Log', 'php_errors'));
			set_exception_handler(array('Log', 'php_errors'));
			register_shutdown_function(array('Log', 'log_shutdown'));

			ob_start(null, 4096);
			ini_set ('arg_separator.output', '&amp;');
			if (!session_start()) {
				log_entry('ERROR: Cannot start session');
				return;
			}

			// Adds PEAR and the site folder
			// XXX Should remove the site folder here?
			ini_set ('include_path', __DIR__ . '/../lib/pear/php' . PATH_SEPARATOR . $config['site']);
		}
		$this->is_ready = true;
	}

	public function __destruct () {
		log_entry ('=== Page generation time was ' . (microtime(true) - $this->time_start) . ' ===');
	}

	public function run_app() {
		if (!$this->is_ready) {	// Something failed on the constructor
			return false;	
		}
		require_once __DIR__.'/app.php';
		$app_factory = new appFactory;
		$this->app = $app_factory->build($_REQUEST);
		if ($this->app) {
			if ($this->app->run() !== 'redirect') {
				return $this->app->render_template();
			}
		} else {
			require_once __DIR__.'/template.php';
			template_render(__DIR__.'/../templates/apperror.php');
			return false;
		}
		return true;
	}
}
?>
