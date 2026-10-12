<?php

declare(strict_types=1);

namespace App\Reading;

use RuntimeException;
use Throwable;

/** The readings store (the table is created by consumers) is unavailable or not yet created. */
final class ReadingsUnavailable extends RuntimeException
{
    public static function because(Throwable $previous): self
    {
        return new self('Sensor readings storage is unavailable', 0, $previous);
    }
}
