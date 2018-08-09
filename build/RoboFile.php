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
        if (true === empty(Robo::Config()->get('repository-path'))) {
            $repositoryPath = exec('git rev-parse --show-toplevel', $output, $resultCode);
            if ($resultCode === 0) {
                Robo::Config()->set('repository-path', $repositoryPath . '/');
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
        return Robo::Config()->get($key);
    }

    /**
     * Lint PHP files (Check only)
     *
     */
    public function lintCheck()
    {
        $repositoryPath = $this->getBuildProperty('repository-path');
        $lintPaths = (array)$this->getBuildProperty('settings.lint.lint-paths');

        $lint = $this->taskExecStack()
            ->dir('./vendor/bin/');
        foreach ($lintPaths as $lintPath) {
            $lint
                ->exec('./php-cs-fixer -vvv fix ' . $repositoryPath . $lintPath . ' --dry-run --diff --using-cache=no --rules=' . escapeshellarg($this->getBuildProperty('php-cs-rules') ?? '@PSR2'))
                ->exec('./parallel-lint --exclude vendor ' . $repositoryPath . $lintPath)
                ->exec('./editorconfig-checker -e \'\.(png|jpg|gif|ico|svg|js|css|ttf|eot|woff|woff2|lock|git)$\' ' . $repositoryPath . $lintPath . '/*')
                ->exec('./phploc -n ' . $repositoryPath . $lintPath);
        }

        if($lint->run()->wasSuccessful() !== true) {
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
     * Build assets (Convert, concat, minify…)
     *
     * Switches to external task runner Grunt if configured
     */
    public function buildassets()
    {
        $repositoryPath = $this->getBuildProperty('repository-path');
        $gruntDirectory = $this->getBuildProperty('settings.grunt.working-directory');
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
        $gruntDirectory = $this->getBuildProperty('settings.grunt.working-directory');
        $gruntTask = $this->getBuildProperty('settings.grunt.task');
        if (empty($gruntDirectory) && empty($gruntTask)) {
            $this->io()->error('Grunt not configured');
            return;
        }
        $gruntDirectory = $this->getBuildProperty('repository-path') . $gruntDirectory;

        $this->say('Install/Update Node Packages');
        $this->taskExec('npm --silent --no-spin --no-progress install')
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
     * Delete and recreate the autoloader file with Composer
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function composerDumpAutoload(array $options = ['stage|s' => 'local'])
    {
        if (true === empty($this->getBuildProperty('settings.composer'))) {
            $this->say('Composer not configured');
            return;
        }
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }

        $composerPath = $this->getBuildProperty('stages.' . $options['stage'] . '.composer.phar');
        $composer = $this->taskComposerDumpAutoload($composerPath);
        if ($options['stage'] === 'local') {
            $composer->workingDir($stageProperties['working-directory'])->run();
        } else {
            $this->taskSshExec($stageProperties['host'], $stageProperties['user'])
                ->remoteDir($stageProperties['working-directory'])
                ->exec($composer)
                ->run();
        }
    }

    /**
     * Install packages with Composer
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     * @option $remote Execute composer localy for a stage or remote on a stage (eg. true)
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
            $composerPath = $composerSettings['phar'] ?? '';
            $composerWorkingDirectory = $this->getBuildProperty('repository-path') . $composerSettings['working-directory'];
        }
        else {
            $composerPath = $this->getBuildProperty('stages.' . $options['stage'] . '.composer.phar');
            $composerWorkingDirectory = $stageProperties['working-directory'];
        }

        $composer = $this->taskComposerInstall($composerPath);
        $composer->workingDir($composerWorkingDirectory);
        if ($options['stage'] !== 'local') {
            $composer->noDev();
        }

        if ((bool)$options['remote'] !== true) {
            $composer->run();
        }
        else {
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
            $rsync = $this->taskRsync()
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
        $this->sync(['stage' => $options['stage']]);
        $this->composerInstall(['stage' => $options['stage']]);
    }

    /**
     * Sync changed files automatically to local stage
     *
     */
    public function watch()
    {
        $properties = $this->getBuildProperty();

        $this->taskWatch()
            ->monitor($this->getBuildProperty('repository-path') . $this->getBuildProperty('settings.watch.working-directory'), function () {
                $this->sync(['stage' => 'local']);
            }
            )
            ->run();
    }
}
