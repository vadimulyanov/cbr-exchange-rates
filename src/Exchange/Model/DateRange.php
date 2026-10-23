<?php

declare(strict_types=1);

namespace App\Exchange\Model;

final readonly class DateRange
{
    public function __construct(
        public CalendarDate $from,
        public CalendarDate $to,
    ) {
        if ($from->value > $to->value) {
            throw new \InvalidArgumentException('The start date must not exceed the end date');
        }
    }
}
