<?php
$app_js = "spidey/$app/$app.js";
?>
<script type="text/javascript" src="aftertime/lib/labjs/LAB-debug.min.js" charset="utf-8"></script>
<script type="text/javascript" charset="utf-8">
	$LAB
	.setOptions({Debug: true})
	.script("aftertime/lib/jquery-1.9.1.min.js").wait() <?php 
	if (is_readable($app_js)) { ?>
		.script("<?php echo "$app_js?$rev"; ?>") <?php
	} ?>
	;
</script>
