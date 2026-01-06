<?php

use Pixelbrackets\PhpAppPublication\RoboFile;
use PHPUnit\Framework\TestCase;

require __DIR__ . '/src/CommandTesterTrait.php';

class PapCommandsTest extends TestCase
{
    use CommandTesterTrait;

    /** @var string[] */
    protected $commandClass;

    /**
     * Prepare CLI setup
     */
    protected function setUp(): void
    {
        $this->commandClass = [ \Pixelbrackets\PhpAppPublication\RoboFile::class ];
        $this->setupCommandTester('TestFixtureApp', '1.0.1');
    }

    /**
     * Data provider for testExampleCommands.
     *
     * Structure: Expected output snippets, expected status codes
     * and CLI arguments for various commands.
     */
    public static function generalCommandsProvider()
    {
        return [
            // Basic commands
            [
                'publish',
                0,
                'list',
            ],
            [
                'help',
                0,
                'help',
            ],
            [
                'repository-path:',
                0,
                'show',
            ],
            [
                'Stage',
                0,
                'show', 'stages',
            ],

            // Lint commands
            [
                'my lint check script',
                0,
                'lint',
            ],
            [
                'my lint check script',
                0,
                'lint:check',
            ],
            [
                'my lint fix script',
                0,
                'lint:fix',
            ],

            // Build commands
            [
                'buildassets script',
                0,
                'buildassets',
            ],
            [
                'Installing Packages',
                0,
                'buildapp',
            ],
            [
                'buildassets script',
                0,
                'build',
            ],

            // Test commands
            [
                'unit test script',
                0,
                'test:unit',
            ],
            [
                'unit test script',
                0,
                'unittest', // Backwards compatible alias
            ],
            [
                'integration test script',
                0,
                'test:integration',
            ],
            [
                'integration test script',
                0,
                'integrationtest', // Backwards compatible alias
            ],
            [
                'integration test script',
                0,
                'test', // Backwards compatible alias for test:integration
            ],
            [
                'Stage origin not configured',
                0,
                'test:smoke', '-s', 'not-existing-stage'
            ],
            [
                'Stage origin not configured',
                0,
                'test:smoke', '-s', 'faulty'
            ],
            [
                'Smoke test successful',
                0,
                'test:smoke', '-s', 'live'
            ],
            [
                'Smoke test successful',
                0,
                'smoketest', '-s', 'live' // Backwards compatible alias
            ],

            // Deploy commands (test error messages without actual SSH)
            [
                'Stage not configured',
                0,
                'sync', '-s', 'not-existing-stage'
            ],
            [
                'Stage not configured',
                0,
                'deploy', '-s', 'not-existing-stage'
            ],

            // SSH commands (test error messages)
            [
                'Stage not configured',
                0,
                'ssh:connect', '-s', 'not-existing-stage'
            ],
            [
                'No command specified',
                0,
                'ssh:exec', '-s', 'live'
            ],

            // Composer commands (test error messages)
            [
                'Stage not configured',
                0,
                'composer:command', '-s', 'not-existing-stage'
            ],
            [
                'Stage not configured',
                0,
                'composer:install', '-s', 'not-existing-stage'
            ],

            // Publish workflow (runs through lint and unit tests, exits with 1 at deploy step)
            [
                'unit test script',
                1,
                'publish',
            ],
        ];
    }

    /**
     * @dataProvider generalCommandsProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneralCommands($expectedOutput, $expectedStatus, $CliArguments)
    {
        // Change working directory to load fixture files
        chdir(__DIR__ . '/../fixtures/');

        // Create Robo arguments and execute a runner instance
        $argv = $this->argv(func_get_args());
        list($actualOutput, $statusCode) = $this->execute($argv, $this->commandClass);

        // Confirm that our output and status code match expectations
        $this->assertStringContainsString($expectedOutput, $actualOutput);
        $this->assertEquals($expectedStatus, $statusCode);
    }
}
