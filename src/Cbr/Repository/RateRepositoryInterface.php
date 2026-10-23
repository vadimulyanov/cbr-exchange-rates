<?php

declare(strict_types=1);

namespace App\Cbr\Repository;

use App\Cbr\Model\CurrencyHistory;
use App\Cbr\Model\CurrencyRecord;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\DateRange;
use App\Exchange\Model\Quote;

interface RateRepositoryInterface
{
    public function saveHistory(
        CurrencyHistory $history,
    ): int;

    public function findLatest(
        CurrencyRecord $currency,
        CalendarDate $date,
        CalendarDate $notBefore,
    ): ?Quote;

    public function deleteAll(): int;

    public function hasHistory(CurrencyRecord $currency, DateRange $range): bool;
}
