<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Cbr\Client\XmlParser;
use App\Cbr\Model\CurrencyHistory;
use App\Cbr\Model\HistoryRecord;
use App\Cbr\Model\RateRecord;
use App\Cbr\Repository\RateRepositoryInterface;
use App\Exchange\Dto\RateRequest;
use App\Exchange\Exception\RateNotFoundException;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\DateRange;
use App\Exchange\Service\ExchangeRateService;
use App\Tests\Support\ConcurrentCurrencyInsert;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HistoryImportTest extends DatabaseTestCase
{
    /**
     * При отсутствии котировок за неделю и 180 дней возвращается ошибка
     */
    public function testEmptyHistoryReturnsNotFound(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture('catalog')),
            new MockResponse('<ValCurs ID="R01235"/>'),
            new MockResponse('<ValCurs ID="R01235"/>'),
        ]);
        self::getContainer()->set('cbr.client', $httpClient);

        $this->expectException(RateNotFoundException::class);
        self::getContainer()->get(ExchangeRateService::class)->getRate(
            new RateRequest(
                '2026-10-18',
                'USD',
            ),
        );
    }

    /**
     * Конкурентная вставка вызывает повтор транзакции без дубликатов котировок
     */
    public function testConcurrentInsertIsRetriedWithoutDuplicates(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $concurrentInsert = new ConcurrentCurrencyInsert($this->connection);
        $entityManager->getEventManager()->addEventListener([Events::onFlush], $concurrentInsert);
        $parser = new XmlParser();
        $catalog = $parser->getCurrencies($this->fixture('catalog'));
        $currency = $catalog[0];
        $rates = array_map(
            static fn (HistoryRecord $record) => new RateRecord(
                $currency,
                $record->date,
                $record->value,
                $record->nominal,
            ),
            $parser->history($this->fixture('history')),
        );
        $history = new CurrencyHistory(
            $currency,
            new DateRange(
                new CalendarDate('2026-10-16'),
                new CalendarDate('2026-10-17'),
            ),
            [...$rates, $rates[0]],
        );
        $repository = self::getContainer()->get(RateRepositoryInterface::class);

        $repository->saveHistory($history);

        self::assertTrue($concurrentInsert->inserted);
        self::assertTrue($entityManager->isOpen());
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cbr_rate'));

        $repository->saveHistory($history);

        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cbr_rate'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cbr_history_range'));
        self::assertSame(
            'R01235',
            $this->connection->fetchOne("SELECT parent_code FROM cbr_currency WHERE id = 'R01235'"),
        );
    }
}
