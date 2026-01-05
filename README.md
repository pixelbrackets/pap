# PAP

![Logo](./docs/icon.png)

**P**HP **A**pp **P**ublication

[![Version](https://img.shields.io/packagist/v/pixelbrackets/pap.svg?style=flat-square)](https://packagist.org/packages/pixelbrackets/pap/)
[![Build Status](https://img.shields.io/gitlab/pipeline/pixelbrackets/pap?style=flat-square)](https://gitlab.com/pixelbrackets/pap/pipelines)
[![Made With](https://img.shields.io/badge/made_with-php-blue?style=flat-square)](https://gitlab.com/pixelbrackets/pap#requirements)
[![License](https://img.shields.io/badge/license-gpl--2.0--or--later-blue.svg?style=flat-square)](https://spdx.org/licenses/GPL-2.0-or-later.html)
[![Contribution](https://img.shields.io/badge/contributions_welcome-%F0%9F%94%B0-brightgreen.svg?labelColor=brightgreen&style=flat-square)](https://gitlab.com/pixelbrackets/pap/-/blob/master/CONTRIBUTING.md)

Toolchain to publish a PHP App. One YAML file. One command set. Any project.

**⚡ Same commands in every project** - Learn once, use everywhere.
No need to memorize different deployment steps for each project.

**📝 One YAML file** - All deployment configuration in one place.
No scattered scripts, no complex build tools.

**🚀 15 minute setup** - Add PAP to any project in minutes.
No coupling with your app code, works as standalone build directory.

**👥 Team and CI-friendly** - New teammates and CI robots can deploy
without understanding the internals. Just one command: `pap publish`.

## What it does

- Build Assets - Minify & concat CSS, JavaScript, SVG assets
- Build App - Prepare expected directory structures & fetch packages
- Lint - Identify errors before the app is running
- Unit Test - Run unit tests against local code
- Deploy - Sync files to configurable target stages (local, test, live, …)
- Verify - Do a smoke test to verify that the app is still working
- Test - Run integration tests against deployed app

![Screenshot](./docs/screenshot.png)

## Design Principles

**KISS approach** - Not made for every condition, but easy to use and integrate

- Fixed set of task commands (but you can run custom scripts within them)
- YAML configuration with local overrides support
- Works as standalone build directory (no coupling with app code)
- CI/CD friendly - same commands work for humans and robots
- Minimal requirements: Git, PHP, Composer, rsync, SSH
- Multiple deployment stages (local, test, live, …)
- Monorepo support
- No rollback (use Git to revert changes)
- No provisioning (deploy only)

When to use alternatives: Need custom task workflows, atomic releases, advanced rollback strategies,
or server provisioning? Tools like [Deployer](https://deployer.org/), [Capistrano](https://capistranorb.com/),
or [Ansible](https://www.ansible.com/) offer more features. PAPs sweet spot is that it is *deliberately* simple -
perfect for small to medium projects where complexity isn't worth the overhead.

## Requirements

- cURL, SSH & rsync
- Git
- PHP
- Composer
- SSH-Account on target stage(s) with read & write access,
  and right to run cURL, rsync and PHP

## Source

https://gitlab.com/pixelbrackets/pap/

Mirror https://github.com/pixelbrackets/pap/ (Issues & Pull Requests
mirrored to GitLab)

## Installation

### New Projects

The recommend way to add PAP to a new project is to use the provided skeleton package.
This creates a `build/` directory with all required configuration files and
the PAP executable in one command.

```bash
composer create-project pixelbrackets/pap-skeleton build
cd build
./vendor/bin/pap list
```

Now edit `pap.yml` to configure your deployment stages.

**📚 New to PAP?** Follow the [step-by-step walkthrough tutorial](./docs/walktrough.md) to
learn how to set up PAP and publish your PHP webapp or website (~15 minutes).

### Existing Projects

If your project already has a `build/` directory with PAP configuration files,
then all you need to do is fetching PAP using Composer, no additional setup required.

```bash
cd build
composer install
./vendor/bin/pap list
```

### Global Installation (Advanced)

For special use cases like global installation, team consistency, or CI environments,
you can install PAP as a phar executable or self-contained binary.

Distribution repository: https://github.com/pixelbrackets/pap-dist/releases

**Binary** (Linux):

```bash
wget https://github.com/pixelbrackets/pap-dist/releases/latest/download/pap-linux-x64
sudo mv pap-linux-x64 /usr/local/bin/pap
sudo chmod +x /usr/local/bin/pap
pap list
```

The binary bundles a specific PHP version, so it works independent of your projects PHP version.
**Security Note:** The bundled PHP version may lag behind security updates. For production deployments
within public CI workflows, prefer the standard Composer installation to control PHP updates via system updates.

**PHAR** (all platforms):

```bash
wget https://github.com/pixelbrackets/pap-dist/releases/latest/download/pap.phar
php pap.phar list
```

### CI/CD Usage

**GitLab CI example:**

```yaml
deploy:
  stage: deploy
  image: composer:latest
  script:
    - cd build && composer install  # Installs PAP via Composer
    - vendor/bin/pap deploy --stage live # Deploy app to live stage using the versioned configuration files
```

**GitHub Actions example:**

```yaml
- name: Install dependencies
  run: cd build && composer install

- name: Deploy
  run: cd build && vendor/bin/pap deploy --stage live
```

## Configuration

PAP is configured with YAML files:

- **`pap.yml`** - Shared settings and stages (committed to Git)
- **`pap.local.yml`** - Local overrides (add to `.gitignore`)

**The skeleton package provides these files as templates.** Just edit `pap.yml` to configure:
- Deployment stages (local, test, live)
- Sync paths (which files to deploy)
- Build tasks (assets, dependencies)
- Lint and test commands

All configuration paths are relative to your Git repository root, so you can place the configuration
in any subdirectory (typically `build/`) and are still good to go.

- 📖 [Walkthrough: Publish your first app](./docs/walktrough.md) - Complete beginner guide with example app (15 min)
- 📝 [Reference: All available configuration options](./docs/configuration.md)
- 🛠️ [Tutorial: Manual setup without skeleton package](./docs/configuration.md#manual-setup) (advanced)

## Updates

See [Upgrade Guide](./docs/upgrade-guide.md)

## Usage

This section gives a brief overview of available commands and common tasks.

**Quick Tips:**
- Run `./vendor/bin/pap` to see all available tasks
- Add `--help` to each task command to see all available options
- Add `--simulate` to each task command to run in dry-mode first
- Most tasks have a stage as target, passed with `--stage <stagename>`
- If no stagename is passed, the name "local" is used as default - use this for development on your local machine

Somme common tasks are:

**Deploy to live stage:**
```bash
./vendor/bin/pap deploy --stage live
```

**Deploy to local stage (default, for development):**
```bash
./vendor/bin/pap deploy
```

**Sync files without building assets:**
```bash
./vendor/bin/pap sync
```

**Watch and auto-sync to local stage on file changes:**
```bash
./vendor/bin/pap watch
```

**Lint files:**
```bash
./vendor/bin/pap lint
```

**SSH into stage:**
```bash
./vendor/bin/pap ssh -s live
```

**Open stage URL in browser:**
```bash
./vendor/bin/pap view -s live
```

### Commands

**Task Hierarchy** - Understanding the full publication stack:

```
publish (Complete release workflow)
├── lint (Validate code syntax)
├── unittest (Run unit tests against local code)
├── deploy (Full deployment)
│   ├── build (Prepare application)
│   │   ├── buildassets (Process CSS/JS/images)
│   │   └── buildapp (Prepare directory structure and install composer dependencies locally)
│   ├── sync (Transfer files via rsync)
│   └── composer:install (Install remaining dependencies and trigger post-install commands on target stage)
├── smoketest (Quick HTTP check)
└── integrationtest (Run integration tests against deployed app)

Common standalone tasks:
├── show stages (get a list of all configured stages)
├── sync (Quick file sync without rebuilding)
├── watch (Auto-sync on file changes)
├── lint:fix (Auto-fix code style issues)
├── ssh:connect (SSH into stage)
└── view (Open stage URL in browser)
```

**All Available Commands:**

<!-- Generate using `./bin/pap list` and sort alphabetically -->

```
build             Alias to run »buildassets« and »buildapp«
buildapp          Build PHP structure for desired target stage (move files, fetch dependencies)
buildassets       Build HTML assets (convert, concat, minify…)
composer:command  Execute Composer commands on target stage
composer:install  Install packages with Composer
deploy            Run full deployment stack (build, sync, composer command)
help              Displays help for a command
integrationtest   Run integration tests against target stage
lint              Alias to run »lint:check«
lint:check        Lint files (Check only)
lint:fix          Lint files (Fix)
list              Lists commands
publish           Run full publication stack (lint, unittest, deploy, smoketest, integrationtest)
show              Pretty print configuration for debugging
smoketest         Run a build verification test against target stage
ssh               Alias to run »ssh:connect«
ssh:connect       Open SSH connection to target stage
sync              Synchronize files to target stage
test              Alias to run »integrationtest«
unittest          Run unit tests against local code
view              Open the public URL of target stage in the browser
watch             Sync changed files automatically to local stage
```

## License

GNU General Public License version 2 or later

The GNU General Public License can be found at https://www.gnu.org/copyleft/gpl.html.

## Author

Dan Kleine (<mail@pixelbrackets.de> / [@pixelbrackets](https://pixelbrackets.de))

## Changelog

See [CHANGELOG.md](./CHANGELOG.md)

## Contribution

This script is Open Source, so please use, share, patch, extend or fork it.

[Contributions](./CONTRIBUTING.md) are welcome!

## Feedback

Please send some [feedback](https://pixelbrackets.de/) and share how this
package has proven useful to you or how you may help to improve it.
