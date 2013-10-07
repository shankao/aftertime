<?php
class session_object {
	private function clear () {
		foreach ($this as $varname => $value)
			unset($this->$varname);
		unset($_SESSION['app']);
	}

	private function load() {
		if (isset($_SESSION['app'])) {
			foreach ($_SESSION['app'] as $varname => $value)
				$this->$varname = $value;
		}
	}

	function reset () {
		$this->clear();	
		$vars = get_class_vars(get_class($this));
		foreach ($vars as $varname => $default)
			$this->$varname = $default;
		$this->save();
	}
	
	private function save() {
		$_SESSION ['app'] = $this;
	}

	function bind (array $source)	{
		foreach ($this as $varname => $value) {
			if (isset($source[$varname]))
				$this->$varname = $source[$varname];
		}				
		$this->save();
	}
	
	function get($var) {
		return $this->$var;
	}
	
	function set($varname, $value) {
		$this->$varname = $value;
		$_SESSION ['app']->$varname = $value;
	}
	
	function reset_var ($varname) {
		$defauls = get_class_vars(get_class($this));
		if (isset($defauls[$varname]))
			$this->set($varname, $defauls[$varname]);
		else
			unset($this->$varname);
	}
	
	function show ($show_session_image = false) {
		echo "<pre>This:<br />";
		print_r ($this);
		
		if ($show_session_image) {
			echo "<br />Session:<br />";
			print_r ($_SESSION['app']);
		}
		
		echo '</pre>';
	}
	
	function __construct(array $source) {
		$this->load();
		foreach ($this as $varname => $value) {
                        if (isset($source[$varname]))
                                $this->$varname = $source[$varname];
                }                               
                $this->save();
	}
}
?>
