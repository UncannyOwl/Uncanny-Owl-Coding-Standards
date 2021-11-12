VENDOR_BIN := ./vendor/bin

install:
	composer install
	 $(VENDOR_BIN)/phpcs --config-set default_standard Uncanny-Owl
	 $(VENDOR_BIN)/phpcs --config-set show_progress 1 > /dev/null 2>&1
	 $(VENDOR_BIN)/phpcs --config-set colors 1 > /dev/null 2>&1
	chmod +x ${CURDIR}/bin/uocs
	chmod +x ${CURDIR}/bin/uocbf
	rm /usr/local/bin/uocs || true
	rm /usr/local/bin/uocbf || true
	sudo ln -s ${CURDIR}/bin/uocs /usr/local/bin/uocs
	sudo ln -s ${CURDIR}/bin/uocbf /usr/local/bin/uocbf
	 $(VENDOR_BIN)/phpcs -i; echo "\n"
	echo "USAGE: uocs [--strict] [phpcs-options] <path>\n"
	echo "For assistance getting started try 'uocs -h'\n"
update:
	git pull origin master
	$(MAKE) install