<?php
namespace Aftertime;

require_once __DIR__.'/log.php';

// Very simple template class
class Template {

	private $filename;
	private $vars;
	private $logging;

	public function __construct($filename, array $vars = null, $logging = true) {
		if (!is_readable($filename)) {
			return false;
		}

		$this->filename = $filename;
		$this->vars = $vars;
		$this->logging = $logging;
	}
	
	public function set_var ($name, $value) {
		$this->vars[$name] = $value;
	}

        public function render() {
		if ($this->logging === true) {
			log_entry ("Template::render({$this->filename})");
		}

		if (!$this->filename) {
			if ($this->logging === true) {
				log_entry("ERROR rendering {$this->filename}: no filename");
			}
			return false;
		}

		if (isset($this->vars)) {
			$template_vars = $this->vars;
			foreach ($template_vars as $template_varname => $template_value) {
				if ($template_varname === 'template_varname' || $template_varname === 'template_varvalue') {
					if ($this->logging === true) {
						log_entry("ERROR rendering {$this->filename}: forbidden var name '$template_varname'");
					}
					return false;
				}
				$$template_varname = $template_value;
			}
			unset($template_varname);
			unset($template_value);
		}

                $result = include($this->filename);
		if ($result === false && $this->logging === true) {
			log_entry("ERROR rendering {$this->filename}: include error");
		}
		return $result;
        }
}

// One-liner for lazyness
function template_render($filename, array $vars = null, $logging = true) {
	$t = new Template($filename, $vars, $logging);
	return $t->render();
}  
?>
