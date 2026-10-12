<?php

declare(strict_types=1);

namespace App\Calculator;

use RuntimeException;

use function sprintf;

final class UnknownCalculator extends RuntimeException
{
    public static function named(string $name): self
    {
        return new self(sprintf('Calculator "%s" is not registered', $name));
    }
}
