PHP Deployment
==============

  * Install the required software
    * Install PHP to run PHAR files (`sudo apt-get install php`)
    * Install Composer to fetch required PHP packages (`wget https://getcomposer.org/composer.phar`)
    * Fetch required php packages (`./composer.phar install`)
  * Run `./robo.phar` to see all available tasks
    * e.g. `./robo.phar deploy -s test` to deploy to the test stage
  * If you have a local development environment, then copy
    »build.local.properties.template.yml«, rename it to »build.local.properties.yml«
    and change the parameters as desired
  * Any task may be run in dry-mode first by adding `--simulate` to the command
  * Done
