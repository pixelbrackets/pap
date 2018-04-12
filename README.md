Deployment Toolchain
====================

Deployment Toolchain made with Robo.

* Build Assets
* Prepare directory structures
* Use Composer for PHP package mangement
* Rsync to configurable target stages

Installation
------------

* Checkout latest release
* Copy `build` directory, including all hidden files (`.gitignore`)
* Follow installation steps in [build/README.md](./build/README.md)

Update
------

* Òverwrite `build` directory with latest release
* Merge `build.common.properties.yml` with latest structure changes
* Run `composer update`
