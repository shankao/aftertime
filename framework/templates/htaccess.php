<?php
// TODO Upgrade to apache 2.4 format: https://httpd.apache.org/docs/current/upgrading.html

global $config;
$rootfiles = 'index.php';
if (isset($config['root-content'])) {
	foreach ($config['root-content'] as $file) {
		$file = basename($file);
		$rootfiles = "$rootfiles|$file";
	}
}
?>
# In the case the site is installed in a shared hosting without access to the main apache conf, but allows overrides
# This has 2 limitations:
#	1. It still allows index.php in other folders than root
#	2. .htaccess is bad

# Disallow everything
Order Deny,Allow
Deny from All

# Allowed files in the root folder
<FilesMatch ^(|<?php echo $rootfiles; ?>)$>
	Allow from All
</FilesMatch>

# Allowed extensions
<FilesMatch ^.*\.(css|js|gif|jpg|png)$>
	Allow from All
</FilesMatch>
