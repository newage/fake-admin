<?php

declare(strict_types=1);

namespace App\Sensor;

use App\Http\InvalidParams;
use App\Http\QueryParams;
use DateTimeImmutable;

use function in_array;
use function mb_strlen;

/**
 * Validated parameters of `GET /api/sensors` (ADR 0001): OFFSET pagination, sorting by
 * indexed columns, prefix filters (≥3 characters) and a created_at range.
 */
final class SensorListQuery
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE     = 100;
    public const MIN_PREFIX_LENGTH = 3;
    public const SORTABLE          = ['name', 'code', 'created_at'];

    private function __construct(
        public readonly int $page,
        public readonly int $pageSize,
        public readonly string $sort,
        public readonly string $order,
        public readonly ?string $name,
        public readonly ?string $code,
        public readonly ?DateTimeImmutable $createdFrom,
        public readonly ?DateTimeImmutable $createdTo,
    ) {
    }

    /** @param array<string, mixed> $params @throws InvalidParams */
    public static function fromParams(array $params): self
    {
        $errors = [];

        $page     = QueryParams::positiveInt($params, 'page', 1, null, $errors);
        $pageSize = QueryParams::positiveInt($params, 'page_size', self::DEFAULT_PAGE_SIZE, self::MAX_PAGE_SIZE, $errors);

        $sort = QueryParams::scalar($params, 'sort', $errors) ?? 'created_at';
        if (! in_array($sort, self::SORTABLE, true)) {
            $errors['sort'] = 'Must be one of: ' . implode(', ', self::SORTABLE);
        }

        $order = QueryParams::scalar($params, 'order', $errors) ?? 'desc';
        if (! in_array($order, ['asc', 'desc'], true)) {
            $errors['order'] = 'Must be asc or desc';
        }

        $name = self::prefix($params, 'name', $errors);
        $code = self::prefix($params, 'code', $errors);
        $from = QueryParams::date($params, 'created_from', false, $errors);
        $to   = QueryParams::date($params, 'created_to', true, $errors);

        if ($from !== null && $to !== null && $from > $to && ! isset($errors['created_from'], $errors['created_to'])) {
            $errors['created_from'] = 'Must not be after created_to';
        }

        if ($errors !== []) {
            throw new InvalidParams($errors);
        }

        return new self($page, $pageSize, $sort, $order, $name, $code, $from, $to);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }

    /** @param array<string, string> $errors */
    private static function prefix(array $params, string $key, array &$errors): ?string
    {
        $value = QueryParams::scalar($params, $key, $errors);
        if ($value !== null && mb_strlen($value) < self::MIN_PREFIX_LENGTH) {
            $errors[$key] = 'Must be at least ' . self::MIN_PREFIX_LENGTH . ' characters';

            return null;
        }

        return $value;
    }
}
