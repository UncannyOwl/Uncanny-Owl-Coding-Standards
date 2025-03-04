# Define variables
VENDOR_BIN := ./vendor/bin
UNAME_S := $(shell uname -s)
CURRENT_DIR := $(shell pwd)
ADDITIONAL_SNIFFS_DIR := $(CURRENT_DIR)/Uncanny-Owl/additional-sniffs/Uncanny_Automator

# Detect OS and set variables accordingly
ifeq ($(UNAME_S),Darwin)
    # macOS
    INSTALL_DIR := /usr/local/bin
    SUDO_CMD := sudo
else ifeq ($(UNAME_S),Linux)
    # Linux
    INSTALL_DIR := /usr/local/bin
    SUDO_CMD := sudo
else
    # Windows (MSYS2/MinGW/Git Bash)
    INSTALL_DIR := $(APPDATA)/Composer/vendor/bin
    SUDO_CMD :=
endif

# Colors for output
YELLOW := \033[1;33m
GREEN := \033[0;32m
RED := \033[0;31m
NC := \033[0m # No Color

# Default target: Install coding standards and set up environment
install:
	@echo "$(GREEN)Installing Uncanny Owl Coding Standards...$(NC)"
	# Install Composer dependencies
	@composer install
	# Set the default standard to Uncanny-Owl
	@$(VENDOR_BIN)/phpcs --config-set default_standard Uncanny-Owl
	# Enable show progress and colors
	@$(VENDOR_BIN)/phpcs --config-set show_progress 1 > /dev/null 2>&1
	@$(VENDOR_BIN)/phpcs --config-set colors 1 > /dev/null 2>&1
	# Make bin scripts executable (not needed on Windows)
ifneq ($(OS),Windows_NT)
	@chmod +x $(CURRENT_DIR)/bin/uocs
	@chmod +x $(CURRENT_DIR)/bin/uocbf
endif
	# Remove existing symlinks if they exist
	@$(RM) $(INSTALL_DIR)/uocs 2>/dev/null || true
	@$(RM) $(INSTALL_DIR)/uocbf 2>/dev/null || true
	# Create new symlinks or copies based on OS
ifeq ($(OS),Windows_NT)
	@echo "$(YELLOW)Creating Windows shortcuts...$(NC)"
	@echo "@echo off\nphp \"$(CURRENT_DIR)/bin/uocs\" %*" > $(INSTALL_DIR)/uocs.bat
	@echo "@echo off\nphp \"$(CURRENT_DIR)/bin/uocbf\" %*" > $(INSTALL_DIR)/uocbf.bat
else
	@echo "$(YELLOW)Creating symlinks...$(NC)"
	@$(SUDO_CMD) ln -s $(CURRENT_DIR)/bin/uocs $(INSTALL_DIR)/uocs
	@$(SUDO_CMD) ln -s $(CURRENT_DIR)/bin/uocbf $(INSTALL_DIR)/uocbf
endif
	# Check for additional sniffs directory
	@if [ -d "$(ADDITIONAL_SNIFFS_DIR)" ]; then \
		echo "$(GREEN)Found additional sniffs directory...$(NC)"; \
		INSTALLED_PATHS="$(CURRENT_DIR)/Uncanny-Owl,$(CURRENT_DIR)/vendor/phpcsstandards/phpcsutils,$(CURRENT_DIR)/vendor/phpcsstandards/phpcsextra,$(CURRENT_DIR)/vendor/wp-coding-standards/wpcs,$(CURRENT_DIR)/vendor/phpcompatibility/php-compatibility,$(CURRENT_DIR)/vendor/phpcompatibility/phpcompatibility-wp,$(CURRENT_DIR)/vendor/phpcompatibility/phpcompatibility-paragonie,$(ADDITIONAL_SNIFFS_DIR)"; \
		$(VENDOR_BIN)/phpcs --config-set installed_paths $$INSTALLED_PATHS; \
	else \
		echo "$(YELLOW)No additional sniffs directory found, skipping...$(NC)"; \
		INSTALLED_PATHS="$(CURRENT_DIR)/Uncanny-Owl,$(CURRENT_DIR)/vendor/phpcsstandards/phpcsutils,$(CURRENT_DIR)/vendor/phpcsstandards/phpcsextra,$(CURRENT_DIR)/vendor/wp-coding-standards/wpcs,$(CURRENT_DIR)/vendor/phpcompatibility/php-compatibility,$(CURRENT_DIR)/vendor/phpcompatibility/phpcompatibility-wp,$(CURRENT_DIR)/vendor/phpcompatibility/phpcompatibility-paragonie"; \
		$(VENDOR_BIN)/phpcs --config-set installed_paths $$INSTALLED_PATHS; \
	fi
	# Display installed coding standards
	@echo "$(GREEN)Installed coding standards:$(NC)"
	@$(VENDOR_BIN)/phpcs -i
	@echo "\n$(GREEN)Installation complete!$(NC)"
	@echo "$(YELLOW)USAGE: uocs [--strict] [phpcs-options] <path>$(NC)"
	@echo "$(YELLOW)For assistance getting started, try 'uocs -h'$(NC)\n"

# Target to add additional sniffs
add-sniffs:
	@echo "$(GREEN)Setting up additional sniffs directory...$(NC)"
	@mkdir -p $(ADDITIONAL_SNIFFS_DIR)
	@echo "$(YELLOW)Created directory: $(ADDITIONAL_SNIFFS_DIR)$(NC)"
	@echo "$(YELLOW)Please add your custom sniffs to this directory and run 'make reinstall'$(NC)"

# Target to update the repository and re-install coding standards
update:
	@echo "$(GREEN)Updating Uncanny Owl Coding Standards...$(NC)"
	# Pull the latest changes from the master branch
	@git pull origin master
	# Run the install target
	@$(MAKE) install

# Target to check the installed coding standards
check-standards:
	@echo "$(GREEN)Checking installed coding standards...$(NC)"
	# Display the installed coding standards
	@$(VENDOR_BIN)/phpcs -i
	# Display current configuration
	@echo "\n$(YELLOW)Current configuration:$(NC)"
	@$(VENDOR_BIN)/phpcs --config-show
	# Check additional sniffs
	@if [ -d "$(ADDITIONAL_SNIFFS_DIR)" ]; then \
		echo "\n$(YELLOW)Additional sniffs found in:$(NC)"; \
		echo "$(ADDITIONAL_SNIFFS_DIR)"; \
		echo "\n$(YELLOW)Available sniffs:$(NC)"; \
		ls -la $(ADDITIONAL_SNIFFS_DIR); \
	else \
		echo "\n$(YELLOW)No additional sniffs directory found$(NC)"; \
	fi

# Target to clean up installation
clean:
	@echo "$(GREEN)Cleaning up installation...$(NC)"
ifeq ($(OS),Windows_NT)
	@$(RM) $(INSTALL_DIR)/uocs.bat 2>/dev/null || true
	@$(RM) $(INSTALL_DIR)/uocbf.bat 2>/dev/null || true
else
	@$(SUDO_CMD) $(RM) $(INSTALL_DIR)/uocs 2>/dev/null || true
	@$(SUDO_CMD) $(RM) $(INSTALL_DIR)/uocbf 2>/dev/null || true
endif
	@echo "$(GREEN)Cleanup complete!$(NC)"

# Target to reinstall (clean and install)
reinstall: clean install

# Default target
.DEFAULT_GOAL := install

# Phony targets (not actual files)
.PHONY: install update check-standards clean reinstall add-sniffs
