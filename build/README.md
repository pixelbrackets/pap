PHP Deployment
==============

  * Install the required software
    * Install PHP to run PHAR files (»sudo apt-get install php«)
  * Run »./robo.phar« to see all available tasks
    * e.g. »./robo.phar deploy -s test« to deploy to the test stage
  * If you have a local development environment, then copy
    »build.local.properties.template«, rename it to »build.local.properties«
    and change the parameters as desired
  * Done
