<?php
require_once 'aftertime/include/aftertime.php';
$aftertime = new Aftertime;
$aftertime->framework_folder = 'aftertime';
$aftertime->config = 'socialads/config';
$aftertime->get_app();
$aftertime->run_app();
?>
