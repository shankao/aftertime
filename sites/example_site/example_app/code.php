<?php
/* 
Main class for our example app.
The name of the class must be the app's ID
*/
class example_app extends app {

	// example_page is the only page defined in the app. This method is called when requesting it
	function example_page() {
	}

	// Custom validator
	// It can return multiple error codes in an array
	// Return false for "no error found"
	function my_validator($param, $request) {
		if ($param == 'test' && $request['int'] == '5') {
			return false;
		} else {
			return 'CALLBACK_VALIDATOR_FAILED';
		}
	}
}
?>
