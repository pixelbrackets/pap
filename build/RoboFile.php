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
     * Sync files between repository and stage folder
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     *
     */
    public function sync($options = ['stage|s' => 'local'])
    {
        $stageProperties = $this->getBuildProperty($options['stage']);
        if(true === empty($stageProperties)) {
            $this->io()->error('Stage not configured');
            return;
        }

        $rsync = $this->taskRsync()
            ->rawArg($stageProperties['rsync']['options'])
            ->fromPath($this->getBuildProperty('repositoryPath') . $this->getBuildProperty('src'))
            ->toUser($stageProperties['user'])
            ->toHost($stageProperties['host'])
            ->toPath($stageProperties['webdir'])
            ->verbose();

        // @todo no real sync yet (with deletions), only copy
        //$rsync->delete();

        $rsync->run();
    }

    public function deploy($options = ['stage|s' => 'local'])
    {
        // @todo build assets
        $this->sync(['stage' => $options['stage']]);
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
