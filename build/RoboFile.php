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
        $lint->run();
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
     * Build assets (Convert, concat, minify)
     *
     * Uses existing task runners like Grunt
     */
    public function buildassets()
    {
        $gruntDirectory = $this->getBuildProperty('settings.grunt.working-directory');
        if (true === empty($gruntDirectory)) {
            $this->say('Nothing to do!');
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
        $this->taskExec('grunt -q ' . $this->getBuildProperty('settings.grunt.task'))
            ->dir($gruntDirectory)
            ->run();
    }

    /**
     * Delete and recreate the autoloader file with Composer
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function composerDumpAutoload(array $options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }
        if (true === empty($this->getBuildProperty('settings.composer'))) {
            $this->say('Nothing to do!');
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
     */
    public function composerInstall(array $options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }
        if (true === empty($this->getBuildProperty('settings.composer'))) {
            $this->say('Nothing to do!');
            return;
        }

        $composerPath = $this->getBuildProperty('stages.' . $options['stage'] . '.composer.phar');
        $composer = $this->taskComposerInstall($composerPath);
        if ($options['stage'] === 'local') {
            $composer->workingDir($stageProperties['working-directory'])->run();
        } else {
            $composer->noDev();
            $this->taskSshExec($stageProperties['host'], $stageProperties['user'])
                ->remoteDir($stageProperties['working-directory'])
                ->exec($composer)
                ->run();
        }
    }

    /**
     * Move directories in repository to prepare a working sync task
     * (move build assets to desired target etc.)
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
    public function sync(array $options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty('stages.' . $options['stage']);
        if (true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }

        // prepare files in repository
        $this->prepareSyncPaths();

        // rsync files to stage
        $syncPaths = $this->getBuildProperty('settings.sync-paths');
        foreach ((array)$syncPaths as $syncPath) {
            $rsync = $this->taskRsync()
                ->rawArg($stageProperties['rsync']['options'])
                ->exclude($syncPath['exclude'] ?? [])
                ->fromPath($this->getBuildProperty('repository-path') . $syncPath['source'])
                ->toUser($stageProperties['user'])
                ->toHost($stageProperties['host'])
                ->toPath($stageProperties['working-directory'] . $syncPath['target'])
                ->verbose();

            // real sync: delete files as well!
            $rsync->delete();

            $rsync->run();
        }

        $this->composerDumpAutoload(['stage' => $options['stage']]);
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
