<?php
/* 
Main class for our example app.
The name of the class must match the app ID
*/
class example_app extends Aftertime\App {
	// Custom validator
	// It can return multiple error codes in an array or false for "no error found"
	public function my_validator($param, $request) {
		if ($param == 'test' && $request['int'] == '5') {
			return false;
		} else {
			return 'CALLBACK_VALIDATOR_FAILED';
		}
	}
}

