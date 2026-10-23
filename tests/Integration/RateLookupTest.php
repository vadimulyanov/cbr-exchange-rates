<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Cbr\Model\CurrencyRecord;
use App\Cbr\Repository\CurrencyRepositoryInterface;
use App\Cbr\Service\CbrCurrencyCatalog;
use App\Cbr\Service\CbrHistoryImporter;
use App\Cbr\Service\CbrRateProvider;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\DateRange;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RateLookupTest extends DatabaseTestCase
{
    /**
     * Устаревший справочник обновляется и для уже известного ISO-кода
     */
    public function testCatalogRefreshFindsNewSourceId(): void
    {
        self::getContainer()->get(CurrencyRepositoryInterface::class)->save(
            new CurrencyRecord('R01580', 'Saudi Riyal', 'SAR', '682'),
            new \DateTimeImmutable('2000-01-01'),
        );

        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture('catalog')),
        ]);
        self::getContainer()->set('cbr.client', $httpClient);
        $catalog = self::getContainer()->get(CbrCurrencyCatalog::class);

        self::assertSame(['R01580', 'R01580C'], array_keys($catalog->getRecords(['SAR'])));
        self::assertSame(1, $httpClient->getRequestsCount());

        $catalog->getRecords(['SAR']);

        self::assertSame(1, $httpClient->getRequestsCount());
    }

    /**
     * Неполный кеш проверяется через ЦБ, после загрузки выходной обслуживается из БД
     */
    public function testEarlierQuoteDoesNotHideNewerQuote(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture('catalog')),
            new MockResponse(
                '<ValCurs><Record Date="16.10.2026" Id="R01235"><Nominal>1</Nominal><Value>80,0000</Value></Record></ValCurs>',
            ),
            new MockResponse($this->fixture('history')),
        ]);
        self::getContainer()->set('cbr.client', $httpClient);
        $oldDate = new CalendarDate('2026-10-16');
        self::getContainer()->get(CbrHistoryImporter::class)->import(
            'R01235',
            new DateRange($oldDate, $oldDate),
        );
        $rateProvider = self::getContainer()->get(CbrRateProvider::class);
        $date = new CalendarDate('2026-10-18');
        $currency = new CurrencyCode('USD');
        $base = new CurrencyCode(CurrencyCode::RUB);
        $snapshot = $rateProvider->getRatesOn($date, $currency, $base);

        self::assertSame('2026-10-17', (string) $snapshot->date);
        self::assertSame('90.000000000000', $snapshot->quote($currency)->value->value);
        self::assertSame(3, $httpClient->getRequestsCount());

        $rateProvider->getRatesOn($date, $currency, $base);

        self::assertSame(3, $httpClient->getRequestsCount());
    }

    /**
     * ID из истории сохраняются, последняя котировка выбирается среди обоих ID SAR
     */
    public function testLatestQuoteAcrossSourceIds(): void
    {
        $history = <<<'XML'
            <ValCurs ID="R01580">
                <Record Date="16.10.2026" Id="R01580"><Nominal>1</Nominal><Value>20,0000</Value></Record>
                <Record Date="17.10.2026" Id="R01580C"><Nominal>10</Nominal><Value>210,0000</Value></Record>
            </ValCurs>
            XML;
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture('catalog')),
            new MockResponse($history),
        ]);
        self::getContainer()->set('cbr.client', $httpClient);
        $date = new CalendarDate('2026-10-17');
        self::getContainer()->get(CbrHistoryImporter::class)->import(
            'R01580',
            new DateRange(
                new CalendarDate('2026-10-16'),
                $date,
            ),
        );
        $currency = new CurrencyCode('SAR');
        $snapshot = self::getContainer()->get(CbrRateProvider::class)->getRatesOn(
            $date,
            $currency,
            new CurrencyCode(CurrencyCode::RUB),
        );
        $quote = $snapshot->quote($currency);

        self::assertSame('210.000000000000', $quote->value->value);
        self::assertSame(10, $quote->nominal);
        self::assertSame('2026-10-17', (string) $snapshot->date);
        self::assertSame(2, $httpClient->getRequestsCount());
        self::assertSame(
            ['R01580', 'R01580C'],
            $this->connection->fetchFirstColumn('SELECT DISTINCT currency_id FROM cbr_rate ORDER BY currency_id'),
        );
    }
}
