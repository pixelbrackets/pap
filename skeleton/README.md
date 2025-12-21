# Build & Deployment

Deployment is managed with [PAP (PHP App Publication)](https://github.com/pixelbrackets/pap).

## Requirements

- cURL, SSH & rsync
- Git
- PHP & Composer
- SSH access to deployment targets

## Installation

```bash
composer install
# PAP executable is downloaded automatically
```

## Usage

**Common commands:**

```bash
# Deploy to live stage
./vendor/bin/pap deploy --stage live

# Deploy to local stage (default, for development)
./vendor/bin/pap deploy

# Sync files without building assets
./vendor/bin/pap sync

# Watch and auto-sync on file changes
./vendor/bin/pap watch

# Lint files
./vendor/bin/pap lint

# See all available commands
./vendor/bin/pap list

# Get help for any command
./vendor/bin/pap deploy --help

# Dry-run mode
./vendor/bin/pap deploy --simulate
```

## Configuration

**Shared settings** (`pap.yml`):
- Deployment stages (local, test, live)
- Sync paths
- Build tasks

**Local overrides** (`pap.local.yml`):
- Copy from `pap.local.template.yml`
- Customize for your environment
- Ignored by Git

**Documentation:**
- [Configuration options](https://github.com/pixelbrackets/pap/blob/master/docs/configuration.md)
- [Walkthrough tutorial](https://github.com/pixelbrackets/pap/blob/master/docs/walktrough.md)

## Updates

```bash
composer update
git add composer.lock
git commit -m "Update dependencies"
```
