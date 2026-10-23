<?php

declare(strict_types=1);

namespace App\Exchange\Validation;

use BcMath\Number;

final class QuoteValueValidator
{
    private const string VALUE_PATTERN = '/^\d{1,18}(?:\.\d{1,12})?$/D';

    /**
     * @phpstan-assert numeric-string $value
     */
    public static function validate(string $value, int $nominal): void
    {
        if (
            $nominal < 1
            || !is_numeric($value)
            || !preg_match(self::VALUE_PATTERN, $value)
        ) {
            throw new \InvalidArgumentException('A quote must have a valid decimal value and a positive nominal');
        }

        if (new Number($value)->compare(0) <= 0) {
            throw new \InvalidArgumentException('A quote value must be positive');
        }
    }
}
