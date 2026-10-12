<?php

declare(strict_types=1);

namespace AppTest\Reading;

use App\Reading\ReadingsQuery;
use App\Reading\ReadingsRepository;
use App\Reading\ReadingsUnavailable;
use AppTest\Db\DatabaseTestCase;

use function array_column;

final class ReadingsRepositoryTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
        $this->db->executeStatement("INSERT INTO sensors (id, code, name, type, unit) VALUES (1, 'SN-1', 'One', 'temperature', '°C'), (2, 'SN-2', 'Two', 'temperature', '°C')");
    }

    public function testSensorExists(): void
    {
        $repository = new ReadingsRepository($this->db);

        self::assertTrue($repository->sensorExists(1));
        self::assertFalse($repository->sensorExists(999));
    }

    public function testReportsUnavailableWhenReadingsTableIsMissing(): void
    {
        $this->expectException(ReadingsUnavailable::class);

        (new ReadingsRepository($this->db))->page(1, $this->query(['from' => '2026-10-01', 'to' => '2026-10-02']));
    }

    public function testReturnsReadingsOfOneSensorInRangeOrderedByTime(): void
    {
        $this->createReadings([
            [1, '2026-09-30 23:59:59', 1.0],
            [1, '2026-10-01 00:00:00', 2.0],
            [1, '2026-10-01 12:00:00', 3.0],
            [1, '2026-10-02 23:59:59', 4.0],
            [1, '2026-10-03 00:00:00', 5.0],
            [2, '2026-10-01 06:00:00', 9.0],
        ]);

        $page = (new ReadingsRepository($this->db))->page(1, $this->query(['from' => '2026-10-01', 'to' => '2026-10-02']));

        self::assertSame([2.0, 3.0, 4.0], array_column($page['items'], 'value'));
        self::assertSame('2026-10-01T00:00:00Z', $page['items'][0]['ts']);
        self::assertFalse($page['has_more']);
    }

    public function testPaginatesWithHasMore(): void
    {
        $rows = [];
        for ($i = 0; $i < 5; ++$i) {
            $rows[] = [1, '2026-10-01 00:00:0' . $i, (float) $i];
        }
        $this->createReadings($rows);
        $repository = new ReadingsRepository($this->db);
        $range      = ['from' => '2026-10-01', 'to' => '2026-10-01', 'page_size' => '2'];

        $first = $repository->page(1, $this->query($range + ['page' => '1']));
        $last  = $repository->page(1, $this->query($range + ['page' => '3']));

        self::assertSame([0.0, 1.0], array_column($first['items'], 'value'));
        self::assertTrue($first['has_more']);
        self::assertSame([4.0], array_column($last['items'], 'value'));
        self::assertFalse($last['has_more']);
    }

    /** @param array<string, string> $params */
    private function query(array $params): ReadingsQuery
    {
        return ReadingsQuery::fromParams($params);
    }

    /**
     * The table schema is defined by consumers; here only the minimum the API needs: (sensor_id, ts, value).
     *
     * @param list<array{int, string, float}> $rows
     */
    private function createReadings(array $rows): void
    {
        $this->db->executeStatement(
            'CREATE TABLE sensor_readings (sensor_id BIGINT UNSIGNED NOT NULL, ts DATETIME NOT NULL, value DOUBLE NOT NULL, seq BIGINT DEFAULT NULL, PRIMARY KEY (sensor_id, ts))',
        );
        foreach ($rows as [$sensorId, $ts, $value]) {
            $this->db->executeStatement('INSERT INTO sensor_readings (sensor_id, ts, value) VALUES (?, ?, ?)', [$sensorId, $ts, $value]);
        }
    }
}
