<?php
namespace Aftertime;

class HTMLTitle {
	static private $title_tag = '';

	static public function setFromPage($app, $page) {
		$config = Config::get();
		$page_config = Config::get("apps.$app.pages.$page");
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

