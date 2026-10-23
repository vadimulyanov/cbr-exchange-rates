<?php

declare(strict_types=1);

namespace App\Cbr\Model;

final readonly class CurrencyRecord
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $charCode,
        public ?string $numCode,
        public ?string $parentCode = null,
    ) {
        if (!preg_match('/^R[0-9A-Za-z]+$/D', $id) || $name === '') {
            throw new \InvalidArgumentException('Invalid CBR currency record');
        }

        if ($charCode !== null && !preg_match('/^[A-Z]{3}$/D', $charCode)) {
            throw new \InvalidArgumentException('Invalid CBR currency code');
        }
    }
}
