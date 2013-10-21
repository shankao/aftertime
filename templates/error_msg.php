<?php
if (isset($app->error)) {
	$error = isset($app->error_msg)? $app->error_msg : $app->error;
	echo "<div class=\"error\">$error</div>";
}
?>
