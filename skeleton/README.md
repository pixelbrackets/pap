# Build & Deployment Guide

This guide explains how to build and deploy the app.

The toolchain is set up with
[PAP](https://packagist.org/packages/pixelbrackets/pap).

## Requirements

- cURL, SSH & rsync to sync files
- Git to checkout package repositories
- PHP to run the script
- Composer to fetch required PHP packages
- SSH-Account on target stage(s) with read & write access,
  and right to run cURL, rsync and PHP

```bash
apt-get install curl ssh rsync git php
wget https://getcomposer.org/composer.phar
```

## Installation

- Fetch required PHP packages running `./composer.phar install`

## Usage

- Run `./vendor/bin/pap` to see all available tasks
- Add `--help` to each task command, to see all available options
- Add `--simulate` to each task command, to run in dry-mode first
- Most tasks have a stage as target, passed with `--stage <stagename>`
- If no stagename is passed, the name »local« is used as default - use this
  for development on your local machine

1. Deploy to »live« stage
   ```bash
   ./vendor/bin/pap deploy --stage live
   ```

1. Deploy to »local« stage, used for development (default stage)
   ```bash
   ./vendor/bin/pap deploy
   ```

1. Sync to »local« stage (skips building assets)
   ```bash
   ./vendor/bin/pap sync
   ```

1. Sync to »local« stage automatically if anything changes in the
   source directory (files changed, added or removed)
   ```bash
   ./vendor/bin/pap watch
   ```

1. Lint current build
   ```bash
   ./vendor/bin/pap lint
   ```

## Configuration

- All general settings and shared stages are configured in
  the distribution file `build.common.properties.yml`
- All settings and stages may be overriden in a local environment file
  `build.local.properties.yml`, which is ignored by Git
  - Copy `build.local.properties.template.yml`, rename it to
    `build.local.properties.yml` and change parameters as desired
- The documentation of all options is available in the
  [PAP](https://packagist.org/packages/pixelbrackets/pap) package repository

## Update

- Update required PHP packages running `./composer.phar update`
- Commit the updated `composer.lock` file

## Upgrade

- Check the [PAP](https://packagist.org/packages/pixelbrackets/pap) package
  repository for new releases, and the upgrade guide
