<?php

declare(strict_types=1);

namespace App\Exchange\Model;

use Symfony\Component\Intl\Currencies;

final readonly class CurrencyCode implements \Stringable
{
    public const string RUB = 'RUB';
    public const string RUR = 'RUR';

    public string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));
        $value = $value === self::RUR ? self::RUB : $value;

        // Intl не включает расчетную единицу XDR, но ЦБ публикует для нее курс
        if ($value !== 'XDR' && !Currencies::exists($value)) {
            throw new \InvalidArgumentException('Currency must be a valid ISO 4217 code');
        }

        $this->value = $value;
    }

    public function isRuble(): bool
    {
        return $this->value === self::RUB;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
