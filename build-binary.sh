#!/bin/bash
set -e

# Check requirements
if [ ! -f "pap.phar" ]; then
    echo "Error: pap.phar not found. Run build-phar.php first"
    exit 1
fi

if [ ! -f "$HOME/.config/composer/vendor/bin/phpacker" ]; then
    echo "Error: PHPacker not found. Install with: composer global require phpacker/phpacker"
    exit 1
fi

# Build Linux binary with PHPacker
~/.config/composer/vendor/bin/phpacker build linux x64 --src=./pap.phar --dest=./build --php=8.2 --no-interaction

# Clean up
if [ -d "build/linux" ]; then
    [ -f "build/linux/linux-x64" ] && mv build/linux/linux-x64 build/pap-linux-x64 && chmod +x build/pap-linux-x64
    [ -f "build/linux/linux-arm64" ] && mv build/linux/linux-arm64 build/pap-linux-arm64 && chmod +x build/pap-linux-arm64
    rmdir build/linux 2>/dev/null || true
fi

echo "Done"
