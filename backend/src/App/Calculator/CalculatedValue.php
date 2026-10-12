<?php

declare(strict_types=1);

namespace App\Calculator;

final class CalculatedValue
{
    public function __construct(
        public readonly float $value,
        /** ISO 8601, UTC */
        public readonly string $computedAt,
    ) {
    }
}
