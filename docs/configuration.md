# Configuration

All general settings and shared stages are configured in the distribution file `pap.yml`.

PAP always uses the root directory of the Git repository for all configurable paths.
This allows storing the configuration file in any subdirectory.

## Minimal Example

This is roughly what `pap init` generates. It deploys the `src/` directory
to one stage and installs Composer packages there.

```yaml
settings:
  sync-paths:
    - source: src/
      target: src/
  composer:
    working-directory: ./

stages:
  live:
    user: johndoe
    host: example.com
    origin: https://www.example.com
    working-directory: /var/www/
    rsync:
      options: -razc
```

Start with this and add only the sections you need from the reference below.
Every section is optional - a task without configuration does nothing.

## Path Conventions

- Local paths are relative to the Git root directory, unless stated otherwise
- Directory paths end with a trailing slash (`src/`, not `src`)
- Sync tasks use rsync, so the trailing slash matters: `src/` copies the
  contents of the directory, `src` copies the directory itself
- Sync tasks delete files in the target which do not exist in the source

## Configuration Reference

### `settings`

General settings, used for all stages.

| Key                 | Type    | Default            | Description                                                                                                                       |
|---------------------|---------|--------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `repository-path`   | string  | auto-detected      | Absolute path to the Git root directory. Override only if auto-detection fails (worktrees, bare repos, no git binary available). |
| `default-stage`     | string  | `local`            | Stage used when a command is called without a stage. Usually set in `pap.local.yml`.                                              |
| `sync-lock-timeout` | integer | `259200` (3 days)  | Seconds after the last deployment until `sync` requires a full `deploy` again.                                                    |

### `settings.sync-paths`

Directories to synchronize from the Git repository to the stage
(used by `deploy` and `sync`). List of entries:

| Key       | Type            | Default    | Description                                                  |
|-----------|-----------------|------------|--------------------------------------------------------------|
| `source`  | string          | (required) | Directory, relative to the Git root directory                |
| `target`  | string          | (required) | Directory, relative to the `working-directory` of the stage  |
| `exclude` | list of strings | -          | Rsync exclude patterns, eg. `cache/` or `*.log`              |

```yaml
settings:
  sync-paths:
    - source: src/
      target: src/
    - source: public/
      target: public/
      exclude:
        - uploads/
```

### `settings.prepare-sync-paths`

Rearrange files inside the Git repository before the synchronization starts,
for example to move built assets into the web directory or to rearrange
packages in a monorepo. Same keys as `sync-paths`, but `target` is relative
to the Git root directory as well.

```yaml
settings:
  prepare-sync-paths:
    - source: theme/build/assets/
      target: public/assets/
      exclude:
        - sandbox.html
```

### `settings.composer`

Composer fetches packages during `build` and `deploy`.
Composer tasks are skipped if this section is missing.

| Key                 | Type   | Default    | Description                                                           |
|---------------------|--------|------------|-----------------------------------------------------------------------|
| `working-directory` | string | (required) | Directory with the `composer.json`, relative to the Git root directory |
| `phar`              | string | `composer` | Path to the Composer binary on the local machine                      |

### `settings.assets`

Build assets with `buildassets`. Built-in tasks run in this order:
`mirror`, `minify-css`, `minify-js`, `concat`, `minify-img`.

| Key          | Type       | Default | Description                                                                                      |
|--------------|------------|---------|--------------------------------------------------------------------------------------------------|
| `mirror`     | dictionary | -       | Copy directory `source` to directory `target`, eg. into a build directory listed in `.gitignore` |
| `minify-css` | list       | -       | Minify CSS file `source` and write it to file `target`                                           |
| `minify-js`  | list       | -       | Minify JavaScript file `source` and write it to file `target`                                    |
| `concat`     | list       | -       | Concat a list of files `sources` into file `target`                                              |
| `minify-img` | list       | -       | Minify images `source` (wildcards allowed) into directory `target`                               |
| `scripts`    | list       | -       | Run these shell commands instead (eg. npm, gulp) - **all other asset options are ignored**       |

```yaml
settings:
  assets:
    mirror:
      source: theme/assets/
      target: theme/build/assets/
    minify-css:
      - source: theme/build/assets/css/stylesheet.css
        target: theme/build/assets/css/stylesheet.css
    minify-js:
      - source: theme/build/assets/js/bootstrap.js
        target: theme/build/assets/js/bootstrap.js
    concat:
      - sources:
          - theme/build/assets/css/features.css
          - theme/build/assets/css/fonts.css
        target: theme/build/assets/css/zen-garden.css
    minify-img:
      - source: theme/build/assets/img/*
        target: theme/build/assets/img/
```

Or with an external task runner:

```yaml
settings:
  assets:
    scripts:
      - npm ci
      - npm run build
```

### `settings.lint`

