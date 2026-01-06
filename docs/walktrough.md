# Walkthrough

**📚 Step-by-step tutorial for beginners**

The goal of this walkthrough is to publish a complete example app with PAP from scratch.

**What you'll learn:**
- Setting up deployment stages
- Configuring file synchronization
- Building and minifying assets
- Managing dependencies
- Running tests and smoke tests
- Complete publication workflow

**Prerequisites:** Git repository, SSH access to target server, basic command line knowledge

**Time:** ~15 minutes

## Example App

Our example contains some assets, has some dependencies to other packages,
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
`build/pap.yml` holds *all* needed settings.

## Commands

Before we start configuring PAP, here are some helpful tips for working with PAP commands:

- Run `./vendor/bin/pap` to see all available tasks
- Add `--help` to each task command, to see all available options
- Add `--simulate` to each task command, to run in dry-mode first
- Most tasks have a stage as target, passed with `--stage <stagename>`
- If no stagename is passed, the name "local" is used as default - use this
  for development on your local machine
- Run `./vendor/bin/pap show` to see a pretty print of your configuration for debugging

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

Throughout this walkthrough we'll use the `test` stage in our examples now.
You may replace it with `live` when you want to publish to the live stage
or omit the `--stage` parameter to use the default local stage.

## Synchronization

Now we set up the file synchronization. We don't need to sync documentation
files or tests, but everything else may be sent to the target stage.

We also want to sync the `vendor/` directory, because installing dependencies
on the target stage may be slow or not possible at all.

In PAP all paths start in the root directory of our Git repository. Regardless of
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

You may wonder why the `source` and `target` paths have separate entries. The reason is that
in monorepos some teams split backend and frontend code in separate Git directories
like `src/php/` and `src/html/` and need to deploy them to different locations 
on the target stage (like `app/`, `public/`, …). In our simple example app the Git repository
structure matches the target stage structure, so source and target paths happen to be the same.

To test the synchronization we run the command
`./vendor/bin/pap sync --stage test`. It will sync all files to the test stage.
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

Run `./vendor/bin/pap buildapp` to let Composer fetch all dependencies of your app locally.

## Deployment

The command `./vendor/bin/pap deploy --stage test` combines all the above
commands. It will fetch dependencies, build assets, sync all files,
and run `composer install` on the target stage to install remaining dependencies.

**This is the key benefit of PAP:** Your teammates don't need to memorize multiple build
steps or understand the complete deployment process. Just document the deployment command
and everyone is able to publish the app. Perfect for new team members, CI/CD pipelines,
or when you manage multiple projects.

## Tests

Tests should be a part of your publication process. The setup is optional,
but it reduces having to remember multiple test commands for different projects.

PAP supports several popular test methods out of the box (linting, unit tests,
smoke tests, integration tests), but not all testing tools for simplicity reasons.
However, you can easily adapt any test command to your needs using custom scripts,
as shown in the examples below.

### Static Analysis / Linting

PAP provides a basic PHP syntax check, which will catch fatal errors in your
PHP code before you try to deploy it to the target stage.

Setup which directories should be checked:

```yaml
settings:
  lint:
    lint-paths:
      - src/
```

Run `./vendor/bin/pap lint` to check the `src` directory only.

### Unit Tests

Unit tests verify your code logic before deployment. PAP supports running
PHPUnit or any other unit test framework on your local code.

```yaml
settings:
  unit-test:
    phpunit:
      config: phpunit.xml
```

Run `./vendor/bin/pap test:unit` to run your unit tests.

Alternatively, you can use custom scripts for other test runners:

```yaml
settings:
  unit-test:
    scripts:
      - composer test
```

### Smoke test

After the deployment we may want to run a so-called smoke test to check that
the app did not crash. PAP offers a command to do so. It will use the domain
setup for each stage.

Run `./vendor/bin/pap test:smoke --stage test` to run a quick availability test.

### Integration Tests

Integration tests verify that your complete application works correctly as a whole.
It will test how all parts (code, templates, APIs) work together in the target environment.

PAP supports Codeception for integration testing by default:

```yaml
settings:
  integration-test:
    codeception:
      working-directory: test/
      suite: acceptance
```

Run `./vendor/bin/pap test:integration --stage test` to run integration tests against the deployed app.

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
  unit-test:
    phpunit:
      config: phpunit.xml
  integration-test:
    codeception:
      working-directory: test/
      suite: acceptance

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
- test:unit
- deploy
  - buildassets
  - buildapp
  - synchronize
- test:smoke
- test:integration

PAP will skip any command which is not configured in the configuration file.
So it is okay to run the `publish` command even when you sync static files only.

That's it! Your app is now published to the test stage.

## Next Steps

PAP provides more [commands](../README.md#usage) and
[configuration](configuration.md) options. Take a look at the existing
documentation, it should help you to publish even more complex structures.

Useful standalone commands for development:

- `./vendor/bin/pap watch` - Automatically sync files when changes are detected
  - Defaults to the local stage again, but you may also send changes to another stage
  using `./vendor/bin/pap watch --stage <stagename>`
- `./vendor/bin/pap show stages` - Display all configured stages of the current project
- `./vendor/bin/pap composer:command --stage test --command <my composer command or script>` - Run
  arbitrary Composer commands on a stage
- `./vendor/bin/pap ssh:connect --stage test` - Connect to a stage via SSH
- Some commands and most options have a short alias, run `./vendor/bin/pap --help` to see them
  
  For example `pap ssh -s live` is an alias for `pap ssh:connect --stage live`
- When you switch a lot between projects, you may consider installing PAP globally
  → See [Global Installation](../README.md#global-installation) for details

You missed something or spotted an error? [Contributions](../CONTRIBUTING.md) are welcome!
