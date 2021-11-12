# Configuration

All general settings and shared stages are configured in the distribution file
`pap.yml`.

PAP always uses the root directory of the Git repository for all configurable
paths. This allows storing the configuration file in any subdirectory.

```yaml
### General settings of PAP, used for all stages
### settings: (dictionary)
settings:
  ### Name of the apps web directory - usually »public« or »web«
  ### web-directory: (string, with trailing slash, relative to working directory)
  web-directory: public/
  ### Prepare the folder structure in the Git repository before the 
  ### synchronisation to the target stage starts. For example to move newly
  ### build assets to a view folder or rearange packages in a monorepo.
  ### Uses Rsync, so be aware of trailing slashes
  ### prepare-sync-paths: (list)
  prepare-sync-paths:
    ### source: (string, with trailing slash, relative to git root directory)
    ### target: (string, with trailing slash, relative to git root directory)
    ### exclude: (list, optional)
    ###   - (string, with trailing slash, relative to »target« directory)
    - source: theme/build/assets/
      target: public/assets/
      exclude:
        - sandbox.html
  ### Synchronize files from Git repository to target stage
  ### Uses Rsync, so be aware of trailing slashes
  ### sync-paths: (list)
  sync-paths:
    ### source: (string, with trailing slash, relative to git root directory)
    ### target: (string, with trailing slash, relative to stage working directory)
    ### exclude: (list, optional)
    ###   - (string, with trailing slash, relative to »target« directory)
    - source: src/
      target: src/
    - source: public/assets/
      target: public/assets/
  ### The »watch« task watches for any file changes in the Git repository
  ### starts a synchronisation to the »local« stage right away
  ### watch: (dictionary)
  watch:
    ### Git directory to watch for files changes 
    ### Dont use target directories from the prepare-sync option,
    ### since this would cause an infinite loop
    ### working-directory: (string, with trailing slash, relative to git root directory)
    working-directory: src/
  ### The »view« task opens the public URL of a target stage in a browser
  ### The URL is set up in »stages.<stagename>.origin«
  ### view: (dictionary)
  #view:
  ### Build assets, either with existings task options or custom bash commands
  ### assets: (dictionary)
  assets:
    ### Copy assets to a directory, which should be in the .gitignore file
    ### mirror: (dictionary)
    mirror:
      ### source: (string, with trailing slash, relative to git root directory)
      ### target: (string, with trailing slash, relative to git root directory)
      source: theme/assets/
      target: theme/build/assets/
    ### Minify stylesheets
    ### minify-css: (list)
    minify-css:
      ### source: (string, with filename, relative to git root directory)
      ### target: (string, with filename, relative to git root directory)
      - source: theme/build/assets/css/stylesheet.css
        target: theme/build/assets/css/stylesheet.css
    ### Minify JavaScripts
    ### minify-js: (list)
    minify-js:
      ### source: (string, with filename, relative to git root directory)
      ### target: (string, with filename, relative to git root directory)
      - source: theme/build/assets/js/bootstrap.js
        target: theme/build/assets/js/bootstrap.js
    ### Concat files
    ### concat: (list)
    concat:
      ### sources: (list)
      ###   - (string, with filename, relative to git root directory)
      ### target: (string, with filename, relative to git root directory)
      - sources:
          - theme/build/assets/css/features.css
          - theme/build/assets/css/fonts.css
        target: theme/build/assets/css/zen-garden.css
    ### Minify images - allows asterisks
    ### minify-img: (list)
    minify-img:
      ### source: (string, with trailing slash, relative to git root directory)
      ### target: (string, with trailing slash, relative to git root directory)
      - source: theme/build/assets/img/*
        target: theme/build/assets/img/
    ### Run any CLI commands instead to build assets, for example starting
    ### npm, yarn, grunt, gulp etc
    ### When this option is configured, then all other options of this task 
    ### (mirror, concat, minify*) are ignored
    ### scripts: (list)
    ###   - (string, bash command, relative to git root directory)
    #scripts:
    #  - npm ci
  ### Composer is used to fetch packages during the build process
  ### composer: (dictionary)
  composer:
    ### The working directory is usually the root directory, but in a monorepo
    ### is might be located somewhere else
    ### working-directory: (string, with trailing slash, relative to git root directory)
    working-directory: ./
    ### Path to composer if not installed as globaly
    ### phar: (string, with filename, absolute path on local machine)
    phar: /usr/local/bin/composer
  ### Validate app files
  ### lint: (dictionary)
  lint:
    ### Ruleset for PHP-Codestyle Fixer
    ### php-cs-rules: (string)
    php-cs-rules: '@PSR2'
    ### working directories for the lint scripts
    ### lint-paths: (list)
    ###   - (string, with trailing slash, relative to git root directory)
    lint-paths:
      - src/
    ### Run any CLI commands instead to lint files
    ### When this option is configured, then all other options of this task 
    ### (php-cs-fixer etc) are ignored
    ### scripts: (list)
    ###   - (string, bash command, relative to git root directory)
    #scripts:
    #  - composer lint
    ### Action to fix files automatically
    ### fix: (dictionary)
    fix:
      ### Any CLI commands to fix files
      ### scripts: (list)
      ###   - (string, bash command, relative to git root directory)
      #scripts:
      #  - ./vendor/bin/php-cs-fixer fix src
  ### Test app files
  ### test: (dictionary)
  test:
    ### Codeception testing framework
    ### codeception: (dictionary)
    codeception:
      ### Working directory of the test framework, usually »test«
      ### working-directory: (string, with trailing slash, relative to git root directory)
      working-directory: test/
      ### Test suite to start
      ### suite: (string, one of unit, acceptance or functional)
      suite: acceptance
    ### Run any CLI commands instead to test files
    ### When this option is configured, then all other options of this task 
    ### (codeception etc) are ignored
    ### scripts: (list)
    ###   - (string, bash command, relative to git root directory)
    #scripts:
    #  - composer test

### Settings for each stage
### Add all shared stages here, all stages used on a local machine only
### should be configured in »pap.local.yml« instead
### stages: (dictionary)
stages:
  ### Name of the stage - used in commands as argument
  ### <stagename>: (dictionary)
  test:
    ### Username to start the SSH connection with
    ### The current machines needs to be authorized to to use this username
    ### user: (string)
    user: johndoe
    ### Port to start the SSH connection with (22 if empty)
    ### port: (integer, SSH port)
    #port: 22
    ### The server host to connect to
    ### host: (string, domain or ip address)
    host: example.com
    ### URL of the app installed on the stage
    ### origin: (string, HTTP/HTTPS)
    origin: https://test.example.com
    ### Working directory on the host
    ### host: (string, with trailing slash, absolute path on host machine)
    working-directory: /var/dev/
    ### Rsync options
    ### rsync: (dictionary)
    rsync:
      ### Rsync command arguments
      options: -razc
    ### Composer options
    ### composer: (dictionary)
    composer:
      ### Path to composer if not installed as globaly
      ### phar: (string, with filename, absolute path on host machine)
      phar: ${stages.test.working-directory}composer.phar
    ### Stop the »deploy« task if the currently checked out branch of the Git
    ### repository is not listed in this option
    ### Avoids deploying unwanted branches on a target stage
    ### lock-branches: (list)
    ###   - (string, local branch names)
    lock-branches:
      - test
    ### Test options
    ### test: (dictionary)
    test:
      ### Group to exclude in Codeception tests against this stage
      deny-groups:
        - dataflow
  ### Add as many more stages as you like…
  live:
    user: johndoe
    host: example.com
    origin: https://www.example.com
    working-directory: /var/www/
```

All settings of the distribution file may be overriden with a local file named
`pap.local.yml`. Add this file to your `.gitignore`.

Best practice is to configure the local stage in this file only.

```yaml
### All settings are inherited from the distributon file,
### use the same keys to overwrite any setting
settings:
  composer:
    phar: /home/acme/composer/bin/composer

### Stages have the same structure as in the distribution file as well
stages:
  local:
    user:
    host:
    origin: http://localhost:8000
    working-directory: /var/www/
    rsync:
      options: -razc
```