Validate files with `lint`.

| Key           | Type | Default | Description                                                    |
|---------------|------|---------|----------------------------------------------------------------|
| `lint-paths`  | list | -       | Directories to check with the PHP linter (`php -l`)            |
| `scripts`     | list | -       | Run these shell commands instead - **`lint-paths` is ignored** |
| `fix.scripts` | list | -       | Shell commands to fix files automatically, used by `lint:fix`  |

```yaml
settings:
  lint:
    lint-paths:
      - src/
    fix:
      scripts:
        - ./vendor/bin/php-cs-fixer fix src
```

Or with Composer scripts defined in the `composer.json` of the app:

```yaml
settings:
  lint:
    scripts:
      - composer lint
    fix:
      scripts:
        - composer fix
```

### `settings.unit-test`

Run unit tests against the local code with `test:unit`.

| Key                         | Type   | Default    | Description                                                   |
|-----------------------------|--------|------------|---------------------------------------------------------------|
| `phpunit.working-directory` | string | (required) | Directory to run PHPUnit in                                   |
| `phpunit.config`            | string | -          | PHPUnit configuration file, relative to the working directory |
| `scripts`                   | list   | -          | Run these shell commands instead - **`phpunit` is ignored**   |

```yaml
settings:
  unit-test:
    phpunit:
      working-directory: ./
      config: phpunit.xml
```

Or with a Composer script defined in the `composer.json` of the app:

```yaml
settings:
  unit-test:
    scripts:
      - composer test
```

### `settings.integration-test`

Run Codeception tests against a deployed stage with `test`.
The `origin` of the stage is passed to the tests as `$_ENV['BASEURL']`.
The legacy key `settings.test` still works.

| Key                             | Type   | Default    | Description                                                     |
|---------------------------------|--------|------------|-----------------------------------------------------------------|
| `codeception.working-directory` | string | (required) | Directory with the Codeception setup, usually `test/`           |
| `codeception.suite`             | string | -          | Test suite to run, eg. `acceptance`                             |
| `scripts`                       | list   | -          | Run these shell commands instead - **`codeception` is ignored** |

```yaml
settings:
  integration-test:
    codeception:
      working-directory: test/
      suite: acceptance
```

### `settings.watch`

The `watch` task syncs changed files to a stage right away.

| Key                 | Type   | Default    | Description                                                                                                  |
|---------------------|--------|------------|--------------------------------------------------------------------------------------------------------------|
| `working-directory` | string | (required) | Directory to watch. Must not contain a `target` of `prepare-sync-paths`, since this causes an infinite loop. |

```yaml
settings:
  watch:
    working-directory: src/
```

### `stages`

Each key below `stages` is the name of a stage, used as command argument
(eg. `pap deploy live`). Configure shared stages in `pap.yml` and
stages for the local machine only in `pap.local.yml`.

| Key                 | Type    | Default    | Description                                                                                             |
|---------------------|---------|------------|---------------------------------------------------------------------------------------------------------|
| `user`              | string  | -          | SSH user, the local machine must be authorized to log in with it                                         |
| `host`              | string  | -          | SSH host (domain or IP address). Without a host, file synchronization is skipped (app runs from the Git checkout) |
| `port`              | integer | `22`       | SSH port                                                                                                |
| `working-directory` | string  | -          | Absolute path on the host, with trailing slash                                                          |
| `origin`            | string  | -          | Public URL of the app, used by `view`, `smoketest` and `test`                                           |
| `rsync.options`     | string  | (none)     | Rsync arguments, eg. `-razc`                                                                            |
| `composer.phar`     | string  | `composer` | Path to the Composer binary on the host                                                                 |
| `lock-branches`     | list    | -          | `deploy` stops if the current Git branch is not listed here                                             |
| `test.deny-groups`  | list    | -          | Codeception groups to exclude when testing this stage                                                   |

Values may reference other values with `${…}`, eg. `${stages.test.working-directory}`.

```yaml
stages:
  test:
    user: johndoe
    host: example.com
    port: 2222
    origin: https://test.example.com
    working-directory: /var/dev/
    rsync:
      options: -razc
    composer:
      phar: ${stages.test.working-directory}composer.phar
    lock-branches:
      - test
    test:
      deny-groups:
        - dataflow
  live:
    user: johndoe
    host: example.com
    origin: https://www.example.com
    working-directory: /var/www/
    rsync:
      options: -razc
```

## Local Overrides

All settings of `pap.yml` may be overridden with a local file named `pap.local.yml`.
Add this file to your `.gitignore`. Use the same keys to override a setting.

Best practice is to configure the local stage and the default stage in this file only.

```yaml
settings:
  default-stage: local
  composer:
    phar: /home/acme/composer/bin/composer

stages:
  local:
    origin: http://localhost:8000
```
