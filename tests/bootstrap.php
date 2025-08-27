<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput as ConsoleOutput;
use TMI\TranslationBundle\Test\Fixtures\App\AppKernel;

/*
 * Code inspired by https://github.com/Orbitale/CmsBundle/blob/master/Tests/bootstrap.php
 * (c) Alexandre Rock Ancelet <alex@orbitale.io>
 */
$file = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies using Composer to run the test suite.');
}
$autoload = require $file;


// Test Setup: remove all the contents in the build/ directory
// (PHP doesn't allow to delete directories unless they are empty)
if (is_dir($buildDir = dirname(__DIR__) . '/tests/build')) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath());
    }
}
include dirname(__DIR__) . '/tests/TMI/TranslatioBundle/Test/Fixtures/App/AppKernel.php';

$application = new Application(new AppKernel('test', true));
$application->setAutoExit(false);

// Drop database schema
$input = new ArrayInput(['command' => 'doctrine:database:drop', '--force' => true]);
$application->run($input, new ConsoleOutput());

// Create database
$input = new ArrayInput(['command' => 'doctrine:database:create']);
$application->run($input, new ConsoleOutput());

// Create database schema
$input = new ArrayInput(['command' => 'doctrine:schema:create']);
$application->run($input, new ConsoleOutput());