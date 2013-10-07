<?php
$config = Config::get();
$base_folder = "sites/{$config['site']}";	// XXX Investigate if it can be moved to an app class member or function
echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
                <meta charset="UTF-8" />
                <meta http-equiv="Content-Language" content="en" />
		<title><?php echo get_title_tag(); ?></title>
		<?php
			$favicon = "$base_folder/img/favicon.ico";
			if (is_readable($favicon)) { ?>
				<link rel="shortcut icon" type="image/vnd.microsoft.icon" href="<?php echo $favicon; ?>" /><?php
			}
			TemplateLog::render("$base_folder/templates/load_css.php");
			TemplateLog::render("$base_folder/templates/load_js.php");
		?>
        </head>
        <body>
		<?php TemplateLog::render("$base_folder/templates/header.php"); // XXX This header and footer in every page it's becoming as a bad idea ?>
		<div id="content"><?php 
			// Load the current page
			// XXX check that include file is in allowed pages list. TODO declare a pages list
			$page = $app->current_page();
			$app_name = get_class($app);
			if (!$page || TemplateLog::render("$base_folder/$app_name/$page.php") === false ) {
	        		echo '<div class="error">Unexistent page</div>';
			} ?>
		</div>
		<?php TemplateLog::render("$base_folder/templates/footer.php"); ?>
	</body>
	<!-- Rev. <?php echo $config['code_revision']; ?> -->
</html>
