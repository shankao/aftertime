<?php
namespace Aftertime;

require_once __DIR__.'/template.php';

// One-liner for lazyness
function template_render($filename, array $vars = null, $logging = true) {
	$t = new Template($filename, $vars, $logging);
	return $t->render();
}
?>
