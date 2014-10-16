<?php
require_once __DIR__.'/../include/titletag.php';
$title = HTMLTitle::get();
if (empty($title)) {
	HTMLTitle::set_from_page($app, $page);
}
?>
<title><?php echo HTMLTitle::get(); ?></title>
