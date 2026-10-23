<?php

declare(strict_types=1);

namespace App\Exchange\Contract;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\RateSnapshot;

interface RateProviderInterface
{
    public function getRatesOn(
        CalendarDate $date,
        CurrencyCode $currency,
        CurrencyCode $base,
    ): RateSnapshot;
}
