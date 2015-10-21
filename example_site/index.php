<?php
require_once 'aftertime/vendor/autoload.php';
$aftertime = new Aftertime\Aftertime('config');
$aftertime->run_app();
?>
