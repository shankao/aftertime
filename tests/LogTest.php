<?php
require_once __DIR__.'/../vendor/autoload.php';

use Aftertime\Log;
use org\bovigo\vfs\vfsStream;

class LogTest extends PHPUnit_Framework_TestCase {

	private $vfs;
	private $logs_folder;

	public function setUp() {
		$this->vfs = vfsStream::setup();
		$this->logs_folder = $this->vfs->url().'/logs';
		mkdir($this->logs_folder);
	}

	public function tearDown() {
		Log::log_file(false);
	}

	public function test_log_file() {
		$this->assertFalse(Log::log_file());
		$logfile = Log::log_file($this->logs_folder);
		$this->assertRegExp('/^vfs:\/\/root\/logs\/....-..-..\.log$/', $logfile);
		$this->assertFalse(Log::log_file(false));
	}

	public function test_log_entry() {
		$logfile = Log::log_file($this->logs_folder);
		$this->assertFalse(is_readable($logfile));
		Log::log_entry('logline in logfile');
		$this->assertRegExp('/^..:..:.. logline in logfile$/', file_get_contents($logfile));
		Log::log_entry('Second line');
		$this->assertRegExp('/^..:..:.. logline in logfile\n..:..:.. Second line$/', file_get_contents($logfile));
	}

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

	public function test_log_php_error() {
		set_error_handler(array('Aftertime\Log', 'php_errors'));
		$this->expectOutputRegex(<<<EOT
/^..:..:.. file\(not_existing_file\.php\): failed to open stream: No such file or directory \(.*?\/LogTest\.php:[0-9]+\)
(..:..:.. at .*?:[0-9])*/
EOT
		);
		@file('not_existing_file.php');
	}

	public function test_log_php_exception() {
		set_exception_handler(array('Aftertime\Log', 'php_exceptions'));
		$this->assertEquals(array('Aftertime\Log', 'php_exceptions'), set_exception_handler(null));
		$this->expectOutputRegex(<<<EOT
/^..:..:.. Exception: Testing exception
..:..:.. #0 \[internal function\]: LogTest->test_log_php_exception\(\)
(#[0-9]+? .*?\([0-9]+?\): .*?)+/
EOT
);
		LOG::php_exceptions(new Exception('Testing exception', 23));
	}
}

