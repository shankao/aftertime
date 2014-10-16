<?php
/*
Gets all the css files for the site/app/page
*/
$files = Config::get('css');
if (!$files) $files = array();
$files_app = Config::get("apps.$app.css");
if (!$files_app) $files_app = array();
$files_page = Config::get("apps.$app.pages.$page.css");
if (!$files_page) $files_page = array();
$all_files = array_unique(array_merge($files, $files_app, $files_page));
foreach ($all_files as $file) {
	if (is_readable($file)) { ?>
		<link rel="stylesheet" type="text/css" href="<?php echo "$file?$rev"; ?>" /><?php
	}
}
?>
