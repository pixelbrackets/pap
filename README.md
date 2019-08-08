Deployment Toolchain
====================

Deployment Toolchain made with Robo.

* Build Assets
* Prepare directory structures
* Use Composer for PHP package mangement
* Rsync to configurable target stages

## Requirements

* cURL, SSH & rsync
* PHP
* Git
* Composer

## Installation

* Checkout latest release
* Copy `build` directory, including all hidden files (`.gitignore`)
* Install dependencies as described in [build/README.md](./build/README.md#Requirements)
* Add `composer.lock` to VCS
* Configure stages as described in [build/README.md](./build/README.md#Configuration)

## Update

* Overwrite `build` directory with latest release
* Merge `build.common.properties.yml` with latest structure changes
* Run `composer update`
