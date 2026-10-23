<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);

        self::assertStringEndsWith('_test', $this->connection->fetchOne('SELECT current_database()'));
        $this->connection->executeStatement('TRUNCATE cbr_rate, cbr_currency RESTART IDENTITY CASCADE');
    }

    protected function fixture(string $name): string
    {
        $contents = file_get_contents(dirname(__DIR__) . '/Fixtures/' . $name . '.xml');

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read fixture: %s', $name));
        }

        return $contents;
    }
}
