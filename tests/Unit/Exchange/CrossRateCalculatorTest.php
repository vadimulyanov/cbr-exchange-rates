<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exchange;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\Quote;
use App\Exchange\Service\CrossRateCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CrossRateCalculatorTest extends TestCase
{
    /**
     * Кросс-курс учитывает номинал, обратную пару и совпадающие валюты
     */
    public function testCrossRateAccountsForNominal(): void
    {
        $calculator = new CrossRateCalculator();
        $yen = $this->quote('JPY', '90', nominal: 100);
        $euro = $this->quote('EUR', '100');

        self::assertSame('0.009000000000', $calculator->calculate($yen, $euro)->value);
        self::assertSame('111.111111111111', $calculator->calculate($euro, $yen)->value);
        self::assertSame('1.000000000000', $calculator->calculate($yen, $yen)->value);
    }

    /**
     * На границе округления выбирается четная последняя цифра (half-even)
     */
    #[DataProvider('roundingCases')]
    public function testRoundingUsesHalfEven(
        string $dollarValue,
        string $euroValue,
        string $expectedRate,
    ): void {
        $calculator = new CrossRateCalculator();

        $rate = $calculator->calculate(
            $this->quote('USD', $dollarValue),
            $this->quote('EUR', $euroValue),
        );

        self::assertSame($expectedRate, $rate->value);
    }

    /**
     * Процент вычисляется до округления курсов, чтобы не терять точность
     */
    public function testPercentageIsRoundedFromOriginalQuotes(): void
    {
        $calculator = new CrossRateCalculator();
        $euro = $this->quote('EUR', '6');
        $previousDollar = $this->quote('USD', '1', date: '2026-10-21');
        $previousEuro = $this->quote('EUR', '6', date: '2026-10-21');

        $comparison = $calculator->compare(
            $this->quote('USD', '1.01000000015'),
            $euro,
            $previousDollar,
            $previousEuro,
        );

        self::assertSame('1.00000002', $comparison->changePercent->value);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function roundingCases(): iterable
    {
        yield 'tie rounds down to even' => ['2.000000000001', '2', '1.000000000000'];
        yield 'tie rounds up to even' => ['2.000000000003', '2', '1.000000000002'];
    }

    private function quote(
        string $currency,
        string $value,
        int $nominal = 1,
        string $date = '2026-10-22',
    ): Quote {
        return new Quote(
            new CurrencyCode($currency),
            new CalendarDate($date),
            $value,
            $nominal,
        );
    }
}
