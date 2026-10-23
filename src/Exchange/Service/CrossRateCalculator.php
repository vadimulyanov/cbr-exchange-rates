<?php

declare(strict_types=1);

namespace App\Exchange\Service;

use App\Exchange\Model\Quote;
use App\Exchange\Model\RateComparison;
use BcMath\Number;

final class CrossRateCalculator
{
    private const int RATE_SCALE = 12;
    private const int PERCENT_SCALE = 8;

    public function calculate(Quote $currency, Quote $base): Number
    {
        $this->assertSameDate($currency, $base);

        return $this->divide(
            $currency->value->mul($base->nominal),
            $base->value->mul($currency->nominal),
            self::RATE_SCALE,
        );
    }

    public function compare(
        Quote $currency,
        Quote $base,
        Quote $previousCurrency,
        Quote $previousBase,
    ): RateComparison {
        $this->assertSameDate($currency, $base);
        $this->assertSameDate($previousCurrency, $previousBase);

        // Учитываем оба номинала умножением, сохраняя точные числитель и знаменатель до деления
        $numerator = $currency->value->mul($base->nominal);
        $denominator = $base->value->mul($currency->nominal);
        $previousNumerator = $previousCurrency->value->mul($previousBase->nominal);
        $previousDenominator = $previousBase->value->mul($previousCurrency->nominal);
        $changeNumerator = $numerator->mul($previousDenominator)->sub($previousNumerator->mul($denominator));

        return new RateComparison(
            $this->divide($numerator, $denominator, self::RATE_SCALE),
            $this->divide($previousNumerator, $previousDenominator, self::RATE_SCALE),
            $this->divide($changeNumerator, $denominator->mul($previousDenominator), self::RATE_SCALE),
            $this->divide($changeNumerator->mul(100), $denominator->mul($previousNumerator), self::PERCENT_SCALE),
        );
    }

    private function assertSameDate(Quote $currency, Quote $base): void
    {
        if ((string) $currency->date !== (string) $base->date) {
            throw new \InvalidArgumentException('Cross rates require quotes on the same date');
        }
    }

    private function divide(Number $numerator, Number $denominator, int $scale): Number
    {
        $quotient = $numerator->div($denominator, $scale + 1);
        $remainder = $numerator->sub($quotient->mul($denominator));
        $remainderSign = $remainder->compare(0);

        if ($remainderSign !== 0) {
            // Сохраняем признак ненулевого хвоста, чтобы число чуть выше половины не стало точной половиной
            $tail = (new Number(10))->pow(-$scale - 2, $scale + 2);
            $quotient = $quotient->add($remainderSign < 0 ? $tail->mul(-1) : $tail);
        }

        return $quotient->round($scale, \RoundingMode::HalfEven);
    }
}
