<?php
$title = Aftertime\HTMLTitle::get();
if (empty($title)) {
	Aftertime\HTMLTitle::setFromPage($app, $page);
}
?>
<title><?php echo Aftertime\HTMLTitle::get(); ?></title>
