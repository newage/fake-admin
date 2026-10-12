<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

final class InvalidParams extends InvalidArgumentException
{
    /** @param array<string, string> $errors param => message */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid request parameters: ' . implode(', ', array_keys($errors)));
    }
}
