<?php
$title = Aftertime\HTMLTitle::get();
if (empty($title)) {
	Aftertime\HTMLTitle::set_from_page($app, $page);
}
?>
<title><?php echo Aftertime\HTMLTitle::get(); ?></title>
