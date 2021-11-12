<?php

namespace Pixelbrackets\PhpAppPublication;

/**
 * Robo command configuration
 *
 * Usage:
 *   ./robo.phar sync --stage local
 *
 * If Robo is installed somewhere else then load the project like this
 *   /some/path/robo.phar --load-from ~/git/repository/build/ sync
 *
 */
use Robo\Robo;

class RoboFile extends \Robo\Tasks
{
    public function __construct()
    {
        Robo::loadConfiguration(['build.common.properties.yml','build.local.properties.yml']);

        // Calculate absolute path to repository if not set already
        if (true === empty(Robo::config()->get('repository-path'))) {
            $repositoryPath = exec('git rev-parse --show-toplevel', $output, $resultCode);
            if ($resultCode === 0) {
                Robo::config()->set('repository-path', $repositoryPath . '/');
            } else {
                throw new \Robo\Exception\TaskException($this, 'Missing repository path');
            }
        }
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
     * @param array $scripts List of commands
     * @param string $workingDirectory
     * @throws \Robo\Exception\TaskException
     */
    protected function runScripts($scripts, $workingDirectory = null)
    {
        $workingDirectory = $workingDirectory ?? $this->getBuildProperty('repository-path');

        $commandRunner = $this->taskExecStack()
            ->dir($workingDirectory);
        foreach ($scripts as $script) {
            $commandRunner->exec($script);
        }

        if ($commandRunner->run()->wasSuccessful() !== true) {
            throw new \Robo\Exception\TaskException($this, 'Script execution failed');
        }
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
            return $this->runScripts($lintSettings['scripts']);
        }
        if (true === empty($lintSettings['lint-paths'])) {
            $this->say('Lint not configured');
            return;
        }

        $lint = $this->taskExecStack()
            ->dir('./vendor/bin/');
        foreach ($lintSettings['lint-paths'] as $lintPath) {
            $lint->exec('./phplint.sh ' . $repositoryPath . $lintPath);
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
            return $this->runScripts($lintSettings['fix']['scripts']);
        }
    }

