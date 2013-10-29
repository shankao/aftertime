<?php
$config = Config::get();
$base_folder = "sites/{$config['site']}";	// XXX Investigate if it can be moved to an app class member or function
echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
                <meta charset="UTF-8" />
                <meta http-equiv="Content-Language" content="en" />
		<title><?php echo $app->get_title_tag(); ?></title>
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
			if (!isset($app->page) || TemplateLog::render("$base_folder/{$app->params['app']}/{$app->page}.php") === false) {
				log_entry("ERROR: unexistent page {$app->page}");
				TemplateLog::render('templates/apperror.php');
			} ?>
		</div>
		<?php TemplateLog::render("$base_folder/templates/footer.php"); ?>
	</body>
	<!-- Rev. <?php echo $config['code_revision']; ?> -->
</html>
