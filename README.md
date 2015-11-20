Aftertime: a lightweight, simple and scalable PHP web framework
=========

* Provide the simplest solution that solves the problem
* Limited run-time dependencies
* Avoid deep levels of abstraction
* Actively fight code bloat, legacy code and bit rot in general.
* Plan for multiple server deployment from the beginning

What's included?
----------------
* Simple routing capabilities with validation
* PDO as direct database interface, and PDOClass for very simple Object-Relational Mapping
* Configuration system
* Logging system. Because maintenance represents 90% of the work
* Simple PHP-based template system
* User authentication and management classes
* Common HTML templates and javascript libraries included: jQuery, Lab.js
* Lightweight!

Requirements
----------------
* Very light: PHP >= 5.3.7 required. See the composer.json file

Example site
----------------
Consider it a "hello world": it simply defines the minimum elements required to show a webpage.
All the site's code is inside the "example_site" folder.
Simply point your web browser to its folder to get to the main page.
You will find some logs under /tmp/example_site

Run the tests
------------------
make test

Some ideas for the future
------------------
* Add more unit tests
* Decouple the User management from the rest
* Better management of global services
* Run-time config management with a web-based interface
* Caching
* Better routing
