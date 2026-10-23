<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Cbr\Model\CurrencyHistory;
use App\Cbr\Model\CurrencyRecord;
use App\Cbr\Repository\RateRepositoryInterface;
use App\Cbr\Service\CbrHistoryImporter;
use App\Exchange\Dto\RateRequest;
use App\Exchange\Exception\SourceUnavailableException;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\DateRange;
use App\Exchange\Service\ExchangeRateService;
use App\Import\Handler\ImportCurrencyHistoryHandler;
use App\Import\Message\ImportCurrencyHistoryMessage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HistoryRangeTest extends DatabaseTestCase
{
    /**
     * Диапазоны объединяются при пересечении и соседстве, непроверенные дни остаются разрывом
     */
    public function testRangesMergeWithoutCoveringGaps(): void
    {
        $currency = new CurrencyRecord('R01235', 'US Dollar', 'USD', '840');
        $repository = self::getContainer()->get(RateRepositoryInterface::class);

        foreach ([['01', '03'], ['07', '09'], ['03', '04'], ['06', '07']] as [$from, $to]) {
            $repository->saveHistory(new CurrencyHistory($currency, $this->range($from, $to), []));
        }

        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cbr_history_range'));
        self::assertFalse($repository->hasHistory($currency, $this->range('01', '09')));

        $repository->saveHistory(new CurrencyHistory($currency, $this->range('05', '05'), []));
        $repository->saveHistory(new CurrencyHistory($currency, $this->range('02', '08'), []));

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cbr_history_range'));
        self::assertTrue($repository->hasHistory($currency, $this->range('01', '09')));
        self::assertFalse($repository->hasHistory($currency, $this->range('01', '10')));
        self::assertFalse(
            $repository->hasHistory(
                new CurrencyRecord('R01239', 'Euro', 'EUR', '978'),
                $this->range('01', '09'),
            ),
        );
    }

    /**
     * Воркер получает опорную котировку, запрос на выходной использует подтвержденную историю
     */
    public function testWorkerPreparesWeekendCache(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture('catalog')),
            new MockResponse('<ValCurs/>'),
            new MockResponse($this->fixture('history')),
            new MockResponse(str_replace('90,0000', '95,0000', $this->fixture('history'))),
        ]);
        self::getContainer()->set('cbr.client', $httpClient);

        $handler = self::getContainer()->get(ImportCurrencyHistoryHandler::class);
        $message = new ImportCurrencyHistoryMessage(
            'R01235',
            '2026-10-18',
            '2026-10-19',
        );
        $handler($message);

        $service = self::getContainer()->get(ExchangeRateService::class);
        $request = new RateRequest('2026-10-18', 'USD');
        $result = $service->getRate($request);

        self::assertSame('2026-10-17', $result->effectiveDate);
        self::assertSame('2026-10-16', $result->previousDate);
        self::assertSame('90.000000000000', $result->rate);
        self::assertSame(3, $httpClient->getRequestsCount());

        $handler($message);

        self::assertSame(3, $httpClient->getRequestsCount());

        $handler(
            new ImportCurrencyHistoryMessage(
                'R01235',
                '2026-10-18',
                '2026-10-19',
                force: true,
            ),
        );

        self::assertSame(4, $httpClient->getRequestsCount());
        self::assertSame('95.000000000000', $service->getRate($request)->rate);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cbr_rate'));
    }

    /**
     * Ошибка источника не оставляет подтвержденный диапазон
     */
    public function testFailedImportDoesNotConfirmHistory(): void
    {
        self::getContainer()->set('cbr.client', new MockHttpClient([
            new MockResponse($this->fixture('catalog')),
            new MockResponse('', ['http_code' => 502]),
        ]));
        $this->expectException(SourceUnavailableException::class);

        try {
            self::getContainer()->get(CbrHistoryImporter::class)->import('R01235', $this->range('01', '09'));
        } finally {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cbr_history_range'));
        }
    }

    private function range(string $from, string $to): DateRange
    {
        return new DateRange(
            new CalendarDate(sprintf('2026-10-%s', $from)),
            new CalendarDate(sprintf('2026-10-%s', $to)),
        );
    }
}
