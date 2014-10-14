<?php
require_once __DIR__.'/../include/titletag.php';
$title = HTMLTitle::get();
if (empty($title)) {
	HTMLTitle::set_from_page($params['app'], $params['page']);
}

$config = Config::get();
$base_folder = "{$config['site']}";	// XXX Investigate if it can be moved to an app class member or function
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
			Template::render("$base_folder/templates/load_css.php", array('app' => $params['app']));
			Template::render("$base_folder/templates/load_js.php", array('app' => $params['app']));
		?>
	</head>
        <body>
		<?php 
		// Load the current page's template
		if (TemplateLog::render("$base_folder/{$params['app']}/{$params['page']}.php", $template_vars, true) === false) {
			log_entry("ERROR: unexistent page template {$params['page']}");
			Template::render('templates/apperror.php');
		} 
		?>
	</body>
	<!-- Rev. <?php echo $config['code_revision']; ?> -->
</html>
