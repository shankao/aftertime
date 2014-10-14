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

	static public function render($template_filename, array $template_vars = null, $use_app = false) {
                if (!is_readable($template_filename)) {
                        return false;
                }

		// TODO import *only* the template vars
		if ($use_app === true) {
			$app = self::clone_app();	// Each template can only access to this clone of the app object. Unless they use global...
		}
		if (isset($template_vars)) {
			foreach ($template_vars as $template_varname => $template_value) {
				if ($template_varname == 'template_varname' || $template_varname == 'template_varvalue') {
					return false;
				}
				$$template_varname = $template_value;
			}
			unset($template_varname);
			unset($template_value);
		}
                return include($template_filename);
	}
}
?>
