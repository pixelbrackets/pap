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
    public function __construct() {
        Robo::loadConfiguration(['build.common.properties.yml','build.local.properties.yml']);

        // Repository path is always relative,
        // even if Robo is called from another directory
        Robo::Config()->set('repositoryPath', '../');
    }

    /**
     * Wrapper method for Robo Configuration Reader
     *
     * @param string $key
     * @return string | array Configuration value as defined in YML file
     */
    private function getBuildProperty($key = '') {
        return Robo::Config()->get($key);
    }

    /**
     * Build assets (Convert, concat, minify)
     *
     * Uses existing task runners like Grunt
     */
    public function buildassets()
    {
        $gruntDirectory = $this->getBuildProperty('settings.grunt.working-directory');
        if(true === empty($gruntDirectory)) {
            $this->say('Nothing to do!');
            return;
        }
        else {
            $gruntDirectory = $this->getBuildProperty('repositoryPath') . $gruntDirectory;
        }

        $this->say('Install/Update Node Packages');
        $this->taskExec('npm --silent --no-spin --no-progress install')
            ->dir($gruntDirectory)
            ->run();

        if(true === file_exists($gruntDirectory . 'gems.rb')) {
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
    public function composerDumpAutoload($options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty($options['stage']);
        if(true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }
        if(true === empty($this->getBuildProperty('settings.composer'))) {
            $this->say('Nothing to do!');
            return;
        }

        $composer = $this->taskComposerDumpAutoload();
        if($options['stage'] === 'local') {
            $composer->workingDir($stageProperties['working-directory'])->run();
        }
        else {
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
    public function composerInstall($options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty($options['stage']);
        if(true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }
        if(true === empty($this->getBuildProperty('settings.composer'))) {
            $this->say('Nothing to do!');
            return;
        }

        $composer = $this->taskComposerInstall();
        if($options['stage'] === 'local') {
            $composer->workingDir($stageProperties['working-directory'])->run();
        }
        else {
            $composer->noDev();
            $this->taskSshExec($stageProperties['host'], $stageProperties['user'])
                ->remoteDir($stageProperties['working-directory'])
                ->exec($composer)
                ->run();
        }
    }

    /**
     * Sync files between repository and stage folder
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     */
    public function sync($options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty($options['stage']);
        if(true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }

        $syncPaths = $this->getBuildProperty('settings.sync-paths');
        foreach ($syncPaths as $syncPath) {
            $rsync = $this->taskRsync()
                ->rawArg($stageProperties['rsync']['options'])
                ->exclude($syncPath['exclude'] ?? [])
                ->fromPath($this->getBuildProperty('repositoryPath') . $syncPath['source'])
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
    public function deploy($options = ['stage|s' => 'local'])
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
            ->monitor($this->getBuildProperty('repositoryPath') . $this->getBuildProperty('src'), function() {
                $this->sync(['stage' => 'local']);}
            )
            ->run();
    }
}
