<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class ApiError
{
    public function __construct(
        public int $status,
        public string $message,
    ) {
    }
}
