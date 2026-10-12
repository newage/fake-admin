<?php

declare(strict_types=1);

namespace App\Http;

use DateTimeImmutable;

use function array_key_exists;
use function ctype_digit;
use function is_string;
use function preg_match;

/**
 * Parses request parameters, accumulating validation errors (param => message).
 */
final class QueryParams
{
    /** @param array<string, string> $errors */
    public static function scalar(array $params, string $key, array &$errors): ?string
    {
        if (! array_key_exists($key, $params) || $params[$key] === '') {
            return null;
        }
        if (! is_string($params[$key])) {
            $errors[$key] = 'Must be a single value';

            return null;
        }

        return $params[$key];
    }

    /** @param array<string, string> $errors */
    public static function positiveInt(array $params, string $key, int $default, ?int $max, array &$errors): int
    {
        $value = self::scalar($params, $key, $errors);
        if ($value === null) {
            return $default;
        }
        if (! ctype_digit($value) || (int) $value < 1 || ($max !== null && (int) $value > $max)) {
            $errors[$key] = $max === null ? 'Must be a positive integer' : "Must be an integer from 1 to $max";

            return $default;
        }

        return (int) $value;
    }

    /**
     * Date `YYYY-MM-DD` or `YYYY-MM-DDTHH:MM:SS` (UTC). For a date without time: start of day,
     * or end of day with $endOfDay.
     *
     * @param array<string, string> $errors
     */
    public static function date(array $params, string $key, bool $endOfDay, array &$errors): ?DateTimeImmutable
    {
        $value = self::scalar($params, $key, $errors);
        if ($value === null) {
            return null;
        }

        $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
        $date     = DateTimeImmutable::createFromFormat($dateOnly ? '!Y-m-d' : 'Y-m-d\TH:i:s', $value);
        $valid    = $date !== false && $date->format($dateOnly ? 'Y-m-d' : 'Y-m-d\TH:i:s') === $value;
        if (! $valid) {
            $errors[$key] = 'Must be YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS (UTC)';

            return null;
        }

        return $dateOnly && $endOfDay ? $date->setTime(23, 59, 59) : $date;
    }
}
