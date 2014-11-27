# TODO: Get the dependencies per-project

JQUERY_VERSION=1.9.1

jquery: jquery-$(JQUERY_VERSION).min.js

jquery-$(JQUERY_VERSION).min.js:
	wget http://code.jquery.com/jquery-$(JQUERY_VERSION).min.js -N

.PHONY: jquery
