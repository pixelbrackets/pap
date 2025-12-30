# PAP

![Logo](./docs/icon.png)

**P**HP **A**pp **P**ublication

[![Version](https://img.shields.io/packagist/v/pixelbrackets/pap.svg?style=flat-square)](https://packagist.org/packages/pixelbrackets/pap/)
[![Build Status](https://img.shields.io/gitlab/pipeline/pixelbrackets/pap?style=flat-square)](https://gitlab.com/pixelbrackets/pap/pipelines)
[![Made With](https://img.shields.io/badge/made_with-php-blue?style=flat-square)](https://gitlab.com/pixelbrackets/pap#requirements)
[![License](https://img.shields.io/badge/license-gpl--2.0--or--later-blue.svg?style=flat-square)](https://spdx.org/licenses/GPL-2.0-or-later.html)
[![Contribution](https://img.shields.io/badge/contributions_welcome-%F0%9F%94%B0-brightgreen.svg?labelColor=brightgreen&style=flat-square)](https://gitlab.com/pixelbrackets/pap/-/blob/master/CONTRIBUTING.md)

Toolchain to publish a PHP App. Configured with a YAML file only.

🚀

- Build Assets - Minify & concat CSS, JavaScript, SVG assets
- Build App - Prepare expected directory structures & fetch packages
- Lint - Identify errors before the app is running
- Deploy - Sync files to configurable target stages
- Verify - Do a smoke test to verify that the app is still working
- Test - Start integration tests

🔧

- All general settings and shared stages are configured in a YAML file

🎯

- KISS - Not made for every condition, but easy to use and integrate

![Screenshot](./docs/screenshot.png)

## Vision

- One CLI script with a fixed set of task commands
  - No mix, extending or renaming of task commands
  - Tasks not configured will abort instead of failing
- Configuration with a flat text file
- Override settings for local machines
- Installation reduced to a bare minimum
- Portable, easy to integrate in many repositories
- Usable by a person who never deployed the app before
  - No additional knowledge required
  - One command is enough to deploy the app to a stage
- Always the same commands, don't care about the configuration set up
- Works well with robots (CI)
- Minimal requirements on target stage
- Rsync to synchronize files - no FTP
- SSH to connect to stages
- No rollback - Use Git to revert changes
- No provisioning
- Support for monorepos
- Deploy to many stages

General approach: Not made for every condition, but easy to use and integrate

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
```

Now edit `build/pap.yml` to configure your deployment stages. See [Configuration](#configuration).

### Existing Projects

If your project already has a `build/` directory with PAP configuration files,
then all you need to do is fetching the PAP executable using Composer,
no additional setup required.

```bash
cd build
composer install
# PAP is now available as ./vendor/bin/pap
```

The installer detects your platform and downloads the appropriate executable automatically:
- Linux: Self-contained binary (PHP binary bundled, no version conflicts)
- Other platforms: Universal PHAR (requires system PHP 7.2+)

*🧑‍🔧 Opt-out from automatic installation* to install PAP manually instead:
```bash
cd build
export PAP_NO_BINARY=1
composer install
# Now install PAP manually (see below)
```

### Global Installation (Advanced)

Install PAP once globally to use across multiple projects.

```bash
# Linux binary (PHP-independent, recommended for convenience)
wget https://raw.githubusercontent.com/pixelbrackets/pap-dist/main/pap-linux-x64
sudo mv pap-linux-x64 /usr/local/bin/pap
sudo chmod +x /usr/local/bin/pap

# Universal PHAR (requires PHP 7.2+, recommended for security-critical environments)
wget https://raw.githubusercontent.com/pixelbrackets/pap-dist/main/pap.phar
sudo mv pap.phar /usr/local/bin/pap
sudo chmod +x /usr/local/bin/pap
```

**Security Note:** The binary bundles a specific PHP version and may lag behind PHP security updates.
For security-critical deployments, use the PHAR to maintain control over PHP updates via your system's package manager.

Distribution repository with all available executables: https://github.com/pixelbrackets/pap-dist

### CI/CD Usage

Example GitLab CI configuration:

```yaml
deploy:
  stage: deploy
  image: composer:latest
  script:
    - cd build && composer install  # Installs PAP automatically
    - vendor/bin/pap deploy --stage live # Deploy app to live stage using the versioned configuration files
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

**Documentation:**
- 📝 [All available configuration options](./docs/configuration.md)
- 📖 [Step-by-step walkthrough tutorial](./docs/walktrough.md)
- 🛠️ [Manual setup without skeleton package](./docs/configuration.md#manual-setup) (advanced)

## Updates

See [Upgrade Guide](./docs/upgrade-guide.md)

## Usage

This section gives a brief overview of available commands and common tasks.

Run `./vendor/bin/pap` to see all available tasks. Some common tasks are:

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
├── deploy (Full deployment)
│   ├── build (Prepare application)
│   │   ├── buildassets (Process CSS/JS/images)
│   │   └── buildapp (Prepare directory structure and install composer dependencies locally)
│   ├── sync (Transfer files via rsync)
│   └── composer:install (Install remaining dependencies and trigger post-install commands on target stage)
├── smoketest (Quick HTTP check)
└── test (Run integration tests via Codeception)

Common standalone tasks:
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
lint              Alias to run »lint:check«
lint:check        Lint files (Check only)
lint:fix          Lint files (Fix)
list              Lists commands
publish           Run full publication stack (lint, deploy, smoketest, test)
show              Pretty print configuration for debugging
smoketest         Run a build verification test against target stage
ssh               Alias to run »ssh:connect«
ssh:connect       Open SSH connection to target stage
sync              Synchronize files to target stage
test              Run tests suite against target stage
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
