# Walktrough

The goal in this walktrough is to publish a minimal app with PAP.
It contains some assets, has some dependencies to other packages,
and will be available on a test and live stage.

This is the fictional file structure:

```tree
.
├── .gitignore
├── composer.json
├── composer.lock
├── theme
│   ├── index.html
│   └── assets
│       ├── logo.png
│       ├── script.js
│       └── stylesheet.css
├── docs
│   └── installation-guide.md
├── README.md
├── src
│   └── App.php
├── tests
│   └── AppTest.php
├── web
│   └── index.php
└── vendor
```

## Installation

At the beginning we install PAP in the project repository. As a best practice
we don't add PAP as dependency to the app itself, but create a subfolder for
the whole publishing process instead.

```
composer create-project pixelbrackets/pap-skeleton build
```

The skeleton project provides all required files to run PAP. The file
`build/build.common.properties.yml` holds *all* needed settings.

## Stages

First we set up all target stages. Below the key `stages` we define one stage
called `test` and another one called `live`. Both stages run on the same host
`example.com`, but have different SSH users, working directories and domains.

The setup looks like this:

```yaml
stages:
  test:
    user: web173
    host: example.com
    origin: https://test.app.example.com/
    working-directory: /var/web173/app/www/
  live:
    user: web178
    host: example.com
    origin: https://app.example.com/
    working-directory: /var/web178/app/www/
```

When we run `./vendor/bin/pap show stages` we should get a list
of all configured stages now.

To test the connection we may use the `ssh:connect` task.
Run `./vendor/bin/pap ssh:connect --stage test`. If everything is correct
it will connect to the target stage and switch into the working directory.

## Synchronisation

Now we set up the file synchronisation. We don't need to sync documentation
files or tests, but everything else may be send to the target stage. In PAP
all paths start in the root directory of our Git repository. Regardless of
the location of the PAP configuration file.

So for our example app the sync paths may look like this:

```yaml
settings:
  sync-paths:
    - source: src/
      target: src/
    - source: web/
      target: web/
    - source: vendor/
      target: vendor/
    - source: composer.json
      target: composer.json
    - source: composer.lock
      target: composer.lock
```

To test the synchronisation we run the command
`./vendor/bin/pap sync --stage test`. It will sync all files.
On the next execution of the command only new and changed files will be synced.

We may run `./vendor/bin/pap view --stage test` to open a browser and point to
the configured URL of the test stage, which is `https://test.app.example.com/`
in our case.

Right now the app dependencies and assets are still missing. Let's see how we
can build them in the next chapter.

## Assets

Our repository contains a prepared theme with assets we want to minify
and then move to the public web directory.

PAP has a task to do basic asset build operations. For our example app this
setup would be sufficient:

```yaml
settings:
  assets:
    mirror:
      # Note: /web/assets/ should be in the .gitignore file
      source: theme/assets/
      target: web/assets/
    minify-css:
      - source: theme/assets/stylesheet.css
        target: web/assets/stylesheet.css
    minify-js:
      - source: theme/assets/script.js
        target: web/assets/script.js
    # …see also »minify-img« and »concat«
```

Run `./vendor/bin/pap buildassets` to start the build process.

If these basic tasks are not sufficient, then you may run custom commands to
start npm, yarn, grunt, gulp or whatever frontend task runner you want to use.

```yaml
settings:
  assets:
    scripts:
      - npm ci
      - grunt build
```

The build command will then execute these scripts instead.

## Dependencies

Run `./vendor/bin/pap buildapp` to let Composer fetch all dependencies
of your app.

## Deployment

The command `./vendor/bin/pap deploy --stage test` combines all the above
commands. It will fetch dependencies, build assets, synchronize all files,
and trigger composer scripts running `composer install` on the target stage.

So any teammates or your CI may run this one command only to deploy your app. 🎉

## Tests

Tests should be a part of your publication process. The setup is optional,
but it reduces having to remember multiple test commands for different projects.

PAP provides a basic PHP syntax check, which will catch fatal errors in your
PHP code before you try to deploy it to the target stage.

Setup which directories should be checked:

```yaml
settings:
  lint:
    lint-paths:
      - src/
```

Run `./vendor/bin/pap lint` to check the `src` directory.

To run custom linters you may use the `scripts` option again.

```yaml
settings:
  lint:
    scripts:
      - composer lint
```

After the deployment we may want to run a so called smoketest to check that
the app did not crash. PAP offers a command to do so. It will use the domain
setup for each stage.

Run `./vendor/bin/pap smoketest --stage test` to run a quick availability test.

To run integration tests or any other test suite you use the `test` option:

```yaml
settings:
  test:
   scripts:
     - composer test
```

Run `./vendor/bin/pap test` to trigger the registered test scripts.

## Publication

The complete configuration file for our example app looks like this:

```yaml
settings:
  sync-paths:
    - source: src/
      target: src/
    - source: web/
      target: web/
    - source: vendor/
      target: vendor/
    - source: composer.json
      target: composer.json
    - source: composer.lock
      target: composer.lock
  assets:
    mirror:
      source: theme/assets/
      target: web/assets/
    minify-css:
      - source: theme/assets/stylesheet.css
        target: web/assets/stylesheet.css
    minify-js:
      - source: theme/assets/script.js
        target: web/assets/script.js
  lint:
    lint-paths:
      - src/
  test:
   scripts:
     - composer test

stages:
  test:
    user: web173
    host: example.com
    origin: https://test.app.example.com/
    working-directory: /var/web173/app/www/
  live:
    user: web178
    host: example.com
    origin: https://app.example.com/
    working-directory: /var/web178/app/www/
```

`./vendor/bin/pap publish` will run the full publication stack for us:

- lint
- deploy
  - buildassets
  - buildapp
  - synchronize
- smoketest
- test

PAP will skip any command which is not configured in the configuration file.
So it is okay to run the `publish` command even when you sync static files only.

## More

PAP provides more [commands](../README.md#usage) and
[configuration](configuration.md) options. Take a look at the existing
documentation, it should help you to publish even more complex structures.

[Contributions](../CONTRIBUTING.md) are welcome!
