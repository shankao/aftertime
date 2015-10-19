<?php
namespace Aftertime;

// One-liner for lazyness
function template_render($filename, array $vars = null, $logging = true) {
	$t = new Template($filename, $vars, $logging);
	return $t->render();
}
?>
