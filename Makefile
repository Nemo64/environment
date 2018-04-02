COMPOSER=composer
PHP=php

.PHONY: test install

test: install
	$(PHP) vendor/bin/phpunit tests

install: vendor

vendor: composer.lock
	$(COMPOSER) install

composer.lock: composer.json
	$(COMPOSER) update