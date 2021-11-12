# Upgrade Guide

## Updates

- PAP uses semantic versioning, therefore no breaking changes are to expect
  in minor & patch releases
- You are encouraged to update minor and patch versions frequently
- Run `composer.phar update pixelbrackets/pap` to update PAP to the latest 
  release in your version range

## Upgrades

- Check the version of currently used PAP package release with
  `composer.phar show pixelbrackets/pap`
- Check the latest available version on
  [Packagist](https://packagist.org/packages/pixelbrackets/pap/)
- Read the [Changelog](../CHANGELOG.md) to check for breaking changes
- Run `composer.phar require pixelbrackets/pap:^7.0` to update PAP to the latest
  release (version 7 in this example command, replace with the current version)
- Merge any configuration changes into `pap.yml`, `pap.local.template.yml` and 
  `pap.local.yml`, 
  fix any breaking changes and adapt your README if neccessary
