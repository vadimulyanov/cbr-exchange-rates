<?php

declare(strict_types=1);

namespace App\Import\Message;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\DateRange;

final readonly class PlanImportMessage
{
    /**
     * @param list<string> $currencies
     */
    public function __construct(
        public array $currencies,
        public bool $allCurrencies,
        public string $from,
        public string $to,
        public bool $force = false,
    ) {
        $hasCurrencies = !empty($currencies);

        if ($allCurrencies === $hasCurrencies) {
            throw new \InvalidArgumentException('Choose either one or more currencies or all currencies');
        }

        foreach ($currencies as $currency) {
            new CurrencyCode($currency);
        }

        new DateRange(
            new CalendarDate($from),
            new CalendarDate($to),
        );
    }
}
