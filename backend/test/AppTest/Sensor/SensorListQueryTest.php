<?php

declare(strict_types=1);

namespace AppTest\Sensor;

use App\Http\InvalidParams;
use App\Sensor\SensorListQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SensorListQueryTest extends TestCase
{
    public function testDefaults(): void
    {
        $query = SensorListQuery::fromParams([]);

        self::assertSame(1, $query->page);
        self::assertSame(25, $query->pageSize);
        self::assertSame('created_at', $query->sort);
        self::assertSame('desc', $query->order);
        self::assertNull($query->name);
        self::assertNull($query->code);
        self::assertNull($query->createdFrom);
        self::assertNull($query->createdTo);
        self::assertSame(0, $query->offset());
    }

    public function testParsesAllParameters(): void
    {
        $query = SensorListQuery::fromParams([
            'page'         => '3',
            'page_size'    => '50',
            'sort'         => 'name',
            'order'        => 'asc',
            'name'         => 'Temp',
            'code'         => 'SN-0',
            'created_from' => '2026-01-01',
            'created_to'   => '2026-01-31',
        ]);

        self::assertSame(3, $query->page);
        self::assertSame(50, $query->pageSize);
        self::assertSame(100, $query->offset());
        self::assertSame('name', $query->sort);
        self::assertSame('asc', $query->order);
        self::assertSame('Temp', $query->name);
        self::assertSame('SN-0', $query->code);
        self::assertSame('2026-01-01 00:00:00', $query->createdFrom?->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-31 23:59:59', $query->createdTo?->format('Y-m-d H:i:s'));
    }

    public function testMaxPageSizeIsAccepted(): void
    {
        self::assertSame(100, SensorListQuery::fromParams(['page_size' => '100'])->pageSize);
    }

    /** @param array<string, string> $params */
    #[DataProvider('invalidParams')]
    public function testRejectsInvalidParameters(array $params, string $field): void
    {
        try {
            SensorListQuery::fromParams($params);
            self::fail('InvalidParams expected');
        } catch (InvalidParams $e) {
            self::assertArrayHasKey($field, $e->errors);
        }
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidParams(): iterable
    {
        yield 'page size above max' => [['page_size' => '101'], 'page_size'];
        yield 'page size zero' => [['page_size' => '0'], 'page_size'];
        yield 'page size not a number' => [['page_size' => 'many'], 'page_size'];
        yield 'page zero' => [['page' => '0'], 'page'];
        yield 'page negative' => [['page' => '-1'], 'page'];
        yield 'sort by secret' => [['sort' => 'secret_enc'], 'sort'];
        yield 'sort by injection' => [['sort' => 'name; DROP TABLE sensors'], 'sort'];
        yield 'bad order' => [['order' => 'sideways'], 'order'];
        yield 'name prefix too short' => [['name' => 'te'], 'name'];
        yield 'code prefix too short' => [['code' => 'SN'], 'code'];
        yield 'bad date' => [['created_from' => 'yesterday'], 'created_from'];
        yield 'impossible date' => [['created_to' => '2026-02-30'], 'created_to'];
        yield 'from after to' => [['created_from' => '2026-02-01', 'created_to' => '2026-01-01'], 'created_from'];
        yield 'array instead of scalar' => [['name' => ['a', 'b']], 'name'];
    }

    public function testReportsAllErrorsAtOnce(): void
    {
        try {
            SensorListQuery::fromParams(['page' => '0', 'sort' => 'x']);
            self::fail('InvalidParams expected');
        } catch (InvalidParams $e) {
            self::assertSame(['page', 'sort'], array_keys($e->errors));
        }
    }
}
