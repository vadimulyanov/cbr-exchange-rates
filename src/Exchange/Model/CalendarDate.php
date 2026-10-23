<?php

declare(strict_types=1);

namespace App\Exchange\Model;

final readonly class CalendarDate implements \Stringable
{
    public const string TIMEZONE = 'Europe/Moscow';

    private const string DATE_FORMAT = 'Y-m-d';

    public \DateTimeImmutable $value;

    public function __construct(string|\DateTimeImmutable $date)
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);

        if ($date instanceof \DateTimeImmutable) {
            // Сохраняем календарный день источника, а не момент времени в другом часовом поясе
            $this->value = $date->setTimezone($timezone)
                ->setDate(
                    (int) $date->format('Y'),
                    (int) $date->format('m'),
                    (int) $date->format('d'),
                )
                ->setTime(0, 0);

            return;
        }

        $value = \DateTimeImmutable::createFromFormat(
            '!' . self::DATE_FORMAT,
            $date,
            $timezone,
        );

        if (!$value || $value->format(self::DATE_FORMAT) !== $date) {
            throw new \InvalidArgumentException('Date must be a valid date in YYYY-MM-DD format');
        }

        $this->value = $value;
    }

    public function previousDay(): self
    {
        return new self($this->value->modify('-1 day'));
    }

    public function __toString(): string
    {
        return $this->value->format(self::DATE_FORMAT);
    }
}
