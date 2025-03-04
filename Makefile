# Define variables
VENDOR_BIN := ./vendor/bin

# Default target: Install coding standards and set up environment
install:
	# Install Composer dependencies
	composer install
	# Set the default standard to Uncanny-Owl
	$(VENDOR_BIN)/phpcs --config-set default_standard Uncanny-Owl
	# Enable show progress and colors
	$(VENDOR_BIN)/phpcs --config-set show_progress 1 > /dev/null 2>&1
	$(VENDOR_BIN)/phpcs --config-set colors 1 > /dev/null 2>&1
	# Make bin scripts executable
	chmod +x ${CURDIR}/bin/uocs
	chmod +x ${CURDIR}/bin/uocbf
	# Remove existing symlinks if they exist
	rm /usr/local/bin/uocs || true
	rm /usr/local/bin/uocbf || true
	# Create new symlinks
	sudo ln -s ${CURDIR}/bin/uocs /usr/local/bin/uocs
	sudo ln -s ${CURDIR}/bin/uocbf /usr/local/bin/uocbf
	# Display installed coding standards
	$(VENDOR_BIN)/phpcs -i; echo "\n"
	# Display usage information
	@echo "USAGE: uocs [--strict] [phpcs-options] <path>\n"
	@echo "For assistance getting started, try 'uocs -h'\n"

# Target to update the repository and re-install coding standards
update:
	# Pull the latest changes from the master branch
	git pull origin master
	# Run the install target
	$(MAKE) install

# Target to check the installed coding standards
check-standards:
	# Display the installed coding standards
	$(VENDOR_BIN)/phpcs -i

# Target to clean up symlinks
clean:
	# Remove symlinks
	rm /usr/local/bin/uocs || true
	rm /usr/local/bin/uocbf || true

# Default target
.DEFAULT_GOAL := install

# Phony targets (not actual files)
.PHONY: install update check-standards clean
