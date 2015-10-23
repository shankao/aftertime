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
		$this->assertEquals('', Log::caller());
		$this->assertEquals('', Log::caller(false));
		$this->assertEquals('another', Log::caller('another'));
		$this->assertEquals('another', Log::caller(true));
		$this->assertEquals('', Log::caller());
	}

	public function test_log_echo() {
		$this->expectOutputRegex('/^..:..:.. line$/');
		Log::log_entry('line');
	}

	public function test_log_echo_mute() {
		$this->expectOutputRegex('/^..:..:.. Logs are muted\n..:..:.. Logs are unmuted$/');
		Log::mute(true);
		Log::log_entry('line');
		Log::mute(false);
	}

	public function test_log_echo_unmute() {
		$this->expectOutputRegex('/^..:..:.. Logs are muted\n..:..:.. Logs are unmuted\n..:..:.. another$/');
		Log::mute(true);
		Log::log_entry('line');
		Log::mute(false);
		Log::log_entry('another');
	}

	public function test_log_echo_caller() {
		$this->expectOutputRegex('/^..:..:.. me! line\n..:..:.. me! line2$/');
		Log::caller('me!');
		Log::log_entry('line');
		Log::log_entry('line2');
		Log::caller(false);
	}
}
?>
