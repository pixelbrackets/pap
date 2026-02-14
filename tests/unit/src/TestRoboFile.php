<?php

use Pixelbrackets\PhpAppPublication\RoboFile;

class TestRoboFile extends RoboFile
{
    protected function createHttpClient()
    {
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200),
        ]);
        return new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($mock)]);
    }
}
