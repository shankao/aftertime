<?php
function get_title_tag($app, $page) {
	$title_tag = '';
	$config = Config::get();
	$page_config = Config::get($app, $page);
	if (isset($page_config['title'])) {
		$title_tag = "{$page_config['title']}";
		if (isset($config['webtitle'])) {
			$title_tag .= " - {$config['webtitle']}";
		}
	} else if (isset($config['webtitle'])) {
		$title_tag = "{$config['webtitle']}";
	}
	return $title_tag;
}

$config = Config::get();
$base_folder = "sites/{$config['site']}";	// XXX Investigate if it can be moved to an app class member or function
echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
                <meta charset="UTF-8" />
                <meta http-equiv="Content-Language" content="en" />
		<title><?php echo get_title_tag($app->params['app'], $app->page); ?></title>
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
