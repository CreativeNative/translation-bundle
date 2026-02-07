<?php

use Tmi\TranslationBundle\Test\TestKernel;

require __DIR__ . '/vendor/autoload.php';

$kernel = new TestKernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine.orm.entity_manager');
