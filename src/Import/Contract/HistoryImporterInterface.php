<?php

declare(strict_types=1);

namespace App\Import\Contract;

use App\Exchange\Model\DateRange;

interface HistoryImporterInterface
{
    public function import(
        string $sourceId,
        DateRange $range,
        bool $includePreviousQuote = false,
        bool $force = false,
    ): int;
}
