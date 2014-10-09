<?php
require_once __DIR__.'/config.php';

class HTMLTitle {
	static private $title_tag = '';

	static public function set_from_page($app, $page) {
		$config = Config::get();
		$page_config = Config::get($app, $page);
		if (isset($page_config['title'])) {
			self::$title_tag = "{$page_config['title']}";
			if (isset($config['webtitle'])) {
				self::$title_tag .= " - {$config['webtitle']}";
			}
		} else if (isset($config['webtitle'])) {
			self::$title_tag = "{$config['webtitle']}";
		}
	}

	static public function set($title) {
		self::$title_tag = $title;
	}

	static public function get() {
		return self::$title_tag;
	}
}
