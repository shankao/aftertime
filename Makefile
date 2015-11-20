PHPCS=./vendor/squizlabs/php_codesniffer/scripts/phpcs --standard=psr1
PHPUNIT=./vendor/bin/phpunit

all: test

codesniff:
	find include -name *.php | xargs ${PHPCS}
	find scripts -name *.php | xargs ${PHPCS}
	find templates -name *.php | xargs ${PHPCS}

test:
	${PHPUNIT} tests
	$(MAKE) codesniff

.PHONY: all
