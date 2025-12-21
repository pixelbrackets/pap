#!/bin/bash
set -e

# Build all executables (PHAR and binary)

# Install dependencies
composer install --no-dev --optimize-autoloader

# Build PHAR
php --define phar.readonly=0 build-phar.php
if [ ! -f "pap.phar" ]; then
    echo "Error: pap.phar was not created"
    exit 1
fi

# Test PHAR
php pap.phar list > /dev/null

# Build binary
./build-binary.sh

# Move PHAR to build directory
mv pap.phar build/pap.phar

# Generate checksums
cd build
sha256sum pap-linux-* pap.phar > checksums.txt 2>/dev/null || sha256sum pap.phar > checksums.txt
cd ..

echo "Done"
