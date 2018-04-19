COMPOSER=composer
PHP=php

.PHONY: test install

test: install
	$(PHP) vendor/bin/phpunit tests

install: vendor

vendor: $(wildcard composer.*)
	$(COMPOSER) install