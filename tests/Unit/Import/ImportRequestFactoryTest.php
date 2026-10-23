<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Factory\ImportRequestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ImportRequestFactoryTest extends TestCase
{
    /**
     * По умолчанию загружаются ровно 180 предыдущих дней без сегодняшнего
     */
    public function testDefaultPeriodEndsYesterday(): void
    {
        $factory = new ImportRequestFactory(new MockClock('2026-10-22T00:30:00+03:00'));

        $request = $factory->create(
            currencies: ['usd', 'USD', 'EUR', 'rur'],
            all: false,
            from: null,
            to: null,
            previousDays: null,
            force: true,
        );

        self::assertSame(['USD', 'EUR', 'RUB'], $request->currencies);
        self::assertSame('2026-04-25', $request->from);
        self::assertSame('2026-10-21', $request->to);
        self::assertTrue($request->force);
    }
}
