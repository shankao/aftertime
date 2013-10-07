<?php
/* 
Main class for our example app.
The name of the class must be the app's ID
*/
class example_app extends app {

	// default_action() is called whenever there's no other action requested
	function default_action () {
		// Set's the title to show in the browser (<title> tag)
		set_pagetitle('Page example');

		// Presents the standard HTML type (/templates/default.php)
		$this->template = 'default';		
	}
}
?>
