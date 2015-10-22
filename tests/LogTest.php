<?php
require_once __DIR__.'/../vendor/autoload.php';

use Aftertime\Log;

class LogTest extends PHPUnit_Framework_TestCase {
	// Not complete
	public function test_log_file() {
		$this->assertEquals(false, Aftertime\Log::log_file());
	}

	// Complete
	public function test_caller() {
		$this->assertEquals('', Log::caller());
		$this->assertEquals('', Log::caller(123));
		$this->assertEquals('caller', Log::caller('caller'));
		$this->assertEquals('caller', Log::caller());
		$this->assertEquals('caller', Log::caller(false));
		$this->assertEquals('another', Log::caller('another'));
		$this->assertEquals('another', Log::caller(true));
	}
}
?>
