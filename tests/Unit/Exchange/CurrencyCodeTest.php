<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exchange;

use App\Exchange\Model\CurrencyCode;
use PHPUnit\Framework\TestCase;

final class CurrencyCodeTest extends TestCase
{
    /**
     * Коды нормализуются, RUR заменяется на RUB, неизвестные валюты отклоняются
     */
    public function testNormalizesKnownCodesAndRejectsUnknownCodes(): void
    {
        self::assertSame('RUB', (new CurrencyCode(' rur '))->value);
        self::assertSame('USD', (new CurrencyCode('usd'))->value);
        self::assertSame('DEM', (new CurrencyCode('DEM'))->value);
        self::assertSame('XDR', (new CurrencyCode('xdr'))->value);

        $this->expectException(\InvalidArgumentException::class);
        new CurrencyCode('ZZZ');
    }
}
