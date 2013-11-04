<?php
if ($app->has_error()) {
	foreach($app->get_all_errors() as $error) {
		echo "<div class=\"error\">$error</div>";
	}
}
?>
