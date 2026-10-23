<?php

declare(strict_types=1);

namespace App\Exchange\Model;

use BcMath\Number;

final readonly class RateComparison
{
    public function __construct(
        public Number $rate,
        public Number $previousRate,
        public Number $change,
        public Number $changePercent,
    ) {
    }
}
