<?php

declare(strict_types=1);

namespace App\Exchange\Service;

use App\Exchange\Contract\RateProviderInterface;
use App\Exchange\Exception\RateNotFoundException;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\RateSnapshot;

final readonly class PreviousRateResolver
{
    public function __construct(private RateProviderInterface $rateProvider)
    {
    }

    public function resolve(
        RateSnapshot $current,
        CurrencyCode $currency,
        CurrencyCode $base,
    ): RateSnapshot {
        // Для выходных сравниваем с котировкой перед фактической датой курса, а не с календарным вчера
        $previous = $this->rateProvider->getRatesOn(
            $current->date->previousDay(),
            $currency,
            $base,
        );

        if ($previous->date->value >= $current->date->value) {
            throw new RateNotFoundException('The previous quotation is unavailable');
        }

        return $previous;
    }
}
