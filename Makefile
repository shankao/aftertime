all: test

test:
	./vendor/bin/phpunit tests

.PHONY: all
