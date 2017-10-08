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
class RoboFile extends \Robo\Tasks
{
    private $buildProperties = [];

    function getBuildPropertiesFromFile() {
        $localBuildProperties = [];
        if(file_exists('build.local.properties')) {
            $localBuildProperties = parse_ini_file('build.local.properties', true, INI_SCANNER_RAW);
        }

        $commonBuildProperties = parse_ini_file('build.common.properties', true, INI_SCANNER_RAW);
        if(false === $commonBuildProperties) {
            $this->yell('Can not load build properties file');
            return;
        }

        // Repository path is always relative,
        // even if Robo is called from another directory
        $commonBuildProperties['repositoryPath'] = '../';
        $this->buildProperties = array_merge($localBuildProperties, $commonBuildProperties);
        //$this->say(var_dump($this->buildProperties, true));
    }

    function getBuildProperties() {
        if(true === empty($this->buildProperties)) {
            $this->getBuildPropertiesFromFile();
        }
        return $this->buildProperties;
    }

    /**
     * Sync files between repository and stage folder
     *
     * @param array $options
     * @option $stage Target stage (eg. local or live)
     *
     */
    function sync($options = ['stage|s' => 'local'])
    {
        $properties = $this->getBuildProperties();
        if(true === empty($properties[$options['stage']])) {
            $this->io()->error('Stage not configured');
            return;
        }

        $rsync = $this->taskRsync()
            ->rawArg($properties[$options['stage']]['rsync']['options'])
            ->fromPath($properties['repositoryPath'] . $properties['src'])
            ->toUser($properties[$options['stage']]['user'])
            ->toHost($properties[$options['stage']]['host'])
            ->toPath($properties[$options['stage']]['webdir'])
            ->verbose();

        // @todo no real sync yet (with deletions), only copy
        //$rsync->delete();

        $rsync->run();
    }

    function deploy($options = ['stage|s' => 'local'])
    {
        // @todo build assets
        $this->sync(['stage' => $options['stage']]);
    }

    /**
     * Sync changed files automatically to local stage
     *
     */
    function watch()
    {
        $properties = $this->getBuildProperties();

        $this->taskWatch()
            ->monitor($properties['repositoryPath'] . $properties['src'], function() {
                $this->sync(['stage' => 'local']);
            })
            ->run();
    }
}