    /**
     * Run tests suite against target stage
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @option $group Use a specific test group (default: run all tests, with and without groups)
     * @option $suite Use a specific test suite (eg. acceptance)
     * @throws \Robo\Exception\TaskException Reports failed tests
     */
    public function test(array $options = ['stage|s' => 'local', 'group|g' => null, 'suite' => null])
    {
        $testSettings = $this->getBuildProperty('settings.test');
        if (false === empty($testSettings['scripts'])) {
            // use external task runner instead
            return $this->runScripts($testSettings['scripts']);
        }
        $codeceptionDirectory = $this->getBuildProperty('settings.test.codeception.working-directory');
        if (true === empty($codeceptionDirectory)) {
            $this->say('Test framework not configured');
            return;
        }
        $repositoryPath = $this->getBuildProperty('repository-path');
        $composerPath = $this->getBuildProperty('settings.composer.phar') ?? 'composer';

        $stageOrigin = $this->getBuildProperty('stages.' . $options['stage'] . '.origin');
        if (true === empty($stageOrigin)) {
            $this->io()->error('Stage origin not configured');
            return;
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
            ->suite($options['suite'] ?? $this->getBuildProperty('settings.test.codeception.suite'));

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
        if (false === empty($assetSettings['grunt'])) {
            // use external task runner Grunt instead
            // Deprecated - Register scripts instead
            return $this->buildassetsGrunt();
        }
        if (false === empty($assetSettings['scripts'])) {
            // use external task runner instead
            return $this->runScripts($assetSettings['scripts']);
        }
        if (empty($assetSettings)) {
            $this->say('Assets not configured - Nothing to do');
            return;
        }

        if (false === empty($assetSettings['mirror'])) {
            $this->taskMirrorDir([
                $repositoryPath . $assetSettings['mirror']['source'] => $repositoryPath . $assetSettings['mirror']['target']
            ])->run();
        }

        if (false === empty($assetSettings['minify-css'])) {
            foreach ($assetSettings['minify-css'] as $minifyPaths) {
                $this->taskMinify($repositoryPath . $minifyPaths['source'])
                    ->to($repositoryPath . $minifyPaths['target'])
                    ->run();
            }
        }

        if (false === empty($assetSettings['minify-js'])) {
            foreach ($assetSettings['minify-js'] as $minifyPaths) {
                $this->taskMinify($repositoryPath . $minifyPaths['source'])
                    ->to($repositoryPath . $minifyPaths['target'])
                    ->run();
            }
        }

        if (false === empty($assetSettings['concat'])) {
            foreach ($assetSettings['concat'] as $concatPaths) {
                // prefix path to each source item
                $this->taskConcat(preg_filter('/^/', $repositoryPath, $concatPaths['sources']))
                    ->to($repositoryPath . $concatPaths['target'])
                    ->run();
            }
        }

        if (false === empty($assetSettings['minify-img'])) {
            foreach ($assetSettings['minify-img'] as $minifyPaths) {
                $this->taskImageMinify($repositoryPath . $minifyPaths['source'])
                    ->to($repositoryPath . $minifyPaths['target'])
                    ->run();
            }
        }
    }

    /**
     * Build assets using external task runner Grunt
     *
     * Uses NPM to install dependencies and executes Grunt as task runner
     *
     * @deprecated Will be removed in next version, register »scripts« instead
     */
    protected function buildassetsGrunt()
    {
        $gruntDirectory = $this->getBuildProperty('settings.assets.grunt.working-directory');
        $gruntTask = $this->getBuildProperty('settings.assets.grunt.task');
        if (empty($gruntDirectory) && empty($gruntTask)) {
            $this->io()->error('Grunt not configured');
            return;
        }
        $gruntDirectory = $this->getBuildProperty('repository-path') . $gruntDirectory;

        $buildassets = $this->taskExecStack()->dir($gruntDirectory);

        // Fetch dependencies using NPM
        $npmInstall = (true === file_exists($gruntDirectory . 'package-lock.json')) ? 'ci' : 'install';
        $buildassets->exec('npm --silent --progress=false --prefer-offline ' . $npmInstall);

        // Manage additional build scripts (sass etc.)
        // Deprecated - Everything should be a NPM package by now!
        if (true === file_exists($gruntDirectory . 'gems.rb')) {
            $buildassets->exec('bundle install --quiet');
        }

        // Execute Grunt as task runner
        $buildassets->exec('grunt -q ' . $gruntTask);

        if ($buildassets->run()->wasSuccessful() !== true) {
            throw new \Robo\Exception\TaskException($this, 'Building Assets failed');
        }
    }

    /**
     * Alias to run »buildassets« and »buildapp«
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function build(array $options = ['stage|s' => 'local'])
    {
        $this->buildassets();
        $this->buildapp(['stage' => $options['stage']]);
    }

    /**
     * Build PHP structure for desired target stage (move files,
     * fetch dependencies)
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function buildapp(array $options = ['stage|s' => 'local'])
    {
        $this->prepareSyncPaths();
        $this->composerInstall(['stage' => $options['stage'], 'remote' => false]);
    }

    /**
     * Install packages with Composer
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @option $remote Execute composer locally for a stage or remote on a stage (eg. true)
     * @throws \Robo\Exception\TaskException Reports failed installs
     */
    public function composerInstall(array $options = ['stage|s' => 'local', 'remote' => true])
    {
        $composerSettings = $this->getBuildProperty('settings.composer');
        if (true === empty($composerSettings)) {
            $this->say('Composer not configured');
            return;
        }
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
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

        $composer = $this->taskComposerInstall($composerPath);
        $composer->workingDir($composerWorkingDirectory);
        if ($options['stage'] !== 'local') {
            $composer->noDev();
        }

        if ((bool)$options['remote'] !== true || $options['stage'] === 'local') {
            if ($composer->run()->wasSuccessful() !== true) {
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
     * Execute Composer commands on target stage
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live), leave empty to run in repository working directory
     * @option $command Name of the Command to execute (eg. dump-autoload)
     * @throws \Robo\Exception\TaskException Reports failed commands
     */
    public function composerCommand(array $options = ['stage|s' => null, 'command|c' => null])
    {
        if (true === empty($this->getBuildProperty('settings.composer'))) {
            $this->say('Composer not configured');
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
            return;
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
     * Move files in repository to prepare a working sync task
     *
     */
    protected function prepareSyncPaths()
    {
        $syncPaths = $this->getBuildProperty('settings.prepare-sync-paths');
        if (true === empty($syncPaths)) {
            return;
        }

        foreach ((array)$syncPaths as $syncPath) {
            $this->taskRsync()
                ->recursive()
                ->archive()
                ->exclude($syncPath['exclude'] ?? [])
                ->fromPath($this->getBuildProperty('repository-path') . $syncPath['source'])
                ->toPath($this->getBuildProperty('repository-path') . $syncPath['target'])
                ->delete()
                ->run();
        }
    }

    /**
     * Rsync files between repository and stage folder
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @throws \Robo\Exception\TaskException
     */
    protected function syncStage(array $options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }

        $syncPaths = $this->getBuildProperty('settings.sync-paths');
        foreach ((array)$syncPaths as $syncPath) {
            $sync = $this->taskRsync()
                ->rawArg($stageProperties['rsync']['options'])
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
        if (false === is_file('.lock')) {
            $this->io()->note('»lock« file not present');
            return true;
        }

        $lock = file_get_contents('.lock');
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

        // last deployment > 3 days
        if (((int)$lock[2] + 259200) < time()) {
            $this->io()->warning('The last deployment is too long ago');
            return false;
        }

        return true;
    }

    /**
     * Synchronize files to target stage
     *
     * File sync only! Needs »buildapp« or »deploy« task to rebuild the app
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @return void
     */
    public function sync(array $options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }

        if (false === $this->syncIsAllowed($options['stage'])) {
            $this->io()->error('Sync currently not allowed, please run deploy task instead');
            return;
        }

        $this->prepareSyncPaths();
        $this->syncStage(['stage' => $options['stage']]);
    }

    /**
     * Check if deploy task can be executed safely (eg. current branch is
     * allowed on target stage)
     *
     * @param string Current target stage
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
     * @param string Current target stage
     */
    protected function setLockFile(string $stage)
    {
        $lock = $stage . ',' . $this->getCurrentGitBranch() . ',' . time();
        file_put_contents('.lock', $lock);
    }

    /**
     * Run full deployment stack (build, sync, composer command)
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function deploy(array $options = ['stage|s' => 'local'])
    {
        if (false === $this->deployIsAllowed($options['stage'])) {
            $this->io()->error('Deployment is not allowed');
            return;
        }

        $this->build(['stage' => $options['stage']]);

        $this->syncStage(['stage' => $options['stage']]);

        // run composer install on stage as well to update tables etc.
        $this->composerInstall(['stage' => $options['stage'], 'remote' => true]);

        $this->setLockFile($options['stage']);
    }

    /**
     * Run full publication stack (lint, deploy, smoketest, test)
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function publish(array $options = ['stage|s' => 'local'])
    {
        $this->lint();
        $this->deploy(['stage' => $options['stage']]);
        $this->smoketest(['stage' => $options['stage']]);
        $this->test(['stage' => $options['stage']]);
    }

    /**
     * Run a build verification test against target stage
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function smoketest(array $options = ['stage|s' => 'local'])
    {
        $stageOrigin = $this->getBuildProperty('stages.' . $options['stage'] . '.origin');
        if (true === empty($stageOrigin)) {
            $this->io()->error('Stage origin not configured - Nothing to do');
            return;
        }

        try {
            $ping = (new \GuzzleHttp\Client())->get($stageOrigin);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new \Robo\Exception\TaskException($this, 'Smoke test failed');
        }

        $this->say('Smoke test successful for ' . $stageOrigin);
    }

    /**
     * Open the public URL of target stage in the browser
     *
     * The URL is set up in »stages.<stagename>.origin«
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function view(array $options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }

        $url = $stageProperties['origin'] ?? '';
        if (true === empty($url)) {
            $this->say('No origin configured');
            return;
        }

        $this->taskOpenBrowser($url)->run();
    }

    /**
     * Open SSH connection to target stage
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function sshConnect(array $options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured - Skip');
            return;
        }

        $sshConnection = $stageProperties['user'] . '@' . $stageProperties['host'];
        $sshPort = (false === empty($stageProperties['port']))? ' -p' . (int)$stageProperties['port'] : '';
        passthru('ssh -t ' . $sshConnection . $sshPort . ' \'cd ' . $stageProperties['working-directory'] . ' && exec bash -l\'');
    }

    /**
     * Sync changed files automatically to local stage
     *
     */
    public function watch()
    {
        $properties = $this->getBuildProperty();

        $this->taskWatch()
            ->monitor(
                $this->getBuildProperty('repository-path') . $this->getBuildProperty('settings.watch.working-directory'),
                function () {
                    $this->sync(['stage' => 'local']);
                }
            )
            ->run();
    }
}
