<?php

namespace Pixelbrackets\PhpAppPublication;

/**
 * Robo command configuration for PAP
 *
 * Usage:
 *   ./robo.phar sync --stage local
 *
 * If Robo is installed somewhere else then load the project like this
 *   /some/path/robo.phar --load-from ~/git/repository/build/ sync
 *
 * Note: This is a default Robo config - PAP itself is bundled with Robo
 * and auto-discovers its own config within Git repositories.
 */

use Consolidation\AnnotatedCommand\CommandError;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\Robo;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class RoboFile extends \Robo\Tasks
{
    private $configDirectory = '';

    public function __construct()
    {
        // Calculate path to configuration directory
        $this->configDirectory = $this->findConfigDirectory();

        Robo::loadConfiguration([
            realpath($this->configDirectory . 'build.common.properties.yml'), // Keep for backwards compatibility
            realpath($this->configDirectory . 'build.local.properties.yml'), // Keep for backwards compatibility
            realpath($this->configDirectory . 'pap.yml'),
            realpath($this->configDirectory . 'pap.local.yml')
        ]);

        // Calculate absolute path to Git repository root if not set already
        // Can be overridden via »repository-path« in pap.yml as a fallback
        // for non-standard Git setups (worktrees, bare repos, missing git binary)
        if (true === empty(Robo::config()->get('repository-path'))) {
            $repositoryPath = $this->getGitRootPath();
            if ($repositoryPath !== null) {
                Robo::config()->set('repository-path', $repositoryPath . '/');
            } else {
                Robo::output()->writeln('<error>[ERROR] PAP must be run from within a Git repository </error>');
                exit(1);
            }
        }
    }

    /**
     * Auto-discover configuration file directory
     *
     * Searches for PAP configuration in this order (first match wins):
     * 1. Current working directory
     * 2. Git repository root
     * 3. build/ subdirectory relative to Git root
     *
     * @return string Path to the config directory with trailing slash
     */
    private function findConfigDirectory()
    {
        // Check current working directory
        $cwd = getcwd();
        if (file_exists($cwd . '/pap.yml')) {
            Robo::output()->writeln('<info>Using configuration from ' . $cwd . '/</info>');
            return $cwd . '/';
        }

        // Try to auto-detect the Git repository root and its build/ subdirectory
        $gitRoot = $this->getGitRootPath();
        if ($gitRoot !== null) {
            if (file_exists($gitRoot . '/pap.yml')) {
                Robo::output()->writeln('<info>Using configuration from ' . $gitRoot . '/</info>');
                return $gitRoot . '/';
            }

            if (file_exists($gitRoot . '/build/pap.yml')) {
                Robo::output()->writeln('<info>Using configuration from ' . $gitRoot . '/build/</info>');
                return $gitRoot . '/build/';
            }
        }

        // Fall back to current working directory (e.g. for init command)
        return $cwd . '/';
    }

    /**
     * Get the absolute path to the Git repository root
     *
     * @return string|null Path to Git root, or null if not in a Git repository
     */
    private function getGitRootPath()
    {
        $repositoryPath = exec('git rev-parse --show-toplevel 2>/dev/null', $output, $resultCode);
        if ($resultCode === 0) {
            return $repositoryPath;
        }
        return null;
    }

    /**
     * Wrapper method for Robo Configuration Reader
     *
     * @param string $key
     * @return string | array Configuration value as defined in YML file
     */
    private function getBuildProperty($key = '')
    {
        return Robo::config()->get($key);
    }

    /**
     * Get the default stage name for all tasks from configuration
     *
     * @return string Default stage name
     */
    private function getDefaultStage()
    {
        return $this->getBuildProperty('settings.default-stage') ?: 'local';
    }

    /**
     * Retrieve the current Git branch name
     *
     * @return string Name of the current branch, empty if not found
     */
    protected function getCurrentGitBranch()
    {
        $branchName = exec('git rev-parse --abbrev-ref HEAD', $output, $resultCode);
        if ($resultCode !== 0) {
            $branchName = '';
        }
        return $branchName;
    }

    /**
     * Run a set of command-line executable commands
     *
     * Shows a spinner at normal verbosity, full output at verbose (-v).
     *
     * @param array $scripts List of commands
     * @param string $workingDirectory
     * @throws \Robo\Exception\TaskException
     */
    protected function runScripts($scripts, $workingDirectory = null)
    {
        $workingDirectory = $workingDirectory ?? $this->getBuildProperty('repository-path');

        foreach ($scripts as $script) {
            $process = Process::fromShellCommandline($script, $workingDirectory);
            $process->setTimeout(null);

            if ($this->output()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $process->run(function ($type, $buffer) {
                    $this->output()->write($buffer);
                });
            } else {
                $indicator = new ProgressIndicator($this->output());
                $indicator->start($script);

                $process->start();
                while ($process->isRunning()) {
                    $indicator->advance();
                    usleep(100000);
                }

                $indicator->finish($script);
            }

            if (!$process->isSuccessful()) {
                throw new \Robo\Exception\TaskException($this, 'Script execution failed');
            }
        }
    }

    /**
     * Generate configuration interactively
     */
    public function init()
    {
        $targetDir = getcwd() . '/';

        if (file_exists($targetDir . 'pap.yml')) {
            if (false === $this->io()->confirm('pap.yml already exists. Overwrite?', false)) {
                $this->say('Aborted');
                return;
            }
        }

        $stageName = $this->io()->ask('Stage name', 'live');
        $user = $this->io()->ask('SSH user', 'deployer');
        $host = $this->io()->ask('SSH host', 'example.com');
        $workingDir = $this->io()->ask('Remote working directory', '/var/www/');
        $origin = $this->io()->ask('Public URL of the stage', 'https://www.example.com/');
        $syncInput = $this->io()->ask('Paths to sync (comma-separated)', 'src/, config/, public/, vendor/, composer.json, composer.lock');
        $enableLint = $this->io()->confirm('Enable linting?', true);

        $syncPaths = [];
        $directoryPaths = [];
        foreach (array_map('trim', explode(',', $syncInput)) as $path) {
            $syncPaths[] = ['source' => $path, 'target' => $path];
            if (substr($path, -1) === '/') {
                $directoryPaths[] = $path;
            }
        }

        $config = [
            'settings' => [
                'sync-paths' => $syncPaths,
                'composer' => ['working-directory' => './'],
            ],
            'stages' => [
                $stageName => [
                    'user' => $user,
                    'host' => $host,
                    'origin' => $origin,
                    'working-directory' => $workingDir,
                    'rsync' => ['options' => '-razc'],
                ],
            ],
        ];

        if ($enableLint && !empty($directoryPaths)) {
            $config['settings']['lint'] = ['lint-paths' => $directoryPaths];
        }

        $header = '# PAP configuration file' . PHP_EOL
            . '# See https://github.com/pixelbrackets/pap for documentation' . PHP_EOL . PHP_EOL;
        file_put_contents($targetDir . 'pap.yml', $header . Yaml::dump($config, 5, 2));

        $template = '# Copy this file to pap.local.yml and adapt to your local setup' . PHP_EOL
            . '# pap.local.yml is gitignored and not committed' . PHP_EOL
            . '# When no host is configured, PAP skips file synchronization on this stage' . PHP_EOL . PHP_EOL;
        $localConfig = [
            'settings' => [
                'default-stage' => 'local',
            ],
            'stages' => [
                'local' => [
                    'origin' => 'http://localhost:8000',
                ],
            ],
        ];
        file_put_contents($targetDir . 'pap.local.template.yml', $template . Yaml::dump($localConfig, 5, 2));

        $gitignorePath = $targetDir . '.gitignore';
        $gitignore = file_exists($gitignorePath) ? file_get_contents($gitignorePath) : '';
        if (strpos($gitignore, 'pap.local.yml') === false) {
            file_put_contents($gitignorePath, 'pap.local.yml' . PHP_EOL, FILE_APPEND);
        }
        if (strpos($gitignore, '.pap.lock') === false) {
            file_put_contents($gitignorePath, '.pap.lock' . PHP_EOL, FILE_APPEND);
        }

        $this->io()->success('Created pap.yml and pap.local.template.yml');
        $this->io()->note([
            'Next steps:',
            '1. Review and adjust pap.yml',
            '2. Copy pap.local.template.yml to pap.local.yml for local overrides',
            '3. Run: pap deploy ' . $stageName,
        ]);
    }

    /**
     * Alias to run »lint:check«
     *
     */
    public function lint()
    {
        $this->lintCheck();
    }

    /**
     * Lint files (Check only)
     *
     */
    public function lintCheck()
    {
        $repositoryPath = $this->getBuildProperty('repository-path');
        $lintSettings = $this->getBuildProperty('settings.lint');
        if (false === empty($lintSettings['scripts'])) {
            $this->say('Linting files');
            return $this->runScripts($lintSettings['scripts']);
        }
        if (true === empty($lintSettings['lint-paths'])) {
            $this->say('Lint not configured - Nothing to do');
            return;
        }

        $this->say('Linting files');
        // Run PHPs internal linter
        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->name('*.php')->in(preg_filter('/^/', $repositoryPath, $lintSettings['lint-paths']));
        $lint = $this->taskExecStack()->stopOnFail(true);
        foreach ($finder as $file) {
            $lint->exec('php -l "' . $file->getRealPath() . '" > /dev/null');
        }

        if ($lint->run()->wasSuccessful() !== true) {
            throw new \Robo\Exception\TaskException($this, 'Check failed');
        }
    }

    /**
     * Lint files (Fix)
     *
     */
    public function lintFix()
    {
        $repositoryPath = $this->getBuildProperty('repository-path');
        $lintSettings = $this->getBuildProperty('settings.lint');
        if (false === empty($lintSettings['fix']['scripts'])) {
            $this->say('Fixing lint issues');
            return $this->runScripts($lintSettings['fix']['scripts']);
        }
    }

    /**
     * Alias to run »test:integration«
     *
     */
    public function test($stage = '', array $options = ['stage|s' => null, 'group|g' => null, 'suite' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $this->testIntegration('', $options);
    }

    /**
     * Alias to run »test:unit«
     *
     */
    public function unittest()
    {
        $this->testUnit();
    }

    /**
     * Run unit tests against local code
     *
     * Runs unit tests against local code (not stage-specific),
     * with built-in PHPUnit support
     *
     * @throws \Robo\Exception\TaskException Reports failed tests
     */
    public function testUnit()
    {
        $unittestSettings = $this->getBuildProperty('settings.unit-test');
        if (false === empty($unittestSettings['scripts'])) {
            // use external task runner instead
            $this->say('Running unit tests');
            return $this->runScripts($unittestSettings['scripts']);
        }

        // Run PHPUnit
        $phpunitWorkingDirectory = $this->getBuildProperty('settings.unit-test.phpunit.working-directory');
        if (true === empty($phpunitWorkingDirectory)) {
            $this->say('Unit test framework not configured - Nothing to do');
            return;
        }
        $repositoryPath = $this->getBuildProperty('repository-path');
        $composerPath = $this->getBuildProperty('settings.composer.phar') ?? 'composer';

        // Install PHPUnit in working directory
        $this->taskComposerInstall($composerPath)
            ->ignorePlatformRequirements()
            ->workingDir($repositoryPath . $phpunitWorkingDirectory)
            ->run();

        $phpunit = $this->taskPhpUnit($repositoryPath . $phpunitWorkingDirectory . 'vendor/bin/phpunit')
            ->dir($repositoryPath . $phpunitWorkingDirectory);

        $phpunitConfig = $this->getBuildProperty('settings.unit-test.phpunit.config');
        if (false === empty($phpunitConfig)) {
            $phpunit->configFile($phpunitConfig);
        }

        if ($phpunit->run()->wasSuccessful() !== true) {
            throw new \Robo\Exception\TaskException($this, 'Unit tests failed');
        }
    }

    /**
     * Alias to run »test:integration«
     *
     */
    public function integrationtest($stage = '', array $options = ['stage|s' => null, 'group|g' => null, 'suite' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $this->testIntegration('', $options);
    }

    /**
     * Run integration tests against target stage
     *
     * Runs integration tests against deployed application (stage-specific),
     * with built-in Codeception support
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @return CommandError|void
     * @throws \Robo\Exception\TaskException Reports failed tests
     * @option $stage Target stage (eg. local or live)
     * @option $group Use a specific test group (default: run all tests, with and without groups)
     * @option $suite Use a specific test suite (eg. acceptance)
     */
    public function testIntegration($stage = '', array $options = ['stage|s' => null, 'group|g' => null, 'suite' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        // Support 'integration-test' and 'test' config keys for backwards compatibility
        $testSettings = $this->getBuildProperty('settings.integration-test')
            ?? $this->getBuildProperty('settings.test');
        if (false === empty($testSettings['scripts'])) {
            // use external task runner instead
            $this->say('Running integration tests');
            return $this->runScripts($testSettings['scripts']);
        }

        // Run Codeception
        // Support 'integration-test' and 'test' config keys for backwards compatibility
        $codeceptionDirectory = $this->getBuildProperty('settings.integration-test.codeception.working-directory')
            ?? $this->getBuildProperty('settings.test.codeception.working-directory');
        if (true === empty($codeceptionDirectory)) {
            $this->say('Test framework not configured - Nothing to do');
            return;
        }
        $repositoryPath = $this->getBuildProperty('repository-path');
        $composerPath = $this->getBuildProperty('settings.composer.phar') ?? 'composer';

        $stageOrigin = $this->getBuildProperty('stages.' . $options['stage'] . '.origin');
        if (true === empty($stageOrigin)) {
            $this->io()->error('Stage origin not configured');
            $this->say('Hint: Set »stages.' . $options['stage'] . '.origin« in pap.yml');
            return new CommandError();
        }

        // Install Codeception in working directory
        $this->taskComposerInstall($composerPath)
            ->ignorePlatformRequirements()
            ->workingDir($repositoryPath . $codeceptionDirectory)
            ->run();

        // Pass stage origin to codeception - modify superglobal ENV
        // since putenv() wont catch on with the codeception configuration loader
        $_ENV['BASEURL'] = $stageOrigin . '/';
        $codeception = $this->taskCodecept($repositoryPath . $codeceptionDirectory . 'vendor/bin/codecept')
            ->dir($repositoryPath . $codeceptionDirectory)
            ->suite($options['suite'] ?? ($this->getBuildProperty('settings.integration-test.codeception.suite')
                ?? $this->getBuildProperty('settings.test.codeception.suite')));

        if (false === empty($options['group'])) {
            $codeception->group($options['group']);
        }

        $denyTestGroups = $this->getBuildProperty('stages.' . $options['stage'] . '.test.deny-groups');
        if (false === empty($denyTestGroups)) {
            $this->io()->note(array_merge(['Excluding test groups'], $denyTestGroups));
            foreach ((array)$denyTestGroups as $denyTestGroup) {
                $codeception->excludeGroup($denyTestGroup);
            }
        }

        if ($codeception->run()->wasSuccessful() !== true) {
            throw new \Robo\Exception\TaskException($this, 'Test failed');
        }
    }

    /**
     * Build HTML assets (convert, concat, minify…)
     *
     * Switches to external task runner if configured
     */
    public function buildassets()
    {
        $repositoryPath = $this->getBuildProperty('repository-path');
        $assetSettings = $this->getBuildProperty('settings.assets');
        if (false === empty($assetSettings['scripts'])) {
            // use external task runner instead
            $this->say('Building assets');
            return $this->runScripts($assetSettings['scripts']);
        }
        if (empty($assetSettings)) {
            $this->say('Assets not configured - Nothing to do');
            return;
        }

        $this->say('Building assets');

        if (false === empty($assetSettings['mirror'])) {
            $this->taskMirrorDir([
                $repositoryPath . $assetSettings['mirror']['source'] => $repositoryPath . $assetSettings['mirror']['target']
            ])->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)->run();
        }

        if (false === empty($assetSettings['minify-css'])) {
            foreach ($assetSettings['minify-css'] as $minifyPaths) {
                $this->taskMinify($repositoryPath . $minifyPaths['source'])
                    ->to($repositoryPath . $minifyPaths['target'])
                    ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
                    ->run();
            }
        }

        if (false === empty($assetSettings['minify-js'])) {
            foreach ($assetSettings['minify-js'] as $minifyPaths) {
                $this->taskMinify($repositoryPath . $minifyPaths['source'])
                    ->to($repositoryPath . $minifyPaths['target'])
                    ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
                    ->run();
            }
        }

        if (false === empty($assetSettings['concat'])) {
            foreach ($assetSettings['concat'] as $concatPaths) {
                // prefix path to each source item
                $this->taskConcat(preg_filter('/^/', $repositoryPath, $concatPaths['sources']))
                    ->to($repositoryPath . $concatPaths['target'])
                    ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
                    ->run();
            }
        }

        if (false === empty($assetSettings['minify-img'])) {
            foreach ($assetSettings['minify-img'] as $minifyPaths) {
                $this->taskImageMinify($repositoryPath . $minifyPaths['source'])
                    ->to($repositoryPath . $minifyPaths['target'])
                    ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
                    ->run();
            }
        }
    }

    /**
     * Alias to run »buildassets« and »buildapp«
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function build($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $this->buildassets();
        $this->buildapp('', $options);
    }

    /**
     * Build PHP structure for desired target stage (move files,
     * fetch dependencies)
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @throws \Robo\Exception\TaskException
     */
    public function buildapp($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $this->prepareSyncPaths();
        $this->composerInstall('', ['stage' => $options['stage'], 'remote' => false]);
    }

    /**
     * Alias to run »composer:command«
     *
     * @hidden
     * @param array $options
     * @option $stage Target stage (eg. local or live), leave empty to run in repository working directory
     * @option $command Name of the Command to execute (eg. dump-autoload)
     * @throws \Robo\Exception\TaskException Reports failed commands
     */
    public function composer($stage = '', $cmd = '', array $options = ['stage|s' => null, 'command|c' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $options['command'] = $cmd ?: $options['command'];
        $this->composerCommand('', '', $options);
    }

    /**
     * Install packages with Composer
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @option $remote Execute composer locally for a stage or remote on a stage (eg. true)
     * @throws \Robo\Exception\TaskException Reports failed installs
     */
    public function composerInstall($stage = '', array $options = ['stage|s' => null, 'remote' => true])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $composerSettings = $this->getBuildProperty('settings.composer');
        if (true === empty($composerSettings)) {
            $this->say('Composer not configured - Nothing to do');
            return;
        }
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->warning('Stage not configured - Skip');
            $this->say('Hint: Run »pap show stages« to view available stages');
            return;
        }

        // Skip remote composer install when no host is configured
        if (true === (bool)$options['remote'] && true === empty($stageProperties['host'])) {
            return;
        }

        if ((bool)$options['remote'] !== true) {
            // run composer in locally in repository
            $composerPath = $composerSettings['phar'] ?? 'composer';
            $composerWorkingDirectory = $this->getBuildProperty('repository-path') . $composerSettings['working-directory'];
        } else {
            $composerPath = $this->getBuildProperty('stages.' . $options['stage'] . '.composer.phar') ?? 'composer';
            $composerWorkingDirectory = $stageProperties['working-directory'];
        }

        $this->say('Installing packages');
        $composer = $this->taskComposerInstall($composerPath);
        $composer->workingDir($composerWorkingDirectory);
        $composer->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE);
        if ($options['stage'] !== 'local') {
            $composer->noDev();
        }

        if ((bool)$options['remote'] !== true || $options['stage'] === 'local') {
            try {
                $this->runScripts([$composer->getCommand()]);
            } catch (\Robo\Exception\TaskException $e) {
                throw new \Robo\Exception\TaskException($this, 'Composer install failed');
            }
        } else {
            $remote = $this->taskSshExec($stageProperties['host'], $stageProperties['user'])
                ->port((int)($stageProperties['port']?? 22))
                ->remoteDir($stageProperties['working-directory'])
                ->exec($composer);

            if ($remote->run()->wasSuccessful() !== true) {
                throw new \Robo\Exception\TaskException($this, 'Composer install failed');
            }
        }
    }

    /**
     * Execute Composer command in working directory on target stage
     *
     * @param string $stage Target stage (eg. local or live), leave empty to run in repository working directory
     * @param string $cmd Name of the Command to execute (eg. dump-autoload)
     * @param array $options
     * @option $stage Target stage (eg. local or live), leave empty to run in repository working directory
     * @option $command Name of the Command to execute (eg. dump-autoload)
     * @return CommandError|void
     * @throws \Robo\Exception\TaskException Reports failed commands
     */
    public function composerCommand($stage = '', $cmd = '', array $options = ['stage|s' => null, 'command|c' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $options['command'] = $cmd ?: $options['command'];
        if (true === empty($this->getBuildProperty('settings.composer'))) {
            $this->say('Composer not configured - Nothing to do');
            return;
        }

        // Missing stage = Use Composer Working Directory in Repository
        if ($options['stage'] === null) {
            $this->taskExec('composer')
                ->rawArg($options['command'])
                ->dir($this->getBuildProperty('repository-path') . $this->getBuildProperty('settings.composer.working-directory'))
                ->run();
            return;
        }

        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            $this->say('Hint: Run »pap show stages« to view available stages');
            return new CommandError();
        }

        $composerPath = $this->getBuildProperty('stages.' . $options['stage'] . '.composer.phar') ?? 'composer';
        $composer = $this->taskExec($composerPath)
            ->rawArg($options['command'])
            ->dir($stageProperties['working-directory']);
        if ($options['stage'] === 'local') {
            if ($composer->run()->wasSuccessful() !== true) {
                throw new \Robo\Exception\TaskException($this, 'Composer command failed');
            }
        } else {
            $remote = $this->taskSshExec($stageProperties['host'], $stageProperties['user'])
                ->port((int)($stageProperties['port']?? 22))
                ->remoteDir($stageProperties['working-directory'])
                ->exec($composer);

            if ($remote->run()->wasSuccessful() !== true) {
                throw new \Robo\Exception\TaskException($this, 'Composer command failed');
            }
        }
    }

    /**
     * Prepare local Git repository before syncing to remote stage
     *
     * Internal method which rearranges files within the Git repository
     * (e.g. move built assets to web folder).
     * This is an optional pre-sync step configured via settings.prepare-sync-paths.
     */
    protected function prepareSyncPaths()
    {
        $syncPaths = $this->getBuildProperty('settings.prepare-sync-paths');
        if (true === empty($syncPaths)) {
            return;
        }

        $this->say('Preparing sync paths');
        foreach ((array)$syncPaths as $syncPath) {
            $this->taskRsync()
                ->recursive()
                ->archive()
                ->exclude($syncPath['exclude'] ?? [])
                ->fromPath($this->getBuildProperty('repository-path') . $syncPath['source'])
                ->toPath($this->getBuildProperty('repository-path') . $syncPath['target'])
                ->delete()
                ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
                ->run();
        }
    }

    /**
     * Synchronize files from local Git repository to remote stage
     *
     * Internal task which performs the actual rsync operation
     * to transfer files to the remote stage.
     *
     * Note: deploy() calls this method directly (bypassing the lock check
     * existing in the sync() method),
     * because deploy creates the lock file itself after sync completes.
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @throws \Robo\Exception\TaskException
     */
    protected function syncStage(array $options = ['stage|s' => null])
    {
        $options['stage'] = $options['stage'] ?: $this->getDefaultStage();
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->warning('Stage not configured - Skip');
            $this->say('Hint: Run »pap show stages« to view available stages');
            return;
        }

        if (true === empty($stageProperties['host'])) {
            $this->say('Skipping synchronisation - No host configured for stage »' . $options['stage'] . '«');
            return;
        }

        $this->say('Syncing files to stage ' . $options['stage']);
        $syncPort = (false === empty($stageProperties['port']))? '-e "ssh -p ' . (int)$stageProperties['port'] . '"' : '';
        $syncOptions = $stageProperties['rsync']['options']?? '';
        $syncPaths = $this->getBuildProperty('settings.sync-paths');
        foreach ((array)$syncPaths as $syncPath) {
            $sync = $this->taskRsync()
                ->rawArg($syncPort . ' ' . $syncOptions)
                ->exclude($syncPath['exclude'] ?? [])
                ->fromPath($this->getBuildProperty('repository-path') . $syncPath['source'])
                ->toUser($stageProperties['user'])
                ->toHost($stageProperties['host'])
                ->toPath($stageProperties['working-directory'] . $syncPath['target'])
                ->delete()
                ->verbose();

            if ($sync->run()->wasSuccessful() !== true) {
                throw new \Robo\Exception\TaskException($this, 'Synchronization failed');
            }
        }
    }

    /**
     * Check if sync task can be executed safely (eg. last build was executed on
     * the same branch), otherwise the deploy task should be used instead
     *
     * @param string Current target stage
     * @return boolean Returns true if the sync task may be executed
     */
    protected function syncIsAllowed(string $stage)
    {
        if (false === is_file($this->configDirectory . '.pap.lock')) {
            $this->io()->note('»lock« file not present');
            return true;
        }

        $lock = file_get_contents($this->configDirectory . '.pap.lock');
        if (false === $lock) {
            $this->io()->note('»lock« file not readable');
            return true;
        }
        $lock = str_getcsv($lock);

        if ((string)$lock[0] !== $stage) {
            $this->io()->warning('The last stage used for deployment differs');
            if (false === $this->io()->confirm('Continue anyway?', false)) {
                return false;
            }
        }

        if ((string)$lock[1] !== $this->getCurrentGitBranch()) {
            $this->io()->warning('The last branch used for deployment differs');
            return false;
        }

        // Last deployment > configured timeout (default 3 days)
        $lockTimeout = (int)($this->getBuildProperty('sync-lock-timeout') ?: 259200);
        if (((int)$lock[2] + $lockTimeout) < time()) {
            $this->io()->warning('The last deployment is too long ago');
            return false;
        }

        return true;
    }

    /**
     * Synchronize files to target stage
     *
     * Command for quick file synchronization without rebuilding assets.
     *
     * Includes safety checks via .pap.lock file to ensure sync is safe to run
     * (checks if last deploy was on the same branch and not too long ago).
     * Use »deploy« task for full rebuild and initial deployment.
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @return CommandError|void
     * @throws \Robo\Exception\TaskException
     */
    public function sync($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            $this->say('Hint: Run »pap show stages« to view available stages');
            return new CommandError();
        }

        if (false === $this->syncIsAllowed($options['stage'])) {
            $this->io()->error('Sync currently not allowed, please run deploy task instead');
            return new CommandError();
        }

        $this->prepareSyncPaths();
        $this->syncStage(['stage' => $options['stage']]);
    }

    /**
     * Check if deploy task can be executed safely
     *
     * Checks if current branch is allowed on target stage to prevent accidental
     * deployments to live from feature branches.
     *
     * @param string $stage Current target stage
     * @return boolean Returns true if the deploy task may be executed
     */
    protected function deployIsAllowed(string $stage)
    {
        $lockBranches = $this->getBuildProperty('stages.' . $stage . '.lock-branches');

        if ((false === empty($lockBranches)) && (false === in_array($this->getCurrentGitBranch(), $lockBranches, true))) {
            $this->io()->warning('The current branch is not allowed for the target stage');
            if (false === $this->io()->confirm('Continue anyway?', false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create lock file
     *
     * Lock stage & branch
     *
     * @param string $stage Current target stage
     */
    protected function setLockFile(string $stage)
    {
        $lock = $stage . ',' . $this->getCurrentGitBranch() . ',' . time();
        file_put_contents($this->configDirectory . '.pap.lock', $lock);
    }

    /**
     * Run full deployment stack (build, sync, composer command)
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @return CommandError|void
     * @throws \Robo\Exception\TaskException
     */
    public function deploy($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        if (false === $this->deployIsAllowed($options['stage'])) {
            $this->io()->error('Deployment is not allowed');
            return new CommandError();
        }

        $this->build('', ['stage' => $options['stage']]);

        $this->syncStage(['stage' => $options['stage']]);

        // run composer install on stage as well to update tables etc.
        $this->composerInstall('', ['stage' => $options['stage'], 'remote' => true]);

        $this->setLockFile($options['stage']);
    }

    /**
     * Run full publication stack (lint, test:unit, deploy, test:smoke, test:integration)
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @throws \Robo\Exception\TaskException
     */
    public function publish($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $this->lint();
        $this->testUnit();
        $this->deploy('', ['stage' => $options['stage']]);
        $this->testSmoke('', ['stage' => $options['stage']]);
        $this->testIntegration('', ['stage' => $options['stage']]);
    }

    /**
     * Alias to run »test:smoke«
     *
     */
    public function smoketest($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $this->testSmoke('', $options);
    }

    /**
     * Create HTTP client for smoke tests
     *
     * @return \GuzzleHttp\Client
     */
    protected function createHttpClient()
    {
        return new \GuzzleHttp\Client();
    }

    /**
     * Run a build verification test against target stage
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @return CommandError|void
     * @throws \Robo\Exception\TaskException
     */
    public function testSmoke($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $stageOrigin = $this->getBuildProperty('stages.' . $options['stage'] . '.origin');
        if (true === empty($stageOrigin)) {
            $this->io()->error('Stage origin not configured');
            $this->say('Hint: Set »stages.' . $options['stage'] . '.origin« in pap.yml');
            return new CommandError();
        }

        try {
            $ping = $this->createHttpClient()->get($stageOrigin);
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            throw new \Robo\Exception\TaskException($this, 'Smoke test failed');
        }

        $this->say('Smoke test successful for ' . $stageOrigin);
    }

    /**
     * Open the public URL of target stage in the browser
     *
     * The URL is set up in »stages.<stagename>.origin«
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @return CommandError|void
     */
    public function view($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            $this->say('Hint: Run »pap show stages« to view available stages');
            return new CommandError();
        }

        $url = $stageProperties['origin'] ?? '';
        if (true === empty($url)) {
            $this->io()->error('No origin configured');
            return new CommandError();
        }

        $this->taskOpenBrowser($url)->run();
    }

    /**
     * Alias to run »ssh:connect«
     *
     */
    public function ssh($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        return $this->sshConnect('', $options);
    }

    /**
     * Open SSH connection to target stage
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @return CommandError|void
     * @option $stage Target stage (eg. local or live)
     */
    public function sshConnect($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            $this->say('Hint: Run »pap show stages« to view available stages');
            return new CommandError();
        }

        $sshConnection = $stageProperties['user'] . '@' . $stageProperties['host'];
        $sshPort = (false === empty($stageProperties['port']))? ' -p' . (int)$stageProperties['port'] : '';
        passthru('ssh -t ' . $sshConnection . $sshPort . ' \'cd ' . $stageProperties['working-directory'] . ' && exec bash -l\'');
    }

    /**
     * Execute command in working directory on target stage via SSH
     *
     * Runs a single command on the remote stage without opening an interactive shell.
     * The command is executed in the stage's working directory.
     *
     * @param string $stage Target stage (eg. local or live)
     * @param string $cmd Command to execute on remote stage
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @option $command Command to execute on remote stage
     * @return CommandError|void
     */
    public function sshExec($stage = '', $cmd = '', array $options = ['stage|s' => null, 'command|c' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();
        $options['command'] = $cmd ?: $options['command'];
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            $this->say('Hint: Run »pap show stages« to view available stages');
            return new CommandError();
        }

        if (true === empty($options['command'])) {
            $this->io()->error('No command specified. Use ssh:exec <stage> "your command here"');
            return new CommandError();
        }

        $sshConnection = $stageProperties['user'] . '@' . $stageProperties['host'];
        $sshPort = (false === empty($stageProperties['port']))? ' -p' . (int)$stageProperties['port'] : '';
        $escapedCommand = escapeshellarg($options['command']);

        $this->io()->note('Executing on ' . $options['stage'] . ': ' . $options['command']);
        passthru('ssh -t ' . $sshConnection . $sshPort . ' \'cd ' . $stageProperties['working-directory'] . ' && ' . $escapedCommand . '\'');
    }

    /**
     * Pretty print configuration for debugging
     *
     * @param string $scope What part of the configuration to print (all | stages)
     */
    public function show($scope = 'all')
    {
        $configuration = [
            'repository-path' => $this->getBuildProperty('repository-path'),
            'settings' => $this->getBuildProperty('settings'),
            'stages' => $this->getBuildProperty('stages')
        ];
        if ($scope === 'stages') {
            $stages = [];
            foreach ((array)$configuration['stages'] as $stagename => $stage) {
                $stages[$stagename]['stage'] = $stagename;
                $stages[$stagename]['user'] = $stage['user'] ?? '';
                $stages[$stagename]['host'] = $stage['host'] ?? '';
                $stages[$stagename]['port'] = $stage['port'] ?? '';
                $stages[$stagename]['working-directory'] = $stage['working-directory'] ?? '';
                $stages[$stagename]['origin'] = $stage['origin'] ?? '';
            }
            $this->io()->table(
                ['Stage', 'User', 'Host', 'Port', 'Working Directory', 'Origin'],
                $stages
            );

            $this->say('Default stage: ' . $this->getDefaultStage());
            $this->io()->newLine();
            $this->say('Hint: Use command »ssh <stage>« to SSH connect to one of the above stages right away, use »view <stage>« to open the public URL of target stage in the browser.');

            return;
        }

        $this->io()->write(Yaml::dump($configuration, 5, 2));
    }

    /**
     * Sync changed files automatically to target stage
     *
     * @param string $stage Target stage (eg. local or live)
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function watch($stage = '', array $options = ['stage|s' => null])
    {
        $options['stage'] = $stage ?: $options['stage'] ?: $this->getDefaultStage();

        $this->io()->note('Watching for changes and syncing to stage: ' . $options['stage']);

        $this->taskWatch()
            ->monitor(
                $this->getBuildProperty('repository-path') . $this->getBuildProperty('settings.watch.working-directory'),
                function () use ($options) {
                    $this->sync('', $options);
                }
            )
            ->run();
    }
}
