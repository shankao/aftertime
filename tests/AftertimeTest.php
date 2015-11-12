<?php
require_once __DIR__.'/../vendor/autoload.php';

use Aftertime\Aftertime;

class AftertimeTest extends PHPUnit_Framework_TestCase {
	/**
	* @expectedException Aftertime\AftertimeException
	* @expectedExceptionCode Aftertime\AftertimeException::E_CONFIG_INIT
	*/
	public function test_bad_config() {
		$this->expectOutputRegex(<<<EOT
/^Config::init\(\)<br \/>
Loading '.*?\/aftertime.json': OK<br \/>
ERROR: no config files found in folder: badconfiglocation<br \/>
$/
EOT
		);
		$aftertime = new Aftertime('badconfiglocation');
	}

	/**
	* @expectedException Aftertime\AftertimeException
	* @expectedExceptionCode Aftertime\AftertimeException::E_LOG_INIT
	*/
	public function test_log_init_error() {
		$this->expectOutputString("No 'logs' key present in the config\n");
		$aftertime = new Aftertime(__DIR__.'/../config');
	}

}
?>
