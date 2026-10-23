<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

final class ConcurrentCurrencyInsert
{
    public bool $inserted = false;

    public function __construct(private Connection $connection)
    {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $event->getObjectManager()->getEventManager()->removeEventListener([Events::onFlush], $this);
        $competingConnection = DriverManager::getConnection($this->connection->getParams());

        try {
            // Второе соединение создает валюту после SELECT, но до INSERT основной транзакции
            $competingConnection->insert('cbr_currency', [
                'id'          => 'R01235',
                'name'        => 'US Dollar',
                'char_code'   => 'USD',
                'num_code'    => '840',
                'parent_code' => 'R01235',
            ]);
            $this->inserted = true;
        } finally {
            $competingConnection->close();
        }
    }
}
