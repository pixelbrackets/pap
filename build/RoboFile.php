<?php
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
     * Lint PHP files (Check only)
     *
     */
    public function lintCheck()
    {
        $repositoryPath = $this->getBuildProperty('repository-path');
        $lintPaths = (array)$this->getBuildProperty('settings.lint.lint-paths');
        if (true === empty($lintPaths)) {
            $this->say('Lint not configured');
            return;
        }

        $lint = $this->taskExecStack()
            ->dir('./vendor/bin/');
        foreach ($lintPaths as $lintPath) {
            $lint
                ->exec('./php-cs-fixer -vvv fix ' . $repositoryPath . $lintPath . ' --dry-run --diff --using-cache=no --rules=' . escapeshellarg($this->getBuildProperty('php-cs-rules') ?? '@PSR2'))
                ->exec('./parallel-lint --exclude vendor ' . $repositoryPath . $lintPath)
                ->exec('./editorconfig-checker -e \'\.(png|jpg|gif|ico|svg|js|css|ttf|eot|woff|woff2|lock|git)$\' ' . $repositoryPath . $lintPath . '/*')
                ->exec('./phploc -n ' . $repositoryPath . $lintPath);
        }

        if ($lint->run()->wasSuccessful() !== true) {
            throw new \Robo\Exception\TaskException($this, 'Check failed');
        }
    }

    /**
     * Lint PHP files (Fix)
     *
     */
    public function lintFix()
    {
        $repositoryPath = $this->getBuildProperty('repository-path');
        $lintPaths = $this->getBuildProperty('settings.lint.lint-paths');
        if (true === empty($lintPaths)) {
            $this->say('Lint not configured');
            return;
        }

        $lint = $this->taskExecStack()
            ->dir('./vendor/bin/');
        foreach ((array)$lintPaths as $lintPath) {
            $lint
                ->exec('./php-cs-fixer -vvv fix ' . $repositoryPath . $lintPath . ' --using-cache=no --rules=' . escapeshellarg($this->getBuildProperty('php-cs-rules') ?? '@PSR2'))
                ->exec('./editorconfig-checker -a -e \'\.(png|jpg|gif|ico|svg|js|css|ttf|eot|woff|woff2|lock|git)$\' ' . $repositoryPath . $lintPath . '/*');
        }
        $lint->run();
    }

    /**
     * Run Codeception test suites
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function test(array $options = ['stage|s' => 'local'])
    {
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

        $this->taskComposerInstall($composerPath)
            ->ignorePlatformRequirements()
            ->workingDir($repositoryPath . $codeceptionDirectory)
            ->run();

        // Pass stage origin to codeception - modify superglobal ENV
        // since putenv() wont catch on with the codeception configuration loader
        $_ENV['BASEURL'] = $stageOrigin . '/';
        $this->taskCodecept()
            ->dir($repositoryPath . $codeceptionDirectory)
            ->run();

        if ($codeception->wasSuccessful() !== true) {
            throw new \Robo\Exception\TaskException($this, 'Test failed');
        }
    }

    /**
     * Build assets (Convert, concat, minify…)
     *
     * Switches to external task runner Grunt if configured
     */
    public function buildassets()
    {
        $repositoryPath = $this->getBuildProperty('repository-path');
        $gruntDirectory = $this->getBuildProperty('settings.assets.grunt.working-directory');
        $assetSettings = $this->getBuildProperty('settings.assets');
        if (false === empty($gruntDirectory)) {
            // use external task runner instead
            return $this->buildassetsGrunt();
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

        $this->say('Install/Update Node Packages');
        $this->taskExec('npm --silent --progress=false ci')
            ->dir($gruntDirectory)
            ->run();

        if (true === file_exists($gruntDirectory . 'gems.rb')) {
            $this->say('Manage Ruby gems');
            $this->taskExec('bundle install --quiet')
                ->dir($gruntDirectory)
                ->run();
        }

        $this->say('Execute Grunt Tasks');
        $this->taskExec('grunt -q ' . $gruntTask)
            ->dir($gruntDirectory)
            ->run();
    }

    /**
     * Build app for desired target stage
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
            $composer->run();
        } else {
            $this->taskSshExec($stageProperties['host'], $stageProperties['user'])
                ->remoteDir($stageProperties['working-directory'])
                ->exec($composer)
                ->run();
        }
    }

    /**
     * Execute Composer commands on a target stage
     *
     * e.g. Run »composer dump-autoload« on test stage
     *     robo composer:command -s test -c dump-autoload
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live), leave empty to run in repository working directory
     * @option $command Name of the Command to execute (eg. dump-autoload)
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
            $composer->run();
        } else {
            $this->taskSshExec($stageProperties['host'], $stageProperties['user'])
                ->remoteDir($stageProperties['working-directory'])
                ->exec($composer)
                ->run();
        }
    }

    /**
     * Move directories in repository to prepare a working sync task
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
     * Sync files between repository and stage folder
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
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
            $this->taskRsync()
                ->rawArg($stageProperties['rsync']['options'])
                ->exclude($syncPath['exclude'] ?? [])
                ->fromPath($this->getBuildProperty('repository-path') . $syncPath['source'])
                ->toUser($stageProperties['user'])
                ->toHost($stageProperties['host'])
                ->toPath($stageProperties['working-directory'] . $syncPath['target'])
                ->delete()
                ->verbose()
                ->run();
        }
    }

    /**
     * Run downgraded deploy stack (sync only)
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function sync(array $options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }

        $this->prepareSyncPaths();
        $this->syncStage(['stage' => $options['stage']]);
    }

    /**
     * Run full deployment stack (build, sync, cache warmup)
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function deploy(array $options = ['stage|s' => 'local'])
    {
        $this->buildassets();
        $this->buildapp(['stage' => $options['stage']]);

        $this->syncStage(['stage' => $options['stage']]);

        // run composer install on stage as well to update tables etc.
        $this->composerInstall(['stage' => $options['stage'], 'remote' => true]);

        if ($options['stage'] !== 'local' && false === empty($this->getBuildProperty('settings.view.open-browser-after-deployment'))) {
            $this->view(['stage' => $options['stage']]);
        }
    }

    /**
     * Open the project URL on configured stages in the default browser
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
