<?php

declare(strict_types=1);

namespace App\Import\Factory;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\DateRange;
use App\Import\Message\PlanImportMessage;
use Symfony\Component\Clock\ClockInterface;

final readonly class ImportRequestFactory
{
    public const int DEFAULT_DAYS = 180;
    public const int MAX_DAYS = 3650;

    public function __construct(private ClockInterface $clock)
    {
    }

    /**
     * @param list<string> $currencies
     */
    public function create(
        array $currencies,
        bool $all,
        ?string $from,
        ?string $to,
        ?string $previousDays,
        bool $force = false,
    ): PlanImportMessage {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone(CalendarDate::TIMEZONE));
        $today = new CalendarDate($now);

        if ($from === null && $to === null) {
            $previousDays ??= (string) self::DEFAULT_DAYS;

            if (!ctype_digit($previousDays)
                || (int) $previousDays < 1
                || (int) $previousDays > self::MAX_DAYS
            ) {
                throw new \InvalidArgumentException(
                    sprintf('--previous-days must be between 1 and %d', self::MAX_DAYS),
                );
            }

            $from = $today->value->modify(sprintf('-%d days', (int) $previousDays))->format('Y-m-d');
            $to = (string) $today->previousDay();
        } else {
            if ($from === null || $to === null) {
                throw new \InvalidArgumentException('Provide both --from and --to');
            }

            if ($previousDays !== null) {
                throw new \InvalidArgumentException('Use either --previous-days or --from and --to');
            }
        }

        $range = new DateRange(new CalendarDate($from), new CalendarDate($to));

        if (
            $range->to->value > $today->value
            || $range->from->value->diff($range->to->value)->days >= self::MAX_DAYS
        ) {
            throw new \InvalidArgumentException(
                sprintf('Import range must not exceed %d calendar days or include future dates', self::MAX_DAYS),
            );
        }

        $normalized = array_map(
            static fn (string $code) => new CurrencyCode($code)->value,
            $currencies,
        )
                |> array_unique(...)
                |> array_values(...);

        return new PlanImportMessage($normalized, $all, $from, $to, $force);
    }
}
