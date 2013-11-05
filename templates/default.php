<?php
require_once 'include/titletag.php';
if (empty(HTMLTitle::get())) {
	HTMLTitle::set_from_page($app->params['app'], $app->page);
}

$config = Config::get();
$base_folder = "sites/{$config['site']}";	// XXX Investigate if it can be moved to an app class member or function
echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
                <meta charset="UTF-8" />
                <meta http-equiv="Content-Language" content="en" />
		<title><?php echo HTMLTitle::get(); ?></title>
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
		<?php 
		// Load the current page's template
		if (!isset($app->page) || TemplateLog::render("$base_folder/{$app->params['app']}/{$app->page}.php") === false) {
			log_entry("ERROR: unexistent page {$app->page}");
			TemplateLog::render('templates/apperror.php');
		} 
		?>
	</body>
	<!-- Rev. <?php echo $config['code_revision']; ?> -->
</html>
