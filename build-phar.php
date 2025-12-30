<?php

// Catch missing write rights
if (ini_get('phar.readonly') == 1) {
    echo 'Run »php --define phar.readonly=0 build-phar.php« to build the phar' . PHP_EOL;
    exit(1);
}

$exclude = [
    '.claude',
    '.editorconfig',
    '.git',
    '.gitattributes',
    '.gitignore',
    '.gitlab-ci.yml',
    '.idea',
    '.notes',
    '.php-version',
    '.php_cs.cache',
    'CLAUDE.md',
    'CONTRIBUTING.md',
    'build',
    'build-phar.php',
    'composer.phar',
    'docs',
    'skeleton',
    'tests',
];

$filter = function ($file, $key, $iterator) use ($exclude) {
    if (($iterator->hasChildren() || $file->isFile() || $file->isLink()) && !in_array($file->getFilename(), $exclude)) {
        return true;
    }
    return false;
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
        $filter
    )
);

// Create entry script to avoid shebang duplicates
$file = file(__DIR__ . '/bin/pap');
unset($file[0]);
file_put_contents(__DIR__ . '/bin/pap.php', $file);

// Create phar
$phar = new \Phar('pap.phar');
$phar->setSignatureAlgorithm(\Phar::SHA1);
$phar->startBuffering();
$phar->buildFromIterator($iterator, __DIR__);
//default executable
$phar->setStub(
    '#!/usr/bin/env php ' . PHP_EOL . $phar->createDefaultStub('bin/pap.php')
);
$phar->stopBuffering();

// Make phar executable
chmod(__DIR__ . '/pap.phar', 0770);

// Remove entry script
unlink(__DIR__ . '/bin/pap.php');

echo 'Done';
