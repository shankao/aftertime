<?php
/*
Gets all the javascript files for the site/app/page
Note that labjs and jquery are always loaded TODO: improve me?
*/

$files = Config::get('javascript');
if (!$files) $files = array();
$files_app = Config::get("apps.$app.javascript");
if (!$files_app) $files_app = array();
$files_page = Config::get("apps.$app.pages.$page.javascript");
if (!$files_page) $files_page = array();
$all_files = array_unique(array_merge($files, $files_app, $files_page));

?>
<script type="text/javascript" src="aftertime/vendor/labjs/LAB-debug.min.js" charset="utf-8"></script>
<script type="text/javascript" charset="utf-8">
	$LAB
	.setOptions({Debug: true})
	.script("aftertime/vendor/yiisoft/jquery/jquery.min.js").wait() <?php
	foreach ($all_files as $file) {
		if (is_readable($file)) { ?>
			.script("<?php echo "$file?$rev"; ?>") <?php
		}
	} ?>
	;
</script>
