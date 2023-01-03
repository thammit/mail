.PHONY: build-mail-dependencies
build-mail-dependencies:
	composer update --no-dev --prefer-dist --optimize-autoloader --working-dir=Resources/Private/PHP
	tools/box compile -c Resources/Private/PHP/box.json

.PHONY: zip
zip:
	ggrep -Po "(?<='version' => ')([0-9]+\.[0-9]+\.[0-9]+)" ext_emconf.php | xargs -I {version} sh -c 'mkdir -p ../zip; git archive -v -o "../zip/$(shell basename $(CURDIR))_{version}.zip" v{version}'
