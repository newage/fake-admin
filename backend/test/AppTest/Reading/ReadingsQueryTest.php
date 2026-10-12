<?php

declare(strict_types=1);

namespace AppTest\Reading;

use App\Http\InvalidParams;
use App\Reading\ReadingsQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReadingsQueryTest extends TestCase
{
    public function testParsesRangeAndDefaults(): void
    {
        $query = ReadingsQuery::fromParams(['from' => '2026-10-01', 'to' => '2026-10-02T12:30:00']);

        self::assertSame('2026-10-01 00:00:00', $query->from->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-02 12:30:00', $query->to->format('Y-m-d H:i:s'));
        self::assertSame(1, $query->page);
        self::assertSame(100, $query->pageSize);
        self::assertSame(0, $query->offset());
    }

    public function testDateOnlyToCoversTheWholeDay(): void
    {
        $query = ReadingsQuery::fromParams(['from' => '2026-10-01', 'to' => '2026-10-01']);

        self::assertSame('2026-10-01 23:59:59', $query->to->format('Y-m-d H:i:s'));
    }

    public function testOffsetFollowsPage(): void
    {
        $query = ReadingsQuery::fromParams(['from' => '2026-10-01', 'to' => '2026-10-02', 'page' => '3', 'page_size' => '50']);

        self::assertSame(100, $query->offset());
    }

    /** @param array<string, string> $params */
    #[DataProvider('invalidParams')]
    public function testRejectsInvalidParameters(array $params, string $field): void
    {
        try {
            ReadingsQuery::fromParams($params);
            self::fail('InvalidParams expected');
        } catch (InvalidParams $e) {
            self::assertArrayHasKey($field, $e->errors);
        }
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidParams(): iterable
    {
        $range = ['from' => '2026-10-01', 'to' => '2026-10-02'];

        yield 'from is required' => [['to' => '2026-10-02'], 'from'];
        yield 'to is required' => [['from' => '2026-10-01'], 'to'];
        yield 'bad from' => [['from' => 'yesterday', 'to' => '2026-10-02'], 'from'];
        yield 'from after to' => [['from' => '2026-10-03', 'to' => '2026-10-02'], 'from'];
        yield 'page size above max' => [$range + ['page_size' => '1001'], 'page_size'];
        yield 'page size zero' => [$range + ['page_size' => '0'], 'page_size'];
        yield 'page zero' => [$range + ['page' => '0'], 'page'];
    }
}
