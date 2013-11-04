<?php
if ($app->has_error()) {
	foreach($app->get_all_errors() as $error) {
		$app->print_error($error);
	}
}
?>
