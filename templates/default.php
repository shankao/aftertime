<?php
require_once __DIR__.'/../include/titletag.php';
$title = HTMLTitle::get();
if (empty($title)) {
	HTMLTitle::set_from_page($params['app'], $params['page']);
}

echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
                <meta charset="UTF-8" />
                <meta http-equiv="Content-Language" content="en" />
		<title><?php echo HTMLTitle::get(); ?></title>
		<?php
			template_render(__DIR__.'/favicon.php', array('filename' => $config['favicon']), false);
			$vars = array(
				'app' => $params['app'],
				'page' => $params['page'],
				'rev' => $config['code_revision']
			);
			template_render(__DIR__.'/load_css.php', $vars, false);
			template_render(__DIR__.'/load_js.php', $vars, false);
		?>
	</head>
        <body>
		<?php 
		// Load the current page's template
		if (template_render("{$config['site']}/{$params['app']}/{$params['page']}.php", $template_vars, true) === false) {
			log_entry("ERROR: unexistent page template {$params['page']}");
			template_render(__DIR__.'/apperror.php');
		} 
		?>
	</body>
	<!-- Rev. <?php echo $config['code_revision']; ?> -->
</html>
