<?php

declare(strict_types=1);

namespace App\Reading;

use App\Http\InvalidParams;
use App\Http\QueryParams;
use DateTimeImmutable;

/**
 * Parameters of `GET /api/sensors/{id}/readings`: the time range is required, pages are bounded.
 */
final class ReadingsQuery
{
    public const DEFAULT_PAGE_SIZE = 100;
    public const MAX_PAGE_SIZE     = 1000;

    private function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly int $page,
        public readonly int $pageSize,
    ) {
    }

    /** @param array<string, mixed> $params @throws InvalidParams */
    public static function fromParams(array $params): self
    {
        $errors = [];

        $from     = QueryParams::date($params, 'from', false, $errors);
        $to       = QueryParams::date($params, 'to', true, $errors);
        $page     = QueryParams::positiveInt($params, 'page', 1, null, $errors);
        $pageSize = QueryParams::positiveInt($params, 'page_size', self::DEFAULT_PAGE_SIZE, self::MAX_PAGE_SIZE, $errors);

        foreach (['from' => $from, 'to' => $to] as $key => $value) {
            if ($value === null && ! isset($errors[$key])) {
                $errors[$key] = 'Required';
            }
        }
        if ($from !== null && $to !== null && $from > $to) {
            $errors['from'] = 'Must not be after to';
        }

        if ($errors !== [] || $from === null || $to === null) {
            throw new InvalidParams($errors);
        }

        return new self($from, $to, $page, $pageSize);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }
}
