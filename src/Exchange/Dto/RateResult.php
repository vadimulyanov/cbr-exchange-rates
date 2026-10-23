<?php

declare(strict_types=1);

namespace App\Exchange\Dto;

use OpenApi\Attributes as OA;

final readonly class RateResult
{
    public function __construct(
        #[OA\Property(
            description: 'Date in the request',
            type: 'string',
            format: 'date',
            example: '2026-01-01',
        )]
        public string $requestedDate,
        #[OA\Property(
            description: 'Date the rate took effect, possibly earlier than requested',
            type: 'string',
            format: 'date',
            example: '2025-12-31',
        )]
        public string $effectiveDate,
        #[OA\Property(
            description: 'Quotation date preceding the effective date',
            type: 'string',
            format: 'date',
            example: '2025-12-30',
        )]
        public string $previousDate,
        #[OA\Property(description: 'Requested currency code', type: 'string', example: 'CAD')]
        public string $currency,
        #[OA\Property(description: 'Base currency code', type: 'string', example: 'RUB')]
        public string $base,
        #[OA\Property(
            description: 'Base currency units per one unit of the requested currency',
            type: 'string',
            example: '57.158200000000',
        )]
        public string $rate,
        #[OA\Property(
            description: 'Rate on the previous date',
            type: 'string',
            example: '56.637900000000',
        )]
        public string $previousRate,
        #[OA\Property(
            description: 'Current rate minus previous rate',
            type: 'string',
            example: '0.520300000000',
        )]
        public string $change,
        #[OA\Property(
            description: 'Change relative to the previous rate (percent %)',
            type: 'string',
            example: '0.91864282',
        )]
        public string $changePercent,
    ) {
        foreach ([$rate, $previousRate, $change, $changePercent] as $decimal) {
            if (!preg_match('/^-?\d+\.\d+$/D', $decimal)) {
                throw new \InvalidArgumentException('Result amounts must be decimal strings');
            }
        }
    }
}
