<?php

declare(strict_types=1);

namespace App\Import\Message;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\DateRange;

final readonly class ImportCurrencyHistoryMessage
{
    public function __construct(
        public string $sourceId,
        public string $from,
        public string $to,
        public bool $force = false,
    ) {
        if ($sourceId === '') {
            throw new \InvalidArgumentException('A source currency identifier is required');
        }
        new DateRange(
            new CalendarDate($from),
            new CalendarDate($to),
        );
    }
}
