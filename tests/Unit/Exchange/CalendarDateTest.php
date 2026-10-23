<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exchange;

use App\Exchange\Model\CalendarDate;
use PHPUnit\Framework\TestCase;

final class CalendarDateTest extends TestCase
{
    /**
     * Часовой пояс и время объекта не сдвигают его календарный день
     */
    public function testPreservesCalendarDayAcrossTimezones(): void
    {
        $date = new CalendarDate(new \DateTimeImmutable('2026-10-23T23:30:15.123456+00:00'));

        self::assertSame('2026-10-23', (string) $date);
        self::assertSame('00:00:00.000000 Europe/Moscow', $date->value->format('H:i:s.u e'));
        self::assertSame('2026-10-22', (string) $date->previousDay());
    }
}
