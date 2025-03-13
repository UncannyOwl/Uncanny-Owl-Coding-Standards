#!/bin/bash

# =============================================================================
# PHP CodeSniffer (PHPCS) Compatibility Check Script
# =============================================================================
#
# This script performs PHP compatibility checks on changed PHP files in a project
# using PHP_CodeSniffer. It can run both in a Buddy CI environment and locally.
#
# Usage:
#   - In Buddy CI: Script runs automatically with CI environment variables
#   - Locally: Run with IS_LOCAL=true [BRANCH=your-branch] [EXECUTION_PATH=path]
#
# Environment Variables:
#   IS_LOCAL         - Set to 'true' for local execution (default: false)
#   BRANCH          - Branch name for local testing (default: "feature/test")
#   EXECUTION_PATH  - Path to execute script from (default: current directory)
#
# Requirements:
#   - PHP
#   - Git
#   - Composer (installed automatically in CI environment)
#   - PHP_CodeSniffer configuration (phpcs.xml)
#
# Sample Usage:
#   1. Run locally against current branch:
#      $ IS_LOCAL=true ./ci-buddy-phpcs.sh
#
#   2. Run locally against specific branch:
#      $ IS_LOCAL=true BRANCH=feature/my-branch ./ci-buddy-phpcs.sh
#
#   3. Run locally from different directory:
#      $ IS_LOCAL=true EXECUTION_PATH=/path/to/project ./ci-buddy-phpcs.sh
#
#   4. Run in CI environment:
#      Script will automatically use Buddy CI environment variables
#
# =============================================================================

# Exit on any error
set -e

# Check if running locally
IS_LOCAL=${IS_LOCAL:-false}

if [ "$IS_LOCAL" = "true" ]; then
    # Local testing setup - require branch parameter
    if [ -z "$BRANCH" ]; then
        echo "❌ Error: BRANCH parameter is required when running locally"
        echo "Usage: IS_LOCAL=true BRANCH=your-branch ./ci-buddy-phpcs.sh"
        exit 1
    fi
    EXECUTION_PATH=${EXECUTION_PATH:-"$(pwd)"}
else
    # Buddy CI environment check
    if [ -z "$BUDDY_EXECUTION_BRANCH" ]; then
        echo "❌ Error: This script must be run in Buddy CI environment or with IS_LOCAL=true"
        exit 1
    fi
    BRANCH=$BUDDY_EXECUTION_BRANCH
    EXECUTION_PATH=$BUDDY_EXECUTION_PATH
fi

cd "$EXECUTION_PATH" || exit 1

echo "Verifying PHP version..."
php -v

echo "Fetching all PHP files changed in this PR..."
git fetch origin pre-release

echo "Getting list of changed PHP files (excluding tests/ and vendors/)..."
CHANGED_FILES=$(git diff --name-only --diff-filter=ACMRT pre-release "$BRANCH" | grep '\.php$' | grep -vE '^(tests/|vendors/)' || true)

if [ -z "$CHANGED_FILES" ]; then
    echo "🌶 No relevant PHP files changed. Skipping compatibility check."
    exit 0
fi

echo "$CHANGED_FILES" > changed_files.txt

echo "🍕🍕🍕 Changed files:"
cat changed_files.txt

# Only install composer in CI environment
if [ "$IS_LOCAL" != "true" ]; then
    echo "Installing Composer and required dev dependencies..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    composer install
    composer update --dev
    composer dump-autoload
fi

echo "Setting up PHP CodeSniffer paths..."
./vendor/bin/phpcs --config-set installed_paths vendor/phpcsstandards/phpcsutils,vendor/phpcsstandards/phpcsextra,vendor/wp-coding-standards/wpcs,vendor/phpcompatibility/php-compatibility,vendor/phpcompatibility/phpcompatibility-wp,vendor/phpcompatibility/phpcompatibility-paragonie,additional-sniffs/Uncanny_Automator

echo "✅ Installed Paths:"
./vendor/bin/phpcs --config-show | grep 'installed_paths'

echo "Running PHPCompatibility check..."

# Run PHPCS and save its output to a file, ignoring the exit code
./vendor/bin/phpcs -s --standard=phpcs.xml $(cat changed_files.txt) > phpcs-results.txt 2>&1 || true

# Process PHPCS results
if [ ! -f "phpcs-results.txt" ]; then
    echo "❌ Error: PHPCS results file not found"
    exit 1
fi

# Remove ANSI color codes and store clean output
PHPCS_CLEAN_OUTPUT=$(sed -r "s/\x1B\[([0-9]{1,3}(;[0-9]{1,3})*)?[mGK]//g" "phpcs-results.txt")

# Debug: Print cleaned output
echo "=== Debug: Cleaned Output ==="
echo "$PHPCS_CLEAN_OUTPUT"
echo "=== End Debug ==="

# Count errors and warnings
ERRORS_COUNT=$(echo "$PHPCS_CLEAN_OUTPUT" | grep -c "| ERROR" || echo "0")
WARNINGS_COUNT=$(echo "$PHPCS_CLEAN_OUTPUT" | grep -c "| WARNING" || echo "0")

# Debug: Print counts
echo "=== Debug: Counts ==="
echo "Errors count: $ERRORS_COUNT"
echo "Warnings count: $WARNINGS_COUNT"
echo "=== End Debug ==="

# Output summary
echo "Found $ERRORS_COUNT errors and $WARNINGS_COUNT warnings"

if [ "$ERRORS_COUNT" -gt 0 ]; then
    echo "❌ There are some errors found while running PHPCS:"
    echo "$PHPCS_CLEAN_OUTPUT"
    exit 1
else
    if [ "$WARNINGS_COUNT" -gt 0 ]; then
        echo "⚠️ No errors found, but there are warnings:"
        echo "$PHPCS_CLEAN_OUTPUT"
        exit 0
    else
        echo "✅ All checks passed successfully"
        exit 0
    fi
fi
