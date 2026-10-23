<?php

declare(strict_types=1);

namespace App\Cbr\Model;

use App\Exchange\Exception\SourceUnavailableException;
use App\Exchange\Model\DateRange;

final readonly class CurrencyHistory
{
    /**
     * @param list<RateRecord> $rates
     */
    public function __construct(
        public CurrencyRecord $currency,
        public DateRange $range,
        public array $rates,
    ) {
        $currencyGroupId = $currency->parentCode ?? $currency->id;

        foreach ($rates as $rate) {
            $rateCurrencyGroupId = $rate->currency->parentCode ?? $rate->currency->id;

            // ЦБ может вернуть валюту-преемника с другим ISO-кодом в той же группе
            if ($rateCurrencyGroupId !== $currencyGroupId) {
                throw new SourceUnavailableException('CBR returned a quotation for a different currency');
            }

            if ($rate->date->value < $range->from->value || $rate->date->value > $range->to->value) {
                throw new SourceUnavailableException('CBR returned a quotation outside the requested period');
            }
        }
    }
}
