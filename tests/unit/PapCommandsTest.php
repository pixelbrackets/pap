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
     */
    public function generalCommandsProvider()
    {
        return [

            [
                'publish',
                0,
                'list',
            ],
            [
                'Stage origin not configured',
                0,
                'smoketest',
            ],
            [
                'Stage origin not configured',
                0,
                'smoketest', '-s', 'not-existing-stage'
            ],
        ];
    }

    /**
     * @dataProvider generalCommandsProvider
     */
    public function testGeneralCommands($expectedOutput, $expectedStatus, $CliArguments)
    {
        // Create Robo arguments and execute a runner instance
        $argv = $this->argv(func_get_args());
        list($actualOutput, $statusCode) = $this->execute($argv, $this->commandClass);

        // Confirm that our output and status code match expectations
        $this->assertStringContainsString($expectedOutput, $actualOutput);
        $this->assertEquals($expectedStatus, $statusCode);
    }
}
