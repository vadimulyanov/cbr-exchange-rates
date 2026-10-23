<?php

declare(strict_types=1);

namespace App\Cbr\Service;

use App\Cbr\Client\CbrClient;
use App\Cbr\Model\CurrencyHistory;
use App\Cbr\Model\HistoryRecord;
use App\Cbr\Model\RateRecord;
use App\Cbr\Repository\RateRepositoryInterface;
use App\Exchange\Exception\SourceUnavailableException;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\DateRange;
use App\Import\Contract\HistoryImporterInterface;
use Psr\Log\LoggerInterface;

final readonly class CbrHistoryImporter implements HistoryImporterInterface
{
    private const int INITIAL_LOOKBACK_DAYS = 7;
    private const int MAX_LOOKBACK_DAYS = 180;

    public function __construct(
        private CbrClient $cbrClient,
        private CbrCurrencyCatalog $currencyCatalog,
        private RateRepositoryInterface $rateRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function import(
        string $sourceId,
        DateRange $range,
        bool $includePreviousQuote = false,
        bool $force = false,
    ): int {
        $catalog = $this->currencyCatalog->getRecordsByIds([$sourceId]);

        $currency = $catalog[$sourceId] ?? throw new \InvalidArgumentException('Unknown CBR currency identifier');

        $requestedRange = $range;

        if ($includePreviousQuote) {
            $range = $this->extendRange($requestedRange, self::INITIAL_LOOKBACK_DAYS);
        }

        if (!$force && $this->rateRepository->hasHistory($currency, $range)) {
            $this->logger->info('cbr.currency_history.skipped', [
                'source_id' => $sourceId,
                'from'      => (string) $range->from,
                'to'        => (string) $range->to,
            ]);

            return 0;
        }
        $records = $this->cbrClient->history($sourceId, $range);

        // Для первых выходных периода нужна котировка, уже действовавшая на его начало
        if ($includePreviousQuote && !$this->hasPreviousQuote($records, $requestedRange->from)) {
            $range = $this->extendRange($requestedRange, self::MAX_LOOKBACK_DAYS);
            $records = $this->cbrClient->history($sourceId, $range);
        }

        $returnedIds = array_values(array_unique(array_column($records, 'sourceId')));
        $missingIds = array_values(array_diff($returnedIds, [$sourceId]));

        $catalog += $this->currencyCatalog->getRecordsByIds($missingIds);
        $rates = [];

        foreach ($records as $record) {
            $recordCurrency = $catalog[$record->sourceId]
                ?? throw new SourceUnavailableException('CBR history contains an unknown currency identifier');

            $rates[] = new RateRecord(
                $recordCurrency,
                $record->date,
                $record->value,
                $record->nominal,
            );
        }
        $history = new CurrencyHistory(
            $currency,
            $range,
            $rates,
        );
        $count = $this->rateRepository->saveHistory($history);

        $this->logger->info('cbr.currency_history.cached', [
            'source_id' => $sourceId,
            'from'      => (string) $range->from,
            'to'        => (string) $range->to,
            'quotes'    => $count,
            'force'     => $force,
        ]);

        return $count;
    }

    private function extendRange(DateRange $range, int $days): DateRange
    {
        return new DateRange(
            new CalendarDate($range->from->value->modify(sprintf('-%d days', $days))),
            $range->to,
        );
    }

    /**
     * @param list<HistoryRecord> $records
     */
    private function hasPreviousQuote(array $records, CalendarDate $date): bool
    {
        foreach ($records as $record) {
            if ($record->date->value <= $date->value) {
                return true;
            }
        }

        return false;
    }
}
