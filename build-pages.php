<?php

/**
 * Render Markdown files as HTML project pages into public/
 *
 * README.md becomes the index page, relative links to Markdown files
 * are rewritten to the generated HTML pages.
 *
 * Run via »composer build:pages«
 */

require __DIR__ . '/vendor/autoload.php';

// Copy files served as they are (llms.txt, images)
$publicPath = __DIR__ . '/public/';
if (false === is_dir($publicPath . 'docs')) {
    mkdir($publicPath . 'docs', 0755, true);
}
copy(__DIR__ . '/llms.txt', $publicPath . 'llms.txt');
foreach (glob(__DIR__ . '/docs/*') as $file) {
    copy($file, $publicPath . 'docs/' . basename($file));
}

// Collect Markdown files to render as HTML pages
$pages = [
    'README.md' => 'index.html',
    'CHANGELOG.md' => 'CHANGELOG.html',
    'CONTRIBUTING.md' => 'CONTRIBUTING.html',
];
foreach (glob(__DIR__ . '/docs/*.md') as $file) {
    $name = 'docs/' . basename($file, '.md');
    $pages[$name . '.md'] = $name . '.html';
}

// Convert
foreach ($pages as $source => $target) {
    $rootPath = str_repeat('../', substr_count($target, '/'));
    $markup = (new \Pixelbrackets\MarkdownMiniPage\MarkdownMiniPage())
        ->setAdditionalMetadata('<link rel="alternate" type="text/plain" href="' . $rootPath . 'llms.txt" title="llms.txt">')
        ->setContent(file_get_contents(__DIR__ . '/' . $source))
        ->getMarkup();

    // Link to rendered pages instead of Markdown files (skip absolute URLs)
    $markup = preg_replace('/href="(?![a-z]+:)([^"#]*)\.md(#[^"]*)?"/', 'href="$1.html$2"', $markup);
    $markup = preg_replace('/href="([^"#]*)README\.html/', 'href="$1index.html', $markup);

    file_put_contents($publicPath . $target, $markup);
    echo 'Created public/' . $target . PHP_EOL;
}
