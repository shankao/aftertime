<ul id="debug-overlay">
	<li>Mem: <?php echo memory_get_usage(true); ?></li>
	<li>Mem peak: <?php echo memory_get_peak_usage(true); ?></li>
	<li>Mem limit: <?php echo ini_get('memory_limit'); ?></li>
</ul>
