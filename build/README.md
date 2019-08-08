PHP Deployment
==============

## Requirements

* cURL, SSH & rsync to sync files `sudo apt-get install curl ssh rsync`
* PHP to run PHAR files `sudo apt-get install php`
* Git to checkout package repositories `sudo apt-get install git`
* Composer to fetch required PHP packages `wget https://getcomposer.org/composer.phar`

## Installation

* Fetch required PHP packages running `./composer.phar install`

## Configuration

* Configure all shared stages (»test«, »live« etc) within `build.common.properties.yml`
* If you have a local development environment, then copy
  `build.local.properties.template.yml`, rename it to `build.local.properties.yml`
  and change the parameters as desired

## Usage

* Run `./robo.phar` to see all available tasks
  * E.g. `./robo.phar deploy -s test` to deploy to the configured test stage
* Add `--help` to each task command, to see all available options
* Any task may be run in dry-mode first by adding `--simulate` to the command
