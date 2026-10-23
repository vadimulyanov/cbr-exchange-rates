<?php

declare(strict_types=1);

namespace App\Exchange\Dto;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RateRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex('/^[A-Z]{3}$/D')]
    public string $currency;

    #[Assert\Regex('/^[A-Z]{3}$/D')]
    public string $base;

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $date,
        string $currency,
        string $base = CurrencyCode::RUR,
    ) {
        new CalendarDate($date);

        $this->currency = (new CurrencyCode($currency))->value;
        $this->base = (new CurrencyCode($base))->value;
    }
}
