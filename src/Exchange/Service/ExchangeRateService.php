<?php

declare(strict_types=1);

namespace App\Exchange\Service;

use App\Exchange\Contract\RateProviderInterface;
use App\Exchange\Dto\RateRequest;
use App\Exchange\Dto\RateResult;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use Symfony\Component\Clock\ClockInterface;

final readonly class ExchangeRateService
{
    public function __construct(
        private RateProviderInterface $rateProvider,
        private PreviousRateResolver $previousRateResolver,
        private CrossRateCalculator $crossRateCalculator,
        private ClockInterface $clock,
    ) {
    }

    public function getRate(RateRequest $request): RateResult
    {
        $date = new CalendarDate($request->date);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone(CalendarDate::TIMEZONE));

        if ($date->value > $now) {
            throw new \InvalidArgumentException('Future dates are not supported');
        }

        $currency = new CurrencyCode($request->currency);
        $base = new CurrencyCode($request->base);

        $current = $this->rateProvider->getRatesOn($date, $currency, $base);
        $previous = $this->previousRateResolver->resolve($current, $currency, $base);

        $comparison = $this->crossRateCalculator->compare(
            $current->quote($currency),
            $current->quote($base),
            $previous->quote($currency),
            $previous->quote($base),
        );

        return new RateResult(
            (string) $date,
            (string) $current->date,
            (string) $previous->date,
            $currency->value,
            $base->value,
            $comparison->rate->value,
            $comparison->previousRate->value,
            $comparison->change->value,
            $comparison->changePercent->value,
        );
    }
}
