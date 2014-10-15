<link rel="stylesheet" type="text/css" href="spidey/css/spidey.css" />
<?php
$app_css = "spidey/$app/$app.css";
if (is_readable($app_css)) { ?>
	<link rel="stylesheet" type="text/css" href="<?php echo "$app_css?$rev"; ?>" /><?php
}
?>
