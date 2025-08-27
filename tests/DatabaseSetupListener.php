<?php


use PHPUnit\Event\TestSuite\Started;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DatabaseSetupListener implements EventSubscriberInterface
{
    private static $databaseInitialized = false;

    public static function getSubscribedEvents(): array
    {
        return [
            Started::class => 'initializeDatabase',
        ];
    }

    public function initializeDatabase(Started $event): void
    {
        if (self::$databaseInitialized) {
            return;
        }

        if ($event->testSuite()->name() !== 'TMI Translation') {
            return;
        }

        $kernel = new \TMI\TranslationBundle\Test\TestKernel('test', true);
        $kernel->boot();

        $application = new Application($kernel);
        $application->setAutoExit(false);

        // Drop database schema
        $input = new ArrayInput(['command' => 'doctrine:database:drop', '--force' => true]);
        $application->run($input, new NullOutput());

        // Create database
        $input = new ArrayInput(['command' => 'doctrine:database:create']);
        $application->run($input, new NullOutput());

        // Create database schema
        $input = new ArrayInput(['command' => 'doctrine:schema:create']);
        $application->run($input, new NullOutput());

        self::$databaseInitialized = true;
    }
}