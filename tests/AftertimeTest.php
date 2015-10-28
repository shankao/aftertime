<?php
require_once __DIR__.'/../vendor/autoload.php';

use Aftertime\Aftertime;

class AftertimeTest extends PHPUnit_Framework_TestCase {
	/**
	* @expectedException Aftertime\AftertimeException
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
}
?>
