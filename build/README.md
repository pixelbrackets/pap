PHP Deployment
==============

Installation
------------

* Install PHP to run PHAR files running `sudo apt-get install php`
* Install Composer to fetch required PHP packages running
  `wget https://getcomposer.org/composer.phar`
* Fetch required PHP packages running `./composer.phar install`
* Add `composer.lock` to VCS

Configuration
-------------

* Configure all shared stages (»test«, »live« etc) within `build.common.properties.yml`
* If you have a local development environment, then copy
  `build.local.properties.template.yml`, rename it to `build.local.properties.yml`
  and change the parameters as desired

Usage
-----

* Run `./robo.phar` to see all available tasks
  * E.g. `./robo.phar deploy -s test` to deploy to the configured test stage
* Add `--help` to each task command, to see all available options
* Any task may be run in dry-mode first by adding `--simulate` to the command
