<?php

declare(strict_types=1);

namespace App\Cbr\Service;

use App\Cbr\Model\CurrencyRecord;
use App\Cbr\Repository\RateRepositoryInterface;
use App\Exchange\Contract\RateProviderInterface;
use App\Exchange\Exception\RateNotFoundException;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\DateRange;
use App\Exchange\Model\Quote;
use App\Exchange\Model\RateSnapshot;

final readonly class CbrRateProvider implements RateProviderInterface
{
    private const int INITIAL_DAYS = 7;
    private const int MAX_DAYS = 180;

    public function __construct(
        private CbrCurrencyCatalog $currencyCatalog,
        private CbrHistoryImporter $historyImporter,
        private RateRepositoryInterface $rateRepository,
    ) {
    }

    public function getRatesOn(
        CalendarDate $date,
        CurrencyCode $currency,
        CurrencyCode $base,
    ): RateSnapshot {
        $currencies = [];

        foreach ([$currency, $base] as $code) {
            if (!$code->isRuble()) {
                $currencies[$code->value] = $code;
            }
        }

        // Для пары RUB/RUB курс всегда равен единице, данные ЦБ не нужны
        if (empty($currencies)) {
            return new RateSnapshot(
                $date,
                [new Quote($currency, $date, '1', 1)],
            );
        }

        $currencyRecords = $this->currencyCatalog->getRecords(array_keys($currencies));
        $quotes = [];

        foreach ($currencies as $code) {
            $filtered = array_filter(
                $currencyRecords,
                static fn (CurrencyRecord $currencyRecord) => $currencyRecord->charCode === $code->value,
            );
            $quotes[] = $this->getQuoteOn($date, $code, $filtered);
        }

        return $this->createSnapshot(
            $quotes,
            includeRuble: $currency->isRuble() || $base->isRuble(),
        );
    }

    /**
     * @param non-empty-list<Quote> $quotes
     */
    private function createSnapshot(array $quotes, bool $includeRuble): RateSnapshot
    {
        $effectiveDate = reset($quotes)->date;

        foreach ($quotes as $quote) {
            if ($quote->date->value > $effectiveDate->value) {
                $effectiveDate = $quote->date;
            }
        }

        // Котировки могут вступать в силу в разные дни, дата пары - последняя из этих дат
        $quotes = array_map(
            static fn (Quote $quote) => new Quote(
                $quote->currency,
                $effectiveDate,
                $quote->value->value,
                $quote->nominal,
            ),
            $quotes,
        );

        if ($includeRuble) {
            $quotes[] = new Quote(new CurrencyCode(CurrencyCode::RUB), $effectiveDate, '1', 1);
        }

        return new RateSnapshot($effectiveDate, $quotes);
    }

    /**
     * @param array<string, CurrencyRecord> $sources
     */
    private function getQuoteOn(
        CalendarDate $date,
        CurrencyCode $currency,
        array $sources,
    ): Quote {
        if (empty($sources)) {
            throw new RateNotFoundException(sprintf('CBR has no currency with code %s', $currency));
        }

        // При одинаковой дате сохраняем выбор по меньшему ID ЦБ
        ksort($sources);
        $notBefore = new CalendarDate($date->value->modify(sprintf('-%d days', self::MAX_DAYS)));
        $quote = $this->findLatestQuote($sources, $date, $notBefore);

        // Ранний курс действителен, если источник проверен до запрошенной даты для всех ID валюты
        if ($quote !== null && (
            $quote->date->value == $date->value
                || $this->hasHistory($sources, new DateRange($quote->date, $date))
        )) {
            return $quote;
        }

        return $this->loadQuoteOn($date, $currency, $sources);
    }

    /**
     * @param array<string, CurrencyRecord> $sources
     */
    private function hasHistory(
        array $sources,
        DateRange $range,
    ): bool {
        foreach ($sources as $source) {
            if (!$this->rateRepository->hasHistory($source, $range)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, CurrencyRecord> $sources
     */
    private function findLatestQuote(
        array $sources,
        CalendarDate $date,
        CalendarDate $notBefore,
    ): ?Quote {
        $latest = null;

        foreach ($sources as $source) {
            $quote = $this->rateRepository->findLatest($source, $date, $notBefore);

            if ($quote !== null && ($latest === null || $quote->date->value > $latest->date->value)) {
                $latest = $quote;
            }
        }

        return $latest;
    }

    /**
     * @param array<string, CurrencyRecord> $sources
     */
    private function loadQuoteOn(
        CalendarDate $date,
        CurrencyCode $currency,
        array $sources,
    ): Quote {
        // Сначала загружаем неделю, при отсутствии котировки расширяем период до 180 дней
        foreach ([self::INITIAL_DAYS, self::MAX_DAYS] as $days) {
            $from = new CalendarDate($date->value->modify(sprintf('-%d days', $days)));
            $range = new DateRange($from, $date);

            foreach ($sources as $source) {
                if (!$this->rateRepository->hasHistory($source, $range)) {
                    $this->historyImporter->import($source->id, $range);
                }
            }

            $quote = $this->findLatestQuote($sources, $date, $from);

            if ($quote !== null) {
                return $quote;
            }
        }

        throw new RateNotFoundException(
            sprintf('No %s rate is available in the %d days preceding %s', $currency, self::MAX_DAYS, $date),
        );
    }
}
