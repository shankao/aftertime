<?php
if ($app->has_error()) {
	foreach($app->errors as $error) {
		echo "<div class=\"error\">$error</div>";
	}
}
?>
