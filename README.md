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

PAP (PHP App Publication)

deployment → project source with the tagged core and without configuration files
  * Add binary
  * Replace linters with configuration → add migration & update guide
  * Add script to release a phar file
deployment-distribution → packed project source (phar file only)
deployment-skeleton → create project, with an example file
deployment-example → example project using deployment (may be a blog post as well)
