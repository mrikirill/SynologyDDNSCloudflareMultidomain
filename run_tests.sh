#!/bin/bash

# Check if composer is installed globally
if command -v composer &> /dev/null; then
    COMPOSER_CMD="composer"
else
    # Check if composer.phar exists locally
    if [ ! -f "composer.phar" ]; then
        echo "Composer not found. Downloading composer.phar..."
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        php composer-setup.php
        php -r "unlink('composer-setup.php');"
    fi
    COMPOSER_CMD="php composer.phar"
fi

# Install dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    $COMPOSER_CMD install
fi

# Run tests
echo "Running tests..."
vendor/bin/phpunit tests
