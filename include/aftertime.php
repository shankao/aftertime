<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/log.php';

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
		ini_set ('error_reporting', 'E_ALL & ~E_STRICT');

		$config = Config::init($config_folder);
		if ($config === false) {
			if ($this->debug()) {
				echo nl2br(Config::init_log());
			}
			return;
		} else {
			if (isset($config['timezone'])) {
				ini_set ('date.timezone', $config['timezone']);
			}
			if (is_bool($config['debug'])) {
				$this->debug($config['debug']);
				log_entry("Debug mode: {$this->debug()}");
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
			ini_set ('include_path', $this->pear_paths() . PATH_SEPARATOR . $config['site']);
		}
		$this->is_ready = true;
	}

	// For all the PEAR require_* hell, that got worse with composer not following the usual PEAR folder structure
	private function pear_paths() {
		$pear_composer = __DIR__ . '/../vendor/pear-pear.php.net/';
		foreach (glob($pear_composer.'*', GLOB_ONLYDIR) as $folder) {
			$paths[] = $folder;
		}
		$paths[] = $pear_composer . 'MDB2_Driver_mysqli/MDB2_Driver_mysqli-1.5.0b4';	// Crap
		return implode(PATH_SEPARATOR, $paths);
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
			$this->app->debug($this->debug());
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
