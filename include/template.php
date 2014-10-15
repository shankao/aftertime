<?php
require_once __DIR__.'/log.php';

// Very simple template class
class Template {

	private $filename;	
	private $vars;	

	public function __construct($filename, array $vars = null) {
		if (!is_readable($filename)) {
			return false;
		}

		$this->filename = $filename;
		$this->vars = $vars;
	}
	
	public function set_var ($name, $value) {
		$this->vars[$name] = $value;
	}

        public function render() {
		if (!$this->filename) {
			return false;
		}

		if (isset($this->vars)) {
			$template_vars = $this->vars;
			foreach ($template_vars as $template_varname => $template_value) {
				if ($template_varname == 'template_varname' || $template_varname == 'template_varvalue') {
					return false;
				}
				$$template_varname = $template_value;
			}
			unset($template_varname);
			unset($template_value);
		}

                return include($this->filename);
        }
}

// Adds automatic logging
class TemplateLog {
	private $filename;
	private $template;

	public function __construct($filename, array $vars = null) {
		$this->filename = $filename;
		$this->template = new Template ($filename, $vars);
	}

	public function render() {
		log_entry ("TemplateLog::render({$this->filename})");
		$result = $this->template->render();
		if ($result === false) {
			log_entry("ERROR rendering {$this->filename}");
		} 
		return $result;
	}

	public function set_var ($name, $value) {
		$this->template->set_var($name, $value);
	}
}

// One liners for lazyness
function template_render($filename, array $vars = null, $logging = true) {
	if ($logging) {
		$t = new TemplateLog($filename, $vars);
	} else {
		$t = new Template($filename, $vars);
	}
	return $t->render();
}  
?>
