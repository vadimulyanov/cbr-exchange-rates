<?php

declare(strict_types=1);

namespace App\Import\Model;

final readonly class ImportCurrency
{
    public function __construct(
        public string $sourceId,
        public ?string $code,
    ) {
    }
}
