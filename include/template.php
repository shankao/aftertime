<?php
// Very simple template class
class Template {
	private function clone_app() {
		global $app;
		if (isset($app)) {
			return clone($app);
		} else {
			return null;
		}
	}

	static public function render($template_filename, array $vars = null) {	// TODO Add support for template vars
                if (!is_readable($template_filename)) {
                        return false;
                }

		$app = self::clone_app();	// Each template can only access to this clone of the app object. Unless they use global...
                return include_once($template_filename);			// TODO import only the template vars
	}
}
?>
