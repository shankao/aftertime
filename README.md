Aftertime
=========

Mission: to be an experimental web framework that implements unconventional ideas

* Don't abstract everything. Use the underlying system. Stop looking away.
* Easy-going on the inside; every site is different. Get code available for common web problems, but don't enforce it.
* Up-to-date technologies. Actively fight code bloat and legacy code and bit rot in general.
* Think on multiserver from the beginning.

= More specific objectives =

* Be aware of the underlying system and use it (i.e. operating system, HTTP server, available packages and modules, etc)
* Uses multiple external libraries and dependencies, the way of the traditional linux package systems. Try to get the jobs that's already done and improve on it instead of creating something from scratch. Gets new features by upgrading the external dependencies
* Always updated. The lastest stable aftertime version will *track* the latest released Ubuntu version. I.e. Ubuntu 13.10 includes PHP 5.5, so it can be used, and any hacks for previous PHP versions will be removed on sight.
* Very lightweigh "App" base class. 
* Good logging and debugging mechanism. Because this represents 90% of the work
* Scale your site from unprivileged shared server to multiple servers
* Multiple deployment: the same commands allow you to use the build site from the development environment, deploy it in a shared hosting configuration, or in a multiple server one with replicated tiers.
* Multiple site development, within the same aftertime instance. Note that this does not mean "multisite" as the sites remain independent.

= Server-side components =

* Build / deploy: GNU Make + git / Unix shell 
* Testing: none yet (planned: PEAR::PHP_Unit)
* DB interface: PEAR::DB_DataObject with MDB2 backend
* Input validation: PHP filter functions (planned: PEAR::Validate / PEAR::Akismet2)
* Caching: none (planned: PEAR::Cache_Lite / Zend opcache)
* Template system: PHP files (planned: mustache)

= Client-side components =

* Included javascript libraries: jQuery, Lab.js
* Image lazy load: none
* Resource compression/packing: none

= Example site =

Consider it a "hello world": it simply defines the minimum elements required to show a webpage.
All the site's code is inside the "sites/example_site" folder.
Build the site by running "make" in aftertime's main folder, and point your web browser to the "build" folder to get to the main page.
You can find some logs under the /tmp/example_site folder
