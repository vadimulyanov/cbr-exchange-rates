<?php

declare(strict_types=1);

namespace App\Exchange\Model;

use App\Exchange\Validation\QuoteValueValidator;
use BcMath\Number;

final readonly class Quote
{
    public Number $value;

    public function __construct(
        public CurrencyCode $currency,
        public CalendarDate $date,
        string $value,
        public int $nominal,
    ) {
        QuoteValueValidator::validate($value, $nominal);

        $this->value = new Number($value);
    }
}
