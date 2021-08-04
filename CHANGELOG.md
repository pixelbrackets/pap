# Changelog

2021-08-04 Dan Untenzu <mail@pixelbrackets.de>

  * 8.0.1
  * BUGFIX Replace faulty SSH connection command

2020-12-22 Dan Untenzu <mail@pixelbrackets.de>

  * 8.0.0
  * FEATURE Add build task alias `pap build` (runs `buildassets & buildapp`)
  * FEATURE Add lint task alias `pap lint` (runs `lint:check`)
  * FEATURE Lint-Fix task: Add scripts hook
  * FEATURE Composer: Upgrade robo framework
  * FEATURE Add minimal test file
  * FEATURE Change PHP version support → Drop PHP 7.0 & 7.1. Add PHP 7.3 & 7.4.
    * Breaking Change: Use PHP >= 7.2
  * FEATURE Remove view action from deploy command
    * Breaking Change: Remove the option
      `settings.view.open-browser-after-deployment` from your configuration
      files and run the »view« task directly instead
  * FEATURE Replace linters → All linters replaced with a single syntax check
    * Breaking Change: The default linter does a syntax check only now → Add any
      additionaly linters manually and configure the run commands withing the
      `lint.scripts` and `lint.fix.scripts` hooks

2021-08-04 Dan Untenzu <mail@pixelbrackets.de>

  * 7.2.1
  * BUGFIX Replace faulty SSH connection command (Backport)

2020-12-21 Dan Untenzu <mail@pixelbrackets.de>

  * 7.2.0
  * FEATURE Test task: Add scripts hook
  * FEATURE Lint task: Add scripts hook
  * FEATURE Composer: Replace Lurker Package
  * FEATURE Composer: Replace Parallel Lint Package
  * FEATURE Docs: Explain fallbacks
  * FEATURE Docs: List commands available

2020-11-26 Dan Untenzu <mail@pixelbrackets.de>

  * 7.1.0
  * FEATURE Docs: Update contribution guide
  * FEATURE Throw exception & error exit code if sync task fails
  * FEATURE Add publish task `pap publish -s test`
  * FEATURE Add smoke test task `pap smoketest -s test`
  * FEATURE Add ssh connection task `pap ssh:connect -s test`

2020-08-02 Dan Untenzu <mail@pixelbrackets.de>

  * 7.0.0
  * FEATURE Rewrite Docs
  * FEATURE Transform project into library

2020-07-25 Dan Untenzu <mail@pixelbrackets.de>

  * Forked project from »pixelbrackets/deployment« version 6.6.0
