<?php
/* 
Main class for our example app.
The name of the class must be the app's ID
*/
class example_app extends app {

	// example_page is the only page defined in the app. Its method is called when requesting the page
	function example_page() {
		// Set's the title to show in the browser (<title> tag)
		set_pagetitle('Page example');

		// Presents a standard HTML type (templates/default.php)
		$this->template = 'default';		
	}

	// Custom validator
	function my_validator($param) {
		if ($param == 'test' && $_REQUEST['int'] == '5') {
			return $param;
		} else {
			return false;
		}
	}
}
?>
