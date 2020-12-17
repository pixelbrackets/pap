<?php

use Pixelbrackets\PhpAppPublication\RoboFile;
use PHPUnit\Framework\TestCase;

class PapTest extends TestCase
{
    protected function setUp(): void
    {
        \Robo\Robo::unsetContainer();
        $container = \Robo\Robo::createDefaultContainer();
        \Robo\Robo::setContainer($container);
    }

    public function testSyncIsAllowed()
    {
        $pap = new RoboFile();
        $expectedOutput = true;

        $methodName = 'syncIsAllowed';
        $parameters = ['stage' => 'local'];
        $reflection = new \ReflectionClass(get_class($pap));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        $actualOutput = $method->invokeArgs($pap, $parameters);

        $this->assertEquals($expectedOutput, $actualOutput);
    }
}
