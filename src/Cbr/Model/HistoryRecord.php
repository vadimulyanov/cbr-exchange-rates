<?php

declare(strict_types=1);

namespace App\Cbr\Model;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Validation\QuoteValueValidator;

final readonly class HistoryRecord
{
    public function __construct(
        public string $sourceId,
        public CalendarDate $date,
        public string $value,
        public int $nominal,
    ) {
        QuoteValueValidator::validate($value, $nominal);
    }
}
