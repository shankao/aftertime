<p class="error">
	Something went wrong... <?php
	if (isset($app->error)) {
		$error_msg = isset($app->error_msg)? $app->error_msg : $app->error;
        	echo $error_msg;
	}
	?>
</p>
